-- Commit 18: booking requests for a single accommodation.
-- Each stay uses [arrival_date, departure_date); departure is not occupied.
-- PostgreSQL exclusion constraint prevents concurrent overlapping pending/confirmed requests.
-- Rejected/cancelled requests do not block the calendar.

CREATE TABLE booking_requests (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  arrival_date date NOT NULL,
  departure_date date NOT NULL,
  guest_name varchar(200) NOT NULL,
  guest_email varchar(254) NOT NULL,
  guest_phone varchar(60),
  guest_note text NOT NULL DEFAULT '',
  status varchar(12) NOT NULL DEFAULT 'pending',
  cancellation_token_hash char(64) NOT NULL UNIQUE,
  decision_reason text,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  decided_at timestamptz,
  CONSTRAINT booking_requests_valid_dates CHECK (arrival_date < departure_date),
  CONSTRAINT booking_requests_valid_status CHECK (
    status IN ('pending', 'confirmed', 'rejected', 'cancelled')
  ),
  CONSTRAINT booking_requests_guest_name_required CHECK (length(btrim(guest_name)) > 0),
  CONSTRAINT booking_requests_guest_email_required CHECK (length(btrim(guest_email)) > 0),
  CONSTRAINT booking_requests_cancellation_hash_format CHECK (
    cancellation_token_hash ~ '^[0-9a-f]{64}$'
  ),
  CONSTRAINT booking_requests_rejection_reason_required CHECK (
    status <> 'rejected' OR nullif(btrim(decision_reason), '') IS NOT NULL
  ),
  CONSTRAINT booking_requests_no_active_overlap EXCLUDE USING gist (
    daterange(arrival_date, departure_date, '[)') WITH &&
  ) WHERE (status IN ('pending', 'confirmed'))
);

CREATE INDEX booking_requests_created_at_idx ON booking_requests (created_at DESC);
CREATE INDEX booking_requests_status_arrival_idx ON booking_requests (status, arrival_date);
