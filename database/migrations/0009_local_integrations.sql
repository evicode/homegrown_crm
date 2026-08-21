CREATE TABLE integration_clients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    client_identifier VARCHAR(128) NOT NULL,
    display_name VARCHAR(160) NOT NULL,
    environment VARCHAR(32) NOT NULL,
    maximum_scopes_json TEXT NOT NULL,
    revoked_at DATETIME(6) NULL,
    revoked_reason VARCHAR(255) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_integration_client_identifier (environment, client_identifier),
    INDEX idx_integration_owner_active (owner_user_id, revoked_at),
    CONSTRAINT fk_integration_client_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE integration_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    integration_client_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    token_prefix VARCHAR(20) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    revoked_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_integration_token_hash (token_hash),
    INDEX idx_integration_token_active (integration_client_id, revoked_at, expires_at),
    CONSTRAINT fk_integration_token_client FOREIGN KEY (integration_client_id) REFERENCES integration_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE integration_access_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    integration_client_id BIGINT UNSIGNED NULL,
    environment VARCHAR(32) NOT NULL,
    transport VARCHAR(32) NOT NULL,
    operation_key VARCHAR(100) NOT NULL,
    outcome_code VARCHAR(64) NOT NULL,
    correlation_id VARCHAR(64) NOT NULL,
    network_fingerprint CHAR(64) NULL,
    occurred_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_integration_access_client_time (integration_client_id, occurred_at, id),
    INDEX idx_integration_access_expiry (occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE idempotency_records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    integration_client_id BIGINT UNSIGNED NOT NULL,
    idempotency_key VARCHAR(128) NOT NULL,
    operation_key VARCHAR(100) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    outcome_code VARCHAR(64) NULL,
    replay_json TEXT NULL,
    completed_at DATETIME(6) NULL,
    expires_at DATETIME(6) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_idempotency_client_key (integration_client_id, idempotency_key),
    INDEX idx_idempotency_expiry (expires_at),
    CONSTRAINT fk_idempotency_client FOREIGN KEY (integration_client_id) REFERENCES integration_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limit_buckets (
    policy_key VARCHAR(100) NOT NULL,
    identity_hash CHAR(64) NOT NULL,
    window_started_at DATETIME(6) NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME(6) NOT NULL,
    PRIMARY KEY (policy_key, identity_hash, window_started_at),
    INDEX idx_rate_limit_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
