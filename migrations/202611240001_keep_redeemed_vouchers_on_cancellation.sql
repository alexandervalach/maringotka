-- A redeemed voucher stays redeemed after the booking is cancelled or rejected.
-- Issuing a replacement voucher is an explicit administrator action.
DROP TRIGGER IF EXISTS gift_voucher_release_on_booking_close ON booking_requests;
DROP FUNCTION IF EXISTS release_gift_voucher_on_booking_close();

-- Prepaid voucher stays do not trigger the separate booking-payment e-mail.
-- Their guest already received the paid voucher PDF, and the confirmed booking
-- e-mail provides the stay details without requesting another payment.
CREATE OR REPLACE FUNCTION queue_booking_paid_mail() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
  IF NEW.status = 'paid' AND (TG_OP = 'INSERT' OR OLD.status IS DISTINCT FROM NEW.status)
    AND NOT EXISTS (
      SELECT 1 FROM gift_vouchers v
      WHERE v.redeemed_booking_id = NEW.booking_id AND v.status = 'redeemed'
    ) THEN
    INSERT INTO booking_mail_outbox (booking_id, event_type, recipient_type)
    VALUES (NEW.booking_id, 'paid', 'guest')
    ON CONFLICT (booking_id, event_type, recipient_type) DO NOTHING;
  END IF;
  RETURN NEW;
END;
$$;
