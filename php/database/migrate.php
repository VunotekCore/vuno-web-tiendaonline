<?php
/**
 * Database migration script.
 * Creates missing tables (blog, translations) and inserts seed data.
 *
 * Run: php php/database/migrate.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function runMigration(): void
{
    $db = getDb();
    echo "=== Ram;Lop Database Migration ===\n\n";

    // 1. Translation tables
    echo "--- Translation tables ---\n";
    $translationTables = [
        "CREATE TABLE IF NOT EXISTS product_translations (
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
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS category_translations (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category_id VARCHAR(50) NOT NULL,
            lang        CHAR(2) NOT NULL,
            name        VARCHAR(100) NOT NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
            UNIQUE KEY uk_category_lang (category_id, lang)
        ) ENGINE=InnoDB",
    ];
    foreach ($translationTables as $sql) {
        try {
            $db->exec($sql);
            echo "  ✓ " . explode(' ', $sql)[5] . "\n";
        } catch (PDOException $e) {
            echo "  ✗ " . $e->getMessage() . "\n";
        }
    }

    // 2. Blog tables
    echo "\n--- Blog tables ---\n";
    $blogTables = [
        "CREATE TABLE IF NOT EXISTS blog_categories (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(200) NOT NULL,
            slug        VARCHAR(200) NOT NULL UNIQUE,
            description TEXT,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS blog_posts (
            id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title             VARCHAR(255) NOT NULL,
            slug              VARCHAR(255) NOT NULL UNIQUE,
            excerpt           TEXT,
            content           LONGTEXT NOT NULL,
            featured_image    VARCHAR(500),
            author            VARCHAR(200) DEFAULT 'Ram;Lop',
            status            ENUM('draft', 'published') DEFAULT 'draft',
            category_id       INT UNSIGNED DEFAULT NULL,
            published_at      TIMESTAMP NULL,
            created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at        TIMESTAMP NULL DEFAULT NULL,
            FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_published (published_at),
            INDEX idx_slug (slug)
        ) ENGINE=InnoDB",
    ];
    foreach ($blogTables as $sql) {
        try {
            $db->exec($sql);
            echo "  ✓ " . explode(' ', $sql)[5] . "\n";
        } catch (PDOException $e) {
            echo "  ✗ " . $e->getMessage() . "\n";
        }
    }

    // 2b. Blog translation tables (must be after blog tables)
    echo "\n--- Blog translation tables ---\n";
    $blogTransTables = [
        "CREATE TABLE IF NOT EXISTS blog_post_translations (
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
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS blog_category_translations (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category_id INT UNSIGNED NOT NULL,
            lang        CHAR(2) NOT NULL,
            name        VARCHAR(200) NOT NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE CASCADE,
            UNIQUE KEY uk_bcat_lang (category_id, lang)
        ) ENGINE=InnoDB",
    ];
    foreach ($blogTransTables as $sql) {
        try {
            $db->exec($sql);
            echo "  ✓ " . explode(' ', $sql)[5] . "\n";
        } catch (PDOException $e) {
            echo "  ✗ " . $e->getMessage() . "\n";
        }
    }

    // 3. Seed category translations (EN)
    echo "\n--- Category translations (EN) ---\n";
    if (count($db->query("SELECT id FROM category_translations LIMIT 1")->fetchAll()) === 0) {
        $catStmt = $db->prepare("SELECT id, name FROM categories");
        $catStmt->execute();
        $cats = $catStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $enNames = [
            'cat-heels' => 'Heels',
            'cat-sandals' => 'Sandals',
            'cat-mules' => 'Mules',
            'cat-boots' => 'Boots',
            'cat-flats' => 'Flats',
        ];
        $insert = $db->prepare("INSERT INTO category_translations (category_id, lang, name) VALUES (?, 'en', ?)");
        $count = 0;
        foreach ($enNames as $id => $en) {
            if (isset($cats[$id])) {
                $insert->execute([$id, $en]);
                $count++;
            }
        }
        echo "  ✓ {$count} category translations inserted\n";
    } else {
        echo "  - already seeded, skipping\n";
    }

    // 4. Seed product translations (EN)
    echo "\n--- Product translations (EN) ---\n";
    if (count($db->query("SELECT id FROM product_translations LIMIT 1")->fetchAll()) === 0) {
        $translations = [
            ['prod-001', 'Architectural Stiletto Noir', 'Redefining the classic silhouette, this stiletto features sharp architectural lines and a sculpted 90mm heel. Made in Italy from premium smooth calf leather, its minimalist design eliminates unnecessary seams for a purist finish.', '["100% Calf leather exterior","Leather lining and insole","Sculptural 90mm heel","Made in Italy","Clean with soft dry cloth"]'],
            ['prod-002', 'Nude Structural Sandal', 'A sandal that embraces the foot\'s shape with clean lines and sculptural aesthetics. Its nude leather construction blends with the skin for a lengthening and sophisticated visual effect.', '["100% Calf leather","Leather sole","Adjustable buckle in gold metal","Block heel 60mm","Handmade in Spain"]'],
            ['prod-003', 'Classic Pointed Pump', 'The ultimate pump for the power woman. Elongated silhouette with pointed toe and 85mm heel. Crafted from calf leather for a perfect fit and timeless elegance.', '["Italian calf leather","Lambskin lining","85mm stiletto heel","Leather sole with insignia","Made in Italy"]'],
            ['prod-004', 'Geometric Block Mule', 'An architectural statement mule. Its sculpted block heel and minimalist silhouette make it the centerpiece of any outfit. Crafted in high-resistance black leather.', '["Black calf leather","70mm geometric block heel","Engraved rubber sole","20mm concealed platform","Made in Portugal"]'],
            ['prod-005', 'Minimal Kitten Heel', 'Discreet elegance with a 50mm kitten heel that lengthens the silhouette without sacrificing comfort. Its clean design and pristine white leather make it a collection essential.', '["White calf leather","50mm kitten heel","Rounded toe","Natural leather lining","Made in Italy"]'],
            ['prod-006', 'Architectural Cage Sandal', 'A sculptural sandal that wraps the foot in fine calf leather straps. The geometric cage design evokes architectural frameworks while the stiletto heel anchors the composition.', '["Calf leather straps","Adjustable ankle buckle","90mm stiletto heel","Leather sole","Made in Italy"]'],
            ['prod-007', 'Sculptural Block Bootie', 'An architectural bootie with a sculptural block heel and clean silhouette. Crafted in structured calf leather, its minimalist aesthetic balances volume and precision.', '["Structured calf leather","Inner zip closure","Sculptural 60mm block heel","Leather lining","Made in Portugal"]'],
        ];
        $insert = $db->prepare("INSERT INTO product_translations (product_id, lang, name, description, details) VALUES (?, 'en', ?, ?, ?)");
        $count = 0;
        foreach ($translations as $t) {
            $insert->execute($t);
            $count++;
        }
        echo "  ✓ {$count} product translations inserted\n";
    } else {
        echo "  - already seeded, skipping\n";
    }

    // 5. Seed blog categories
    echo "\n--- Blog categories ---\n";
    if (count($db->query("SELECT id FROM blog_categories LIMIT 1")->fetchAll()) === 0) {
        $db->exec("INSERT INTO blog_categories (name, slug, description) VALUES
            ('Tendencias', 'tendencias', 'Últimas tendencias en calzado artesanal'),
            ('Cuidados', 'cuidados', 'Guías para el cuidado de tus zapatos'),
            ('Detrás del Diseño', 'detras-del-diseno', 'Historias del proceso creativo y artesanal')");
        echo "  ✓ 3 blog categories inserted\n";
    } else {
        echo "  - already seeded, skipping\n";
    }

    // 6. Seed sample customer
    echo "\n--- Sample customer ---\n";
    $cst = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    if ((int)$cst === 0) {
        $db->exec("INSERT INTO customers (email, name, phone, is_verified) VALUES
            ('maria.garcia@example.com', 'María García', '+52 55 1234 5678', TRUE)");
        echo "  ✓ 1 customer inserted\n";
    } else {
        echo "  - {$cst} customers exist, skipping\n";
    }

    // 7. Seed sample orders
    echo "\n--- Sample orders ---\n";
    $ords = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    if ($ords === 0) {
        // Order 1 — Stripe, paid
        $db->exec("INSERT INTO orders (order_number, customer_id, customer_email, customer_name, customer_phone,
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
             NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 3 DAY)");

        $db->exec("INSERT INTO order_items (order_id, product_id, variant_id, product_name, product_slug, product_image, product_price, product_sku, quantity, unit_price, subtotal, selected_color, selected_size) VALUES
            (1, 'prod-001', 1, 'Mule Arquitectónica', 'mule-arquitectonica', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 520.00, 'MULE-NOIR-36', 1, 520.00, 520.00, 'Noir', '36'),
            (1, 'prod-002', 11, 'Tacón Estructura', 'tacon-estructura', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 330.00, 'TACON-BLANC-36', 1, 330.00, 330.00, 'Blanc Cassé', '36')");

        $db->exec("INSERT INTO order_status_history (order_id, from_status_id, to_status_id, notes, created_at) VALUES
            (1, NULL, 1, 'Order created by customer', NOW() - INTERVAL 3 DAY),
            (1, 1, 2, 'Payment confirmed via Stripe', NOW() - INTERVAL 3 DAY)");

        $db->exec("INSERT INTO stock_movements (variant_id, quantity_change, stock_before, stock_after, reference_type, reference_id, notes, created_at) VALUES
            (1, -1, 10, 9, 'order', 'ORD-2026-001', 'Order item: Mule Arquitectónica Noir/36', NOW() - INTERVAL 3 DAY),
            (11, -1, 10, 9, 'order', 'ORD-2026-001', 'Order item: Tacón Estructura Blanc Cassé/36', NOW() - INTERVAL 3 DAY)");

        // Order 2 — Transfer, pending
        $db->exec("INSERT INTO orders (order_number, customer_id, customer_email, customer_name, customer_phone,
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
             NOW() - INTERVAL 1 DAY)");

        $db->exec("INSERT INTO order_items (order_id, product_id, variant_id, product_name, product_slug, product_image, product_price, product_sku, quantity, unit_price, subtotal, selected_color, selected_size) VALUES
            (2, 'prod-007', 45, 'Botín Bloque Escultural', 'botin-bloque-escultural', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 520.00, 'BOTIN-NOIR-36', 1, 520.00, 520.00, 'Noir', '36')");

        $db->exec("INSERT INTO order_status_history (order_id, from_status_id, to_status_id, notes, created_at) VALUES
            (2, NULL, 1, 'Order created by customer via bank transfer', NOW() - INTERVAL 1 DAY)");

        echo "  ✓ 2 sample orders inserted\n";
    } else {
        echo "  - {$ords} orders exist, skipping\n";
    }

    echo "\n=== Migration complete! ===\n";
}

try {
    runMigration();
} catch (PDOException $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
    exit(1);
} catch (Throwable $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
    exit(1);
}
