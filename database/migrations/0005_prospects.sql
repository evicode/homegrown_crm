CREATE TABLE signals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    signal_key VARCHAR(64) NOT NULL,
    name VARCHAR(160) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id), UNIQUE KEY uq_signals_key (signal_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO signals (signal_key, name) VALUES
('hiring_engineers','Hiring engineers'),('product_launch','Product launch or expansion'),
('legacy_rebuild','Legacy rebuild or modernization'),('delivery_bottleneck','Delivery bottleneck'),
('funding_or_growth','Funding or growth event'),('leadership_change','Leadership change'),
('public_technical_pain','Public technical pain'),('other','Other observed signal');

CREATE TABLE prospects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NULL,
    primary_contact_id BIGINT UNSIGNED NULL,
    segment VARCHAR(32) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'researching',
    source VARCHAR(255) NULL,
    why_them TEXT NULL,
    business_problem TEXT NULL,
    qualification_notes TEXT NULL,
    problem_understood TINYINT(1) NOT NULL DEFAULT 0,
    timing_understood TINYINT(1) NOT NULL DEFAULT 0,
    buyer_understood TINYINT(1) NOT NULL DEFAULT 0,
    budget_plausible TINYINT(1) NOT NULL DEFAULT 0,
    last_contact_at DATETIME(6) NULL,
    archived_at DATETIME(6) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    active_company_only BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN archived_at IS NULL AND company_id IS NOT NULL AND primary_contact_id IS NULL THEN company_id ELSE NULL END) STORED,
    active_contact_only BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN archived_at IS NULL AND company_id IS NULL AND primary_contact_id IS NOT NULL THEN primary_contact_id ELSE NULL END) STORED,
    active_pair_company BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN archived_at IS NULL AND company_id IS NOT NULL AND primary_contact_id IS NOT NULL THEN company_id ELSE NULL END) STORED,
    active_pair_contact BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN archived_at IS NULL AND company_id IS NOT NULL AND primary_contact_id IS NOT NULL THEN primary_contact_id ELSE NULL END) STORED,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_prospect_company_only (campaign_id, active_company_only),
    UNIQUE KEY uq_prospect_contact_only (campaign_id, active_contact_only),
    UNIQUE KEY uq_prospect_pair (campaign_id, active_pair_company, active_pair_contact),
    INDEX idx_prospects_campaign_state (campaign_id, archived_at, status),
    CONSTRAINT fk_prospects_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    CONSTRAINT fk_prospects_company FOREIGN KEY (company_id) REFERENCES companies(id),
    CONSTRAINT fk_prospects_contact FOREIGN KEY (primary_contact_id) REFERENCES contacts(id),
    CONSTRAINT chk_prospect_anchor CHECK (company_id IS NOT NULL OR primary_contact_id IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prospect_signals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    prospect_id BIGINT UNSIGNED NOT NULL,
    signal_id BIGINT UNSIGNED NOT NULL,
    evidence_note TEXT NOT NULL,
    observed_on DATE NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id), UNIQUE KEY uq_prospect_signal (prospect_id, signal_id),
    CONSTRAINT fk_prospect_signals_prospect FOREIGN KEY (prospect_id) REFERENCES prospects(id),
    CONSTRAINT fk_prospect_signals_signal FOREIGN KEY (signal_id) REFERENCES signals(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prospect_status_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    prospect_id BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(32) NULL,
    to_status VARCHAR(32) NOT NULL,
    occurred_at DATETIME(6) NOT NULL,
    reason TEXT NULL,
    voided_at DATETIME(6) NULL,
    voided_reason TEXT NULL,
    voided_by_user_id BIGINT UNSIGNED NULL,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id), INDEX idx_status_events_valid (prospect_id, voided_at, occurred_at, id),
    CONSTRAINT fk_status_event_prospect FOREIGN KEY (prospect_id) REFERENCES prospects(id),
    CONSTRAINT fk_status_event_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
    CONSTRAINT fk_status_event_void_actor FOREIGN KEY (voided_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
