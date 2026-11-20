-- 3D models for portfolio projects: a parametric template plus measurements. The frontend
-- builds the actual geometry; only the template id, its version and the params are stored.
CREATE TABLE IF NOT EXISTS portfolio_models (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id       INT UNSIGNED NOT NULL,
    template         VARCHAR(32) NOT NULL,
    template_version INT UNSIGNED NOT NULL DEFAULT 1,
    params           TEXT NOT NULL,              -- flat JSON object, validated by ProjectModelService
    default_finish   VARCHAR(64) NULL,           -- plywood material id from the frontend library
    show_in_showroom TINYINT(1) NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_portfolio_models_project (project_id),
    CONSTRAINT fk_portfolio_models_project FOREIGN KEY (project_id)
        REFERENCES portfolio_projects (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
