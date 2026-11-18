-- Separate public six-character redemption alias from the existing 128-bit internal code.

ALTER TABLE gift_vouchers
  ADD COLUMN short_code varchar(6) UNIQUE;

ALTER TABLE gift_vouchers
  ADD CONSTRAINT gift_vouchers_short_code_format
  CHECK (
    short_code IS NULL
    OR short_code ~ '^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{6}$'
  );