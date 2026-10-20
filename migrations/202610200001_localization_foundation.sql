-- Allow future ISO 639-1 language codes without rewriting an applied CMS migration.
-- Supported locales remain an application-level allowlist (SupportedLocales::CODES).
-- Existing two-letter SK/EN values are preserved; data is not rewritten.
ALTER TABLE cms_page_translations
  DROP CONSTRAINT cms_page_translations_locale_check;
ALTER TABLE cms_page_translations
  ADD CONSTRAINT cms_page_translations_locale_format
  CHECK (locale ~ '^[a-z]{2}$');

ALTER TABLE cms_media_translations
  DROP CONSTRAINT cms_media_translations_locale_check;
ALTER TABLE cms_media_translations
  ADD CONSTRAINT cms_media_translations_locale_format
  CHECK (locale ~ '^[a-z]{2}$');

ALTER TABLE pricing_extra_translations
  DROP CONSTRAINT pricing_extra_translations_locale_check;
ALTER TABLE pricing_extra_translations
  ADD CONSTRAINT pricing_extra_translations_locale_format
  CHECK (locale ~ '^[a-z]{2}$');
