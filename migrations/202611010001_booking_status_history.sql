-- Append-only audit of booking status transitions.
-- Does not alter booking rules or the e-mail outbox.
-- Records only booking ID, status transition, reason and timestamp (no guest PII).

CREATE TABLE booking_status_history (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  booking_id bigint NOT NULL REFERENCES booking_requests(id) ON DELETE RESTRICT,
  previous_status varchar(12),
  current_status varchar(12) NOT NULL,
  decision_reason text,
  changed_at timestamptz NOT NULL DEFAULT clock_timestamp(),
  CONSTRAINT booking_status_history_previous_status_check CHECK (
    previous_status IS NULL OR previous_status IN ('pending', 'confirmed', 'rejected', 'cancelled')
  ),
  CONSTRAINT booking_status_history_current_status_check CHECK (
    current_status IN ('pending', 'confirmed', 'rejected', 'cancelled')
  ),
  CONSTRAINT booking_status_history_changed_status_check CHECK (
    previous_status IS DISTINCT FROM current_status
  )
);

CREATE INDEX booking_status_history_booking_order_idx
  ON booking_status_history (booking_id, id DESC);

CREATE OR REPLACE FUNCTION record_booking_status_history()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
  IF TG_OP = 'INSERT' THEN
    INSERT INTO booking_status_history (
      booking_id, previous_status, current_status, decision_reason
    ) VALUES (
      NEW.id, NULL, NEW.status, NEW.decision_reason
    );
  ELSIF OLD.status IS DISTINCT FROM NEW.status THEN
    INSERT INTO booking_status_history (
      booking_id, previous_status, current_status, decision_reason
    ) VALUES (
      NEW.id, OLD.status, NEW.status, NEW.decision_reason
    );
  END IF;

  RETURN NEW;
END;
$$;

CREATE TRIGGER booking_status_history_record
AFTER INSERT OR UPDATE OF status ON booking_requests
FOR EACH ROW EXECUTE FUNCTION record_booking_status_history();

-- Initial history record for reservations which existed before this migration.
-- Their real original creation/decision event time cannot be reconstructed.
-- The backfilled row explicitly represents state at migration time.
INSERT INTO booking_status_history (
  booking_id, previous_status, current_status, decision_reason
)
SELECT id, NULL, status, decision_reason
FROM booking_requests;
