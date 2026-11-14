-- Issued invoices are immutable booking snapshots. Numbers are never recycled.
CREATE TABLE booking_invoice_numbers (
  year integer PRIMARY KEY CHECK (year BETWEEN 2020 AND 9999),
  last_number integer NOT NULL CHECK (last_number > 0)
);

CREATE TABLE booking_invoice_supplier_settings (
  id smallint PRIMARY KEY CHECK (id = 1),
  name varchar(200) NOT NULL,
  address text NOT NULL,
  ico varchar(16) NOT NULL,
  dic varchar(16),
  updated_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE booking_invoices (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  booking_id bigint NOT NULL UNIQUE REFERENCES booking_requests(id) ON DELETE RESTRICT,
  invoice_number varchar(32) NOT NULL UNIQUE,
  issued_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
  document jsonb NOT NULL CHECK (jsonb_typeof(document) = 'object')
);

COMMENT ON TABLE booking_invoices IS
  'Immutable PDF invoice snapshots. PDFs are rendered on demand; no invoice document is stored in a public directory.';

-- Include invoice issuance in the existing booking history.
ALTER TABLE booking_audit_events
  DROP CONSTRAINT booking_audit_events_event_type_check;

ALTER TABLE booking_audit_events
  ADD CONSTRAINT booking_audit_events_event_type_check CHECK (event_type IN (
    'payment_paid', 'payment_unpaid', 'mail_sent', 'mail_summary', 'qr_generated', 'invoice_issued'
  ));
