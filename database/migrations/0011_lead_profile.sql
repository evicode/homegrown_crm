CREATE TABLE lead_profile (
    id TINYINT UNSIGNED NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT '',
    required_any_json TEXT NOT NULL,
    positive_keywords_json TEXT NOT NULL,
    negative_keywords_json TEXT NOT NULL,
    preferred_locations_json TEXT NOT NULL,
    minimum_score INT UNSIGNED NOT NULL DEFAULT 1,
    strong_fit_score INT UNSIGNED NOT NULL DEFAULT 3,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    updated_by_user_id BIGINT UNSIGNED NULL,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT chk_lead_profile_singleton CHECK (id = 1),
    CONSTRAINT fk_lead_profile_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO lead_profile (id, required_any_json, positive_keywords_json, negative_keywords_json, preferred_locations_json)
VALUES (1, '[]', '{}', '[]', '[]');
