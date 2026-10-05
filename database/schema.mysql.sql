CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS applications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ref_code VARCHAR(12) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    city VARCHAR(40) NOT NULL,
    district VARCHAR(60) NULL,
    brand VARCHAR(60) NOT NULL,
    model VARCHAR(80) NOT NULL,
    model_year SMALLINT UNSIGNED NOT NULL,
    km INT UNSIGNED NOT NULL,
    fuel VARCHAR(20) NOT NULL,
    gearbox VARCHAR(20) NOT NULL,
    damage VARCHAR(40) NOT NULL,
    tramer_amount INT UNSIGNED NULL,
    expected_price INT UNSIGNED NULL,
    urgency VARCHAR(20) NOT NULL,
    notes TEXT NULL,
    kvkk_consent TINYINT(1) NOT NULL DEFAULT 0,
    marketing_consent TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    admin_notes TEXT NULL,
    ip_hash CHAR(64) NOT NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_applications_ref (ref_code),
    KEY idx_applications_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS application_photos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    file_name VARCHAR(80) NOT NULL,
    mime VARCHAR(30) NOT NULL,
    size INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_photos_application (application_id),
    CONSTRAINT fk_photos_application FOREIGN KEY (application_id) REFERENCES applications (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bucket VARCHAR(40) NOT NULL,
    key_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_rate_limits_lookup (bucket, key_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    name VARCHAR(80) NOT NULL PRIMARY KEY,
    value TEXT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tsb_cache (
    cache_key VARCHAR(80) NOT NULL PRIMARY KEY,
    payload MEDIUMTEXT NOT NULL,
    fetched_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS radar_listings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(30) NOT NULL,
    site VARCHAR(60) NULL,
    source_ref VARCHAR(100) NULL,
    url VARCHAR(600) NULL,
    url_hash CHAR(64) NOT NULL,
    title VARCHAR(300) NULL,
    description TEXT NULL,
    brand VARCHAR(60) NULL,
    model VARCHAR(120) NULL,
    model_key VARCHAR(60) NULL,
    model_year SMALLINT UNSIGNED NULL,
    km INT UNSIGNED NULL,
    fuel VARCHAR(20) NULL,
    gearbox VARCHAR(20) NULL,
    damage VARCHAR(40) NULL,
    city VARCHAR(40) NULL,
    price INT UNSIGNED NULL,
    is_auction TINYINT(1) NOT NULL DEFAULT 0,
    auction_ends_at DATETIME NULL,
    tsb_value INT UNSIGNED NULL,
    tsb_label VARCHAR(160) NULL,
    market_value INT UNSIGNED NULL,
    discount_pct DECIMAL(6,2) NULL,
    score SMALLINT NULL,
    urgent TINYINT(1) NOT NULL DEFAULT 0,
    suspicious TINYINT(1) NOT NULL DEFAULT 0,
    score_notes TEXT NULL,
    needs_valuation TINYINT(1) NOT NULL DEFAULT 1,
    valued_at DATETIME NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    notes TEXT NULL,
    search_text TEXT NULL,
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    price_changed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_radar_url (url_hash),
    KEY idx_radar_score (score),
    KEY idx_radar_brand (brand, model_key, model_year),
    KEY idx_radar_status (status),
    KEY idx_radar_valuation (needs_valuation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS radar_price_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    listing_id INT UNSIGNED NOT NULL,
    price INT UNSIGNED NOT NULL,
    seen_at DATETIME NOT NULL,
    KEY idx_price_history_listing (listing_id),
    CONSTRAINT fk_price_history_listing FOREIGN KEY (listing_id) REFERENCES radar_listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS radar_runs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(30) NOT NULL,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    found INT UNSIGNED NOT NULL DEFAULT 0,
    added INT UNSIGNED NOT NULL DEFAULT 0,
    message TEXT NULL,
    KEY idx_runs_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS radar_saved_searches (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    query_string VARCHAR(1000) NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS radar_alerts (
    listing_id INT UNSIGNED NOT NULL PRIMARY KEY,
    channel VARCHAR(20) NOT NULL,
    sent_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS radar_details (
    listing_id INT UNSIGNED NOT NULL PRIMARY KEY,
    data MEDIUMTEXT NOT NULL,
    analysis MEDIUMTEXT NULL,
    raw MEDIUMTEXT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
