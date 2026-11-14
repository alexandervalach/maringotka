-- Existing bookings have no billing address. Never invent one for invoices.
ALTER TABLE booking_requests
  ADD COLUMN IF NOT EXISTS guest_billing_address varchar(500);

-- Supplier settings live in PaymentSettings and are reused on new invoices.
ALTER TABLE booking_invoice_supplier_settings
  ADD COLUMN IF NOT EXISTS non_vat_payer boolean NOT NULL DEFAULT false;
