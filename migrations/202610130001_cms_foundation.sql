-- CMS foundation: PostgreSQL 14+; no production seed data.
-- All tables refer to one accommodation property; do not model extra properties yet.

CREATE TABLE cms_pages (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    slug varchar(80) NOT NULL UNIQUE,
    is_published boolean NOT NULL DEFAULT false,
    updated_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT cms_pages_slug_format CHECK (slug ~ '^[a-z][a-z0-9_-]*$')
);

CREATE TABLE cms_page_translations (
    page_id bigint NOT NULL REFERENCES cms_pages(id) ON DELETE CASCADE,
    locale varchar(2) NOT NULL CHECK (locale IN ('sk', 'en')),
    title varchar(200) NOT NULL,
    summary text NOT NULL DEFAULT '',
    body text NOT NULL DEFAULT '',
    updated_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (page_id, locale)
);

CREATE TABLE cms_media (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    storage_key varchar(255) NOT NULL UNIQUE,
    mime_type varchar(40) NOT NULL CHECK (mime_type IN ('image/jpeg', 'image/png', 'image/webp', 'image/avif')),
    byte_size bigint NOT NULL CHECK (byte_size > 0 AND byte_size <= 15728640),
    width integer NOT NULL CHECK (width > 0),
    height integer NOT NULL CHECK (height > 0),
    sha256 char(64) NOT NULL CHECK (sha256 ~ '^[0-9a-f]{64}$'),
    created_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT cms_media_safe_key CHECK (storage_key ~ '^[a-zA-Z0-9][a-zA-Z0-9/_-]*\.(jpg|jpeg|png|webp|avif)$' AND storage_key !~ '(^|/)\.\.')
);

CREATE TABLE cms_media_translations (
    media_id bigint NOT NULL REFERENCES cms_media(id) ON DELETE CASCADE,
    locale varchar(2) NOT NULL CHECK (locale IN ('sk','en')),
    alt_text varchar(300) NOT NULL,
    caption varchar(500) NOT NULL DEFAULT '',
    PRIMARY KEY (media_id, locale)
);

CREATE TABLE cms_gallery_items (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    media_id bigint NOT NULL UNIQUE REFERENCES cms_media(id) ON DELETE RESTRICT,
    position integer NOT NULL CHECK (position >= 0),
    is_visible boolean NOT NULL DEFAULT true
);
CREATE INDEX cms_gallery_items_order_idx ON cms_gallery_items(position, id);

-- First night and subsequent nights have different rates.
-- A future pricing service must determine the applicable rate and season for each night;
-- no booking totals or season-boundary rules are implemented by this migration.
-- Dates are inclusive in admin input; stored as [start_date, end_date_exclusive).
CREATE EXTENSION IF NOT EXISTS btree_gist;
CREATE TABLE pricing_settings (
    singleton_id smallint PRIMARY KEY DEFAULT 1 CHECK (singleton_id = 1),
    first_night_price_cents integer CHECK (first_night_price_cents >= 0),
    additional_night_price_cents integer CHECK (additional_night_price_cents >= 0),
    CONSTRAINT pricing_settings_prices_together CHECK (
      (first_night_price_cents IS NULL) = (additional_night_price_cents IS NULL)
    ),
    minimum_nights integer NOT NULL DEFAULT 1 CHECK (minimum_nights BETWEEN 1 AND 365),
    currency char(3) NOT NULL DEFAULT 'EUR' CHECK (currency = 'EUR'),
    updated_at timestamptz NOT NULL DEFAULT now()
);
INSERT INTO pricing_settings (singleton_id) VALUES (1);

CREATE TABLE pricing_seasons (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name varchar(120) NOT NULL,
    start_date date NOT NULL,
    end_date_exclusive date NOT NULL,
    first_night_price_cents integer NOT NULL CHECK (first_night_price_cents >= 0),
    additional_night_price_cents integer NOT NULL CHECK (additional_night_price_cents >= 0),
    updated_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT pricing_seasons_valid_period CHECK (start_date < end_date_exclusive),
    CONSTRAINT pricing_seasons_no_overlap EXCLUDE USING gist
      (daterange(start_date, end_date_exclusive, '[)') WITH &&)
);

CREATE TABLE pricing_extras (
    code varchar(40) PRIMARY KEY CHECK (code ~ '^[a-z][a-z0-9_]*$'),
    charge_unit varchar(12) NOT NULL CHECK (charge_unit IN ('per_day', 'per_stay', 'included')),
    price_cents integer CHECK (price_cents >= 0),
    is_active boolean NOT NULL DEFAULT false,
    sort_order integer NOT NULL DEFAULT 0 CHECK (sort_order >= 0),
    CONSTRAINT pricing_extras_price_required CHECK ((charge_unit = 'included' AND price_cents = 0) OR (charge_unit <> 'included' AND price_cents IS NOT NULL))
);
CREATE TABLE pricing_extra_translations (
    extra_code varchar(40) NOT NULL REFERENCES pricing_extras(code) ON DELETE CASCADE,
    locale varchar(2) NOT NULL CHECK (locale IN ('sk','en')),
    name varchar(120) NOT NULL,
    description text NOT NULL DEFAULT '',
    PRIMARY KEY (extra_code, locale)
);

-- Editable non-secret public facts only. SMTP/password/IBAN are NOT stored here.
CREATE TABLE public_settings (
    key varchar(80) PRIMARY KEY CHECK (key ~ '^[a-z][a-z0-9_]*$'),
    value text NOT NULL,
    updated_at timestamptz NOT NULL DEFAULT now()
);
