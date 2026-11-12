-- Only the administrator may edit templates through the authenticated UI.
CREATE TABLE IF NOT EXISTS booking_mail_templates (
  template_key varchar(32) PRIMARY KEY,
  subject varchar(180) NOT NULL,
  body text NOT NULL,
  show_logo boolean NOT NULL DEFAULT true,
  updated_at timestamptz NOT NULL DEFAULT now()
);
