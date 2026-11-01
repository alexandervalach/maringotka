-- Add notifications for manual cancellation.
ALTER TABLE booking_mail_outbox
  DROP CONSTRAINT booking_mail_outbox_event_type_check;
ALTER TABLE booking_mail_outbox
  ADD CONSTRAINT booking_mail_outbox_event_type_check
  CHECK (event_type IN ('received', 'confirmed', 'rejected', 'cancelled'));

CREATE OR REPLACE FUNCTION queue_booking_mail() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
  v_event varchar(12);
BEGIN
  IF TG_OP = 'INSERT' THEN
    v_event := 'received';
  ELSIF OLD.status = 'pending' AND NEW.status IN ('confirmed', 'rejected', 'cancelled') THEN
    v_event := NEW.status;
  ELSIF OLD.status = 'confirmed' AND NEW.status = 'cancelled' THEN
    v_event := 'cancelled';
  ELSE
    RETURN NEW;
  END IF;

  INSERT INTO booking_mail_outbox (booking_id, event_type, recipient_type)
  VALUES (NEW.id, v_event, 'guest'), (NEW.id, v_event, 'admin')
  ON CONFLICT (booking_id, event_type, recipient_type) DO NOTHING;
  RETURN NEW;
END;
$$;
