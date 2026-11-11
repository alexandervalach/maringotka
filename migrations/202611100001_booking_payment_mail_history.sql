-- Audit of explicitly initiated payment instruction emails. No QR payload or PII stored.
CREATE TABLE booking_payment_mail_history (
  booking_id bigint PRIMARY KEY REFERENCES booking_requests(id) ON DELETE RESTRICT,
  last_sent_at timestamptz NOT NULL,
  send_count integer NOT NULL DEFAULT 1 CHECK (send_count > 0)
);

COMMENT ON TABLE booking_payment_mail_history IS
  'Last successfully submitted payment instruction email and resend counter.';
