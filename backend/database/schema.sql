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
    size_prefix VARCHAR(10) NOT NULL DEFAULT 'US',
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
    UNIQUE KEY uk_product_color (product_id, name),
    INDEX idx_product (product_id)
) ENGINE=InnoDB;

CREATE TABLE product_sizes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  VARCHAR(50) NOT NULL,
    label       VARCHAR(20) NOT NULL,
    value       VARCHAR(20) NOT NULL,
    sort_order  INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uk_product_size (product_id, value),
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
    INDEX idx_customer (customer_id),
    UNIQUE INDEX uq_customer_address_line (customer_id, address_line1(100), city(50))
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
    origin                  ENUM('online','pos') NOT NULL DEFAULT 'online',

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

CREATE TABLE rate_limiting (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    namespace   VARCHAR(50) NOT NULL,
    identifier  VARCHAR(64) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lookup (namespace, identifier, created_at)
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
-- 14. Notificaciones In-App (Admin)
-- =============================================================================

CREATE TABLE admin_notifications (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type            VARCHAR(50) NOT NULL DEFAULT 'new_order',
    title           VARCHAR(255) NOT NULL,
    message         TEXT,
    reference_type  VARCHAR(50) DEFAULT 'order',
    reference_id    VARCHAR(100),
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_unread (is_read, created_at),
    INDEX idx_reference (reference_type, reference_id)
) ENGINE=InnoDB;

-- =============================================================================
-- 15. Blog / Editorial
-- =============================================================================

CREATE TABLE blog_categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(120) NOT NULL UNIQUE,
    description TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug)
) ENGINE=InnoDB;

CREATE TABLE blog_category_translations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    lang        CHAR(2) NOT NULL,
    name        VARCHAR(100) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE CASCADE,
    UNIQUE KEY uk_category_lang (category_id, lang)
) ENGINE=InnoDB;

CREATE TABLE blog_posts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(255) NOT NULL,
    slug            VARCHAR(280) NOT NULL UNIQUE,
    excerpt         TEXT,
    thumbnail_image VARCHAR(500),
    content         LONGTEXT,
    featured_image  VARCHAR(500),
    author          VARCHAR(200) DEFAULT 'Vunotek',
    status          VARCHAR(20) NOT NULL DEFAULT 'draft',
    category_id     INT UNSIGNED DEFAULT NULL,
    published_at    TIMESTAMP NULL,
    deleted_at      TIMESTAMP NULL DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL,
    INDEX idx_slug (slug),
    INDEX idx_status (status, deleted_at, published_at),
    INDEX idx_author (author),
    FULLTEXT INDEX ft_search (title, excerpt, content)
) ENGINE=InnoDB;

CREATE TABLE blog_post_translations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blog_post_id INT UNSIGNED NOT NULL,
    lang        CHAR(2) NOT NULL,
    title       VARCHAR(255),
    excerpt     TEXT,
    content     LONGTEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (blog_post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    UNIQUE KEY uk_post_lang (blog_post_id, lang)
) ENGINE=InnoDB;
