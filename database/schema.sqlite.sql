CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS applications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ref_code TEXT NOT NULL UNIQUE,
    full_name TEXT NOT NULL,
    phone TEXT NOT NULL,
    city TEXT NOT NULL,
    district TEXT NULL,
    brand TEXT NOT NULL,
    model TEXT NOT NULL,
    model_year INTEGER NOT NULL,
    km INTEGER NOT NULL,
    fuel TEXT NOT NULL,
    gearbox TEXT NOT NULL,
    damage TEXT NOT NULL,
    tramer_amount INTEGER NULL,
    expected_price INTEGER NULL,
    urgency TEXT NOT NULL,
    notes TEXT NULL,
    kvkk_consent INTEGER NOT NULL DEFAULT 0,
    marketing_consent INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'new',
    admin_notes TEXT NULL,
    ip_hash TEXT NOT NULL,
    user_agent TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_applications_status ON applications (status, created_at);

CREATE TABLE IF NOT EXISTS application_photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    application_id INTEGER NOT NULL REFERENCES applications (id) ON DELETE CASCADE,
    file_name TEXT NOT NULL,
    mime TEXT NOT NULL,
    size INTEGER NOT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_photos_application ON application_photos (application_id);

CREATE TABLE IF NOT EXISTS rate_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bucket TEXT NOT NULL,
    key_hash TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_rate_limits_lookup ON rate_limits (bucket, key_hash, created_at);

CREATE TABLE IF NOT EXISTS settings (
    name TEXT PRIMARY KEY,
    value TEXT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS tsb_cache (
    cache_key TEXT PRIMARY KEY,
    payload TEXT NOT NULL,
    fetched_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS radar_listings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source TEXT NOT NULL,
    site TEXT NULL,
    source_ref TEXT NULL,
    url TEXT NULL,
    url_hash TEXT NOT NULL UNIQUE,
    title TEXT NULL,
    description TEXT NULL,
    brand TEXT NULL,
    model TEXT NULL,
    model_key TEXT NULL,
    model_year INTEGER NULL,
    km INTEGER NULL,
    fuel TEXT NULL,
    gearbox TEXT NULL,
    damage TEXT NULL,
    city TEXT NULL,
    price INTEGER NULL,
    is_auction INTEGER NOT NULL DEFAULT 0,
    auction_ends_at TEXT NULL,
    tsb_value INTEGER NULL,
    tsb_label TEXT NULL,
    market_value INTEGER NULL,
    discount_pct REAL NULL,
    score INTEGER NULL,
    urgent INTEGER NOT NULL DEFAULT 0,
    suspicious INTEGER NOT NULL DEFAULT 0,
    score_notes TEXT NULL,
    needs_valuation INTEGER NOT NULL DEFAULT 1,
    valued_at TEXT NULL,
    status TEXT NOT NULL DEFAULT 'new',
    notes TEXT NULL,
    search_text TEXT NULL,
    first_seen_at TEXT NOT NULL,
    last_seen_at TEXT NOT NULL,
    price_changed_at TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_radar_score ON radar_listings (score);
CREATE INDEX IF NOT EXISTS idx_radar_brand ON radar_listings (brand, model_key, model_year);
CREATE INDEX IF NOT EXISTS idx_radar_status ON radar_listings (status);
CREATE INDEX IF NOT EXISTS idx_radar_valuation ON radar_listings (needs_valuation);

CREATE TABLE IF NOT EXISTS radar_price_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    listing_id INTEGER NOT NULL REFERENCES radar_listings (id) ON DELETE CASCADE,
    price INTEGER NOT NULL,
    seen_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_price_history_listing ON radar_price_history (listing_id);

CREATE TABLE IF NOT EXISTS radar_runs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source TEXT NOT NULL,
    started_at TEXT NOT NULL,
    finished_at TEXT NULL,
    found INTEGER NOT NULL DEFAULT 0,
    added INTEGER NOT NULL DEFAULT 0,
    message TEXT NULL
);

CREATE INDEX IF NOT EXISTS idx_runs_started ON radar_runs (started_at);

CREATE TABLE IF NOT EXISTS radar_saved_searches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    query_string TEXT NOT NULL,
    created_at TEXT NOT NULL
);
