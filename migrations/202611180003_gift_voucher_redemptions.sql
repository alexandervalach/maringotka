-- Link a voucher to at most one blocking booking. Release it on rejection/cancellation.
ALTER TABLE gift_vouchers
  ADD COLUMN redeemed_booking_id bigint UNIQUE REFERENCES booking_requests(id) ON DELETE SET NULL;

CREATE OR REPLACE FUNCTION release_gift_voucher_on_booking_close() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
  IF OLD.status IN ('pending', 'confirmed')
     AND NEW.status IN ('rejected', 'cancelled') THEN
    UPDATE gift_vouchers
       SET status = 'active', redeemed_booking_id = NULL
     WHERE redeemed_booking_id = NEW.id AND status = 'redeemed';
  END IF;
  RETURN NEW;
END;
$$;

CREATE TRIGGER gift_voucher_release_on_booking_close
AFTER UPDATE OF status ON booking_requests
FOR EACH ROW EXECUTE FUNCTION release_gift_voucher_on_booking_close();
