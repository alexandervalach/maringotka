ALTER TABLE voucher_campaigns
  ADD COLUMN IF NOT EXISTS name_en varchar(120) NOT NULL DEFAULT '';
