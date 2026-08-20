CREATE TABLE application_settings (
    id TINYINT UNSIGNED NOT NULL,
    active_campaign_id BIGINT UNSIGNED NULL,
    owner_timezone VARCHAR(64) NOT NULL,
    external_access_enabled TINYINT(1) NOT NULL DEFAULT 0,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT chk_application_settings_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO application_settings (id, owner_timezone) VALUES (1, 'America/Los_Angeles');

CREATE TABLE audit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_type VARCHAR(32) NOT NULL,
    owner_user_id BIGINT UNSIGNED NULL,
    integration_client_id BIGINT UNSIGNED NULL,
    external_subject VARCHAR(255) NULL,
    issuer VARCHAR(500) NULL,
    operation VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id VARCHAR(100) NULL,
    correlation_id VARCHAR(64) NOT NULL,
    metadata_json TEXT NOT NULL,
    occurred_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_audit_occurred (occurred_at, id),
    INDEX idx_audit_entity (entity_type, entity_id, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
