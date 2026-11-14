-- Fourth transactional email event: payment confirmed by the administrator.
-- Do not enqueue historical paid bookings retroactively.
ALTER TABLE booking_mail_outbox
  DROP CONSTRAINT booking_mail_outbox_event_type_check;
ALTER TABLE booking_mail_outbox
  ADD CONSTRAINT booking_mail_outbox_event_type_check
  CHECK (event_type IN ('received', 'confirmed', 'rejected', 'cancelled', 'paid'));

CREATE OR REPLACE FUNCTION queue_booking_paid_mail() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
  IF NEW.status = 'paid' AND (TG_OP = 'INSERT' OR OLD.status IS DISTINCT FROM NEW.status) THEN
    INSERT INTO booking_mail_outbox (booking_id, event_type, recipient_type)
    VALUES (NEW.booking_id, 'paid', 'guest')
    ON CONFLICT (booking_id, event_type, recipient_type) DO NOTHING;
  END IF;
  RETURN NEW;
END;
$$;

CREATE TRIGGER booking_payment_paid_mail_enqueue
AFTER INSERT OR UPDATE OF status ON booking_payments
FOR EACH ROW EXECUTE FUNCTION queue_booking_paid_mail();
