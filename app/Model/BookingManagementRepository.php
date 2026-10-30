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
   * @return list<array<string, mixed>> Newest requests first.
   */
  public function allRequests(): array
  {
    $result = [];
    foreach ($this->getTable()->order('created_at DESC, id DESC') as $row) {
      $result[] = $row->toArray();
    }
    return $result;
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
