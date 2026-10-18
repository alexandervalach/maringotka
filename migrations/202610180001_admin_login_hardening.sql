-- Additive migration: do not alter the already-applied Commit 08 migration.
ALTER TABLE admin_account
    ADD COLUMN username varchar(64) NOT NULL DEFAULT 'admin',
    ADD CONSTRAINT admin_account_username_format
        CHECK (username ~ '^[a-z][a-z0-9_.-]{2,63}$');

-- IP-specific throttling, not an account-wide lockout. IPs are retained here
-- for up to seven days; see the documented cleanup command.
CREATE TABLE admin_login_attempts (
    ip_address inet PRIMARY KEY,
    failed_attempts integer NOT NULL DEFAULT 0 CHECK (failed_attempts >= 0),
    first_failed_at timestamptz NOT NULL DEFAULT now(),
    last_failed_at timestamptz NOT NULL DEFAULT now(),
    blocked_until timestamptz
);
CREATE INDEX admin_login_attempts_last_failed_at_idx ON admin_login_attempts (last_failed_at);
