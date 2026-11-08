-- Payment settings and independently tracked manual bank-transfer status.
CREATE TABLE booking_payment_settings (
  id smallint PRIMARY KEY CHECK (id = 1),
  iban varchar(34),
  account_holder varchar(160),
  bic varchar(11),
  updated_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT booking_payment_settings_complete CHECK (
    (iban IS NULL AND account_holder IS NULL AND bic IS NULL)
    OR (iban IS NOT NULL AND account_holder IS NOT NULL AND length(trim(account_holder)) > 0)
  )
);

INSERT INTO booking_payment_settings (id) VALUES (1);

CREATE TABLE booking_payments (
  booking_id bigint PRIMARY KEY REFERENCES booking_requests(id) ON DELETE RESTRICT,
  status varchar(8) NOT NULL CHECK (status IN ('unpaid', 'paid')),
  paid_at timestamptz,
  updated_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT booking_payments_paid_at_consistent CHECK (
    (status = 'paid' AND paid_at IS NOT NULL)
    OR (status = 'unpaid' AND paid_at IS NULL)
  )
);

COMMENT ON TABLE booking_payments IS 'Manual payment state is independent from reservation approval; no automatic banking integration.';
