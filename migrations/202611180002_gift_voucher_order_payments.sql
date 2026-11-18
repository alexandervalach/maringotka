-- Link paid customer orders to their issued voucher; keep mail delivery idempotent.
ALTER TABLE gift_voucher_orders
  ADD COLUMN voucher_id bigint UNIQUE REFERENCES gift_vouchers(id),
  ADD COLUMN paid_at timestamptz;

CREATE TABLE gift_voucher_mail_outbox (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  order_id bigint NOT NULL REFERENCES gift_voucher_orders(id) ON DELETE RESTRICT,
  event_type varchar(16) NOT NULL CHECK (event_type IN ('ordered', 'paid')),
  attempts integer NOT NULL DEFAULT 0,
  next_attempt_at timestamptz NOT NULL DEFAULT now(),
  sent_at timestamptz,
  created_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (order_id, event_type)
);
CREATE INDEX gift_voucher_mail_outbox_pending_idx
  ON gift_voucher_mail_outbox (next_attempt_at, id)
  WHERE sent_at IS NULL;
