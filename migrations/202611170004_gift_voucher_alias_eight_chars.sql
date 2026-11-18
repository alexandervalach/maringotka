-- New voucher aliases have eight characters. Existing six-character codes stay valid.
ALTER TABLE gift_vouchers
  ALTER COLUMN short_code TYPE varchar(8);

ALTER TABLE gift_vouchers
  DROP CONSTRAINT IF EXISTS gift_vouchers_short_code_format;

ALTER TABLE gift_vouchers
  ADD CONSTRAINT gift_vouchers_short_code_format
  CHECK (short_code IS NULL OR short_code ~ '^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{6}([23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{2})?$');
