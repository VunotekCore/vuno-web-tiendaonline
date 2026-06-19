-- =============================================================================
-- vuno_ramlop_ecommerce — Esquema Completo
-- Versión: 1.1.0
-- Motor: MySQL 8.0+ InnoDB
-- Charset: utf8mb4
-- PK: VARCHAR(50) para productos/categorías (compatible con IDs "prod-xxx", "cat-xxx")
--       INT AUTO_INCREMENT para tablas hijas
--       INT AUTO_INCREMENT + order_number UNIQUE para órdenes
-- =============================================================================

CREATE DATABASE IF NOT EXISTS vuno_ramlop_ecommerce
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE vuno_ramlop_ecommerce;

-- =============================================================================
-- 1. Admin y Seguridad
-- =============================================================================

CREATE TABLE admin_roles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(50) NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE admin_users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name          VARCHAR(200) NOT NULL,
    role_id       INT UNSIGNED NOT NULL,
    is_active     BOOLEAN DEFAULT TRUE,
    totp_secret   VARCHAR(255) DEFAULT NULL,
    totp_enabled  BOOLEAN DEFAULT FALSE,
    backup_codes  TEXT DEFAULT NULL COMMENT 'JSON array of hashed backup codes',
    last_login_at TIMESTAMP NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES admin_roles(id)
) ENGINE=InnoDB;

CREATE TABLE admin_activity_log (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id      INT UNSIGNED NOT NULL,
    action        VARCHAR(100) NOT NULL,
    entity_type   VARCHAR(50),
    entity_id     VARCHAR(50),
    description   TEXT,
    ip_address    VARCHAR(45),
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE admin_activity_details (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    log_id      BIGINT UNSIGNED NOT NULL,
    meta_key    VARCHAR(100) NOT NULL,
    meta_value  TEXT NOT NULL,
    FOREIGN KEY (log_id) REFERENCES admin_activity_log(id) ON DELETE CASCADE,
    INDEX idx_log (log_id)
) ENGINE=InnoDB;

CREATE TABLE admin_login_attempts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address  VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success     BOOLEAN DEFAULT FALSE,
    INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB;

-- =============================================================================
-- 2. Catálogo de Productos
-- =============================================================================

CREATE TABLE categories (
    id          VARCHAR(50) NOT NULL PRIMARY KEY,
    parent_id   VARCHAR(50) DEFAULT NULL,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(120) NOT NULL UNIQUE,
    description TEXT,
    image_url   VARCHAR(500),
    sort_order  INT DEFAULT 0,
    is_active   BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_parent (parent_id)
) ENGINE=InnoDB;

ALTER TABLE categories ADD FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL;

CREATE TABLE products (
    id                  VARCHAR(50) NOT NULL PRIMARY KEY,
    sku                 VARCHAR(100) UNIQUE,
    name                VARCHAR(255) NOT NULL,
    slug                VARCHAR(280) NOT NULL UNIQUE,
    description         TEXT,
    price               DECIMAL(10,2) NOT NULL,
    compare_at_price    DECIMAL(10,2) DEFAULT NULL,
    cost_price          DECIMAL(10,2) DEFAULT NULL,
    currency            CHAR(3) DEFAULT 'USD',
    weight_kg           DECIMAL(8,2) DEFAULT NULL,
    length_cm           DECIMAL(8,2) DEFAULT NULL,
    width_cm            DECIMAL(8,2) DEFAULT NULL,
    height_cm           DECIMAL(8,2) DEFAULT NULL,
    is_active           BOOLEAN DEFAULT TRUE,
    is_featured         BOOLEAN DEFAULT FALSE,
    size_prefix         VARCHAR(10) NOT NULL DEFAULT 'EU',
    low_stock_threshold TINYINT UNSIGNED DEFAULT 5,
    meta_title          VARCHAR(255),
    meta_description    VARCHAR(500),
    og_image_url        VARCHAR(500),
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_slug (slug),
    INDEX idx_sku (sku),
    INDEX idx_active (is_active, deleted_at),
    INDEX idx_featured (is_featured),
    FULLTEXT INDEX ft_search (name, description)
) ENGINE=InnoDB;

-- Migration: add products.low_stock_threshold (idempotent for existing DBs)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'low_stock_threshold');
SET @sql = IF(@col_exists = 0,
              'ALTER TABLE products ADD COLUMN low_stock_threshold TINYINT UNSIGNED DEFAULT 5 AFTER is_featured',
              'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE product_details (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  VARCHAR(50) NOT NULL,
    detail_text TEXT NOT NULL,
    sort_order  INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product (product_id)
) ENGINE=InnoDB;

CREATE TABLE product_tags (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  VARCHAR(50) NOT NULL,
    tag         VARCHAR(50) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uk_tag (product_id, tag),
    INDEX idx_tag (tag)
) ENGINE=InnoDB;

CREATE TABLE product_categories (
    product_id  VARCHAR(50) NOT NULL,
    category_id VARCHAR(50) NOT NULL,
    PRIMARY KEY (product_id, category_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE product_colors (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  VARCHAR(50) NOT NULL,
    name        VARCHAR(100) NOT NULL,
    hex         VARCHAR(7) NOT NULL,
    sort_order  INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product (product_id)
) ENGINE=InnoDB;

CREATE TABLE product_sizes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  VARCHAR(50) NOT NULL,
    label       VARCHAR(20) NOT NULL,
    value       VARCHAR(20) NOT NULL,
    sort_order  INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product (product_id)
) ENGINE=InnoDB;

CREATE TABLE product_variants (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id      VARCHAR(50) NOT NULL,
    color_id        INT UNSIGNED NOT NULL,
    size_id         INT UNSIGNED NOT NULL,
    sku             VARCHAR(100) UNIQUE,
    stock           INT NOT NULL DEFAULT 0,
    price_override  DECIMAL(10,2) DEFAULT NULL,
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (color_id) REFERENCES product_colors(id) ON DELETE CASCADE,
    FOREIGN KEY (size_id) REFERENCES product_sizes(id) ON DELETE CASCADE,
    UNIQUE KEY uk_variant (product_id, color_id, size_id),
    INDEX idx_stock (stock, is_active)
) ENGINE=InnoDB;

CREATE TABLE product_images (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  VARCHAR(50) NOT NULL,
    color_id    INT UNSIGNED DEFAULT NULL,
    url         VARCHAR(500) NOT NULL,
    file_id     VARCHAR(100) DEFAULT NULL,
    alt_text    VARCHAR(255),
    sort_order  INT DEFAULT 0,
    is_primary  BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (color_id) REFERENCES product_colors(id) ON DELETE SET NULL,
    INDEX idx_product (product_id, sort_order)
) ENGINE=InnoDB;

-- =============================================================================
-- 3. Inventario (Stock Movements)
-- =============================================================================

CREATE TABLE stock_movements (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    variant_id      INT UNSIGNED NOT NULL,
    quantity_change INT NOT NULL,
    stock_before    INT NOT NULL,
    stock_after     INT NOT NULL,
    reference_type  VARCHAR(50) NOT NULL,
    reference_id    VARCHAR(100),
    notes           TEXT,
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_variant (variant_id),
    INDEX idx_reference (reference_type, reference_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- =============================================================================
-- 4. Clientes
-- =============================================================================

CREATE TABLE customers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password_hash   VARCHAR(255),
    name            VARCHAR(200) NOT NULL,
    phone           VARCHAR(50),
    is_verified     BOOLEAN DEFAULT FALSE,
    notes           TEXT,
    last_order_at   TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_name (name)
) ENGINE=InnoDB;

CREATE TABLE customer_addresses (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id           INT UNSIGNED NOT NULL,
    label                 VARCHAR(50),
    address_line1         VARCHAR(255) NOT NULL,
    address_line2         VARCHAR(255),
    city                  VARCHAR(100) NOT NULL,
    state                 VARCHAR(100),
    zip                   VARCHAR(20),
    country               CHAR(2) NOT NULL,
    phone                 VARCHAR(50),
    is_default_shipping   BOOLEAN DEFAULT FALSE,
    is_default_billing    BOOLEAN DEFAULT FALSE,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_customer (customer_id)
) ENGINE=InnoDB;

CREATE TABLE customer_sessions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT UNSIGNED NOT NULL,
    token           VARCHAR(255) NOT NULL UNIQUE,
    ip_address      VARCHAR(45),
    user_agent      TEXT,
    expires_at      TIMESTAMP NOT NULL,
    last_activity   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

CREATE TABLE password_resets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(255) NOT NULL,
    token       VARCHAR(255) NOT NULL UNIQUE,
    expires_at  TIMESTAMP NOT NULL,
    used_at     TIMESTAMP NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email)
) ENGINE=InnoDB;

CREATE TABLE cart_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT UNSIGNED NOT NULL,
    product_id      VARCHAR(50) NOT NULL,
    quantity        INT NOT NULL DEFAULT 1,
    selected_color  VARCHAR(100) DEFAULT '',
    selected_size   VARCHAR(20) DEFAULT '',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    UNIQUE KEY uk_cart (customer_id, product_id, selected_color, selected_size),
    INDEX idx_customer (customer_id)
) ENGINE=InnoDB;

-- =============================================================================
-- 5. Envíos
-- =============================================================================

CREATE TABLE shipping_zones (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    is_active   BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE shipping_zone_countries (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    zone_id      INT UNSIGNED NOT NULL,
    country_code CHAR(2) NOT NULL,
    FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE CASCADE,
    UNIQUE KEY uk_zone_country (zone_id, country_code),
    INDEX idx_country (country_code)
) ENGINE=InnoDB;

CREATE TABLE shipping_methods (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                VARCHAR(50) NOT NULL UNIQUE,
    name                VARCHAR(100) NOT NULL,
    description         TEXT,
    estimated_days_min  INT,
    estimated_days_max  INT,
    is_active           BOOLEAN DEFAULT TRUE,
    sort_order          INT DEFAULT 0,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE shipping_rates (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    zone_id         INT UNSIGNED NOT NULL,
    method_id       INT UNSIGNED NOT NULL,
    base_rate       DECIMAL(10,2) NOT NULL DEFAULT 0,
    per_item_rate   DECIMAL(10,2) DEFAULT 0,
    weight_rate     DECIMAL(10,2) DEFAULT 0,
    free_above      DECIMAL(10,2) DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE CASCADE,
    FOREIGN KEY (method_id) REFERENCES shipping_methods(id) ON DELETE CASCADE,
    UNIQUE KEY uk_rate (zone_id, method_id)
) ENGINE=InnoDB;

-- =============================================================================
-- 6. Impuestos
-- =============================================================================

CREATE TABLE tax_rates (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    country     CHAR(2) NOT NULL,
    state       VARCHAR(100) DEFAULT NULL,
    rate        DECIMAL(5,3) NOT NULL,
    is_active   BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_location (country, state)
) ENGINE=InnoDB;

-- =============================================================================
-- 7. Cupones
-- =============================================================================

CREATE TABLE coupons (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                  VARCHAR(50) NOT NULL UNIQUE,
    description           VARCHAR(255),
    discount_type         ENUM('percentage', 'fixed') NOT NULL,
    discount_value        DECIMAL(10,2) NOT NULL,
    min_order_amount      DECIMAL(10,2) DEFAULT NULL,
    max_uses              INT DEFAULT NULL,
    max_uses_per_customer INT DEFAULT NULL,
    is_active             BOOLEAN DEFAULT TRUE,
    starts_at             TIMESTAMP NULL,
    expires_at            TIMESTAMP NULL,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_active_dates (is_active, starts_at, expires_at)
) ENGINE=InnoDB;

-- =============================================================================
-- 8. Configuración de Pagos
-- =============================================================================

CREATE TABLE payment_methods (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(50) NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    is_active   BOOLEAN DEFAULT TRUE,
    sort_order  INT DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE bank_accounts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bank_name       VARCHAR(200) NOT NULL,
    account_holder  VARCHAR(200) NOT NULL,
    account_number  VARCHAR(100) NOT NULL,
    account_type    VARCHAR(50),
    routing_number  VARCHAR(100),
    instructions    TEXT,
    is_active       BOOLEAN DEFAULT TRUE,
    sort_order      INT DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE order_statuses (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(30) NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    sort_order  INT DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE payment_statuses (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(30) NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- =============================================================================
-- 9. Órdenes
-- =============================================================================

CREATE TABLE orders (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number            VARCHAR(30) NOT NULL UNIQUE,

    customer_id             INT UNSIGNED DEFAULT NULL,
    customer_email          VARCHAR(255) NOT NULL,
    customer_name           VARCHAR(200) NOT NULL,
    customer_phone          VARCHAR(50),

    shipping_address_id     INT UNSIGNED DEFAULT NULL,
    shipping_name           VARCHAR(200),
    shipping_line1          VARCHAR(255),
    shipping_line2          VARCHAR(255),
    shipping_city           VARCHAR(100),
    shipping_state          VARCHAR(100),
    shipping_zip            VARCHAR(20),
    shipping_country        CHAR(2),
    shipping_phone          VARCHAR(50),

    billing_address_id      INT UNSIGNED DEFAULT NULL,
    billing_name            VARCHAR(200),
    billing_line1           VARCHAR(255),
    billing_line2           VARCHAR(255),
    billing_city            VARCHAR(100),
    billing_state           VARCHAR(100),
    billing_zip             VARCHAR(20),
    billing_country         CHAR(2),
    billing_phone           VARCHAR(50),

    subtotal                DECIMAL(10,2) NOT NULL,
    shipping_total          DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax_total               DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_total          DECIMAL(10,2) NOT NULL DEFAULT 0,
    total                   DECIMAL(10,2) NOT NULL,
    currency                CHAR(3) DEFAULT 'USD',

    status_id               INT UNSIGNED NOT NULL,
    payment_method_id       INT UNSIGNED NOT NULL,
    payment_status_id       INT UNSIGNED NOT NULL,
    stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
    transfer_receipt_url    VARCHAR(500) DEFAULT NULL,
    selected_bank_id        INT UNSIGNED DEFAULT NULL,
    coupon_id               INT UNSIGNED DEFAULT NULL,
    shipping_method_id      INT UNSIGNED DEFAULT NULL,

    notes                   TEXT,
    ip_address              VARCHAR(45),
    user_agent              TEXT,

    paid_at                 TIMESTAMP NULL,
    shipped_at              TIMESTAMP NULL,
    delivered_at            TIMESTAMP NULL,
    cancelled_at            TIMESTAMP NULL,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (shipping_address_id) REFERENCES customer_addresses(id) ON DELETE SET NULL,
    FOREIGN KEY (billing_address_id) REFERENCES customer_addresses(id) ON DELETE SET NULL,
    FOREIGN KEY (status_id) REFERENCES order_statuses(id),
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id),
    FOREIGN KEY (payment_status_id) REFERENCES payment_statuses(id),
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL,
    FOREIGN KEY (shipping_method_id) REFERENCES shipping_methods(id) ON DELETE SET NULL,
    INDEX idx_order_number (order_number),
    INDEX idx_customer (customer_id),
    INDEX idx_email (customer_email),
    INDEX idx_status (status_id),
    INDEX idx_payment (payment_method_id, payment_status_id),
    INDEX idx_created (created_at),
    INDEX idx_stripe_intent (stripe_payment_intent_id)
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,
    product_id      VARCHAR(50) NOT NULL,
    variant_id      INT UNSIGNED DEFAULT NULL,

    product_name    VARCHAR(255) NOT NULL,
    product_slug    VARCHAR(280) NOT NULL,
    product_image   VARCHAR(500),
    product_price   DECIMAL(10,2) NOT NULL,
    product_currency CHAR(3) DEFAULT 'USD',
    product_sku     VARCHAR(100),

    quantity        INT NOT NULL CHECK (quantity > 0),
    unit_price      DECIMAL(10,2) NOT NULL,
    subtotal        DECIMAL(10,2) NOT NULL,
    selected_color  VARCHAR(100),
    selected_size   VARCHAR(20),

    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
    INDEX idx_order (order_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB;

CREATE TABLE order_status_history (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,
    from_status_id  INT UNSIGNED DEFAULT NULL,
    to_status_id    INT UNSIGNED NOT NULL,
    changed_by      INT UNSIGNED DEFAULT NULL,
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (from_status_id) REFERENCES order_statuses(id) ON DELETE SET NULL,
    FOREIGN KEY (to_status_id) REFERENCES order_statuses(id),
    FOREIGN KEY (changed_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_order (order_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE coupon_usage (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    coupon_id       INT UNSIGNED NOT NULL,
    order_id        INT UNSIGNED NOT NULL,
    customer_email  VARCHAR(255) DEFAULT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    customer_id     INT UNSIGNED DEFAULT NULL,
    used_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    UNIQUE KEY uk_usage (coupon_id, order_id)
) ENGINE=InnoDB;

-- =============================================================================
-- 10. Reseñas y Wishlist
-- =============================================================================

CREATE TABLE product_reviews (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id      VARCHAR(50) NOT NULL,
    customer_id     INT UNSIGNED DEFAULT NULL,
    order_id        INT UNSIGNED DEFAULT NULL,
    reviewer_name   VARCHAR(100) NOT NULL DEFAULT '',
    reviewer_email  VARCHAR(255) NOT NULL DEFAULT '',
    rating          TINYINT UNSIGNED NOT NULL,
    title           VARCHAR(255),
    comment         TEXT,
    is_approved     BOOLEAN DEFAULT FALSE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_product (product_id, is_approved),
    INDEX idx_rating (rating)
) ENGINE=InnoDB;

CREATE TABLE wishlist_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    product_id  VARCHAR(50) NOT NULL,
    variant_id  INT UNSIGNED DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
    UNIQUE KEY uk_wishlist (customer_id, product_id, variant_id)
) ENGINE=InnoDB;

-- =============================================================================
-- 11. Notificaciones y Email
-- =============================================================================

CREATE TABLE email_templates (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(100) NOT NULL UNIQUE,
    name        VARCHAR(200) NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    body_html   LONGTEXT NOT NULL,
    body_text   LONGTEXT,
    is_active   BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE email_queue (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id     INT UNSIGNED DEFAULT NULL,
    to_email        VARCHAR(255) NOT NULL,
    to_name         VARCHAR(200),
    subject         VARCHAR(255) NOT NULL,
    body_html       LONGTEXT NOT NULL,
    body_text       LONGTEXT,
    status          ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts        TINYINT UNSIGNED DEFAULT 0,
    max_attempts    TINYINT UNSIGNED DEFAULT 3,
    error           TEXT,
    sent_at         TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES email_templates(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE notification_log (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type            VARCHAR(50) NOT NULL,
    recipient       VARCHAR(255) NOT NULL,
    subject         VARCHAR(255),
    message         TEXT,
    status          ENUM('sent', 'failed') NOT NULL,
    reference_type  VARCHAR(50),
    reference_id    VARCHAR(100),
    error           TEXT,
    sent_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_reference (reference_type, reference_id),
    INDEX idx_sent (sent_at)
) ENGINE=InnoDB;

-- =============================================================================
--  Size Guide Conversion Table
-- =============================================================================

CREATE TABLE size_guide_rows (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    us_size         VARCHAR(10) NOT NULL,
    eu_size         VARCHAR(10) NOT NULL,
    uk_size         VARCHAR(10) NOT NULL,
    cm_size         VARCHAR(10) NOT NULL,
    sort_order      TINYINT UNSIGNED DEFAULT 0
) ENGINE=InnoDB;

-- =============================================================================
-- 12. CMS y Configuración
-- =============================================================================

CREATE TABLE pages (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(255) NOT NULL,
    slug            VARCHAR(280) NOT NULL UNIQUE,
    content         LONGTEXT,
    meta_title      VARCHAR(255),
    meta_description VARCHAR(500),
    is_published    BOOLEAN DEFAULT FALSE,
    published_at    TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_published (is_published, published_at)
) ENGINE=InnoDB;

CREATE TABLE settings (
    section     VARCHAR(50) NOT NULL,
    `key`       VARCHAR(100) NOT NULL,
    value       TEXT NOT NULL,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (section, `key`)
) ENGINE=InnoDB;

-- =============================================================================
-- 13. Monedas (multi-currency)
-- =============================================================================

CREATE TABLE currencies (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            CHAR(3) NOT NULL UNIQUE,
    name            VARCHAR(100) NOT NULL,
    symbol          VARCHAR(10) NOT NULL,
    exchange_rate   DECIMAL(12,6) NOT NULL DEFAULT 1.000000,
    decimal_places  TINYINT UNSIGNED NOT NULL DEFAULT 2,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    sort_order      INT UNSIGNED NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add exchange_rate column to orders (for historical tracking)
ALTER TABLE orders ADD COLUMN exchange_rate DECIMAL(12,6) DEFAULT 1.000000 AFTER currency;

-- =============================================================================
-- 14. Traducciones (i18n)
-- =============================================================================

CREATE TABLE product_translations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  VARCHAR(50) NOT NULL,
    lang        CHAR(2) NOT NULL,
    name        VARCHAR(255),
    description TEXT,
    details     TEXT COMMENT 'JSON array of translated detail texts',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uk_product_lang (product_id, lang)
) ENGINE=InnoDB;

CREATE TABLE category_translations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id VARCHAR(50) NOT NULL,
    lang        CHAR(2) NOT NULL,
    name        VARCHAR(100) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE KEY uk_category_lang (category_id, lang)
) ENGINE=InnoDB;

-- =============================================================================
-- Seed Data
-- =============================================================================

-- Categories
INSERT INTO categories (id, name, slug, description, sort_order, is_active) VALUES
('cat-heels', 'Heels', 'heels', 'Tacones y pumps', 1, TRUE),
('cat-sandals', 'Sandals', 'sandals', 'Sandalias', 2, TRUE),
('cat-mules', 'Mules', 'mules', 'Mules y slippers', 3, TRUE),
('cat-boots', 'Boots', 'boots', 'Botas y botines', 4, TRUE),
('cat-flats', 'Flats', 'flats', 'Calzado plano', 5, TRUE);

-- ============================================================
-- Product: Stiletto Noir Arquitectónico (prod-001)
-- ============================================================
INSERT INTO products (id, sku, name, slug, description, price, currency, is_featured, created_at) VALUES
('prod-001', 'SKU-STIL-001', 'Stiletto Noir Arquitectónico', 'stiletto-noir-arquitectonico',
 'Redefiniendo la silueta clásica, este stiletto presenta líneas arquitectónicas afiladas y un tacón esculpido de 90mm. Fabricado en Italia con piel de becerro lisa de primera calidad, su diseño minimalista elimina cualquier costura innecesaria para un acabado purista.',
 450.00, 'USD', TRUE, NOW());

INSERT INTO product_details (product_id, detail_text, sort_order) VALUES
('prod-001', '100% Piel de becerro exterior', 1),
('prod-001', 'Forro y suela interior de piel', 2),
('prod-001', 'Tacón escultural de 90mm', 3),
('prod-001', 'Hecho en Italia', 4),
('prod-001', 'Limpiar con paño suave y seco', 5);

INSERT INTO product_categories (product_id, category_id) VALUES ('prod-001', 'cat-heels');

INSERT INTO product_colors (id, product_id, name, hex, sort_order) VALUES
(1, 'prod-001', 'Noir', '#1A1A1A', 1),
(2, 'prod-001', 'Sand', '#E6DED5', 2),
(3, 'prod-001', 'White', '#FFFFFF', 3);

INSERT INTO product_sizes (id, product_id, label, value, sort_order) VALUES
(1, 'prod-001', '35', '35', 1),
(2, 'prod-001', '36', '36', 2),
(3, 'prod-001', '37', '37', 3),
(4, 'prod-001', '38', '38', 4),
(5, 'prod-001', '39', '39', 5),
(6, 'prod-001', '40', '40', 6),
(7, 'prod-001', '41', '41', 7);

-- Variants: color_id x size_id with stock based on inStock flag
INSERT INTO product_variants (product_id, color_id, size_id, sku, stock, is_active) VALUES
('prod-001', 1, 1, 'STIL-NOIR-35', 5, TRUE),
('prod-001', 1, 2, 'STIL-NOIR-36', 5, TRUE),
('prod-001', 1, 3, 'STIL-NOIR-37', 5, TRUE),
('prod-001', 1, 4, 'STIL-NOIR-38', 5, TRUE),
('prod-001', 1, 5, 'STIL-NOIR-39', 5, TRUE),
('prod-001', 1, 6, 'STIL-NOIR-40', 5, TRUE),
('prod-001', 1, 7, 'STIL-NOIR-41', 0, FALSE),
('prod-001', 2, 1, 'STIL-SAND-35', 5, TRUE),
('prod-001', 2, 2, 'STIL-SAND-36', 5, TRUE),
('prod-001', 2, 3, 'STIL-SAND-37', 5, TRUE),
('prod-001', 2, 4, 'STIL-SAND-38', 5, TRUE),
('prod-001', 2, 5, 'STIL-SAND-39', 5, TRUE),
('prod-001', 2, 6, 'STIL-SAND-40', 5, TRUE),
('prod-001', 2, 7, 'STIL-SAND-41', 0, FALSE),
('prod-001', 3, 1, 'STIL-WHITE-35', 5, TRUE),
('prod-001', 3, 2, 'STIL-WHITE-36', 5, TRUE),
('prod-001', 3, 3, 'STIL-WHITE-37', 5, TRUE),
('prod-001', 3, 4, 'STIL-WHITE-38', 5, TRUE),
('prod-001', 3, 5, 'STIL-WHITE-39', 5, TRUE),
('prod-001', 3, 6, 'STIL-WHITE-40', 5, TRUE),
('prod-001', 3, 7, 'STIL-WHITE-41', 0, FALSE);

INSERT INTO product_images (product_id, url, alt_text, sort_order, is_primary) VALUES
('prod-001', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 'Stiletto Noir vista frontal', 1, TRUE),
('prod-001', 'https://images.unsplash.com/photo-1605812860427-4024433a70fd?w=800&q=80', 'Stiletto Noir vista lateral', 2, FALSE),
('prod-001', 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=800&q=80', 'Stiletto Noir detalle tacón', 3, FALSE);

-- ============================================================
-- Product: Sandalia Estructural Nude (prod-002)
-- ============================================================
INSERT INTO products (id, sku, name, slug, description, price, currency, is_featured, created_at) VALUES
('prod-002', 'SKU-SAND-001', 'Sandalia Estructural Nude', 'sandalia-estructural-nude',
 'Una sandalia que abraza la forma del pie con líneas depuradas y una estética escultórica. Su construcción en piel nude se funde con la piel para un efecto visual alargador y sofisticado.',
 380.00, 'USD', TRUE, NOW());

INSERT INTO product_details (product_id, detail_text, sort_order) VALUES
('prod-002', '100% Piel de becerro', 1),
('prod-002', 'Suela de cuero', 2),
('prod-002', 'Hebilla ajustable en metal dorado', 3),
('prod-002', 'Tacón bloque 60mm', 4),
('prod-002', 'Hecho a mano en España', 5);

INSERT INTO product_categories (product_id, category_id) VALUES ('prod-002', 'cat-sandals');

INSERT INTO product_colors (id, product_id, name, hex, sort_order) VALUES
(4, 'prod-002', 'Nude', '#E6DED5', 1);

INSERT INTO product_sizes (id, product_id, label, value, sort_order) VALUES
(8,  'prod-002', '36', '36', 1),
(9,  'prod-002', '37', '37', 2),
(10, 'prod-002', '38', '38', 3),
(11, 'prod-002', '39', '39', 4),
(12, 'prod-002', '40', '40', 5);

INSERT INTO product_variants (product_id, color_id, size_id, sku, stock, is_active) VALUES
('prod-002', 4, 8,  'SAND-NUDE-36', 5, TRUE),
('prod-002', 4, 9,  'SAND-NUDE-37', 5, TRUE),
('prod-002', 4, 10, 'SAND-NUDE-38', 5, TRUE),
('prod-002', 4, 11, 'SAND-NUDE-39', 5, TRUE),
('prod-002', 4, 12, 'SAND-NUDE-40', 0, FALSE);

INSERT INTO product_images (product_id, url, alt_text, sort_order, is_primary) VALUES
('prod-002', 'https://images.unsplash.com/photo-1605812860427-4024433a70fd?w=800&q=80', 'Sandalia Nude vista frontal', 1, TRUE),
('prod-002', 'https://images.unsplash.com/photo-1604176354204-9268737828e4?w=800&q=80', 'Sandalia Nude vista lateral', 2, FALSE);

-- ============================================================
-- Product: Pump Clásico Punta Fina (prod-003)
-- ============================================================
INSERT INTO products (id, sku, name, slug, description, price, currency, is_featured, created_at) VALUES
('prod-003', 'SKU-PUMP-001', 'Pump Clásico Punta Fina', 'pump-clasico-punta-fina',
 'El pump definitivo para la mujer de poder. Silueta alargada con punta fina y tacón de 85mm. Su construcción en piel de becerro ofrece un ajuste perfecto y una elegancia atemporal.',
 420.00, 'USD', TRUE, NOW());

INSERT INTO product_details (product_id, detail_text, sort_order) VALUES
('prod-003', 'Piel de becerro italiana', 1),
('prod-003', 'Forro de piel de cordero', 2),
('prod-003', 'Tacón aguja 85mm', 3),
('prod-003', 'Suela de cuero con insignia', 4),
('prod-003', 'Hecho en Italia', 5);

INSERT INTO product_categories (product_id, category_id) VALUES ('prod-003', 'cat-heels');

INSERT INTO product_colors (id, product_id, name, hex, sort_order) VALUES
(5, 'prod-003', 'Arcilla', '#C18C7E', 1),
(6, 'prod-003', 'Noir', '#1A1A1A', 2),
(7, 'prod-003', 'Blanco', '#FFFFFF', 3);

INSERT INTO product_sizes (id, product_id, label, value, sort_order) VALUES
(13, 'prod-003', '36', '36', 1),
(14, 'prod-003', '37', '37', 2),
(15, 'prod-003', '38', '38', 3),
(16, 'prod-003', '39', '39', 4),
(17, 'prod-003', '40', '40', 5);

INSERT INTO product_variants (product_id, color_id, size_id, sku, stock, is_active) VALUES
('prod-003', 5, 13, 'PUMP-ARCILLA-36', 5, TRUE),
('prod-003', 5, 14, 'PUMP-ARCILLA-37', 5, TRUE),
('prod-003', 5, 15, 'PUMP-ARCILLA-38', 5, TRUE),
('prod-003', 5, 16, 'PUMP-ARCILLA-39', 5, TRUE),
('prod-003', 5, 17, 'PUMP-ARCILLA-40', 5, TRUE),
('prod-003', 6, 13, 'PUMP-NOIR-36', 5, TRUE),
('prod-003', 6, 14, 'PUMP-NOIR-37', 5, TRUE),
('prod-003', 6, 15, 'PUMP-NOIR-38', 5, TRUE),
('prod-003', 6, 16, 'PUMP-NOIR-39', 5, TRUE),
('prod-003', 6, 17, 'PUMP-NOIR-40', 5, TRUE),
('prod-003', 7, 13, 'PUMP-BLANCO-36', 5, TRUE),
('prod-003', 7, 14, 'PUMP-BLANCO-37', 5, TRUE),
('prod-003', 7, 15, 'PUMP-BLANCO-38', 5, TRUE),
('prod-003', 7, 16, 'PUMP-BLANCO-39', 5, TRUE),
('prod-003', 7, 17, 'PUMP-BLANCO-40', 5, TRUE);

INSERT INTO product_images (product_id, url, alt_text, sort_order, is_primary) VALUES
('prod-003', 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=800&q=80', 'Pump Punta Fina vista frontal', 1, TRUE),
('prod-003', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 'Pump Punta Fina vista lateral', 2, FALSE);

-- ============================================================
-- Product: Mule Bloque Geométrico (prod-004)
-- ============================================================
INSERT INTO products (id, sku, name, slug, description, price, currency, is_featured, created_at) VALUES
('prod-004', 'SKU-MULE-001', 'Mule Bloque Geométrico', 'mule-bloque-geometrico',
 'Una mule de declaración arquitectónica. Su tacón bloque esculpido y su silueta minimalista la convierten en la pieza central de cualquier conjunto. Confeccionada en piel negra de alta resistencia.',
 395.00, 'USD', TRUE, NOW());

INSERT INTO product_details (product_id, detail_text, sort_order) VALUES
('prod-004', 'Piel de becerro negra', 1),
('prod-004', 'Tacón bloque geométrico 70mm', 2),
('prod-004', 'Suela de caucho grabada', 3),
('prod-004', 'Plataforma oculta 20mm', 4),
('prod-004', 'Hecho en Portugal', 5);

INSERT INTO product_categories (product_id, category_id) VALUES ('prod-004', 'cat-mules');

INSERT INTO product_colors (id, product_id, name, hex, sort_order) VALUES
(8, 'prod-004', 'Noir', '#1A1A1A', 1);

INSERT INTO product_sizes (id, product_id, label, value, sort_order) VALUES
(18, 'prod-004', '36', '36', 1),
(19, 'prod-004', '37', '37', 2),
(20, 'prod-004', '38', '38', 3),
(21, 'prod-004', '39', '39', 4),
(22, 'prod-004', '40', '40', 5),
(23, 'prod-004', '41', '41', 6);

INSERT INTO product_variants (product_id, color_id, size_id, sku, stock, is_active) VALUES
('prod-004', 8, 18, 'MULE-NOIR-36', 5, TRUE),
('prod-004', 8, 19, 'MULE-NOIR-37', 5, TRUE),
('prod-004', 8, 20, 'MULE-NOIR-38', 5, TRUE),
('prod-004', 8, 21, 'MULE-NOIR-39', 5, TRUE),
('prod-004', 8, 22, 'MULE-NOIR-40', 5, TRUE),
('prod-004', 8, 23, 'MULE-NOIR-41', 5, TRUE);

INSERT INTO product_images (product_id, url, alt_text, sort_order, is_primary) VALUES
('prod-004', 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=800&q=80', 'Mule Geométrico vista frontal', 1, TRUE),
('prod-004', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 'Mule Geométrico vista lateral', 2, FALSE);

-- ============================================================
-- Product: Kitten Heel Minimal (prod-005)
-- ============================================================
INSERT INTO products (id, sku, name, slug, description, price, currency, is_featured, created_at) VALUES
('prod-005', 'SKU-KITTEN-001', 'Kitten Heel Minimal', 'kitten-heel-minimal',
 'Elegancia discreta con un tacón kitten de 50mm que alarga la silueta sin sacrificar confort. Su diseño depurado y la piel blanca inmaculada la convierten en un básico de colección.',
 350.00, 'USD', TRUE, NOW());

INSERT INTO product_details (product_id, detail_text, sort_order) VALUES
('prod-005', 'Piel de becerro blanca', 1),
('prod-005', 'Tacón kitten 50mm', 2),
('prod-005', 'Puntera redondeada', 3),
('prod-005', 'Forro de piel natural', 4),
('prod-005', 'Hecho en Italia', 5);

INSERT INTO product_categories (product_id, category_id) VALUES ('prod-005', 'cat-heels');

INSERT INTO product_colors (id, product_id, name, hex, sort_order) VALUES
(9,  'prod-005', 'Blanco', '#FFFFFF', 1),
(10, 'prod-005', 'Noir', '#1A1A1A', 2);

INSERT INTO product_sizes (id, product_id, label, value, sort_order) VALUES
(24, 'prod-005', '35', '35', 1),
(25, 'prod-005', '36', '36', 2),
(26, 'prod-005', '37', '37', 3),
(27, 'prod-005', '38', '38', 4),
(28, 'prod-005', '39', '39', 5);

INSERT INTO product_variants (product_id, color_id, size_id, sku, stock, is_active) VALUES
('prod-005', 9, 24, 'KITTEN-BLANCO-35', 5, TRUE),
('prod-005', 9, 25, 'KITTEN-BLANCO-36', 5, TRUE),
('prod-005', 9, 26, 'KITTEN-BLANCO-37', 5, TRUE),
('prod-005', 9, 27, 'KITTEN-BLANCO-38', 5, TRUE),
('prod-005', 9, 28, 'KITTEN-BLANCO-39', 5, TRUE),
('prod-005', 10, 24, 'KITTEN-NOIR-35', 5, TRUE),
('prod-005', 10, 25, 'KITTEN-NOIR-36', 5, TRUE),
('prod-005', 10, 26, 'KITTEN-NOIR-37', 5, TRUE),
('prod-005', 10, 27, 'KITTEN-NOIR-38', 5, TRUE),
('prod-005', 10, 28, 'KITTEN-NOIR-39', 5, TRUE);

INSERT INTO product_images (product_id, url, alt_text, sort_order, is_primary) VALUES
('prod-005', 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=800&q=80', 'Kitten Heel vista frontal', 1, TRUE),
('prod-005', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 'Kitten Heel vista lateral', 2, FALSE);

-- ============================================================
-- Product: Sandalia Jaula Arquitectónica (prod-006)
-- ============================================================
INSERT INTO products (id, sku, name, slug, description, price, currency, is_featured, created_at) VALUES
('prod-006', 'SKU-JAULA-001', 'Sandalia Jaula Arquitectónica', 'sandalia-jaula-arquitectonica',
 'Una exploración del espacio negativo. Esta sandalia de jaula entrelaza tiras de piel negra en una estructura geométrica que envuelve el pie. Tacón escultural de 100mm para una silueta imponente.',
 480.00, 'USD', TRUE, NOW());

INSERT INTO product_details (product_id, detail_text, sort_order) VALUES
('prod-006', 'Tiras de piel de becerro', 1),
('prod-006', 'Tacón escultural 100mm', 2),
('prod-006', 'Cierre de hebilla ajustable', 3),
('prod-006', 'Planta anatómica acolchada', 4),
('prod-006', 'Hecho a mano en España', 5);

INSERT INTO product_categories (product_id, category_id) VALUES ('prod-006', 'cat-sandals');

INSERT INTO product_colors (id, product_id, name, hex, sort_order) VALUES
(11, 'prod-006', 'Noir', '#1A1A1A', 1);

INSERT INTO product_sizes (id, product_id, label, value, sort_order) VALUES
(29, 'prod-006', '36', '36', 1),
(30, 'prod-006', '37', '37', 2),
(31, 'prod-006', '38', '38', 3),
(32, 'prod-006', '39', '39', 4),
(33, 'prod-006', '40', '40', 5);

INSERT INTO product_variants (product_id, color_id, size_id, sku, stock, is_active) VALUES
('prod-006', 11, 29, 'JAULA-NOIR-36', 5, TRUE),
('prod-006', 11, 30, 'JAULA-NOIR-37', 5, TRUE),
('prod-006', 11, 31, 'JAULA-NOIR-38', 5, TRUE),
('prod-006', 11, 32, 'JAULA-NOIR-39', 0, FALSE),
('prod-006', 11, 33, 'JAULA-NOIR-40', 0, FALSE);

INSERT INTO product_images (product_id, url, alt_text, sort_order, is_primary) VALUES
('prod-006', 'https://images.unsplash.com/photo-1604176354204-9268737828e4?w=800&q=80', 'Sandalia Jaula vista frontal', 1, TRUE),
('prod-006', 'https://images.unsplash.com/photo-1605812860427-4024433a70fd?w=800&q=80', 'Sandalia Jaula vista lateral', 2, FALSE);

-- ============================================================
-- Product: Botín Bloque Escultural (prod-007)
-- ============================================================
INSERT INTO products (id, sku, name, slug, description, price, currency, is_featured, created_at) VALUES
('prod-007', 'SKU-BOTIN-001', 'Botín Bloque Escultural', 'botin-bloque-escultural',
 'Un botín de bloque que desafía las convenciones. Tacón esculpido en ángulo y silueta recortada para un look vanguardista. La piel negra de alta resistencia y la cremallera trasera facilitan el calce.',
 520.00, 'USD', TRUE, NOW());

INSERT INTO product_details (product_id, detail_text, sort_order) VALUES
('prod-007', 'Piel de becerro negra', 1),
('prod-007', 'Tacón bloque angular 80mm', 2),
('prod-007', 'Cremallera trasera dorada', 3),
('prod-007', 'Puntera almendrada', 4),
('prod-007', 'Hecho en Portugal', 5);

INSERT INTO product_categories (product_id, category_id) VALUES ('prod-007', 'cat-boots');

INSERT INTO product_colors (id, product_id, name, hex, sort_order) VALUES
(12, 'prod-007', 'Noir', '#1A1A1A', 1);

INSERT INTO product_sizes (id, product_id, label, value, sort_order) VALUES
(34, 'prod-007', '36', '36', 1),
(35, 'prod-007', '37', '37', 2),
(36, 'prod-007', '38', '38', 3),
(37, 'prod-007', '39', '39', 4),
(38, 'prod-007', '40', '40', 5);

INSERT INTO product_variants (product_id, color_id, size_id, sku, stock, is_active) VALUES
('prod-007', 12, 34, 'BOTIN-NOIR-36', 5, TRUE),
('prod-007', 12, 35, 'BOTIN-NOIR-37', 5, TRUE),
('prod-007', 12, 36, 'BOTIN-NOIR-38', 5, TRUE),
('prod-007', 12, 37, 'BOTIN-NOIR-39', 5, TRUE),
('prod-007', 12, 38, 'BOTIN-NOIR-40', 5, TRUE);

INSERT INTO product_images (product_id, url, alt_text, sort_order, is_primary) VALUES
('prod-007', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 'Botín Bloque vista frontal', 1, TRUE),
('prod-007', 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=800&q=80', 'Botín Bloque vista lateral', 2, FALSE);

-- English translations for seed categories
INSERT INTO category_translations (category_id, lang, name) VALUES
('cat-heels', 'en', 'Heels'),
('cat-sandals', 'en', 'Sandals'),
('cat-mules', 'en', 'Mules'),
('cat-boots', 'en', 'Boots'),
('cat-flats', 'en', 'Flats');

-- English translations for seed products
INSERT INTO product_translations (product_id, lang, name, description, details) VALUES
('prod-001', 'en',
 'Architectural Stiletto Noir',
 'Redefining the classic silhouette, this stiletto features sharp architectural lines and a sculpted 90mm heel. Made in Italy from premium smooth calf leather, its minimalist design eliminates unnecessary seams for a purist finish.',
 '["100% Calf leather exterior","Leather lining and insole","Sculptural 90mm heel","Made in Italy","Clean with soft dry cloth"]'),
('prod-002', 'en',
 'Nude Structural Sandal',
 'A sandal that embraces the foot\'s shape with clean lines and sculptural aesthetics. Its nude leather construction blends with the skin for a lengthening and sophisticated visual effect.',
 '["100% Calf leather","Leather sole","Adjustable buckle in gold metal","Block heel 60mm","Handmade in Spain"]'),
('prod-003', 'en',
 'Classic Pointed Pump',
 'The ultimate pump for the power woman. Elongated silhouette with pointed toe and 85mm heel. Crafted from calf leather for a perfect fit and timeless elegance.',
 '["Italian calf leather","Lambskin lining","85mm stiletto heel","Leather sole with insignia","Made in Italy"]'),
('prod-004', 'en',
 'Geometric Block Mule',
 'An architectural statement mule. Its sculpted block heel and minimalist silhouette make it the centerpiece of any outfit. Crafted in high-resistance black leather.',
 '["Black calf leather","70mm geometric block heel","Engraved rubber sole","20mm concealed platform","Made in Portugal"]'),
('prod-005', 'en',
 'Minimal Kitten Heel',
 'Discreet elegance with a 50mm kitten heel that lengthens the silhouette without sacrificing comfort. Its clean design and pristine white leather make it a collection essential.',
 '["White calf leather","50mm kitten heel","Rounded toe","Natural leather lining","Made in Italy"]'),
('prod-006', 'en',
 'Architectural Cage Sandal',
 'An exploration of negative space. This cage sandal interweaves black leather straps in a geometric structure that envelops the foot. Sculptural 100mm heel for an imposing silhouette.',
 '["Calf leather straps","Sculptural 100mm heel","Adjustable buckle closure","Cushioned anatomical footbed","Handmade in Spain"]'),
('prod-007', 'en',
 'Sculptural Block Bootie',
 'A block bootie that defies convention. Angled sculpted heel and cropped silhouette for an avant-garde look. High-resistance black leather and rear zipper for easy fitting.',
 '["Black calf leather","80mm angular block heel","Gold rear zipper","Almond toe","Made in Portugal"]');

-- Admin roles
INSERT INTO admin_roles (code, name) VALUES
('superadmin', 'Super Administrador'),
('editor', 'Editor'),
('viewer', 'Visor'),
('cashier', 'Vendedor / Cajero');

-- Default admin user (password: admin123)
INSERT INTO admin_users (email, password_hash, name, role_id, is_active) VALUES
('admin@vunotek.com', '$2y$10$oRW4DfzqhXvcXNbnln07AeKIKCnisf.kV84Jc0uNnLndrmk4SmAva', 'Administrador', 1, 1);

-- Order statuses
INSERT INTO order_statuses (code, name, sort_order) VALUES
('pending', 'Pendiente', 1),
('paid', 'Pagado', 2),
('shipped', 'Enviado', 3),
('delivered', 'Entregado', 4),
('cancelled', 'Cancelado', 5);

-- Payment methods
INSERT IGNORE INTO payment_methods (code, name, sort_order) VALUES
('stripe', 'Tarjeta (Stripe)', 1),
('transfer', 'Transferencia Bancaria', 2),
('pos_cash', 'Efectivo (POS)', 3),
('pos_card', 'Tarjeta (POS)', 4),
('pos_transfer', 'Transferencia (POS)', 5);

-- Payment statuses
INSERT INTO payment_statuses (code, name) VALUES
('pending', 'Pendiente'),
('completed', 'Completado'),
('failed', 'Fallido'),
('refunded', 'Reembolsado');

-- ============================================================
--  Migration: origin column for orders (POS module)
-- ============================================================
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'origin');
SET @sql = IF(@col_exists = 0,
              'ALTER TABLE orders ADD COLUMN origin ENUM(''online'',''pos'') NOT NULL DEFAULT ''online'' AFTER total',
              'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Default settings
INSERT INTO settings (section, `key`, value) VALUES
('store', 'name', 'Ram;Lop'),
('store', 'slogan', 'Architectural Minimalism in Footwear'),
('store', 'email', 'hola@ramlop.com'),
('store', 'logo', ''),
('store', 'description', 'Calzado artesanal para damas — diseño minimalista con inspiración arquitectónica.'),
('store', 'newsletter_discount_code', ''),
('receipt', 'business_name', ''),
('receipt', 'tax_id', ''),
('receipt', 'address', ''),
('receipt', 'city', ''),
('receipt', 'state', ''),
('receipt', 'zip', ''),
('receipt', 'phone', ''),
('imagekit', 'public_key', ''),
('imagekit', 'private_key', ''),
('imagekit', 'url_endpoint', ''),
('smtp', 'host', ''),
('smtp', 'port', '587'),
('smtp', 'user', ''),
('smtp', 'pass', ''),
('smtp', 'from_email', ''),
('smtp', 'from_name', 'Ram;Lop'),
('smtp', 'adminEmail', 'admin@vunotek.com'),
('stripe', 'enabled', '1'),
('stripe', 'publishableKey', ''),
('stripe', 'secretKey', ''),
('stripe', 'webhookSecret', ''),
('transfer', 'enabled', '1'),
('policies', 'shipping_es', 'Ofrecemos envío estándar gratuito en todos los pedidos. Los envíos se procesan en un plazo de 1-2 días hábiles y la entrega estimada es de 5-10 días hábiles según tu ubicación.'),
('policies', 'shipping_en', 'We offer free standard shipping on all orders. Orders are processed within 1-2 business days and estimated delivery is 5-10 business days depending on your location.'),
('policies', 'returns_es', 'Aceptamos devoluciones dentro de los 14 días posteriores a la entrega para artículos en su condición original, sin usar y con todas las etiquetas originales. Los gastos de envío de devolución corren por cuenta del cliente.'),
('policies', 'returns_en', 'Returns are accepted within 14 days of delivery for items in their original condition, unworn, and with all original tags. Return shipping costs are the responsibility of the customer.'),
('policies', 'privacy_es', 'Tu información personal se utiliza únicamente para procesar pedidos y mejorar tu experiencia en Ram;Lop. No compartimos datos con terceros sin tu consentimiento explícito. Al realizar una compra, aceptas los términos de esta política de privacidad.'),
('policies', 'privacy_en', 'Your personal information is used solely to process orders and improve your experience at Ram;Lop. We do not share data with third parties without your explicit consent. By making a purchase, you agree to the terms of this privacy policy.'),
('size_guide', 'title_es', 'Guía de Talles'),
('size_guide', 'title_en', 'Size Guide'),
('size_guide', 'footer_es', 'Medí tu pie desde el talón hasta el dedo más largo. Si estás entre talles, recomendamos elegir el talle superior.'),
('size_guide', 'footer_en', 'Measure your foot from heel to longest toe. If you are between sizes, we recommend choosing the larger size.');

-- Size guide conversion rows
INSERT INTO size_guide_rows (us_size, eu_size, uk_size, cm_size, sort_order) VALUES
('5',   '35',   '2.5', '22',   1),
('5.5', '35.5', '3',   '22.5', 2),
('6',   '36',   '3.5', '23',   3),
('6.5', '36.5', '4',   '23.5', 4),
('7',   '37',   '4.5', '23.8', 5),
('7.5', '37.5', '5',   '24.1', 6),
('8',   '38',   '5.5', '24.4', 7),
('8.5', '38.5', '6',   '24.8', 8),
('9',   '39',   '6.5', '25.1', 9),
('9.5', '39.5', '7',   '25.4', 10),
('10',  '40',   '7.5', '25.8', 11),
('10.5','40.5', '8',   '26.1', 12),
('11',  '41',   '8.5', '26.4', 13),
('11.5','41.5', '9',   '26.7', 14);

-- Bank accounts for transfer payments
INSERT INTO bank_accounts (bank_name, account_holder, account_number, account_type, routing_number, instructions, is_active, sort_order) VALUES
('Banco Nacional', 'Ram;Lop S.A.S.', '1234567890', 'Corriente', 'BNC-001', 'Depósito en ventanilla o transferencia electrónica', TRUE, 1),
('Banco del Estado', 'Ram;Lop S.A.S.', '0987654321', 'Ahorros', 'BDE-001', 'Transferencia inmediata desde cualquier banco nacional', TRUE, 2);

-- Currencies (multi-currency support, base = USD)
INSERT INTO currencies (code, name, symbol, exchange_rate, decimal_places, is_active, sort_order) VALUES
('USD', 'US Dollar',          '$',    1.000000, 2, TRUE,  1),
('MXN', 'Peso Mexicano',      'Mex$', 20.000000, 2, TRUE,  2),
('GTQ', 'Quetzal Guatemalteco', 'Q', 7.800000,  2, TRUE,  3),
('HNL', 'Lempira Hondureño',  'L',    24.700000, 2, TRUE,  4),
('NIO', 'Córdoba Nicaragüense', 'C$', 36.500000, 2, TRUE, 5),
('CRC', 'Colón Costarricense', '₡',   525.000000, 2, TRUE, 6),
('COP', 'Peso Colombiano',    '$',    4000.000000, 2, TRUE, 7);

-- Default store currency
INSERT INTO settings (section, `key`, value) VALUES ('currency', 'code', 'USD');

-- Sample customer
INSERT INTO customers (email, name, phone, is_verified) VALUES
('maria.garcia@example.com', 'María García', '+52 55 1234 5678', TRUE);

-- Sample Order 1: Stripe, paid
INSERT INTO orders (order_number, customer_id, customer_email, customer_name, customer_phone,
    shipping_name, shipping_line1, shipping_city, shipping_state, shipping_zip, shipping_country,
    billing_name, billing_line1, billing_city, billing_state, billing_zip, billing_country,
    subtotal, shipping_total, tax_total, discount_total, total, currency,
    status_id, payment_method_id, payment_status_id, stripe_payment_intent_id,
    paid_at, created_at)
VALUES
('ORD-2026-001', 1, 'maria.garcia@example.com', 'María García', '+52 55 1234 5678',
 'María García', 'Av. Reforma 123, Col. Juárez', 'Ciudad de México', 'CDMX', '06600', 'MX',
 'María García', 'Av. Reforma 123, Col. Juárez', 'Ciudad de México', 'CDMX', '06600', 'MX',
 850.00, 50.00, 0, 0, 900.00, 'USD',
 2, 1, 2, 'pi_stripe_sample_001',
 NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 3 DAY);

INSERT INTO order_items (order_id, product_id, variant_id, product_name, product_slug, product_image, product_price, product_sku, quantity, unit_price, subtotal, selected_color, selected_size) VALUES
(1, 'prod-001', 1, 'Mule Arquitectónica', 'mule-arquitectonica', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 520.00, 'MULE-NOIR-36', 1, 520.00, 520.00, 'Noir', '36'),
(1, 'prod-002', 11, 'Tacón Estructura', 'tacon-estructura', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 330.00, 'TACON-BLANC-36', 1, 330.00, 330.00, 'Blanc Cassé', '36');

INSERT INTO order_status_history (order_id, from_status_id, to_status_id, notes, created_at) VALUES
(1, NULL, 1, 'Order created by customer', NOW() - INTERVAL 3 DAY),
(1, 1, 2, 'Payment confirmed via Stripe', NOW() - INTERVAL 3 DAY);

INSERT INTO stock_movements (variant_id, quantity_change, stock_before, stock_after, reference_type, reference_id, notes, created_at) VALUES
(1, -1, 10, 9, 'order', 'ORD-2026-001', 'Order item: Mule Arquitectónica Noir/36', NOW() - INTERVAL 3 DAY),
(11, -1, 10, 9, 'order', 'ORD-2026-001', 'Order item: Tacón Estructura Blanc Cassé/36', NOW() - INTERVAL 3 DAY);

-- Sample Order 2: Transfer, pending
INSERT INTO orders (order_number, customer_id, customer_email, customer_name, customer_phone,
    shipping_name, shipping_line1, shipping_city, shipping_state, shipping_zip, shipping_country,
    billing_name, billing_line1, billing_city, billing_state, billing_zip, billing_country,
    subtotal, shipping_total, tax_total, discount_total, total, currency,
    status_id, payment_method_id, payment_status_id, notes, created_at)
VALUES
('ORD-2026-002', 1, 'maria.garcia@example.com', 'María García', '+52 55 1234 5678',
 'María García', 'Av. Reforma 123, Col. Juárez', 'Ciudad de México', 'CDMX', '06600', 'MX',
 'María García', 'Av. Reforma 123, Col. Juárez', 'Ciudad de México', 'CDMX', '06600', 'MX',
 520.00, 50.00, 0, 0, 570.00, 'USD',
 1, 2, 1, 'Cliente pagará por transferencia bancaria',
 NOW() - INTERVAL 1 DAY);

INSERT INTO order_items (order_id, product_id, variant_id, product_name, product_slug, product_image, product_price, product_sku, quantity, unit_price, subtotal, selected_color, selected_size) VALUES
(2, 'prod-007', 45, 'Botín Bloque Escultural', 'botin-bloque-escultural', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 520.00, 'BOTIN-NOIR-36', 1, 520.00, 520.00, 'Noir', '36');

INSERT INTO order_status_history (order_id, from_status_id, to_status_id, notes, created_at) VALUES
(2, NULL, 1, 'Order created by customer via bank transfer', NOW() - INTERVAL 1 DAY);

-- =============================================================================
-- Newsletter
-- =============================================================================

CREATE TABLE newsletter_subscribers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(255) NOT NULL UNIQUE,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    subscribed_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    unsubscribed_at TIMESTAMP NULL DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- =============================================================================
-- Blog
-- =============================================================================

CREATE TABLE blog_categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(200) NOT NULL,
    slug        VARCHAR(200) NOT NULL UNIQUE,
    description TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE blog_posts (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title             VARCHAR(255) NOT NULL,
    slug              VARCHAR(255) NOT NULL UNIQUE,
    excerpt           TEXT,
    thumbnail_image   VARCHAR(500),
    content           LONGTEXT NOT NULL,
    featured_image    VARCHAR(500),
    author            VARCHAR(200) DEFAULT 'Ram;Lop',
    status            ENUM('draft', 'published') DEFAULT 'draft',
    category_id       INT UNSIGNED DEFAULT NULL,
    meta_title        VARCHAR(255),
    meta_description  VARCHAR(500),
    published_at      TIMESTAMP NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at        TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_published (published_at),
    INDEX idx_slug (slug)
) ENGINE=InnoDB;

CREATE TABLE blog_post_translations (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blog_post_id  INT UNSIGNED NOT NULL,
    lang          CHAR(2) NOT NULL,
    title         VARCHAR(255),
    excerpt       TEXT,
    content       LONGTEXT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (blog_post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    UNIQUE KEY uk_post_lang (blog_post_id, lang)
) ENGINE=InnoDB;

CREATE TABLE blog_category_translations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    lang        CHAR(2) NOT NULL,
    name        VARCHAR(200) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE CASCADE,
    UNIQUE KEY uk_bcat_lang (category_id, lang)
) ENGINE=InnoDB;

-- =============================================================================
-- Email Templates
-- =============================================================================
INSERT INTO email_templates (code, name, subject, body_html, is_active) VALUES
('new_order_notification', 'New order notification', 'New Order #{{order_id}} — {{store_name}} Admin', '<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="format-detection" content="telephone=no">
<title>New Order #{{order_id}}</title>
<style type="text/css">
body{margin:0;padding:0;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
table,td{border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0}
img{border:0;height:auto;line-height:100%;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic}
p{display:block;margin:13px 0}
@media only screen and (max-width:600px){
  .email-container{width:100%!important}
  .stack-cell{display:block!important;width:100%!important}
}
</style>
</head>
<body style="margin:0;padding:0;background-color:#f5f3f0;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif">
<div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">{{preheader}}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f3f0">
<tr><td align="center" style="padding:40px 16px">

  <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%">

    <!-- Logo -->
    <tr>
      <td align="center" style="padding:0 0 32px 0">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="background-color:#1a1a1a;padding:12px 32px;border-radius:2px">
              {{store_logo_block}}
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Content -->
    <tr>
      <td style="background-color:#ffffff;padding:32px;border-radius:2px;box-shadow:0 1px 2px rgba(0,0,0,0.06)">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#1a1a1a">

              <h1 style="font-family:''Playfair Display'',Georgia,serif;font-size:24px;color:#1a1a1a;margin:0 0 16px;font-weight:400">New Order #{{order_id}}</h1>

              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px">
                <tr><td style="padding:4px 0;font-size:14px;color:#6b6b6b">Customer</td><td style="padding:4px 0;font-size:14px;color:#1a1a1a">{{customer_name}} ({{customer_email}})</td></tr>
                <tr><td style="padding:4px 0;font-size:14px;color:#6b6b6b">Subtotal</td><td style="padding:4px 0;font-size:14px;color:#1a1a1a">{{currency_symbol}}{{order_subtotal}}</td></tr>
                <tr><td style="padding:4px 0;font-size:14px;color:#6b6b6b">Shipping</td><td style="padding:4px 0;font-size:14px;color:#1a1a1a">{{order_shipping}}</td></tr>
                {{coupon_discount_row}}
                <tr><td style="padding:4px 0;font-size:14px;color:#6b6b6b">IVA</td><td style="padding:4px 0;font-size:14px;color:#1a1a1a">{{order_tax}}</td></tr>
                <tr><td style="padding:8px 0 4px;border-top:2px solid #1a1a1a;font-size:14px;color:#1a1a1a;font-weight:600">Total</td><td style="padding:8px 0 4px;border-top:2px solid #1a1a1a;font-size:14px;color:#1a1a1a;font-weight:600">{{currency_symbol}}{{order_total}}</td></tr>
                <tr><td style="padding:4px 0;font-size:14px;color:#6b6b6b">Payment</td><td style="padding:4px 0;font-size:14px;color:#1a1a1a;text-transform:capitalize">{{payment_method}}</td></tr>
                <tr><td style="padding:4px 0;font-size:14px;color:#6b6b6b">Status</td><td style="padding:4px 0;font-size:14px;color:#1a1a1a;text-transform:capitalize">{{order_status}}</td></tr>
              </table>

              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px">
                <thead>
                  <tr>
                    <th style="padding:6px 0;border-bottom:2px solid #1a1a1a;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#9A9A9A;text-align:left;font-weight:700">Item</th>
                    <th style="padding:6px 0;border-bottom:2px solid #1a1a1a;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#9A9A9A;text-align:center;font-weight:700">Qty</th>
                    <th style="padding:6px 0;border-bottom:2px solid #1a1a1a;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#9A9A9A;text-align:right;font-weight:700">Total</th>
                  </tr>
                </thead>
                <tbody>
                  {{order_items_html}}
                </tbody>
              </table>

              {{receipt_block}}

              <p style="margin:16px 0 0;font-size:13px;color:#9A9A9A">View in admin: <a href="{{admin_order_url}}" style="color:#1a1a1a">{{order_id}}</a></p>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td style="padding:24px 0 0 0;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#9A9A9A;text-align:center">
        <p style="margin:0 0 4px">&copy; 2026 {{store_name}}. {{store_slogan}}.</p>
        <p style="margin:0">Este es un correo automático, por favor no respondas a este mensaje.</p>
      </td>
    </tr>

  </table>

</td></tr>
</table>
</body>
</html>', TRUE),
('order_confirmation', 'Order confirmation', '{{status_subject}} — {{store_name}}', '<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="format-detection" content="telephone=no">
<title>Order Confirmation #{{order_id}}</title>
<style type="text/css">
body{margin:0;padding:0;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
table,td{border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0}
img{border:0;height:auto;line-height:100%;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic}
p{display:block;margin:13px 0}
@media only screen and (max-width:600px){
  .email-container{width:100%!important}
  .stack-cell{display:block!important;width:100%!important}
  .hide-mobile{display:none!important}
  .p-resp{padding:20px 16px!important}
}
</style>
</head>
<body style="margin:0;padding:0;background-color:#f5f3f0;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif">
<div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">{{preheader}}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f3f0">
<tr><td align="center" style="padding:40px 16px">

  <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%">

    <!-- Logo -->
    <tr>
      <td align="center" style="padding:0 0 32px 0">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="background-color:#1a1a1a;padding:12px 32px;border-radius:2px">
              {{store_logo_block}}
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Content -->
    <tr>
      <td style="background-color:#ffffff;padding:32px;border-radius:2px;box-shadow:0 1px 2px rgba(0,0,0,0.06)">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#1a1a1a">

              <h1 style="font-family:''Playfair Display'',Georgia,serif;font-size:24px;color:#1a1a1a;margin:0 0 8px;font-weight:400">Thank you, {{customer_name}}!</h1>
              <p style="margin:0 0 24px;color:#6b6b6b">{{status_message}} Here''s a summary of your purchase:</p>

              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px">
                <thead>
                  <tr>
                    <th style="padding:8px 0;border-bottom:2px solid #1a1a1a;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#9A9A9A;text-align:left">Item</th>
                    <th style="padding:8px 0;border-bottom:2px solid #1a1a1a;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#9A9A9A;text-align:center">Qty</th>
                    <th style="padding:8px 0;border-bottom:2px solid #1a1a1a;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#9A9A9A;text-align:right">Total</th>
                  </tr>
                </thead>
                <tbody>
                  {{order_items_html}}
                </tbody>
              </table>

              {{transfer_details_block}}

              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="padding:4px 0;font-size:14px;color:#6b6b6b">Subtotal</td>
                  <td style="padding:4px 0;font-size:14px;color:#1a1a1a;text-align:right">{{currency_symbol}}{{order_subtotal}}</td>
                </tr>
                {{coupon_discount_row}}
                <tr>
                  <td style="padding:4px 0;font-size:14px;color:#6b6b6b">Shipping</td>
                  <td style="padding:4px 0;font-size:14px;color:#1a1a1a;text-align:right">{{order_shipping}}</td>
                </tr>
                <tr>
                  <td style="padding:4px 0;font-size:14px;color:#6b6b6b">IVA</td>
                  <td style="padding:4px 0;font-size:14px;color:#1a1a1a;text-align:right">{{order_tax}}</td>
                </tr>
                <tr>
                  <td style="padding:12px 0 4px;border-top:2px solid #1a1a1a;font-size:16px;color:#1a1a1a;font-weight:600">Total</td>
                  <td style="padding:12px 0 4px;border-top:2px solid #1a1a1a;font-size:16px;color:#1a1a1a;font-weight:600;text-align:right">{{currency_symbol}}{{order_total}}</td>
                </tr>
              </table>

            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td style="padding:24px 0 0 0;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#9A9A9A;text-align:center">
        <p style="margin:0 0 4px">&copy; 2026 {{store_name}}. {{store_slogan}}.</p>
        <p style="margin:0">Este es un correo automático, por favor no respondas a este mensaje.</p>
      </td>
    </tr>

  </table>

</td></tr>
</table>
</body>
</html>', TRUE),
('contact_notification', 'Contact notification', '{{subject}} — {{name}} &lt;{{email}}&gt;', '<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="format-detection" content="telephone=no">
<title>Nuevo mensaje de contacto</title>
<style type="text/css">
body{margin:0;padding:0;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
table,td{border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0}
img{border:0;height:auto;line-height:100%;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic}
p{display:block;margin:13px 0}
@media only screen and (max-width:600px){
  .email-container{width:100%!important}
  .stack-cell{display:block!important;width:100%!important}
}
</style>
</head>
<body style="margin:0;padding:0;background-color:#f5f3f0;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f3f0">
<tr><td align="center" style="padding:40px 16px">

  <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%">

    <!-- Header -->
    <tr>
      <td align="center" style="padding:0 0 24px 0">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="background-color:#1a1a1a;padding:12px 32px;border-radius:2px">
              {{store_logo_block}}
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <tr>
      <td style="background-color:#ffffff;border-radius:2px;padding:32px">

        <h1 style="margin:0 0 4px 0;font-family:''Playfair Display'',Georgia,serif;font-size:22px;font-weight:400;color:#1a1a1a;letter-spacing:0.01em">Nuevo mensaje de contacto</h1>
        <p style="margin:0 0 20px 0;font-size:13px;color:#9a9a9a">Recibido desde el formulario de contacto de {{store_name}}</p>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="padding:10px 0;border-bottom:1px solid #f0eeeb">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td width="100" style="font-size:13px;color:#9a9a9a;vertical-align:top;padding-right:12px">Nombre</td>
                  <td style="font-size:14px;color:#1a1a1a">{{name}}</td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:10px 0;border-bottom:1px solid #f0eeeb">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td width="100" style="font-size:13px;color:#9a9a9a;vertical-align:top;padding-right:12px">Email</td>
                  <td style="font-size:14px;color:#1a1a1a">{{email}}</td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:10px 0;border-bottom:1px solid #f0eeeb">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td width="100" style="font-size:13px;color:#9a9a9a;vertical-align:top;padding-right:12px">Teléfono</td>
                  <td style="font-size:14px;color:#1a1a1a">{{phone}}</td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:10px 0;border-bottom:1px solid #f0eeeb">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td width="100" style="font-size:13px;color:#9a9a9a;vertical-align:top;padding-right:12px">Asunto</td>
                  <td style="font-size:14px;color:#1a1a1a;font-weight:600">{{subject}}</td>
                </tr>
              </table>
            </td>
          </tr>
        </table>

        <h3 style="margin:24px 0 8px 0;font-size:13px;color:#9a9a9a;font-weight:500;text-transform:uppercase;letter-spacing:0.08em">Mensaje</h3>
        <div style="font-size:14px;color:#1a1a1a;line-height:1.6;background-color:#faf9f8;padding:16px;border:1px solid #f0eeeb;border-radius:2px;white-space:pre-wrap">{{message}}</div>

      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td align="center" style="padding:24px 0 0 0">
        <p style="margin:0;font-size:11px;color:#9a9a9a">Enviado desde el formulario de contacto de {{store_name}}</p>
      </td>
    </tr>

  </table>

</td></tr>
</table>
</body>
</html>', TRUE),
('welcome', 'Welcome', 'Welcome to {{store_name}} — {{customer_name}}', '<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="format-detection" content="telephone=no">
<title>Welcome to {{store_name}}</title>
<style type="text/css">
body{margin:0;padding:0;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
table,td{border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0}
img{border:0;height:auto;line-height:100%;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic}
p{display:block;margin:13px 0}
@media only screen and (max-width:600px){
  .email-container{width:100%!important}
}
</style>
</head>
<body style="margin:0;padding:0;background-color:#f5f3f0;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif">
<div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">{{preheader}}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f3f0">
<tr><td align="center" style="padding:40px 16px">

  <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%">

    <!-- Logo -->
    <tr>
      <td align="center" style="padding:0 0 32px 0">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="background-color:#1a1a1a;padding:12px 32px;border-radius:2px">
              {{store_logo_block}}
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Content -->
    <tr>
      <td style="background-color:#ffffff;padding:32px;border-radius:2px;box-shadow:0 1px 2px rgba(0,0,0,0.06)">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#1a1a1a">

              <h1 style="font-family:''Playfair Display'',Georgia,serif;font-size:24px;color:#1a1a1a;margin:0 0 16px;font-weight:400">Welcome, {{customer_name}}.</h1>

              <p style="margin:0 0 16px">Thank you for creating an account at {{store_name}}. We are delighted to have you as part of our community.</p>

              <p style="margin:0 0 16px">With your account you can:</p>

              <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px">
                <tr><td style="padding:4px 0;font-size:14px;color:#1a1a1a">✦ &nbsp;Track your orders in real time</td></tr>
                <tr><td style="padding:4px 0;font-size:14px;color:#1a1a1a">✦ &nbsp;Save your favorite styles to your wishlist</td></tr>
                <tr><td style="padding:4px 0;font-size:14px;color:#1a1a1a">✦ &nbsp;Manage your shipping addresses</td></tr>
                <tr><td style="padding:4px 0;font-size:14px;color:#1a1a1a">✦ &nbsp;Enjoy a faster checkout experience</td></tr>
              </table>

              <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px">
                <tr>
                  <td style="background-color:#1a1a1a;border-radius:2px;text-align:center">
                    <a href="{{store_url}}" style="display:inline-block;padding:14px 36px;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif;font-size:14px;font-weight:600;letter-spacing:0.08em;color:#f5f3f0;text-decoration:none;text-transform:uppercase">Explore the Collection</a>
                  </td>
                </tr>
              </table>

              <p style="margin:0;font-size:13px;color:#6b6b6b">If you have any questions, simply reply to this email or visit our help center.</p>

            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td style="padding:24px 0 0 0;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#9A9A9A;text-align:center">
        <p style="margin:0 0 4px">&copy; 2026 {{store_name}}. {{store_slogan}}.</p>
        <p style="margin:0">This is an automated message, please do not reply.</p>
      </td>
    </tr>

  </table>

</td></tr>
</table>
</body>
</html>', TRUE),
('newsletter_welcome', 'Newsletter welcome', 'Welcome to {{store_name}} — {{store_slogan}}', '<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="format-detection" content="telephone=no">
<title>Welcome to {{store_name}}</title>
<style type="text/css">
body{margin:0;padding:0;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
table,td{border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0}
img{border:0;height:auto;line-height:100%;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic}
p{display:block;margin:13px 0}
@media only screen and (max-width:600px){
  .email-container{width:100%!important}
  .stack-cell{display:block!important;width:100%!important}
  .hide-mobile{display:none!important}
  .p-resp{padding:20px 16px!important}
}
</style>
</head>
<body style="margin:0;padding:0;background-color:#f5f3f0;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif">
<div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">{{preheader}}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f3f0">
<tr><td align="center" style="padding:40px 16px">

  <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%">

    <!-- Logo -->
    <tr>
      <td align="center" style="padding:0 0 32px 0">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="background-color:transparent;padding:12px 32px;border-radius:2px">
              {{store_logo_block}}
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Content -->
    <tr>
      <td style="background-color:#ffffff;padding:32px;border-radius:2px;box-shadow:0 1px 2px rgba(0,0,0,0.06)">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#1a1a1a">

              <h1 style="font-family:''Playfair Display'',Georgia,serif;font-size:24px;color:#1a1a1a;margin:0 0 8px;font-weight:400">Welcome to {{store_name}}, {{subscriber_name}}!</h1>
              <p style="margin:0 0 24px;color:#6b6b6b">Thanks for joining our community. You''ll now receive exclusive updates on new collections, artisan stories, and special offers.</p>

              {{discount_block}}

              <p style="margin:24px 0 8px;color:#1a1a1a;font-weight:600">What to expect:</p>
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px">
                <tr><td style="padding:4px 0;font-size:14px;color:#6b6b6b;padding-left:12px">✦ &nbsp;New collection launches and restocks</td></tr>
                <tr><td style="padding:4px 0;font-size:14px;color:#6b6b6b;padding-left:12px">✦ &nbsp;Behind-the-scenes artisan stories</td></tr>
                <tr><td style="padding:4px 0;font-size:14px;color:#6b6b6b;padding-left:12px">✦ &nbsp;Exclusive subscriber-only discounts</td></tr>
                <tr><td style="padding:4px 0;font-size:14px;color:#6b6b6b;padding-left:12px">✦ &nbsp;Style guides and care tips</td></tr>
              </table>

              <p style="margin:0 0 24px;color:#6b6b6b">Follow us on social media for daily inspiration:</p>
              <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  {{social_links_block}}
                </tr>
              </table>

            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td style="padding:24px 0 0 0;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#9A9A9A;text-align:center">
        <p style="margin:0 0 4px">&copy; 2026 {{store_name}}. {{store_slogan}}.</p>
        <p style="margin:0">You received this email because you subscribed to our newsletter.</p>
        <p style="margin:0"><a href="{{unsubscribe_url}}" style="color:#9A9A9A;text-decoration:underline">Unsubscribe</a></p>
      </td>
    </tr>

  </table>

</td></tr>
</table>
</body>
</html>', TRUE),
('newsletter_campaign', 'Newsletter campaign', '{{subject}}', '<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="format-detection" content="telephone=no">
<title>{{subject}}</title>
<style type="text/css">
body{margin:0;padding:0;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
table,td{border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0}
img{border:0;height:auto;line-height:100%;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic}
p{display:block;margin:13px 0}
@media only screen and (max-width:600px){
  .email-container{width:100%!important}
  .stack-cell{display:block!important;width:100%!important}
  .hide-mobile{display:none!important}
  .p-resp{padding:20px 16px!important}
}
</style>
</head>
<body style="margin:0;padding:0;background-color:#f5f3f0;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif">
<div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">{{preheader}}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f3f0">
<tr><td align="center" style="padding:40px 16px">

  <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%">

    <!-- Logo -->
    <tr>
      <td align="center" style="padding:0 0 32px 0">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="background-color:#1a1a1a;padding:12px 32px;border-radius:2px">
              {{store_logo_block}}
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Content -->
    <tr>
      <td style="background-color:#ffffff;padding:32px;border-radius:2px;box-shadow:0 1px 2px rgba(0,0,0,0.06)">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#1a1a1a">

              <h1 style="font-family:''Playfair Display'',Georgia,serif;font-size:24px;color:#1a1a1a;margin:0 0 8px;font-weight:400">{{title}}</h1>
              <p style="margin:0 0 24px;color:#6b6b6b">{{message}}</p>

              {{content_block}}

            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td style="padding:24px 0 0 0;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#9A9A9A;text-align:center">
        <p style="margin:0 0 4px">&copy; 2026 {{store_name}}. {{store_slogan}}.</p>
        <p style="margin:0 4px">You are receiving this email because you subscribed to our newsletter.</p>
        <p style="margin:0"><a href="{{unsubscribe_url}}" style="color:#9A9A9A;text-decoration:underline">Unsubscribe</a></p>
        <p style="margin:4px 0 0;font-size:11px;color:#ccc">{{store_name}} &middot; {{store_email}}</p>
      </td>
    </tr>

  </table>

</td></tr>
</table>
</body>
</html>', TRUE),
('password_reset', 'Password reset', 'Reset Your Password — {{store_name}}', '<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="format-detection" content="telephone=no">
<title>Reset Your Password</title>
<style type="text/css">
body{margin:0;padding:0;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
table,td{border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0}
img{border:0;height:auto;line-height:100%;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic}
p{display:block;margin:13px 0}
@media only screen and (max-width:600px){
  .email-container{width:100%!important}
}
</style>
</head>
<body style="margin:0;padding:0;background-color:#f5f3f0;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif">
<div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">{{preheader}}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f3f0">
<tr><td align="center" style="padding:40px 16px">

  <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%">

    <!-- Logo -->
    <tr>
      <td align="center" style="padding:0 0 32px 0">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="background-color:#1a1a1a;padding:12px 32px;border-radius:2px">
              {{store_logo_block}}
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Content -->
    <tr>
      <td style="background-color:#ffffff;padding:32px;border-radius:2px;box-shadow:0 1px 2px rgba(0,0,0,0.06)">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#1a1a1a">

              <h1 style="font-family:''Playfair Display'',Georgia,serif;font-size:24px;color:#1a1a1a;margin:0 0 16px;font-weight:400">Reset Your Password</h1>

              <p style="margin:0 0 16px">Hi {{customer_name}},</p>

              <p style="margin:0 0 16px">We received a request to reset the password for your {{store_name}} account. Click the button below to set a new password. This link will expire in 1 hour.</p>

              <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px">
                <tr>
                  <td style="background-color:#1a1a1a;border-radius:2px;text-align:center">
                    <a href="{{reset_url}}" style="display:inline-block;padding:14px 36px;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif;font-size:14px;font-weight:600;letter-spacing:0.08em;color:#f5f3f0;text-decoration:none;text-transform:uppercase">Reset Password</a>
                  </td>
                </tr>
              </table>

              <p style="margin:0 0 16px;font-size:13px;color:#6b6b6b">If you didn''t request a password reset, you can safely ignore this email. Your password will not be changed.</p>

              <p style="margin:0;font-size:13px;color:#6b6b6b">Or copy this link into your browser:<br>
              <a href="{{reset_url}}" style="color:#1a1a1a;word-break:break-all">{{reset_url}}</a></p>

            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td style="padding:24px 0 0 0;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#9A9A9A;text-align:center">
        <p style="margin:0 0 4px">&copy; 2026 {{store_name}}. {{store_slogan}}.</p>
        <p style="margin:0">This is an automated message, please do not reply.</p>
      </td>
    </tr>

  </table>

</td></tr>
</table>
</body>
</html>', TRUE);
-- Seed blog categories
INSERT INTO blog_categories (name, slug, description) VALUES
('Tendencias', 'tendencias', 'Últimas tendencias en calzado artesanal'),
('Cuidados', 'cuidados', 'Guías para el cuidado de tus zapatos'),
('Detrás del Diseño', 'detras-del-diseno', 'Historias del proceso creativo y artesanal');

-- Seed blog posts
INSERT INTO blog_posts (title, slug, excerpt, thumbnail_image, content, featured_image, author, category_id, status, published_at) VALUES
('5 Tendencias de Moda que Definirán Esta Temporada', 'tendencias-moda-temporada',
 'Descubre las cinco tendencias imprescindibles que están marcando el rumbo de la moda: desde el calzado escultural hasta los accesorios que roban miradas.',
 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=600&q=80',
 '<h2>El Regreso del Minimalismo Arquitectónico</h2><p>Esta temporada, la moda abraza la pureza de las líneas limpias y las siluetas depuradas. Los diseñadores apuestan por formas escultóricas que recuerdan a la arquitectura brutalista, con bloques geométricos y tacones que desafían la gravedad.</p><p>Los materiales nobles como el cuero vacuno, la piel de becerro y los acabados satinados dominan las colecciones. Los colores tierra, el negro monólito y los tonos nude se consolidan como la paleta esencial del armario consciente.</p>',
 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=1200&q=80',
 'María Ram;Lop', 1, 'published', NOW() - INTERVAL 7 DAY),
('Guía Completa para el Cuidado de Tus Zapatos Artesanales', 'guia-cuidado-zapatos-artesanales',
 'Aprende a preservar la belleza y durabilidad de tus zapatos hechos a mano con nuestra guía experta de cuidados, limpieza y almacenamiento.',
 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=600&q=80',
 '<h2>Por Qué el Cuidado es Esencial</h2><p>Un par de zapatos artesanales es una inversión en calidad, diseño y sostenibilidad. Cuidarlos adecuadamente no solo prolonga su vida útil, sino que honra el trabajo del artesano que los creó.</p><p>Con los cuidados apropiados, unos zapatos artesanales de buena calidad pueden durar décadas.</p>',
 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=1200&q=80',
 'Ram;Lop', 2, 'published', NOW() - INTERVAL 4 DAY),
('El Proceso Artesanal: De la Inspiración al Calzado', 'proceso-artesanal-inspiracion-calzado',
 'Te llevamos detrás del taller para mostrarte cómo nace cada diseño: desde el boceto inicial hasta el último punto de costura en nuestras piezas artesanales.',
 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=600&q=80',
 '<h2>La Chispa Creativa</h2><p>Todo comienza con una imagen mental. La arquitectura minimalista es nuestra musa constante: líneas rectas, volúmenes precisos, espacios vacíos que respiran.</p><p>El proceso creativo es iterativo. Boceto tras boceto, vamos depurando la forma hasta encontrar la silueta que encapsula la visión inicial.</p>',
 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=1200&q=80',
 'Carlos Mendoza', 3, 'published', NOW() - INTERVAL 1 DAY),
('Cómo Combinar tus Zapatos con los Accesorios Perfectos', 'como-combinar-zapatos-accesorios',
 'Dominar el arte de coordinar calzado, carteras y accesorios puede transformar cualquier look. Te compartimos las claves para crear combinaciones armónicas y sofisticadas.',
 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=600&q=80',
 '<h2>La Regla de Oro: Menos es Más</h2><p>En la moda consciente, la máxima de menos es más sigue vigente. La clave está en elegir un punto focal y construir alrededor de él.</p><p>Si tus zapatos son la pieza protagonista, el resto de accesorios deben acompañar sin competir.</p>',
 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=1200&q=80',
 'Ram;Lop', 1, 'published', NOW());

-- Seed coupons
INSERT INTO coupons (code, description, discount_type, discount_value, min_order_amount, max_uses, max_uses_per_customer, is_active, starts_at, expires_at) VALUES
('WELCOME10', '10% de descuento en tu primera compra', 'percentage', 10, 0, 100, 1, TRUE, NOW(), NOW() + INTERVAL 90 DAY),
('VIP50', 'C$50 de descuento en compras mayores a C$500', 'fixed', 50, 500, 50, 1, TRUE, NOW(), NOW() + INTERVAL 365 DAY);
