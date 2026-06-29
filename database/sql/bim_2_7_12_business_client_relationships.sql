-- BIM 2.7.12
-- Business / client relationship stats foundation.
-- This table tracks booking history between one client and one business.
-- It does not change platform service fees. Booking/menu/service fees remain payable to the platform.

CREATE TABLE IF NOT EXISTS business_client_relationships (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,

    total_operations INT UNSIGNED NOT NULL DEFAULT 0,
    completed_operations INT UNSIGNED NOT NULL DEFAULT 0,
    cancelled_operations INT UNSIGNED NOT NULL DEFAULT 0,
    rejected_operations INT UNSIGNED NOT NULL DEFAULT 0,
    disputed_operations INT UNSIGNED NOT NULL DEFAULT 0,
    client_no_show_count INT UNSIGNED NOT NULL DEFAULT 0,
    business_cancelled_count INT UNSIGNED NOT NULL DEFAULT 0,

    total_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    completed_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    avg_client_rating_for_business DECIMAL(5,2) NULL,
    avg_business_rating_for_client DECIMAL(5,2) NULL,
    client_trust_score_for_business DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    business_trust_score_for_client DECIMAL(5,2) NOT NULL DEFAULT 0.00,

    last_operation_at DATETIME NULL,
    last_completed_at DATETIME NULL,
    last_problem_at DATETIME NULL,

    is_trusted_client TINYINT(1) NOT NULL DEFAULT 0,
    trusted_type VARCHAR(50) NULL,
    trusted_at DATETIME NULL,
    trusted_by BIGINT UNSIGNED NULL,
    trust_notes TEXT NULL,

    meta JSON NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,

    UNIQUE KEY uq_business_client_relationship (business_id, client_id),
    KEY idx_bcr_business (business_id),
    KEY idx_bcr_client (client_id),
    KEY idx_bcr_trusted (business_id, is_trusted_client),
    KEY idx_bcr_score (business_id, client_trust_score_for_business)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO business_client_relationships (
    business_id,
    client_id,
    total_operations,
    completed_operations,
    cancelled_operations,
    rejected_operations,
    disputed_operations,
    total_value,
    completed_value,
    client_trust_score_for_business,
    business_trust_score_for_client,
    last_operation_at,
    last_completed_at,
    last_problem_at,
    created_at,
    updated_at
)
SELECT
    b.business_id,
    b.user_id AS client_id,
    COUNT(*) AS total_operations,
    SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) AS completed_operations,
    SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_operations,
    SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_operations,
    0 AS disputed_operations,
    COALESCE(SUM(b.price), 0) AS total_value,
    COALESCE(SUM(CASE WHEN b.status = 'completed' THEN b.price ELSE 0 END), 0) AS completed_value,
    LEAST(100, GREATEST(0,
        50
        + LEAST(SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) * 2.5, 35)
        - LEAST(SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) * 4, 20)
        - LEAST(SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) * 3, 15)
    )) AS client_trust_score_for_business,
    LEAST(100, GREATEST(0,
        50
        + LEAST(SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) * 2.5, 35)
        - LEAST(SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) * 4, 20)
        - LEAST(SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) * 3, 15)
    )) AS business_trust_score_for_client,
    MAX(b.created_at) AS last_operation_at,
    MAX(CASE WHEN b.status = 'completed' THEN b.updated_at ELSE NULL END) AS last_completed_at,
    MAX(CASE WHEN b.status IN ('cancelled', 'rejected') THEN b.updated_at ELSE NULL END) AS last_problem_at,
    NOW(),
    NOW()
FROM bookings b
WHERE b.business_id IS NOT NULL
  AND b.user_id IS NOT NULL
GROUP BY b.business_id, b.user_id
ON DUPLICATE KEY UPDATE
    total_operations = VALUES(total_operations),
    completed_operations = VALUES(completed_operations),
    cancelled_operations = VALUES(cancelled_operations),
    rejected_operations = VALUES(rejected_operations),
    total_value = VALUES(total_value),
    completed_value = VALUES(completed_value),
    client_trust_score_for_business = VALUES(client_trust_score_for_business),
    business_trust_score_for_client = VALUES(business_trust_score_for_client),
    last_operation_at = VALUES(last_operation_at),
    last_completed_at = VALUES(last_completed_at),
    last_problem_at = VALUES(last_problem_at),
    updated_at = NOW();

SELECT
    COUNT(*) AS relationship_rows,
    SUM(total_operations) AS tracked_operations,
    SUM(completed_operations) AS tracked_completed
FROM business_client_relationships;
