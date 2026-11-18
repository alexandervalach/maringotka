-- Customer requests are separate from issued, active vouchers.
-- No redeemable code exists until a later payment/issuance workflow.
CREATE TABLE gift_voucher_orders (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  campaign_id bigint NOT NULL REFERENCES voucher_campaigns(id) ON DELETE RESTRICT,
  reference varchar(24) NOT NULL UNIQUE
    CHECK (reference ~ '^[A-F0-9]{24}$'),
  customer_name varchar(200) NOT NULL,
  customer_email varchar(254) NOT NULL,
  locale varchar(2) NOT NULL DEFAULT 'sk' CHECK (locale IN ('sk', 'en')),
  document jsonb NOT NULL,
  price_cents integer NOT NULL CHECK (price_cents >= 100),
  status varchar(16) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'paid', 'cancelled')),
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX gift_voucher_orders_campaign_idx
  ON gift_voucher_orders (campaign_id, created_at DESC);
