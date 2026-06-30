-- BIM Catalog Data Sources V1
-- Optional tracking table for product source/audit data.

CREATE TABLE IF NOT EXISTS catalog_data_sources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_key VARCHAR(120) NOT NULL,
    source_name VARCHAR(180) NOT NULL,
    source_type ENUM('manual','api','open_dataset','vendor_file','scraper','other') NOT NULL DEFAULT 'manual',
    base_url VARCHAR(255) NULL,
    license_name VARCHAR(160) NULL,
    license_url VARCHAR(255) NULL,
    trust_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    meta JSON NULL,
    last_collected_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY cds_source_key_unique (source_key),
    KEY cds_type_active_idx (source_type, is_active),
    KEY cds_trust_idx (trust_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_product_source_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    external_id VARCHAR(180) NULL,
    external_url VARCHAR(255) NULL,
    source_barcode VARCHAR(80) NULL,
    source_image_url VARCHAR(255) NULL,
    confidence_score DECIMAL(5,2) NOT NULL DEFAULT 0,
    review_status ENUM('pending','approved','rejected','needs_review') NOT NULL DEFAULT 'pending',
    collected_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY cpsl_product_source_external_unique (product_id, source_id, external_id),
    KEY cpsl_source_idx (source_id),
    KEY cpsl_barcode_idx (source_barcode),
    KEY cpsl_review_idx (review_status),
    CONSTRAINT cpsl_product_fk FOREIGN KEY (product_id) REFERENCES catalog_products(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT cpsl_source_fk FOREIGN KEY (source_id) REFERENCES catalog_data_sources(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
