-- Administration of voucher campaigns only
CREATE TABLE voucher_settings (
  singleton_id smallint PRIMARY KEY DEFAULT 1 CHECK (singleton_id = 1),
  public_enabled boolean NOT NULL DEFAULT false
);

INSERT INTO voucher_settings (singleton_id, public_enabled) VALUES (1, false);

CREATE TABLE voucher_campaigns (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  name varchar(120) NOT NULL CHECK (length(btrim(name)) > 0),
  price_cents integer NOT NULL CHECK (price_cents >= 100),
  nights smallint NOT NULL CHECK (nights BETWEEN 1 AND 30),
  validity_months smallint NOT NULL CHECK (validity_months BETWEEN 1 AND 36),
  includes_wellness boolean NOT NULL DEFAULT false,
  includes_wine boolean NOT NULL DEFAULT false,
  is_active boolean NOT NULL DEFAULT false,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
