-- BIM 2.7.13

CREATE TABLE IF NOT EXISTS business_client_allowlist (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    list_type VARCHAR(50) NOT NULL DEFAULT 'vip',
    skip_deposit TINYINT(1) NOT NULL DEFAULT 1,
    skip_guarantee TINYINT(1) NOT NULL DEFAULT 1,
    max_active_bookings INT UNSIGNED NULL,
    max_booking_value DECIMAL(12,2) NULL,
    notes TEXT NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    meta JSON NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_business_client_allowlist (business_id, client_id),
    KEY idx_bca_business_active (business_id, is_active),
    KEY idx_bca_client_active (client_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
