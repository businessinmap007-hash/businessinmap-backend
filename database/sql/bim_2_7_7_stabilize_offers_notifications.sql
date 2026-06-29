-- BIM 2.7.7 — Stabilize Offers + Notifications + SQL
-- Run this file manually on the BIM database.
-- It is written to be safe for existing data where possible.

/*
|--------------------------------------------------------------------------
| 1) platform_services: rules / meta support
|--------------------------------------------------------------------------
*/

ALTER TABLE platform_services
    ADD COLUMN IF NOT EXISTS rules JSON NULL AFTER supports_deposit,
    ADD COLUMN IF NOT EXISTS meta JSON NULL AFTER rules;

/*
|--------------------------------------------------------------------------
| 2) business_offers platform service seed
|--------------------------------------------------------------------------
*/

INSERT INTO platform_services
(`key`, name_ar, name_en, is_active, supports_deposit, rules, created_at, updated_at)
VALUES
(
  'business_offers',
  'العروض التجارية',
  'Business Offers',
  1,
  0,
  JSON_OBJECT(
    'requires_subscription', true,
    'free_trial_enabled', false,
    'max_active_offers', 5,
    'duration_days', 30,
    'fixed_fee', 20,
    'currency', 'EGP',
    'count_sources', JSON_ARRAY('direct', 'reseller', 'promotion', 'marketplace')
  ),
  NOW(),
  NOW()
)
ON DUPLICATE KEY UPDATE
  name_ar = VALUES(name_ar),
  name_en = VALUES(name_en),
  is_active = VALUES(is_active),
  supports_deposit = VALUES(supports_deposit),
  rules = VALUES(rules),
  updated_at = NOW();

/*
|--------------------------------------------------------------------------
| 3) commercial_offers compatibility columns
|--------------------------------------------------------------------------
*/

ALTER TABLE commercial_offers
    ADD COLUMN IF NOT EXISTS audience_type VARCHAR(30) NOT NULL DEFAULT 'both' AFTER source_type,
    ADD COLUMN IF NOT EXISTS is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER ranking_score,
    ADD COLUMN IF NOT EXISTS featured_until DATETIME NULL AFTER is_featured,
    ADD COLUMN IF NOT EXISTS boost_score DECIMAL(10,4) NOT NULL DEFAULT 0.0000 AFTER featured_until;

CREATE INDEX IF NOT EXISTS idx_commercial_offers_audience_status
    ON commercial_offers (audience_type, status);

CREATE INDEX IF NOT EXISTS idx_commercial_offers_boost
    ON commercial_offers (is_featured, featured_until, boost_score);

/*
|--------------------------------------------------------------------------
| 4) offer_follows table
|--------------------------------------------------------------------------
*/

CREATE TABLE IF NOT EXISTS offer_follows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    followable_type VARCHAR(50) NOT NULL,
    followable_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    keyword VARCHAR(191) NULL,
    category_id BIGINT UNSIGNED NULL,
    category_child_id BIGINT UNSIGNED NULL,
    audience_type VARCHAR(30) NULL,
    min_price DECIMAL(12,2) NULL,
    max_price DECIMAL(12,2) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_matched_at DATETIME NULL,
    meta JSON NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_offer_follows_user_active (user_id, is_active),
    INDEX idx_offer_follows_followable (followable_type, followable_id),
    INDEX idx_offer_follows_category_child (category_child_id),
    INDEX idx_offer_follows_keyword (keyword)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*
|--------------------------------------------------------------------------
| 5) offer_follow_notifications table
|--------------------------------------------------------------------------
*/

CREATE TABLE IF NOT EXISTS offer_follow_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    follow_id BIGINT UNSIGNED NOT NULL,
    offer_id BIGINT UNSIGNED NOT NULL,
    match_type VARCHAR(50) NOT NULL,
    match_score DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    status VARCHAR(30) NOT NULL DEFAULT 'unread',
    read_at DATETIME NULL,
    meta JSON NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_offer_follow_notification (follow_id, offer_id),
    INDEX idx_offer_follow_notifications_user_status (user_id, status),
    INDEX idx_offer_follow_notifications_offer (offer_id),
    INDEX idx_offer_follow_notifications_follow (follow_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*
|--------------------------------------------------------------------------
| 6) app_notifications table
|--------------------------------------------------------------------------
*/

CREATE TABLE IF NOT EXISTS app_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    actor_id BIGINT UNSIGNED NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'system',
    channel VARCHAR(50) NOT NULL DEFAULT 'in_app',
    priority VARCHAR(30) NOT NULL DEFAULT 'normal',
    title_ar VARCHAR(255) NULL,
    title_en VARCHAR(255) NULL,
    body_ar TEXT NULL,
    body_en TEXT NULL,
    action_type VARCHAR(80) NULL,
    action_url VARCHAR(500) NULL,
    notifiable_type VARCHAR(191) NULL,
    notifiable_id BIGINT UNSIGNED NULL,
    source_type VARCHAR(100) NULL,
    source_id BIGINT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'unread',
    read_at DATETIME NULL,
    archived_at DATETIME NULL,
    delivered_at DATETIME NULL,
    expires_at DATETIME NULL,
    meta JSON NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_app_notifications_user_status (user_id, status),
    INDEX idx_app_notifications_type_priority (type, priority),
    INDEX idx_app_notifications_source (source_type, source_id),
    INDEX idx_app_notifications_notifiable (notifiable_type, notifiable_id),
    INDEX idx_app_notifications_delivered (delivered_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*
|--------------------------------------------------------------------------
| 7) user_push_tokens table
|--------------------------------------------------------------------------
*/

CREATE TABLE IF NOT EXISTS user_push_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    platform VARCHAR(30) NOT NULL,
    provider VARCHAR(50) NOT NULL DEFAULT 'fcm',
    device_id VARCHAR(191) NULL,
    token VARCHAR(1000) NOT NULL,
    app_version VARCHAR(50) NULL,
    locale VARCHAR(20) NULL,
    timezone VARCHAR(80) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_seen_at DATETIME NULL,
    meta JSON NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_user_push_token (user_id, token(191)),
    INDEX idx_user_push_tokens_user_active (user_id, is_active),
    INDEX idx_user_push_tokens_platform (platform),
    INDEX idx_user_push_tokens_provider (provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*
|--------------------------------------------------------------------------
| 8) Optional quick checks
|--------------------------------------------------------------------------
*/

SELECT 'platform_services' AS table_name, COUNT(*) AS rows_count FROM platform_services WHERE `key` = 'business_offers';
SELECT 'commercial_offers' AS table_name, COUNT(*) AS rows_count FROM commercial_offers;
SELECT 'offer_follows' AS table_name, COUNT(*) AS rows_count FROM offer_follows;
SELECT 'offer_follow_notifications' AS table_name, COUNT(*) AS rows_count FROM offer_follow_notifications;
SELECT 'app_notifications' AS table_name, COUNT(*) AS rows_count FROM app_notifications;
SELECT 'user_push_tokens' AS table_name, COUNT(*) AS rows_count FROM user_push_tokens;
