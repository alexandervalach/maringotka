<?php

declare(strict_types=1);

namespace App\Model;

use App\Infrastructure\Database\ConnectionFactory;
use DomainException;
use InvalidArgumentException;

/**
 * Lists booking requests and performs administrator decisions atomically.
 */
final class BookingManagementRepository extends Repository
{
  /**
   * @param ConnectionFactory $connections Project database connection.
   */
  public function __construct(ConnectionFactory $connections)
  {
    parent::__construct($connections->createExplorer(), 'booking_requests');
  }

  /**
   * Returns at most 25 rows using ID-based keyset pagination.
   * Filter values are always bound as query parameters.
   *
   * @param string $status Exact status, or empty string for all.
   * @param string $search Guest name, email, or exact numeric booking ID.
   * @param int|null $before Show rows older than this ID.
   * @param int|null $after Show rows newer than this ID.
   * @return array{requests: list<array<string, mixed>>, older: int|null, newer: int|null}
   */
  public function page(string $status, string $search, ?int $before, ?int $after): array
  {
    $allowed = ['', 'pending', 'confirmed', 'rejected', 'cancelled'];
    if (!in_array($status, $allowed, true)) {
      throw new InvalidArgumentException('Neznámy stav rezervácie.');
    }
    if ($before !== null && $after !== null) {
      throw new InvalidArgumentException('Neplatné stránkovanie.');
    }
    if (($before !== null && $before < 1) || ($after !== null && $after < 1)) {
      throw new InvalidArgumentException('Neplatný kurzor.');
    }

    $search = trim($search);
    if (mb_strlen($search) > 100) {
      throw new InvalidArgumentException('Vyhľadávanie môže mať najviac 100 znakov.');
    }

    $where = [];
    $values = [];
    if ($status !== '') {
      $where[] = 'status = ?';
      $values[] = $status;
    }
    if ($search !== '') {
      // Treat % and _ as literal characters in a user-supplied search.
      $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
      if (ctype_digit($search) && strlen($search) < 19) {
        $where[] = '(id = ? OR guest_name ILIKE ? OR guest_email ILIKE ?)';
        $values[] = (int) $search;
      } else {
        $where[] = '(guest_name ILIKE ? OR guest_email ILIKE ?)';
      }
      $values[] = '%' . $escaped . '%';
      $values[] = '%' . $escaped . '%';
    }

    $baseWhere = $where;
    $baseValues = $values;
    if ($before !== null) {
      $where[] = 'id < ?';
      $values[] = $before;
    } elseif ($after !== null) {
      $where[] = 'id > ?';
      $values[] = $after;
    }

    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $order = $after === null ? 'DESC' : 'ASC';
    // One additional row is fetched to allow detection of the end of a page.
    $rows = $this->database->query(
      'SELECT id, created_at, arrival_date, departure_date, status, guest_name, guest_email, guest_phone, guest_note, decided_at, decision_reason'
      . ' FROM booking_requests' . $whereSql . ' ORDER BY id ' . $order . ' LIMIT 26',
      ...$values,
    )->fetchAll();
    if (count($rows) > 25) {
      array_pop($rows);
    }
    if ($after !== null) {
      $rows = array_reverse($rows);
    }
    if ($rows === []) {
      return ['requests' => [], 'older' => null, 'newer' => null];
    }

    $requests = [];
    foreach ($rows as $row) {
      $requests[] = iterator_to_array($row);
    }
    $firstId = (int) $requests[0]['id'];
    $lastId = (int) $requests[count($requests) - 1]['id'];
    $baseSql = $baseWhere === [] ? '' : ' WHERE ' . implode(' AND ', $baseWhere) . ' AND ';
    $prefix = $baseWhere === [] ? ' WHERE ' : $baseSql;

    $hasNewer = $this->database->query(
      'SELECT id FROM booking_requests' . $prefix . 'id > ? ORDER BY id ASC LIMIT 1',
      ...[...$baseValues, $firstId],
    )->fetch() !== null;
    $hasOlder = $this->database->query(
      'SELECT id FROM booking_requests' . $prefix . 'id < ? ORDER BY id DESC LIMIT 1',
      ...[...$baseValues, $lastId],
    )->fetch() !== null;

    return [
      'requests' => $requests,
      'older' => $hasOlder ? $lastId : null,
      'newer' => $hasNewer ? $firstId : null,
    ];
  }

  /**
   * @param int $id Request ID.
   * @return array<string, mixed>|null Request details.
   */
  public function requestById(int $id): ?array
  {
    $row = $this->getTable()->get($id);
    return $row?->toArray();
  }

  /**
   * Only pending requests may be decided. The row lock also prevents
   * concurrent administrator decisions from overwriting each other.
   *
   * @param int $id Request ID.
   * @param string $decision Either confirmed or rejected.
   * @param string $reason Mandatory rejection reason.
   * @return void
   */
  public function decide(int $id, string $decision, string $reason): void
  {
    if ($id < 1 || !in_array($decision, ['confirmed', 'rejected'], true)) {
      throw new InvalidArgumentException('Neplatné rozhodnutie o rezervácii.');
    }
    $reason = trim($reason);
    if ($decision === 'rejected' && $reason === '') {
      throw new InvalidArgumentException('Pri zamietnutí zadajte dôvod.');
    }
    if (mb_strlen($reason) > 2000) {
      throw new InvalidArgumentException('Dôvod môže mať najviac 2000 znakov.');
    }

    $this->database->beginTransaction();
    try {
      // Serialize competing decisions; column identifiers are fixed, ID is bound.
      $row = $this->database->query(
        'SELECT id, status FROM booking_requests WHERE id = ? FOR UPDATE',
        $id,
      )->fetch();
      if ($row === null) {
        throw new DomainException('Rezervácia neexistuje.');
      }
      if ($row->status !== 'pending') {
        throw new DomainException('O tejto rezervácii už bolo rozhodnuté.');
      }
      $this->database->query(
        'UPDATE booking_requests SET status = ?, decision_reason = ?, decided_at = NOW(), updated_at = NOW() WHERE id = ?',
        $decision,
        $decision === 'rejected' ? $reason : null,
        $id,
      );
      $this->database->commit();
    } catch (\Throwable $error) {
      $this->database->rollBack();
      throw $error;
    }
  }
}
