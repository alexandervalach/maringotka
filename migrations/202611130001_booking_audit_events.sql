CREATE TABLE booking_audit_events (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  booking_id bigint NOT NULL REFERENCES booking_requests(id) ON DELETE RESTRICT,
  event_type varchar(24) NOT NULL CHECK (event_type IN (
    'payment_paid', 'payment_unpaid', 'mail_sent', 'mail_summary', 'qr_generated'
  )),
  detail varchar(500) NOT NULL DEFAULT '',
  event_key varchar(100) UNIQUE,
  occurred_at timestamptz NOT NULL DEFAULT clock_timestamp()
);

CREATE INDEX booking_audit_events_booking_date_idx
  ON booking_audit_events (booking_id, occurred_at DESC, id DESC);

CREATE FUNCTION record_booking_payment_event()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
  IF TG_OP = 'INSERT' OR OLD.status IS DISTINCT FROM NEW.status THEN
    INSERT INTO booking_audit_events (booking_id, event_type, detail)
    VALUES (
      NEW.booking_id,
      CASE WHEN NEW.status = 'paid' THEN 'payment_paid' ELSE 'payment_unpaid' END,
      'Stav úhrady zmenil administrátor'
    );
  END IF;

  RETURN NEW;
END;
$$;

CREATE TRIGGER booking_payment_audit
AFTER INSERT OR UPDATE OF status ON booking_payments
FOR EACH ROW EXECUTE FUNCTION record_booking_payment_event();

INSERT INTO booking_audit_events (booking_id, event_type, detail, event_key, occurred_at)
SELECT o.booking_id, 'mail_sent',
  CASE o.event_type
    WHEN 'received' THEN 'Potvrdenie prijatia žiadosti'
    WHEN 'confirmed' THEN 'Potvrdenie rezervácie'
    WHEN 'rejected' THEN 'Oznámenie o zamietnutí'
    WHEN 'cancelled' THEN 'Oznámenie o zrušení'
    ELSE 'Oznámenie o rezervácii'
  END || CASE WHEN o.recipient_type = 'admin' THEN ' odoslané administrátorovi' ELSE ' odoslané hosťovi' END,
  'outbox:' || o.id::text, o.sent_at
FROM booking_mail_outbox o
WHERE o.sent_at IS NOT NULL
ON CONFLICT (event_key) DO NOTHING;

INSERT INTO booking_audit_events (booking_id, event_type, detail, event_key, occurred_at)
SELECT booking_id, 'mail_summary',
  'Platobné pokyny: historicky ' || send_count::text || ' úspešných odoslaní (známy len posledný čas)',
  'payment-summary:' || booking_id::text, last_sent_at
FROM booking_payment_mail_history
ON CONFLICT (event_key) DO NOTHING;

INSERT INTO booking_audit_events (booking_id, event_type, detail, event_key, occurred_at)
SELECT booking_id, 'payment_paid',
  'Platba označená ako prijatá (doložený historický stav)',
  'payment-current:' || booking_id::text, paid_at
FROM booking_payments
WHERE status = 'paid' AND paid_at IS NOT NULL
ON CONFLICT (event_key) DO NOTHING;
