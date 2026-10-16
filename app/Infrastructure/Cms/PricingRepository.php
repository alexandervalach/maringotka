<?php

declare(strict_types=1);

namespace App\Infrastructure\Cms;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final class PricingRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null Prices are in euro cents; null means not configured. */
    public function settings(): ?array
    {
        $stmt = $this->pdo->query('SELECT first_night_price_cents, additional_night_price_cents, minimum_nights, currency FROM pricing_settings WHERE singleton_id = 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function setBasePrices(?int $firstNightCents, ?int $additionalNightCents, int $minimumNights): void
    {
        if (($firstNightCents === null) !== ($additionalNightCents === null)
            || ($firstNightCents !== null && $firstNightCents < 0)
            || ($additionalNightCents !== null && $additionalNightCents < 0)
            || $minimumNights < 1 || $minimumNights > 365
        ) {
            throw new InvalidArgumentException('Invalid base pricing.');
        }
        $stmt = $this->pdo->prepare(<<<'SQL'
            UPDATE pricing_settings SET first_night_price_cents = :first_price,
                additional_night_price_cents = :additional_price,
                minimum_nights = :minimum_nights, updated_at = now()
            WHERE singleton_id = 1
            SQL);
        $stmt->bindValue(':first_price', $firstNightCents, $firstNightCents === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':additional_price', $additionalNightCents, $additionalNightCents === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':minimum_nights', $minimumNights, PDO::PARAM_INT);
        $stmt->execute();
    }

    /** @return list<array<string, mixed>> */
    public function seasons(): array
    {
        return $this->pdo->query('SELECT id, name, start_date, end_date_exclusive, first_night_price_cents, additional_night_price_cents FROM pricing_seasons ORDER BY start_date, id')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Exclusive end date. Overlaps are rejected by the PostgreSQL exclusion constraint. */
    public function addSeason(string $name, string $start, string $endExclusive, int $firstCents, int $additionalCents): int
    {
        $startDate = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        $endDate = DateTimeImmutable::createFromFormat('!Y-m-d', $endExclusive);
        if (trim($name) === '' || strlen($name) > 120 || $firstCents < 0 || $additionalCents < 0
            || $startDate === false || $endDate === false
            || $startDate->format('Y-m-d') !== $start || $endDate->format('Y-m-d') !== $endExclusive
            || $startDate >= $endDate
        ) {
            throw new InvalidArgumentException('Invalid season.');
        }
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO pricing_seasons (name, start_date, end_date_exclusive, first_night_price_cents, additional_night_price_cents)
            VALUES (:name, :start_date, :end_date, :first_price, :additional_price) RETURNING id
            SQL);
        $stmt->execute([
            'name' => $name, 'start_date' => $start, 'end_date' => $endExclusive,
            'first_price' => $firstCents, 'additional_price' => $additionalCents,
        ]);
        return (int) $stmt->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function activeExtras(string $locale): array
    {
        if (!in_array($locale, ['sk', 'en'], true)) {
            throw new InvalidArgumentException('Unsupported locale.');
        }
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT e.code, e.charge_unit, e.price_cents, t.name, t.description
            FROM pricing_extras e
            LEFT JOIN pricing_extra_translations t ON t.extra_code = e.code AND t.locale = :locale
            WHERE e.is_active = true ORDER BY e.sort_order, e.code
            SQL);
        $stmt->execute(['locale' => $locale]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
