-- Portfolio & Showroom CMS (spec: "Portfolio & Showroom Content Management System").
-- Applied by `php bin/migrate.php`. MariaDB/MySQL, utf8mb4.

CREATE TABLE IF NOT EXISTS admins (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(120) NOT NULL,
    email         VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status        ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    -- Bumped on logout / password change to revoke every issued JWT (LeasePro convention).
    token_version INT UNSIGNED NOT NULL DEFAULT 0,
    last_login_at DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_logs (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level      VARCHAR(10) NOT NULL,
    channel    VARCHAR(40) NOT NULL,
    message    VARCHAR(500) NOT NULL,
    context    TEXT NULL,
    admin_id   INT UNSIGNED NULL,
    ip         VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_app_logs_created (created_at),
    KEY idx_app_logs_level (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portfolio_categories (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(120) NOT NULL,
    slug          VARCHAR(140) NOT NULL,
    description   TEXT NULL,
    image         VARCHAR(255) NULL,          -- relative path under uploads/
    display_order INT NOT NULL DEFAULT 0,
    status        ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_portfolio_categories_slug (slug),
    KEY idx_portfolio_categories_order (status, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portfolio_projects (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- Deleting a category leaves its projects uncategorised rather than deleting them.
    category_id       INT UNSIGNED NULL,
    title             VARCHAR(190) NOT NULL,
    slug              VARCHAR(140) NOT NULL,
    description       TEXT NULL,
    materials_used    TEXT NULL,
    dimensions        VARCHAR(190) NULL,
    location          VARCHAR(190) NULL,
    completion_date   DATE NULL,
    -- "featured_image" in the spec: points at the chosen portfolio_images row.
    featured_image_id INT UNSIGNED NULL,
    is_featured       TINYINT(1) NOT NULL DEFAULT 0,
    featured_order    INT NOT NULL DEFAULT 0,   -- order within the "featured" section
    status            ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    display_order     INT NOT NULL DEFAULT 0,
    meta_title        VARCHAR(190) NULL,
    meta_description  VARCHAR(320) NULL,
    published_at      DATETIME NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_portfolio_projects_slug (slug),
    KEY idx_portfolio_projects_public (status, display_order),
    KEY idx_portfolio_projects_latest (status, published_at),
    KEY idx_portfolio_projects_featured (status, is_featured),
    KEY idx_portfolio_projects_category (category_id),
    CONSTRAINT fk_portfolio_projects_category FOREIGN KEY (category_id)
        REFERENCES portfolio_categories (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portfolio_images (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id     INT UNSIGNED NOT NULL,
    image_path     VARCHAR(255) NOT NULL,     -- large variant, relative path under uploads/
    medium_path    VARCHAR(255) NOT NULL,
    thumbnail_path VARCHAR(255) NOT NULL,
    width          INT UNSIGNED NOT NULL,
    height         INT UNSIGNED NOT NULL,
    alt_text       VARCHAR(255) NULL,
    display_order  INT NOT NULL DEFAULT 0,
    is_featured    TINYINT(1) NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_portfolio_images_project (project_id, display_order),
    CONSTRAINT fk_portfolio_images_project FOREIGN KEY (project_id)
        REFERENCES portfolio_projects (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE portfolio_projects
    ADD CONSTRAINT fk_portfolio_projects_featured_image FOREIGN KEY (featured_image_id)
        REFERENCES portfolio_images (id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS showroom_settings (
    setting_key   VARCHAR(64) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial categories (spec §5). Editable in the admin dashboard; nothing here is hardcoded in the frontend.
INSERT IGNORE INTO portfolio_categories (name, slug, description, display_order) VALUES
    ('Wardrobes', 'wardrobes', 'Fitted and freestanding wardrobes, walk-in closets and dressing rooms.', 1),
    ('Kitchen Cabinets', 'kitchen-cabinets', 'Complete kitchens, base and wall units, islands and pantries.', 2),
    ('TV Consoles', 'tv-consoles', 'TV consoles and full feature walls with panelling and storage.', 3),
    ('Bedroom Furniture', 'bedroom-furniture', 'Beds, headboards, bedside tables and dressers.', 4),
    ('Office Furniture', 'office-furniture', 'Desks, workstations, reception counters and storage.', 5),
    ('Doors', 'doors', 'Interior doors, sliding doors and cabinet fronts.', 6),
    ('Shelving and Storage', 'shelving-and-storage', 'Bookcases, shelving systems and storage units.', 7),
    ('Custom Furniture', 'custom-furniture', 'One-off pieces designed and built to order.', 8),
    ('Other Projects', 'other-projects', 'Everything else we have built.', 9);

INSERT IGNORE INTO showroom_settings (setting_key, setting_value) VALUES
    ('portfolio_title', 'Our work'),
    ('portfolio_intro', 'Real furniture we have designed, built and installed for homes and businesses.'),
    ('homepage_featured_limit', '6'),
    ('homepage_latest_limit', '6'),
    ('portfolio_per_page', '12'),
    ('enquiry_cta_title', 'Want something similar?'),
    ('enquiry_cta_text', 'Tell us about your space and we will design a piece around it.');
