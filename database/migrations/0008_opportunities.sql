CREATE TABLE opportunities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    prospect_id BIGINT UNSIGNED NOT NULL,
    offer_key VARCHAR(64) NOT NULL,
    stage VARCHAR(32) NOT NULL DEFAULT 'qualified',
    value_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    expected_close_on DATE NULL,
    closed_at DATETIME(6) NULL,
    lost_reason TEXT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    open_slot TINYINT GENERATED ALWAYS AS (CASE WHEN stage IN ('qualified','discovery_offered','proposal_sent') THEN 1 ELSE NULL END) STORED,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY(id), UNIQUE KEY uq_opportunity_open(prospect_id,open_slot),
    INDEX idx_opportunity_pipeline(stage,expected_close_on,id),
    CONSTRAINT fk_opportunity_prospect FOREIGN KEY(prospect_id) REFERENCES prospects(id),
    CONSTRAINT chk_opportunity_stage CHECK(stage IN ('qualified','discovery_offered','proposal_sent','won','lost')),
    CONSTRAINT chk_opportunity_value CHECK(value_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE opportunity_stage_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    opportunity_id BIGINT UNSIGNED NOT NULL,
    from_stage VARCHAR(32) NULL,
    to_stage VARCHAR(32) NOT NULL,
    occurred_at DATETIME(6) NOT NULL,
    reason TEXT NULL,
    voided_at DATETIME(6) NULL,
    voided_reason TEXT NULL,
    voided_by_user_id BIGINT UNSIGNED NULL,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY(id), INDEX idx_opportunity_stage_timeline(opportunity_id,occurred_at,id),
    CONSTRAINT fk_opportunity_stage_opportunity FOREIGN KEY(opportunity_id) REFERENCES opportunities(id),
    CONSTRAINT fk_opportunity_stage_actor FOREIGN KEY(actor_user_id) REFERENCES users(id),
    CONSTRAINT fk_opportunity_stage_void_actor FOREIGN KEY(voided_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE follow_ups ADD CONSTRAINT fk_follow_up_opportunity FOREIGN KEY(opportunity_id) REFERENCES opportunities(id);
