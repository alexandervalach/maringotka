-- Issued gift vouchers are independent snapshots of their original campaigns.
CREATE TABLE gift_vouchers (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  campaign_id bigint NOT NULL REFERENCES voucher_campaigns(id) ON DELETE RESTRICT,
  code varchar(39) NOT NULL UNIQUE,
  document jsonb NOT NULL,
  issued_at timestamptz NOT NULL DEFAULT now(),
  expires_at date NOT NULL,
  status varchar(16) NOT NULL DEFAULT 'active'
    CHECK (status IN ('active', 'redeemed', 'cancelled'))
);

CREATE INDEX gift_vouchers_campaign_id_idx ON gift_vouchers (campaign_id, issued_at DESC);
