-- Supports status filtering and keyset pagination without OFFSET.
CREATE INDEX IF NOT EXISTS booking_requests_status_id_idx
  ON booking_requests (status, id DESC);
