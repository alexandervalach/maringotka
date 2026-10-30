-- Transactional booking mail outbox. No modification to existing booking data.
CREATE TABLE booking_mail_outbox (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  booking_id bigint NOT NULL REFERENCES booking_requests(id) ON DELETE CASCADE,
  event_type varchar(12) NOT NULL CHECK (event_type IN ('received', 'confirmed', 'rejected')),
  recipient_type varchar(8) NOT NULL CHECK (recipient_type IN ('guest', 'admin')),
  created_at timestamptz NOT NULL DEFAULT now(),
  next_attempt_at timestamptz NOT NULL DEFAULT now(),
  attempts integer NOT NULL DEFAULT 0 CHECK (attempts >= 0),
  sent_at timestamptz,
  UNIQUE (booking_id, event_type, recipient_type)
);
CREATE INDEX booking_mail_outbox_due_idx
  ON booking_mail_outbox (next_attempt_at, id)
  WHERE sent_at IS NULL;

CREATE FUNCTION queue_booking_mail() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
  v_event varchar(12);
BEGIN
  IF TG_OP = 'INSERT' THEN
    v_event := 'received';
  ELSIF OLD.status = 'pending' AND NEW.status IN ('confirmed', 'rejected') THEN
    v_event := NEW.status;
  ELSE
    RETURN NEW;
  END IF;

  INSERT INTO booking_mail_outbox (booking_id, event_type, recipient_type)
  VALUES (NEW.id, v_event, 'guest'), (NEW.id, v_event, 'admin')
  ON CONFLICT (booking_id, event_type, recipient_type) DO NOTHING;
  RETURN NEW;
END;
$$;
CREATE TRIGGER booking_mail_enqueue
AFTER INSERT OR UPDATE OF status ON booking_requests
FOR EACH ROW EXECUTE FUNCTION queue_booking_mail();
