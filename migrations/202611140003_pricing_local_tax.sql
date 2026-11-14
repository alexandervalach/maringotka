-- Local accommodation tax is included in overnight prices and only split out on invoices.
-- Public prices and existing invoice snapshots stay unchanged.
ALTER TABLE pricing_settings
  ADD COLUMN local_tax_cents integer NOT NULL DEFAULT 200
    CHECK (local_tax_cents BETWEEN 0 AND 100000);
