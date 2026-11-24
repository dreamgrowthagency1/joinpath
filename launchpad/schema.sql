-- LaunchPad schema (MySQL 5.7+/MariaDB 10.3+, utf8mb4). Indexes are separate statements on purpose.

CREATE TABLE accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL
);

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_id INT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL,
  name VARCHAR(120) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  is_admin TINYINT NOT NULL DEFAULT 0,
  lang VARCHAR(8) NOT NULL DEFAULT 'en',
  created_at DATETIME NOT NULL,
  last_login_at DATETIME NULL
);
CREATE UNIQUE INDEX ux_users_email ON users (email);
CREATE INDEX ix_users_account ON users (account_id);

CREATE TABLE pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_id INT UNSIGNED NOT NULL,
  slug VARCHAR(40) NOT NULL,
  name VARCHAR(160) NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'draft',
  template VARCHAR(20) NOT NULL DEFAULT 'channel',
  content_json TEXT NULL,
  custom_html MEDIUMTEXT NULL,
  pixel_id VARCHAR(40) NOT NULL DEFAULT '',
  capi_token_enc TEXT NULL,
  test_event_code VARCHAR(40) NOT NULL DEFAULT '',
  event_name VARCHAR(40) NOT NULL DEFAULT 'Lead',
  bot_token_enc TEXT NULL,
  bot_id BIGINT NULL,
  bot_username VARCHAR(80) NOT NULL DEFAULT '',
  chat_ref VARCHAR(120) NOT NULL DEFAULT '',
  chat_id BIGINT NULL,
  chat_title VARCHAR(190) NOT NULL DEFAULT '',
  webhook_key VARCHAR(32) NOT NULL,
  webhook_secret VARCHAR(64) NOT NULL,
  webhook_set_at DATETIME NULL,
  last_webhook_at DATETIME NULL,
  last_test_at DATETIME NULL,
  last_test_ok TINYINT NULL,
  last_test_msg VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);
CREATE UNIQUE INDEX ux_pages_slug ON pages (slug);
CREATE INDEX ix_pages_account ON pages (account_id);

CREATE TABLE visitors (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page_id INT UNSIGNED NOT NULL,
  vid CHAR(32) NOT NULL,
  fbclid VARCHAR(255) NOT NULL DEFAULT '',
  fbc VARCHAR(300) NOT NULL DEFAULT '',
  fbp VARCHAR(100) NOT NULL DEFAULT '',
  ip VARCHAR(64) NOT NULL DEFAULT '',
  ua VARCHAR(400) NOT NULL DEFAULT '',
  utm_source VARCHAR(120) NOT NULL DEFAULT '',
  utm_medium VARCHAR(120) NOT NULL DEFAULT '',
  utm_campaign VARCHAR(190) NOT NULL DEFAULT '',
  utm_content VARCHAR(190) NOT NULL DEFAULT '',
  utm_term VARCHAR(190) NOT NULL DEFAULT '',
  referrer VARCHAR(400) NOT NULL DEFAULT '',
  landing_url VARCHAR(600) NOT NULL DEFAULT '',
  first_seen DATETIME NOT NULL,
  last_seen DATETIME NOT NULL
);
CREATE UNIQUE INDEX ux_visitors_page_vid ON visitors (page_id, vid);
CREATE INDEX ix_visitors_page_seen ON visitors (page_id, first_seen);

CREATE TABLE invite_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page_id INT UNSIGNED NOT NULL,
  visitor_id BIGINT UNSIGNED NOT NULL,
  link VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL
);
CREATE UNIQUE INDEX ux_links_link ON invite_links (link);
CREATE INDEX ix_links_page_created ON invite_links (page_id, created_at);
CREATE INDEX ix_links_visitor ON invite_links (visitor_id);

CREATE TABLE joins (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page_id INT UNSIGNED NOT NULL,
  visitor_id BIGINT UNSIGNED NULL,
  invite_link_id BIGINT UNSIGNED NULL,
  tg_update_id BIGINT NOT NULL DEFAULT 0,
  tg_user_hash CHAR(64) NOT NULL DEFAULT '',
  matched TINYINT NOT NULL DEFAULT 0,
  event_id VARCHAR(64) NOT NULL,
  sent_status VARCHAR(12) NOT NULL DEFAULT 'pending',
  attempts INT NOT NULL DEFAULT 0,
  last_error VARCHAR(255) NOT NULL DEFAULT '',
  joined_at DATETIME NOT NULL,
  sent_at DATETIME NULL
);
CREATE UNIQUE INDEX ux_joins_update ON joins (page_id, tg_update_id);
CREATE INDEX ix_joins_page_joined ON joins (page_id, joined_at);
CREATE INDEX ix_joins_status ON joins (sent_status, joined_at);

CREATE TABLE meta_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page_id INT UNSIGNED NOT NULL,
  join_id BIGINT UNSIGNED NULL,
  kind VARCHAR(10) NOT NULL,
  event_name VARCHAR(40) NOT NULL,
  event_id VARCHAR(64) NOT NULL,
  http_status INT NOT NULL DEFAULT 0,
  response VARCHAR(1000) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL
);
CREATE INDEX ix_events_page_created ON meta_events (page_id, created_at);

CREATE TABLE health_checks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page_id INT UNSIGNED NOT NULL,
  check_key VARCHAR(32) NOT NULL,
  status VARCHAR(8) NOT NULL,
  detail VARCHAR(400) NOT NULL DEFAULT '',
  checked_at DATETIME NOT NULL
);
CREATE UNIQUE INDEX ux_health_page_key ON health_checks (page_id, check_key);

CREATE TABLE login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(64) NOT NULL,
  email VARCHAR(190) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL
);
CREATE INDEX ix_login_ip_created ON login_attempts (ip, created_at);

CREATE TABLE rate_limits (
  rkey VARCHAR(120) NOT NULL PRIMARY KEY,
  hits INT NOT NULL DEFAULT 0,
  window_start INT NOT NULL
);
