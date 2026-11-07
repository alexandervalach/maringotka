-- Commit 43: immutable price quote recorded with each new reservation request.
-- Existing bookings remain NULL: their original prices and extras cannot be reconstructed.
ALTER TABLE booking_requests
  ADD COLUMN price_quote jsonb,
  ADD COLUMN price_total_cents integer;

ALTER TABLE booking_requests
  ADD CONSTRAINT booking_requests_price_snapshot_complete CHECK (
    (price_quote IS NULL AND price_total_cents IS NULL)
    OR (
      price_quote IS NOT NULL AND price_total_cents IS NOT NULL
      AND jsonb_typeof(price_quote) = 'object'
      AND price_total_cents >= 0
    )
  );

COMMENT ON COLUMN booking_requests.price_quote IS
  'Snapshot of the server-calculated line-item quote when the booking was requested; historical values are never recalculated.';
COMMENT ON COLUMN booking_requests.price_total_cents IS
  'Total quoted price in integer eurocents at request time; NULL for legacy bookings.';
