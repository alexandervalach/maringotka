-- One editable template for payment instructions; missing row = built-in fallback.
CREATE TABLE IF NOT EXISTS booking_payment_email_template (
  id smallint PRIMARY KEY CHECK (id = 1),
  subject text NOT NULL,
  body text NOT NULL,
  show_logo boolean NOT NULL DEFAULT true,
  updated_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);
