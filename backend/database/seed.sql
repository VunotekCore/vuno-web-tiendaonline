-- =============================================================================
-- seed.sql — Unified seed data for Vunotek e-commerce
-- Run AFTER schema.sql on a fresh database.
-- Idempotent: uses INSERT IGNORE throughout (safe to re-run).
--
-- Usage:
--   mysql -u <user> -p vuno_ramlop_ecommerce < backend/database/seed.sql
-- =============================================================================

BEGIN;

-- =============================================================================
-- 1. Admin & Security
-- =============================================================================

INSERT IGNORE INTO admin_roles (code, name) VALUES
('superadmin', 'Super Administrador'),
('editor', 'Editor'),
('viewer', 'Visor'),
('cashier', 'Vendedor / Cajero');

-- password: admin123
INSERT IGNORE INTO admin_users (email, password_hash, name, role_id, is_active) VALUES
('admin@vunotek.com', '$2y$10$oRW4DfzqhXvcXNbnln07AeKIKCnisf.kV84Jc0uNnLndrmk4SmAva', 'Administrador', 1, 1);

INSERT IGNORE INTO order_statuses (code, name, sort_order) VALUES
('pending', 'Pendiente', 1),
('paid', 'Pagado', 2),
('shipped', 'Enviado', 3),
('delivered', 'Entregado', 4),
('cancelled', 'Cancelado', 5);

INSERT IGNORE INTO payment_methods (code, name, sort_order) VALUES
('stripe', 'Tarjeta (Stripe)', 1),
('transfer', 'Transferencia Bancaria', 2),
('pos_cash', 'Efectivo (POS)', 3),
('pos_card', 'Tarjeta (POS)', 4),
('pos_transfer', 'Transferencia (POS)', 5);

INSERT IGNORE INTO payment_statuses (code, name) VALUES
('pending', 'Pendiente'),
('completed', 'Completado'),
('failed', 'Fallido'),
('refunded', 'Reembolsado');

-- =============================================================================
-- 2. Settings — camelCase keys (compatible with Vue admin)
-- =============================================================================

-- Store
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('store', 'name', 'Vuno-Ecomerce');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('store', 'slogan', 'Zapatos 100% artesanales');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('store', 'email', 'info@vunotek.com');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('store', 'description', 'Calzado artesanal para damas');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('store', 'logo', 'https://ik.imagekit.io/vijys5g3r/logos/VUNOTEK-ISOTIPO-N_CL9RXSDwUU.png');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('store', 'newsletter_discount_code', '');

-- Receipt
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('receipt', 'businessName', 'Vunotek');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('receipt', 'business_name', '');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('receipt', 'taxId', '2011210820002T');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('receipt', 'tax_id', '');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('receipt', 'address', 'Metrocentro modulo C1');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('receipt', 'city', 'managua');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('receipt', 'state', 'managua');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('receipt', 'zip', '');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('receipt', 'phone', '22332233');

-- ImageKit
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('imagekit', 'publicKey', '');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('imagekit', 'public_key', '');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('imagekit', 'privateKey', 'bDd4aFa1YV8rvjrAg1mrZ0a84FZ4TjLopy7thvamksEALmiSO1zWCRNIEYJ1UB+3atKJQ9Cj5tVv8psG7SSeEA==');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('imagekit', 'private_key', '');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('imagekit', 'urlEndpoint', 'https://ik.imagekit.io/vijys5g3r');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('imagekit', 'url_endpoint', '');

-- Stripe
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('stripe', 'enabled', '1');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('stripe', 'publishableKey', 'pk_test_51I1fkEDBxHMDyPiavccs30BcS7IFf5EvNYmzLFKHNQHE6aEpCBYi0rqYDIddJEvRPuHTQcn3QRc6qxVbNHbcTRT500XHpzU4Md');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('stripe', 'secretKey', 'oIrUZNnORpJYXL0ohW5cIUN6e7Xh7TQXCkquZlx7pv6DDDcN0oTd8rxQwo0cuVBG7nqHy6HEBfHGrEuoUdN7CiDXzoeosM8UEHhErYCA6k7AIRGYCwNIZlk/oWvwwcJq3OOQtuvHLTD6YzYVocWjxC2tj4OW8VZPmeWhHgagCjyLJRBM2HbJ');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('stripe', 'stripeSecretKey', '');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('stripe', 'webhookSecret', '');

-- Transfer
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('transfer', 'enabled', '1');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('transfer', 'banks', '[]');

-- SMTP
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('smtp', 'host', 'smtp.hostinger.com');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('smtp', 'port', '465');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('smtp', 'user', 'dflores@anicasolucionesintegrales.com');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('smtp', 'pass', 'uE8FWuk1/+qVcA+tlqy/t0NfXMF3WkrWLnn0yTJ7Ees7m9Wo50Bg');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('smtp', 'fromEmail', 'dflores@anicasolucionesintegrales.com');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('smtp', 'from_email', '');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('smtp', 'fromName', 'Online Store - WebSite');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('smtp', 'from_name', 'Vunotek');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('smtp', 'adminEmail', 'dflores2t@gmail.com');

-- SEO
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('seo', 'global_title', 'Vunotek | Calzado Artesanal para Damas');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('seo', 'global_description', 'Calzado artesanal para damas con diseño minimalista arquitectónico. Descubrí nuestra colección de heels, sandals, mules, boots y flats. Envíos a todo el país.');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('seo', 'og_default_image', '');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('seo', 'twitter_site', '');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('seo', 'facebook_page_id', '');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('seo', 'google_site_verification', '');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('seo', 'bing_site_verification', '');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('seo', 'ga_id', '');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('seo', 'robots_default', 'index,follow');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('seo', 'theme_color', '#1A1A1A');

-- Size Guide
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('size_guide', 'title_es', 'Guía de Talles');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('size_guide', 'title_en', 'Size Guide');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('size_guide', 'footer_es', 'Medí tu pie desde el talón hasta el dedo más largo. Si estás entre talles, recomendamos elegir el talle superior.');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('size_guide', 'footer_en', 'Measure your foot from heel to longest toe. If you are between sizes, we recommend choosing the larger size.');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('size_guide', 'rows', '[{"us":"5","eu":"35","uk":"2.5","cm":"22"},{"us":"5.5","eu":"35.5","uk":"3","cm":"22.5"},{"us":"6","eu":"36","uk":"3.5","cm":"23"},{"us":"6.5","eu":"36.5","uk":"4","cm":"23.5"},{"us":"7","eu":"37","uk":"4.5","cm":"24"},{"us":"7.5","eu":"37.5","uk":"5","cm":"24.5"},{"us":"8","eu":"38","uk":"5.5","cm":"25"},{"us":"8.5","eu":"38.5","uk":"6","cm":"25.5"},{"us":"9","eu":"39","uk":"6.5","cm":"26"},{"us":"9.5","eu":"39.5","uk":"7","cm":"26.5"},{"us":"10","eu":"40","uk":"7.5","cm":"27"},{"us":"10.5","eu":"40.5","uk":"8","cm":"27.5"},{"us":"11","eu":"41","uk":"8.5","cm":"28"}]');

-- Policies
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('policies', 'shipping_es', 'Ofrecemos envío estándar gratuito en todos los pedidos. Los envíos se procesan en un plazo de 1-2 días hábiles y la entrega estimada es de 5-10 días hábiles según tu ubicación.');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('policies', 'shipping_en', 'We offer free standard shipping on all orders. Orders are processed within 1-2 business days and estimated delivery is 5-10 business days depending on your location.');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('policies', 'returns_es', 'Aceptamos devoluciones dentro de los 14 días posteriores a la entrega para artículos en su condición original, sin usar y con todas las etiquetas originales. Los gastos de envío de devolución corren por cuenta del cliente.');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('policies', 'returns_en', 'Returns are accepted within 14 days of delivery for items in their original condition, unworn, and with all original tags. Return shipping costs are the responsibility of the customer.');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('policies', 'privacy_es', 'Tu información personal se utiliza únicamente para procesar pedidos y mejorar tu experiencia en Ram;Lop. No compartimos datos con terceros sin tu consentimiento explícito. Al realizar una compra, aceptas los términos de esta política de privacidad.');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('policies', 'privacy_en', 'Your personal information is used solely to process orders and improve your experience at Ram;Lop. We do not share data with third parties without your explicit consent. By making a purchase, you agree to the terms of this privacy policy.');

-- Currency
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('moneda', 'store_currency', 'USD');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('moneda', 'rates', '[{"code":"USD","name":"US Dollar","symbol":"$","rate":"1.00","decimals":"2","active":"1"},{"code":"MXN","name":"Mexican Peso","symbol":"$","rate":"20.00","decimals":"2","active":"0"},{"code":"EUR","name":"Euro","symbol":"€","rate":"0.92","decimals":"2","active":"0"},{"code":"COP","name":"Colombian Peso","symbol":"$","rate":"4000.00","decimals":"0","active":"0"},{"code":"PEN","name":"Peruvian Sol","symbol":"S/","rate":"3.70","decimals":"2","active":"0"},{"code":"ARS","name":"Argentine Peso","symbol":"$","rate":"1000.00","decimals":"0","active":"0"},{"code":"CLP","name":"Chilean Peso","symbol":"$","rate":"950.00","decimals":"0","active":"0"}]');

-- Shipping
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('shipping', 'enabled', '1');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('shipping', 'base_rate', '5');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('shipping', 'free_above', '2000');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('shipping', 'estimated_days', '5-7 dias habiles');

-- Tax
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('tax', 'rate', '15');

-- WhatsApp
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('whatsapp', 'enabled', '1');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('whatsapp', 'number', '50581018800');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('whatsapp', 'message', 'Hola, quiero consultar sobre...');

-- Brand Values (admin-configurable)
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('brand_values', 'enabled', '1');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('brand_values', 'image_url', 'https://ik.imagekit.io/vijys5g3r/products/producto1-2_OyfiJXrOk-.webp');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('brand_values', 'label_es', 'Nuestro Estilo Nuestra Moda');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('brand_values', 'label_en', 'testing style');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('brand_values', 'title_es', 'Placer en servir y ofrecer lo mejor');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('brand_values', 'title_en', 'Our style');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('brand_values', 'paragraph_es', 'estilos unicos , ');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('brand_values', 'paragraph_en', 'example text');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('brand_values', 'cta_es', 'Exploa nuestra collecion');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('brand_values', 'cta_en', 'button to action');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('brand_values', 'cta_link', '');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('brand_values', 'cta_category_slug', '');

-- =============================================================================
-- 3. Landing page settings
-- =============================================================================

INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('landing', 'hero', '{"label_es":"LA VIDRIERA","title_es":"Minimalismo Arquitectónico.","subtitle_es":"Descubre la elegancia estructural de la nueva temporada. Calzado diseñado no solo para usarse, sino para definir el espacio y la forma.","cta_es":"EXPLORAR COLECCIÓN","label_en":"THE STOREFRONT","title_en":"Architectural Minimalism.","subtitle_en":"Discover the new season\'s structural elegance. Footwear designed not to be worn, but to define space and form.","cta_en":"EXPLORE COLLECTION","enabled":true,"cta_link":"","cta_category_slug":""}');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('landing', 'new_arrivals', '{"label_es":"NUEVOS","label_en":"NEW","title_es":"Nuevos Lanzamientos","title_en":"New Arrivals","subtitle_es":"Los últimos estudios en forma y función.","subtitle_en":"The latest studies in form and function.","cta_es":"Ver Todo","cta_en":"View All","enabled":true}');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('landing', 'categories', '{"label_es":"VITRINES","label_en":"WINDOWS","title_es":"Navegar por Colección","title_en":"Browse by Collection","enabled":true}');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('landing', 'closing_cta', '{"label_es":"Vunotek","label_en":"vunotek","title_es":"El minimalismo arquitectónico en cada paso.","title_en":"Architectural minimalism in every step.","subtitle_es":"Descubrí la colección completa donde la estructura se encuentra con la elegancia.111","subtitle_en":"Discover the full collection where structure meets elegance.","cta_es":"EXPLORAR COLECCIÓN","cta_en":"EXPLORE COLLECTION","enabled":true}');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('landing', 'social', '{"title_es":"Seguinos en Redes","title_en":"Follow Us","enabled":true,"platforms":{"facebook":{"enabled":true,"url":"https:\\/\\/facebook.com","image_url":"https:\\/\\/encrypted-tbn0.gstatic.com\\/images?q=tbn:ANd9GcSm4H0t_j0RrcoMQuSPyf5UJvllConJHGumIw&s"},"instagram":{"enabled":true,"url":"https:\\/\\/instagram.com","image_url":"https:\\/\\/encrypted-tbn0.gstatic.com\\/images?q=tbn:ANd9GcRzrR3eCO2A7vkcHMDh5d5M-9Jij4YDBGGnug&s"},"tiktok":{"enabled":false,"url":"","image_url":"https:\\/\\/encrypted-tbn0.gstatic.com\\/images?q=tbn:ANd9GcRzrR3eCO2A7vkcHMDh5d5M-9Jij4YDBGGnug&s"},"linkedin":{"enabled":false,"url":"","image_url":"https:\\/\\/encrypted-tbn0.gstatic.com\\/images?q=tbn:ANd9GcRzrR3eCO2A7vkcHMDh5d5M-9Jij4YDBGGnug&s"},"youtube":{"enabled":true,"url":"https;\\/\\/youtube.com","image_url":"https:\\/\\/kinsta.com\\/wp-content\\/uploads\\/2021\\/07\\/how-to-create-a-youtube-channel.jpg"}},"images":[{"image_url":"","platform":"facebook"}]}');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('landing', 'newsletter', '{"title_es":"Boletin Informativo","subtitle_es":"Boletin Informativo","placeholder_es":"Boletin Informativo","cta_es":"Suscribirse","title_en":"Newsletter","subtitle_en":"Newsletter","placeholder_en":"Newsletter","cta_en":"Subscribe","enabled":true}');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('landing', 'testimonials', '{"enabled":true,"title_es":"Lo Que Dicen Nuestras Clientes","title_en":"What Our Clients Say","subtitle_es":"Historias reales de mujeres que caminan con Vunotek.","subtitle_en":"Real stories from women who walk with Vunotek.","items":[]}');
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('landing', 'blog', '{"enabled":true,"label_es":"DEL JOURNAL","label_en":"FROM THE JOURNAL","title_es":"Últimas Publicaciones","title_en":"Latest Posts","desc_es":"Explorá nuestras historias, tendencias y el arte detrás de cada diseño.","desc_en":"Explore our stories, trends, and the art behind every design.","view_all_es":"Ver Todo","view_all_en":"View All"}');

-- =============================================================================
-- 4. Categories
-- =============================================================================

INSERT IGNORE INTO categories (id, name, slug, description, sort_order, is_active) VALUES
('cat-heels', 'Heels', 'heels', 'Tacones y pumps', 1, TRUE),
('cat-sandals', 'Sandals', 'sandals', 'Sandalias', 2, TRUE),
('cat-mules', 'Mules', 'mules', 'Mules y slippers', 3, TRUE),
('cat-boots', 'Boots', 'boots', 'Botas y botines', 4, TRUE),
('cat-flats', 'Flats', 'flats', 'Calzado plano', 5, TRUE);

INSERT IGNORE INTO category_translations (category_id, lang, name) VALUES
('cat-heels', 'en', 'Heels'),
('cat-sandals', 'en', 'Sandals'),
('cat-mules', 'en', 'Mules'),
('cat-boots', 'en', 'Boots'),
('cat-flats', 'en', 'Flats');

-- =============================================================================
-- 5. Products
-- =============================================================================

INSERT IGNORE INTO `products` (`id`, `sku`, `name`, `slug`, `description`, `price`, `compare_at_price`, `cost_price`, `currency`, `size_prefix`, `weight_kg`, `length_cm`, `width_cm`, `height_cm`, `is_active`, `is_featured`, `low_stock_threshold`, `meta_title`, `meta_description`, `og_image_url`, `created_at`, `updated_at`, `deleted_at`) VALUES
('prod-001', 'SKU-STIL-001', 'Stiletto Noir Arquitectónico', 'stiletto-noir-arquitectónico', 'Redefiniendo la silueta clásica, este stiletto presenta líneas arquitectónicas afiladas y un tacón esculpido de 90mm. Fabricado en Italia con piel de becerro lisa de primera calidad, su diseño minimalista elimina cualquier costura innecesaria para un acabado purista.', 450.00, NULL, NULL, 'USD', 'US', NULL, NULL, NULL, NULL, 1, 1, 5, NULL, NULL, NULL, '2026-06-02 15:13:59', '2026-07-01 07:36:16', NULL),
('prod-002', 'SKU-SAND-001', 'Sandalia Estructural Nude', 'sandalia-estructural-nude', 'testUna sandalia que abraza la forma del pie con líneas depuradas y una estética escultórica. Su construcción en piel nude se funde con la piel para un efecto visual alargador y sofisticado.', 380.00, NULL, NULL, 'USD', 'US', NULL, NULL, NULL, NULL, 1, 1, 5, NULL, NULL, NULL, '2026-06-02 15:13:59', '2026-07-02 09:55:11', NULL),
('prod-003', 'SKU-PUMP-001', 'Pump Clásico Punta Fina', 'pump-clasico-punta-fina', 'El pump definitivo para la mujer de poder. Silueta alargada con punta fina y tacón de 85mm. Su construcción en piel de becerro ofrece un ajuste perfecto y una elegancia atemporal.', 420.00, NULL, NULL, 'USD', 'US', NULL, NULL, NULL, NULL, 1, 1, 5, NULL, NULL, NULL, '2026-06-02 15:13:59', '2026-07-02 09:55:23', NULL),
('prod-004', 'SKU-MULE-001', 'Mule Bloque Geométrico', 'mule-bloque-geometrico', 'Una mule de declaración arquitectónica. Su tacón bloque esculpido y su silueta minimalista la convierten en la pieza central de cualquier conjunto. Confeccionada en piel negra de alta resistencia.', 395.00, NULL, NULL, 'USD', 'US', NULL, NULL, NULL, NULL, 1, 1, 5, NULL, NULL, NULL, '2026-06-02 15:13:59', '2026-07-02 09:55:43', NULL),
('prod-005', 'SKU-KITTEN-001', 'Kitten Heel Minimal', 'kitten-heel-minimal', 'Elegancia discreta con un tacón kitten de 50mm que alarga la silueta sin sacrificar confort. Su diseño depurado y la piel blanca inmaculada la convierten en un básico de colección.', 350.00, NULL, NULL, 'USD', 'US', NULL, NULL, NULL, NULL, 1, 1, 5, NULL, NULL, NULL, '2026-06-02 15:13:59', '2026-07-02 09:55:55', NULL),
('prod-006', 'SKU-JAULA-001', 'Sandalia Jaula Arquitectónica', 'sandalia-jaula-arquitectonica', 'Una exploración del espacio negativo. Esta sandalia de jaula entrelaza tiras de piel negra en una estructura geométrica que envuelve el pie. Tacón escultural de 100mm para una silueta imponente.', 480.00, NULL, NULL, 'USD', 'US', NULL, NULL, NULL, NULL, 1, 1, 5, NULL, NULL, NULL, '2026-06-02 15:13:59', '2026-07-02 09:56:06', NULL),
('prod-007', 'SKU-BOTIN-001', 'Botín Bloque Escultural', 'botin-bloque-escultural', 'Un botín de bloque que desafía las convenciones. Tacón esculpido en ángulo y silueta recortada para un look vanguardista. La piel negra de alta resistencia y la cremallera trasera facilitan el calce.', 520.00, NULL, NULL, 'USD', 'US', NULL, NULL, NULL, NULL, 1, 1, 5, NULL, NULL, NULL, '2026-06-02 15:13:59', '2026-07-02 09:56:19', NULL);

INSERT IGNORE INTO `product_categories` (`product_id`, `category_id`) VALUES
('prod-001', 'cat-heels'),
('prod-002', 'cat-sandals'),
('prod-003', 'cat-heels'),
('prod-004', 'cat-mules'),
('prod-005', 'cat-heels'),
('prod-006', 'cat-sandals'),
('prod-007', 'cat-boots');

INSERT IGNORE INTO `product_details` (`id`, `product_id`, `detail_text`, `sort_order`) VALUES
(321, 'prod-001', '100% Piel de becerro exterior', 0),
(322, 'prod-001', 'Forro y suela interior de piel', 1),
(323, 'prod-001', '100% Piel de becerro exterior', 2),
(324, 'prod-001', 'Tacón escultural de 90mm', 3),
(325, 'prod-001', 'Forro y suela interior de piel', 4),
(326, 'prod-001', 'Hecho en Italia', 5),
(327, 'prod-001', 'Tacón escultural de 90mm', 6),
(328, 'prod-001', 'Limpiar con paño suave y seco', 7),
(329, 'prod-001', 'Hecho en Italia', 8),
(330, 'prod-001', 'Limpiar con paño suave y seco', 9),
(331, 'prod-002', 'test100% Piel de becerro', 0),
(332, 'prod-002', 'Suela de cuero', 1),
(333, 'prod-002', '100% Piel de becerro', 2),
(334, 'prod-002', 'Hebilla ajustable en metal dorado', 3),
(335, 'prod-002', 'Suela de cuero', 4),
(336, 'prod-002', 'Tacón bloque 60mm', 5),
(337, 'prod-002', 'Hebilla ajustable en metal dorado', 6),
(338, 'prod-002', 'Hecho a mano en España', 7),
(339, 'prod-002', 'Tacón bloque 60mm', 8),
(340, 'prod-002', 'Hecho a mano en España', 9),
(341, 'prod-003', 'Piel de becerro italiana', 0),
(342, 'prod-003', 'Forro de piel de cordero', 1),
(343, 'prod-003', 'Piel de becerro italiana', 2),
(344, 'prod-003', 'Tacón aguja 85mm', 3),
(345, 'prod-003', 'Forro de piel de cordero', 4),
(346, 'prod-003', 'Suela de cuero con insignia', 5),
(347, 'prod-003', 'Tacón aguja 85mm', 6),
(348, 'prod-003', 'Hecho en Italia', 7),
(349, 'prod-003', 'Suela de cuero con insignia', 8),
(350, 'prod-003', 'Hecho en Italia', 9),
(351, 'prod-004', 'Piel de becerro negra', 0),
(352, 'prod-004', 'Tacón bloque geométrico 70mm', 1),
(353, 'prod-004', 'Piel de becerro negra', 2),
(354, 'prod-004', 'Suela de caucho grabada', 3),
(355, 'prod-004', 'Tacón bloque geométrico 70mm', 4),
(356, 'prod-004', 'Plataforma oculta 20mm', 5),
(357, 'prod-004', 'Suela de caucho grabada', 6),
(358, 'prod-004', 'Hecho en Portugal', 7),
(359, 'prod-004', 'Plataforma oculta 20mm', 8),
(360, 'prod-004', 'Hecho en Portugal', 9),
(361, 'prod-005', 'Piel de becerro blanca', 0),
(362, 'prod-005', 'Tacón kitten 50mm', 1),
(363, 'prod-005', 'Piel de becerro blanca', 2),
(364, 'prod-005', 'Puntera redondeada', 3),
(365, 'prod-005', 'Tacón kitten 50mm', 4),
(366, 'prod-005', 'Forro de piel natural', 5),
(367, 'prod-005', 'Puntera redondeada', 6),
(368, 'prod-005', 'Hecho en Italia', 7),
(369, 'prod-005', 'Forro de piel natural', 8),
(370, 'prod-005', 'Hecho en Italia', 9),
(371, 'prod-006', 'Tiras de piel de becerro', 0),
(372, 'prod-006', 'Tacón escultural 100mm', 1),
(373, 'prod-006', 'Tiras de piel de becerro', 2),
(374, 'prod-006', 'Cierre de hebilla ajustable', 3),
(375, 'prod-006', 'Tacón escultural 100mm', 4),
(376, 'prod-006', 'Planta anatómica acolchada', 5),
(377, 'prod-006', 'Cierre de hebilla ajustable', 6),
(378, 'prod-006', 'Hecho a mano en España', 7),
(379, 'prod-006', 'Planta anatómica acolchada', 8),
(380, 'prod-006', 'Hecho a mano en España', 9),
(381, 'prod-007', 'Piel de becerro negra', 0),
(382, 'prod-007', 'Tacón bloque angular 80mm', 1),
(383, 'prod-007', 'Piel de becerro negra', 2),
(384, 'prod-007', 'Cremallera trasera dorada', 3),
(385, 'prod-007', 'Tacón bloque angular 80mm', 4),
(386, 'prod-007', 'Puntera almendrada', 5),
(387, 'prod-007', 'Cremallera trasera dorada', 6),
(388, 'prod-007', 'Hecho en Portugal', 7),
(389, 'prod-007', 'Puntera almendrada', 8),
(390, 'prod-007', 'Hecho en Portugal', 9);

INSERT IGNORE INTO `product_colors` (`id`, `product_id`, `name`, `hex`, `sort_order`) VALUES
(279, 'prod-001', 'Black', '#1A1A1A', 0),
(280, 'prod-001', 'Noir', '#1A1A1A', 1),
(281, 'prod-001', 'Sand', '#E6DED5', 2),
(282, 'prod-001', 'Red', '#DC2626', 3),
(283, 'prod-001', 'White', '#FFFFFF', 4),
(284, 'prod-001', 'Cafe', '#6B4423', 5),
(285, 'prod-002', 'Noir', '#1A1A1A', 0),
(286, 'prod-002', 'Nude', '#E6DED5', 1),
(287, 'prod-002', 'White', '#FFFFFF', 2),
(288, 'prod-002', 'Arcilla', '#C18C7E', 3),
(289, 'prod-002', 'eu', '#c91d1d', 4),
(290, 'prod-003', 'Arcilla', '#C18C7E', 0),
(291, 'prod-003', 'White', '#FFF', 1),
(292, 'prod-003', 'Noir', '#1A1A1A', 2),
(293, 'prod-003', 'Nude', '#E6DED5', 3),
(294, 'prod-003', 'Blanco', '#FFFFFF', 4),
(295, 'prod-004', 'Noir', '#1A1A1A', 0),
(296, 'prod-004', 'White', '#FFF', 1),
(297, 'prod-004', 'Nude', '#E6DED5', 2),
(298, 'prod-004', 'Arcilla', '#C18C7E', 3),
(299, 'prod-005', 'Blanco', '#FFFFFF', 0),
(300, 'prod-005', 'White', '#FFF', 1),
(301, 'prod-005', 'Noir', '#1A1A1A', 2),
(302, 'prod-005', 'Nude', '#E6DED5', 3),
(303, 'prod-005', 'Arcilla', '#C18C7E', 4),
(304, 'prod-006', 'Noir', '#1A1A1A', 0),
(305, 'prod-006', 'White', '#FFF', 1),
(306, 'prod-006', 'Nude', '#E6DED5', 2),
(307, 'prod-006', 'Arcilla', '#C18C7E', 3),
(308, 'prod-007', 'Noir', '#1A1A1A', 0),
(309, 'prod-007', 'White', '#FFF', 1),
(310, 'prod-007', 'Nude', '#E6DED5', 2),
(311, 'prod-007', 'Arcilla', '#C18C7E', 3);

INSERT IGNORE INTO `product_sizes` (`id`, `product_id`, `label`, `value`, `sort_order`) VALUES
(503, 'prod-001', 34, 34, 0),
(504, 'prod-001', 35, 35, 1),
(505, 'prod-001', 36, 36, 2),
(506, 'prod-001', 37, 37, 3),
(507, 'prod-001', 38, 38, 4),
(508, 'prod-001', 39, 39, 5),
(509, 'prod-001', 40, 40, 6),
(510, 'prod-002', 36, 36, 0),
(511, 'prod-002', 37, 37, 1),
(512, 'prod-002', 38, 38, 2),
(513, 'prod-002', 39, 39, 3),
(514, 'prod-002', 40, 40, 4),
(515, 'prod-002', 41, 41, 5),
(516, 'prod-003', 36, 36, 0),
(517, 'prod-003', 37, 37, 1),
(518, 'prod-003', 38, 38, 2),
(519, 'prod-003', 39, 39, 3),
(520, 'prod-003', 40, 40, 4),
(521, 'prod-003', 41, 41, 5),
(522, 'prod-004', 36, 36, 0),
(523, 'prod-004', 37, 37, 1),
(524, 'prod-004', 38, 38, 2),
(525, 'prod-004', 39, 39, 3),
(526, 'prod-004', 40, 40, 4),
(527, 'prod-004', 41, 41, 5),
(528, 'prod-005', 35, 35, 0),
(529, 'prod-005', 36, 36, 1),
(530, 'prod-005', 37, 37, 2),
(531, 'prod-005', 38, 38, 3),
(532, 'prod-005', 39, 39, 4),
(533, 'prod-005', 40, 40, 5),
(534, 'prod-005', 41, 41, 6),
(535, 'prod-006', 36, 36, 0),
(536, 'prod-006', 37, 37, 1),
(537, 'prod-006', 38, 38, 2),
(538, 'prod-006', 39, 39, 3),
(539, 'prod-006', 40, 40, 4),
(540, 'prod-006', 41, 41, 5),
(541, 'prod-007', 36, 36, 0),
(542, 'prod-007', 37, 37, 1),
(543, 'prod-007', 38, 38, 2),
(544, 'prod-007', 39, 39, 3),
(545, 'prod-007', 40, 40, 4),
(546, 'prod-007', 41, 41, 5);

INSERT IGNORE INTO `product_variants` (`id`, `product_id`, `color_id`, `size_id`, `sku`, `stock`, `price_override`, `is_active`, `created_at`, `updated_at`) VALUES
(1962, 'prod-001', 279, 503, NULL, 0, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1963, 'prod-001', 279, 504, NULL, 0, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1964, 'prod-001', 279, 505, NULL, 1, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1965, 'prod-001', 279, 506, NULL, 0, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1966, 'prod-001', 279, 507, NULL, 5, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1967, 'prod-001', 279, 508, NULL, 0, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1968, 'prod-001', 279, 509, NULL, 0, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1969, 'prod-001', 280, 503, NULL, 0, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1970, 'prod-001', 280, 504, NULL, 5, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1971, 'prod-001', 280, 505, NULL, 5, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1972, 'prod-001', 280, 506, NULL, 5, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1973, 'prod-001', 280, 507, NULL, 5, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1974, 'prod-001', 280, 508, NULL, 5, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1975, 'prod-001', 280, 509, NULL, 5, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1976, 'prod-001', 281, 503, NULL, 0, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1977, 'prod-001', 281, 504, NULL, 5, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1978, 'prod-001', 281, 505, NULL, 5, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1979, 'prod-001', 281, 506, NULL, 5, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1980, 'prod-001', 281, 507, NULL, 5, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1981, 'prod-001', 281, 508, NULL, 5, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1982, 'prod-001', 281, 509, NULL, 5, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1983, 'prod-001', 282, 503, NULL, 0, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1984, 'prod-001', 282, 504, NULL, 1, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1985, 'prod-001', 282, 505, NULL, 1, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1986, 'prod-001', 282, 506, NULL, 1, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1987, 'prod-001', 282, 507, NULL, 1, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1988, 'prod-001', 282, 508, NULL, 0, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1989, 'prod-001', 282, 509, NULL, 0, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1990, 'prod-001', 283, 503, NULL, 1, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1991, 'prod-001', 283, 504, NULL, 5, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1992, 'prod-001', 283, 505, NULL, 5, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1993, 'prod-001', 283, 506, NULL, 5, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1994, 'prod-001', 283, 507, NULL, 5, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1995, 'prod-001', 283, 508, NULL, 5, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1996, 'prod-001', 283, 509, NULL, 5, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1997, 'prod-001', 284, 503, NULL, 1, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1998, 'prod-001', 284, 504, NULL, 1, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(1999, 'prod-001', 284, 505, NULL, 1, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(2000, 'prod-001', 284, 506, NULL, 1, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(2001, 'prod-001', 284, 507, NULL, 1, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(2002, 'prod-001', 284, 508, NULL, 0, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(2003, 'prod-001', 284, 509, NULL, 0, NULL, 1, '2026-07-02 09:54:55', '2026-07-02 09:54:55'),
(2004, 'prod-002', 285, 510, NULL, 2, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2005, 'prod-002', 285, 511, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2006, 'prod-002', 285, 512, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2007, 'prod-002', 285, 513, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2008, 'prod-002', 285, 514, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2009, 'prod-002', 285, 515, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2010, 'prod-002', 286, 510, NULL, 7, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2011, 'prod-002', 286, 511, NULL, 5, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2012, 'prod-002', 286, 512, NULL, 5, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2013, 'prod-002', 286, 513, NULL, 5, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2014, 'prod-002', 286, 514, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2015, 'prod-002', 286, 515, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2016, 'prod-002', 287, 510, NULL, 1, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2017, 'prod-002', 287, 511, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2018, 'prod-002', 287, 512, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2019, 'prod-002', 287, 513, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2020, 'prod-002', 287, 514, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2021, 'prod-002', 287, 515, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2022, 'prod-002', 288, 510, NULL, 2, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2023, 'prod-002', 288, 511, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2024, 'prod-002', 288, 512, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2025, 'prod-002', 288, 513, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2026, 'prod-002', 288, 514, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2027, 'prod-002', 288, 515, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2028, 'prod-002', 289, 510, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2029, 'prod-002', 289, 511, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2030, 'prod-002', 289, 512, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2031, 'prod-002', 289, 513, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2032, 'prod-002', 289, 514, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2033, 'prod-002', 289, 515, NULL, 0, NULL, 1, '2026-07-02 09:55:11', '2026-07-02 09:55:11'),
(2034, 'prod-003', 290, 516, NULL, 5, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2035, 'prod-003', 290, 517, NULL, 5, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2036, 'prod-003', 290, 518, NULL, 7, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2037, 'prod-003', 290, 519, NULL, 5, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2038, 'prod-003', 290, 520, NULL, 5, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2039, 'prod-003', 290, 521, NULL, 0, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2040, 'prod-003', 291, 516, NULL, 0, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2041, 'prod-003', 291, 517, NULL, 0, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2042, 'prod-003', 291, 518, NULL, 2, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2043, 'prod-003', 291, 519, NULL, 0, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2044, 'prod-003', 291, 520, NULL, 0, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2045, 'prod-003', 291, 521, NULL, 0, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2046, 'prod-003', 292, 516, NULL, 5, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2047, 'prod-003', 292, 517, NULL, 5, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2048, 'prod-003', 292, 518, NULL, 6, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2049, 'prod-003', 292, 519, NULL, 5, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2050, 'prod-003', 292, 520, NULL, 5, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2051, 'prod-003', 292, 521, NULL, 0, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2052, 'prod-003', 293, 516, NULL, 0, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2053, 'prod-003', 293, 517, NULL, 0, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2054, 'prod-003', 293, 518, NULL, 1, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2055, 'prod-003', 293, 519, NULL, 0, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2056, 'prod-003', 293, 520, NULL, 0, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2057, 'prod-003', 293, 521, NULL, 0, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2058, 'prod-003', 294, 516, NULL, 5, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2059, 'prod-003', 294, 517, NULL, 5, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2060, 'prod-003', 294, 518, NULL, 5, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2061, 'prod-003', 294, 519, NULL, 5, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2062, 'prod-003', 294, 520, NULL, 5, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2063, 'prod-003', 294, 521, NULL, 0, NULL, 1, '2026-07-02 09:55:23', '2026-07-02 09:55:23'),
(2064, 'prod-004', 295, 522, NULL, 6, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2065, 'prod-004', 295, 523, NULL, 5, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2066, 'prod-004', 295, 524, NULL, 5, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2067, 'prod-004', 295, 525, NULL, 5, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2068, 'prod-004', 295, 526, NULL, 5, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2069, 'prod-004', 295, 527, NULL, 5, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2070, 'prod-004', 296, 522, NULL, 2, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2071, 'prod-004', 296, 523, NULL, 0, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2072, 'prod-004', 296, 524, NULL, 0, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2073, 'prod-004', 296, 525, NULL, 0, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2074, 'prod-004', 296, 526, NULL, 0, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2075, 'prod-004', 296, 527, NULL, 0, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2076, 'prod-004', 297, 522, NULL, 2, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2077, 'prod-004', 297, 523, NULL, 0, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2078, 'prod-004', 297, 524, NULL, 0, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2079, 'prod-004', 297, 525, NULL, 0, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2080, 'prod-004', 297, 526, NULL, 0, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2081, 'prod-004', 297, 527, NULL, 0, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2082, 'prod-004', 298, 522, NULL, 2, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2083, 'prod-004', 298, 523, NULL, 0, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2084, 'prod-004', 298, 524, NULL, 0, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2085, 'prod-004', 298, 525, NULL, 0, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2086, 'prod-004', 298, 526, NULL, 0, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2087, 'prod-004', 298, 527, NULL, 0, NULL, 1, '2026-07-02 09:55:43', '2026-07-02 09:55:43'),
(2088, 'prod-005', 299, 528, NULL, 5, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2089, 'prod-005', 299, 529, NULL, 5, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2090, 'prod-005', 299, 530, NULL, 5, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2091, 'prod-005', 299, 531, NULL, 5, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2092, 'prod-005', 299, 532, NULL, 5, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2093, 'prod-005', 299, 533, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2094, 'prod-005', 299, 534, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2095, 'prod-005', 300, 528, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2096, 'prod-005', 300, 529, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2097, 'prod-005', 300, 530, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2098, 'prod-005', 300, 531, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2099, 'prod-005', 300, 532, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2100, 'prod-005', 300, 533, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2101, 'prod-005', 300, 534, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2102, 'prod-005', 301, 528, NULL, 5, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2103, 'prod-005', 301, 529, NULL, 5, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2104, 'prod-005', 301, 530, NULL, 5, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2105, 'prod-005', 301, 531, NULL, 6, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2106, 'prod-005', 301, 532, NULL, 6, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2107, 'prod-005', 301, 533, NULL, 1, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2108, 'prod-005', 301, 534, NULL, 1, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2109, 'prod-005', 302, 528, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2110, 'prod-005', 302, 529, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2111, 'prod-005', 302, 530, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2112, 'prod-005', 302, 531, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2113, 'prod-005', 302, 532, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2114, 'prod-005', 302, 533, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2115, 'prod-005', 302, 534, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2116, 'prod-005', 303, 528, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2117, 'prod-005', 303, 529, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2118, 'prod-005', 303, 530, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2119, 'prod-005', 303, 531, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2120, 'prod-005', 303, 532, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2121, 'prod-005', 303, 533, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2122, 'prod-005', 303, 534, NULL, 0, NULL, 1, '2026-07-02 09:55:55', '2026-07-02 09:55:55'),
(2123, 'prod-006', 304, 535, NULL, 5, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2124, 'prod-006', 304, 536, NULL, 5, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2125, 'prod-006', 304, 537, NULL, 5, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2126, 'prod-006', 304, 538, NULL, 0, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2127, 'prod-006', 304, 539, NULL, 0, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2128, 'prod-006', 304, 540, NULL, 0, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2129, 'prod-006', 305, 535, NULL, 0, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2130, 'prod-006', 305, 536, NULL, 1, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2131, 'prod-006', 305, 537, NULL, 1, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2132, 'prod-006', 305, 538, NULL, 1, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2133, 'prod-006', 305, 539, NULL, 1, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2134, 'prod-006', 305, 540, NULL, 1, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2135, 'prod-006', 306, 535, NULL, 0, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2136, 'prod-006', 306, 536, NULL, 0, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2137, 'prod-006', 306, 537, NULL, 0, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2138, 'prod-006', 306, 538, NULL, 0, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2139, 'prod-006', 306, 539, NULL, 0, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2140, 'prod-006', 306, 540, NULL, 0, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2141, 'prod-006', 307, 535, NULL, 0, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2142, 'prod-006', 307, 536, NULL, 0, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2143, 'prod-006', 307, 537, NULL, 0, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2144, 'prod-006', 307, 538, NULL, 0, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2145, 'prod-006', 307, 539, NULL, 0, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2146, 'prod-006', 307, 540, NULL, 0, NULL, 1, '2026-07-02 09:56:06', '2026-07-02 09:56:06'),
(2147, 'prod-007', 308, 541, NULL, 5, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2148, 'prod-007', 308, 542, NULL, 5, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2149, 'prod-007', 308, 543, NULL, 5, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2150, 'prod-007', 308, 544, NULL, 5, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2151, 'prod-007', 308, 545, NULL, 5, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2152, 'prod-007', 308, 546, NULL, 0, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2153, 'prod-007', 309, 541, NULL, 0, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2154, 'prod-007', 309, 542, NULL, 0, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2155, 'prod-007', 309, 543, NULL, 0, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2156, 'prod-007', 309, 544, NULL, 0, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2157, 'prod-007', 309, 545, NULL, 0, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2158, 'prod-007', 309, 546, NULL, 0, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2159, 'prod-007', 310, 541, NULL, 0, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2160, 'prod-007', 310, 542, NULL, 0, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2161, 'prod-007', 310, 543, NULL, 0, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2162, 'prod-007', 310, 544, NULL, 0, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2163, 'prod-007', 310, 545, NULL, 0, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2164, 'prod-007', 310, 546, NULL, 0, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2165, 'prod-007', 311, 541, NULL, 1, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2166, 'prod-007', 311, 542, NULL, 1, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2167, 'prod-007', 311, 543, NULL, 1, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2168, 'prod-007', 311, 544, NULL, 1, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2169, 'prod-007', 311, 545, NULL, 1, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19'),
(2170, 'prod-007', 311, 546, NULL, 1, NULL, 1, '2026-07-02 09:56:19', '2026-07-02 09:56:19');

INSERT IGNORE INTO `product_images` (`id`, `product_id`, `color_id`, `url`, `file_id`, `alt_text`, `sort_order`, `is_primary`) VALUES
(281, 'prod-001', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto1-1_f4_5STjLdj.webp', '6a2740f05c7cd75eb818bb41', NULL, 0, 1),
(282, 'prod-001', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto1-2_OyfiJXrOk-.webp', '6a2740f15c7cd75eb818bdd4', NULL, 1, 0),
(283, 'prod-001', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto1-3_ib4xjUAxF.webp', '6a2740f35c7cd75eb818c126', NULL, 2, 0),
(284, 'prod-001', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto1-4_VWhTXkSYtg.webp', '6a2740f45c7cd75eb818c502', NULL, 3, 0),
(285, 'prod-002', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto2-1_OyBbKXd0Y.webp', '6a24c30f5c7cd75eb8cf845e', NULL, 0, 1),
(286, 'prod-002', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto2-2_Xp_KXOGzN.webp', '6a24c3115c7cd75eb8cf93df', NULL, 1, 0),
(287, 'prod-002', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto2-3_QvzCoZu5K.webp', '6a24c3135c7cd75eb8cfa14b', NULL, 2, 0),
(288, 'prod-002', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto2-4_h1z6rI2_s.webp', '6a24c3165c7cd75eb8cfb663', NULL, 3, 0),
(289, 'prod-002', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto2-5_1g-FFrjz7.webp', '6a24c3185c7cd75eb8cfcbab', NULL, 4, 0),
(290, 'prod-003', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto3-1_IS3HTVxuq.webp', '6a24c3305c7cd75eb8d0873c', NULL, 0, 1),
(291, 'prod-003', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto3-2_xnXoekhdQ.webp', '6a24c3325c7cd75eb8d096b6', NULL, 1, 0),
(292, 'prod-003', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto3-3_g5wxBbsNR.webp', '6a24c3345c7cd75eb8d0a062', NULL, 2, 0),
(293, 'prod-003', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto3-4_cyF2JCvxH.webp', '6a24c3365c7cd75eb8d0b045', NULL, 3, 0),
(294, 'prod-003', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto3-5_tRKD8d1WV.webp', '6a24c3385c7cd75eb8d0c909', NULL, 4, 0),
(295, 'prod-004', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto4-1_wV9YoEo3s.webp', '6a24c3675c7cd75eb8d2236d', NULL, 0, 1),
(296, 'prod-004', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto4-2_nKnBzW0qr.webp', '6a24c36a5c7cd75eb8d23004', NULL, 1, 0),
(297, 'prod-004', NULL, 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=800&q=80', NULL, NULL, 2, 0),
(298, 'prod-004', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto4-3_3UnKvfHMc.webp', '6a24c36d5c7cd75eb8d2446d', NULL, 3, 0),
(299, 'prod-005', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto5-1_yjooFH5Ye.webp', '6a24c37f5c7cd75eb8d2c352', NULL, 0, 1),
(300, 'prod-005', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto5-2_osaU6q1Yu.webp', '6a24c3815c7cd75eb8d2d642', NULL, 1, 0),
(301, 'prod-005', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto5-3_NY4Fj9BAV.webp', '6a24c3825c7cd75eb8d2e1b3', NULL, 2, 0),
(302, 'prod-005', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto5-4_M3jIZdSZd.webp', '6a24c3845c7cd75eb8d2eb32', NULL, 3, 0),
(303, 'prod-005', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto5-5_xiGKrVDyl.webp', '6a24c3855c7cd75eb8d2f532', NULL, 4, 0),
(304, 'prod-006', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto6-1_cOfchQ9Ll.webp', '6a24c3995c7cd75eb8d37d30', NULL, 0, 1),
(305, 'prod-006', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto6-2_eMsxfVlol.webp', '6a24c39a5c7cd75eb8d38cbc', NULL, 1, 0),
(306, 'prod-006', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto6-3_PRSBzefUm.webp', '6a24c39c5c7cd75eb8d39488', NULL, 2, 0),
(307, 'prod-006', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto6-4_RtC6iAHHi.webp', '6a24c39e5c7cd75eb8d39f79', NULL, 3, 0),
(308, 'prod-006', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto6-5_AslXCyogU.webp', '6a24c39f5c7cd75eb8d3ba58', NULL, 4, 0),
(309, 'prod-007', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto7-1_GSp7h3bAk.webp', '6a24c3b45c7cd75eb8d45867', NULL, 0, 1),
(310, 'prod-007', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto7-2_CrN7KyC1J.webp', '6a24c3b65c7cd75eb8d4644f', NULL, 1, 0),
(311, 'prod-007', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto7-3_kETKE48tt.webp', '6a24c3b85c7cd75eb8d46f79', NULL, 2, 0),
(312, 'prod-007', NULL, 'https://ik.imagekit.io/vijys5g3r/products/producto7-4_zZMc5gF85.webp', '6a24c3b95c7cd75eb8d47f50', NULL, 3, 0);

INSERT IGNORE INTO `product_translations` (`id`, `product_id`, `lang`, `name`, `description`, `details`, `created_at`, `updated_at`) VALUES
('1', 'prod-001', 'en', 'Architectural Stiletto Noir', 'Redefining the classic silhouette, this stiletto features sharp architectural lines and a sculpted 90mm heel. Made in Italy from premium smooth calf leather, its minimalist design eliminates unnecessary seams for a purist finish.', '[\"100% Calf leather exterior\",\"Leather lining and insole\",\"Sculptural 90mm heel\",\"Made in Italy\",\"Clean with soft dry cloth\"]', '2026-06-02 21:33:00', '2026-06-02 21:33:00'),
('2', 'prod-002', 'en', 'Nude Structural Sandal', 'A sandal that embraces the foot\'s shape with clean lines and sculptural aesthetics. Its nude leather construction blends with the skin for a lengthening and sophisticated visual effect.', '[\"100% Calf leather\",\"Leather sole\",\"Adjustable buckle in gold metal\",\"Block heel 60mm\",\"Handmade in Spain\"]', '2026-06-02 21:33:00', '2026-06-02 21:33:00'),
('3', 'prod-003', 'en', 'Classic Pointed Pump', 'The ultimate pump for the power woman. Elongated silhouette with pointed toe and 85mm heel. Crafted from calf leather for a perfect fit and timeless elegance.', '[\"Italian calf leather\",\"Lambskin lining\",\"85mm stiletto heel\",\"Leather sole with insignia\",\"Made in Italy\"]', '2026-06-02 21:33:00', '2026-06-02 21:33:00'),
('4', 'prod-004', 'en', 'Geometric Block Mule', 'An architectural statement mule. Its sculpted block heel and minimalist silhouette make it the centerpiece of any outfit. Crafted in high-resistance black leather.', '[\"Black calf leather\",\"70mm geometric block heel\",\"Engraved rubber sole\",\"20mm concealed platform\",\"Made in Portugal\"]', '2026-06-02 21:33:00', '2026-06-02 21:33:00'),
('5', 'prod-005', 'en', 'Minimal Kitten Heel', 'Discreet elegance with a 50mm kitten heel that lengthens the silhouette without sacrificing comfort. Its clean design and pristine white leather make it a collection essential.', '[\"White calf leather\",\"50mm kitten heel\",\"Rounded toe\",\"Natural leather lining\",\"Made in Italy\"]', '2026-06-02 21:33:00', '2026-06-02 21:33:00'),
('6', 'prod-006', 'en', 'Architectural Cage Sandal', 'A sculptural sandal that wraps the foot in fine calf leather straps. The geometric cage design evokes architectural frameworks while the stiletto heel anchors the composition.', '[\"Calf leather straps\",\"Adjustable ankle buckle\",\"90mm stiletto heel\",\"Leather sole\",\"Made in Italy\"]', '2026-06-02 21:33:00', '2026-06-02 21:33:00'),
('7', 'prod-007', 'en', 'Sculptural Block Bootie', 'An architectural bootie with a sculptural block heel and clean silhouette. Crafted in structured calf leather, its minimalist aesthetic balances volume and precision.', '[\"Structured calf leather\",\"Inner zip closure\",\"Sculptural 60mm block heel\",\"Leather lining\",\"Made in Portugal\"]', '2026-06-02 21:33:00', '2026-06-02 21:33:00');

INSERT IGNORE INTO product_reviews (id, product_id, customer_id, order_id, reviewer_name, reviewer_email, rating, title, comment, is_approved, created_at) VALUES
(2, 'prod-001', NULL, NULL, 'kie', 'dflores2t@gmail.com', 4, '', 'producto excelente', 1, '2026-06-10 11:28:21');

-- =============================================================================
-- 6. Size Guide Rows
-- =============================================================================

INSERT IGNORE INTO size_guide_rows (us_size, eu_size, uk_size, cm_size, sort_order) VALUES
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

-- =============================================================================
-- 7. Bank Accounts
-- =============================================================================

INSERT IGNORE INTO bank_accounts (bank_name, account_holder, account_number, account_type, routing_number, instructions, is_active, sort_order) VALUES
('Banco Nacional', 'Vunotek S.A.S.', '1234567890', 'Corriente', 'BNC-001', 'Depósito en ventanilla o transferencia electrónica', TRUE, 1),
('Banco del Estado', 'Vunotek S.A.S.', '0987654321', 'Ahorros', 'BDE-001', 'Transferencia inmediata desde cualquier banco nacional', TRUE, 2);

-- =============================================================================
-- 8. Currencies
-- =============================================================================

INSERT IGNORE INTO currencies (code, name, symbol, exchange_rate, decimal_places, is_active, sort_order) VALUES
('USD', 'US Dollar',          '$',    1.000000, 2, TRUE,  1),
('MXN', 'Peso Mexicano',      'Mex$', 20.000000, 2, TRUE,  2),
('GTQ', 'Quetzal Guatemalteco', 'Q', 7.800000,  2, TRUE,  3),
('HNL', 'Lempira Hondureño',  'L',    24.700000, 2, TRUE,  4),
('NIO', 'Córdoba Nicaragüense', 'C$', 36.500000, 2, TRUE, 5),
('CRC', 'Colón Costarricense', '₡',   525.000000, 2, TRUE, 6),
('COP', 'Peso Colombiano',    '$',    4000.000000, 2, TRUE, 7);

INSERT IGNORE INTO settings (section, `key`, `value`) VALUES ('currency', 'code', 'USD');

-- =============================================================================
-- 9. Blog
-- =============================================================================

INSERT IGNORE INTO blog_categories (id, name, slug, description) VALUES
(1, 'Tendencias', 'tendencias', 'Últimas tendencias en calzado artesanal'),
(2, 'Cuidados', 'cuidados', 'Guías para el cuidado de tus zapatos'),
(3, 'Detrás del Diseño', 'detras-del-diseno', 'Historias del proceso creativo y artesanal');

INSERT IGNORE INTO blog_posts (id, title, slug, excerpt, thumbnail_image, content, featured_image, author, category_id, status, published_at) VALUES
(1,
 '5 Tendencias de Moda que Definirán Esta Temporada',
 'tendencias-moda-temporada',
 'Descubre las cinco tendencias imprescindibles que están marcando el rumbo de la moda: desde el calzado escultural hasta los accesorios que roban miradas.',
 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=600&q=80',
 '<h2>El Regreso del Minimalismo Arquitectónico</h2>
<p>Esta temporada, la moda abraza la pureza de las líneas limpias y las siluetas depuradas. Los diseñadores apuestan por formas escultóricas que recuerdan a la arquitectura brutalista, con bloques geométricos y tacones que desafían la gravedad. El calzado artesanal se convierte en el centro de atención, con piezas que funcionan casi como instalaciones de arte portable.</p>
<p>Los materiales nobles como el cuero vacuno, la piel de becerro y los acabados satinados dominan las colecciones. Los colores tierra, el negro monólito y los tonos nude se consolidan como la paleta esencial del armario consciente.</p>
<h2>La Cartera Estructurada</h2>
<p>Olvídate de los bolsos flácidos. Esta temporada la cartera estructurada es la protagonista indiscutible. Piezas de líneas rectas, asas superiores y cierres minimalistas que complementan cualquier look con un toque de sofisticación arquitectónica.</p>
<h2>Tejidos Artesanales con Consciencia</h2>
<p>La artesanía textil vive un renacimiento. Tejidos como el crochet, el macramé y los bordados hechos a mano se integran en prendas y accesorios con una estética contemporánea.</p>
<h2>La Silueta Oversized Reimaginada</h2>
<p>El volumen se reinventa. La clave está en contrastar volúmenes: una parte superior holgada combinada con una falda lápiz ajustada.</p>
<h2>Accesorios como Punto Focal</h2>
<p>La inversión en piezas de calidad, hechas a mano y con materiales nobles, no solo es una elección estética sino también ética.</p>',
 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=1200&q=80',
 'María Vunotek', 1, 'published', NOW() - INTERVAL 7 DAY),
(2,
 'Guía Completa para el Cuidado de Tus Zapatos Artesanales',
 'guia-cuidado-zapatos-artesanales',
 'Aprende a preservar la belleza y durabilidad de tus zapatos hechos a mano con nuestra guía experta de cuidados, limpieza y almacenamiento.',
 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=600&q=80',
 '<h2>Por Qué el Cuidado es Esencial</h2>
<p>Un par de zapatos artesanales es una inversión en calidad, diseño y sostenibilidad. A diferencia de la producción industrial, cada par hecho a mano pasa por decenas de horas de trabajo minucioso: desde el corte del cuero hasta el cosido de la suela. Cuidarlos adecuadamente no solo prolonga su vida útil, sino que honra el trabajo del artesano que los creó.</p>
<h2>Limpieza Según el Material</h2>
<h3>Cuero Liso</h3>
<p>Retira el polvo superficial con un cepillo de cerdas suaves. Prepara una solución de agua tibia con jabón neutro. Humedece un paño suave y pásalo sobre la superficie. No sumerjas los zapatos en agua. Seca al aire libre, lejos de fuentes de calor directo.</p>
<h3>Cuero Grabado o Texturizado</h3>
<p>Usa un cepillo de cerdas naturales para llegar a los rincones del grabado. Un paño ligeramente humedecido con agua y jabón neutro es suficiente.</p>
<h2>Hidratación y Nutrición</h2>
<p>Aplica un acondicionador de cuero de calidad cada 2-3 meses. Los productos con cera de abeja, lanolina o aceites naturales son excelentes opciones.</p>
<h2>Almacenamiento Correcto</h2>
<p>Usa hormas de madera de cedro. Guarda los zapatos en bolsas de tela transpirable. Mantenlos en un lugar fresco y seco. Alterna el uso de tus zapatos.</p>',
 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=1200&q=80',
 'Vunotek', 2, 'published', NOW() - INTERVAL 4 DAY),
(3,
 'El Proceso Artesanal: De la Inspiración al Calzado',
 'proceso-artesanal-inspiracion-calzado',
 'Te llevamos detrás del taller para mostrarte cómo nace cada diseño: desde el boceto inicial hasta el último punto de costura en nuestras piezas artesanales.',
 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=600&q=80',
 '<h2>La Chispa Creativa</h2>
<p>Todo comienza con una imagen mental. Para nuestros diseñadores, la inspiración puede surgir de cualquier lugar: la curva de un edificio brutalista, el pliegue de una tela al caer, la textura de una piedra erosionada por el tiempo.</p>
<h2>La Selección del Material</h2>
<p>Trabajamos con curtidurías históricas de Italia, España y Portugal. Cada piel se selecciona a mano, buscando la textura, el grosor y la elasticidad perfectos para cada diseño.</p>
<h2>El Corte: Precisión Milimétrica</h2>
<p>Cada pieza se corta individualmente, maximizando el uso del material y respetando la dirección natural de la fibra del cuero.</p>
<h2>El Armado: Donde el Calzado Cobra Vida</h2>
<p>Sobre la horma se montan las piezas cortadas. El cuero se estira, se moldea, se clava y se pega con precisión quirúrgica.</p>
<h2>La Suela y el Acabado</h2>
<p>Cada suela se cose a mano al cuerpo del zapato con hilo encerado, una técnica que garantiza durabilidad.</p>',
 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=1200&q=80',
 'Carlos Mendoza', 3, 'published', NOW() - INTERVAL 1 DAY),
(4,
 'Cómo Combinar tus Zapatos con los Accesorios Perfectos',
 'como-combinar-zapatos-accesorios',
 'Dominar el arte de coordinar calzado, carteras y accesorios puede transformar cualquier look. Te compartimos las claves para crear combinaciones armónicas y sofisticadas.',
 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=600&q=80',
 '<h2>La Regla de Oro: Menos es Más</h2>
<p>La clave está en elegir un punto focal y construir alrededor de él. Si tus zapatos son la pieza protagonista, el resto de accesorios deben acompañar sin competir.</p>
<h2>Zapato + Cartera: La Combinación Clásica</h2>
<p>Tono sobre Tono: la opción más segura y elegante. Combina zapatos y cartera en tonos cercanos de la misma familia cromática. Contraste Controlado: si prefieres un look más dinámico, opta por el contraste.</p>
<h2>El Papel de los Metales</h2>
<p>Elige una temperatura de metal y mantenla. Oro con oro, plata con plata. La coherencia crea un hilo visual que unifica todo el conjunto.</p>
<h2>Accesorios que Suman, No que Restan</h2>
<p>El cinturón debe coordinarse con los zapatos. Un pañuelo de seda puede ser el detalle que eleve todo el look. Menos es más en joyería.</p>',
 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=1200&q=80',
 'Vunotek', 1, 'published', NOW());

-- =============================================================================
-- 10. Coupons
-- =============================================================================

INSERT IGNORE INTO coupons (code, description, discount_type, discount_value, min_order_amount, max_uses, max_uses_per_customer, is_active, starts_at, expires_at) VALUES
('WELCOME10', '10% de descuento en tu primera compra', 'percentage', 10, 0, 100, 1, TRUE, NOW(), NOW() + INTERVAL 90 DAY),
('VIP50', 'C$50 de descuento en compras mayores a C$500', 'fixed', 50, 500, 50, 1, TRUE, NOW(), NOW() + INTERVAL 365 DAY);

-- =============================================================================
-- 11. Sample Customer & Orders
-- =============================================================================

INSERT IGNORE INTO customers (id, email, name, phone, is_verified) VALUES
(1, 'maria.garcia@example.com', 'María García', '+52 55 1234 5678', TRUE);

INSERT IGNORE INTO customers (email, name, password_hash, is_verified, notes) VALUES
('pos@vunotek.com', 'Cliente Mostrador', NULL, TRUE, 'Cliente predeterminado para ventas de mostrador (POS)');

INSERT IGNORE INTO orders (id, order_number, customer_id, customer_email, customer_name, customer_phone,
    shipping_name, shipping_line1, shipping_city, shipping_state, shipping_zip, shipping_country,
    billing_name, billing_line1, billing_city, billing_state, billing_zip, billing_country,
    subtotal, shipping_total, tax_total, discount_total, total, currency,
    status_id, payment_method_id, payment_status_id, stripe_payment_intent_id,
    paid_at, created_at)
VALUES
(1, 'ORD-2026-001', 1, 'maria.garcia@example.com', 'María García', '+52 55 1234 5678',
 'María García', 'Av. Reforma 123, Col. Juárez', 'Ciudad de México', 'CDMX', '06600', 'MX',
 'María García', 'Av. Reforma 123, Col. Juárez', 'Ciudad de México', 'CDMX', '06600', 'MX',
 850.00, 50.00, 0, 0, 900.00, 'USD',
 2, 1, 2, 'pi_stripe_sample_001',
 NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 3 DAY);

INSERT IGNORE INTO order_items (order_id, product_id, variant_id, product_name, product_slug, product_image, product_price, product_sku, quantity, unit_price, subtotal, selected_color, selected_size) VALUES
(1, 'prod-001', 1971, 'Stiletto Noir Arquitectónico', 'stiletto-noir-arquitectonico', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 450.00, 'STIL-NOIR-36', 1, 450.00, 450.00, 'Noir', '36'),
(1, 'prod-004', 2064, 'Mule Bloque Geométrico', 'mule-bloque-geometrico', 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=800&q=80', 395.00, 'MULE-NOIR-36', 1, 395.00, 395.00, 'Noir', '36');

INSERT IGNORE INTO order_status_history (order_id, from_status_id, to_status_id, notes, created_at) VALUES
(1, NULL, 1, 'Order created by customer', NOW() - INTERVAL 3 DAY),
(1, 1, 2, 'Payment confirmed via Stripe', NOW() - INTERVAL 3 DAY);

INSERT IGNORE INTO orders (id, order_number, customer_id, customer_email, customer_name, customer_phone,
    shipping_name, shipping_line1, shipping_city, shipping_state, shipping_zip, shipping_country,
    billing_name, billing_line1, billing_city, billing_state, billing_zip, billing_country,
    subtotal, shipping_total, tax_total, discount_total, total, currency,
    status_id, payment_method_id, payment_status_id, notes, created_at)
VALUES
(2, 'ORD-2026-002', 1, 'maria.garcia@example.com', 'María García', '+52 55 1234 5678',
 'María García', 'Av. Reforma 123, Col. Juárez', 'Ciudad de México', 'CDMX', '06600', 'MX',
 'María García', 'Av. Reforma 123, Col. Juárez', 'Ciudad de México', 'CDMX', '06600', 'MX',
 520.00, 50.00, 0, 0, 570.00, 'USD',
 1, 2, 1, 'Cliente pagará por transferencia bancaria',
 NOW() - INTERVAL 1 DAY);

INSERT IGNORE INTO order_items (order_id, product_id, variant_id, product_name, product_slug, product_image, product_price, product_sku, quantity, unit_price, subtotal, selected_color, selected_size) VALUES
(2, 'prod-007', 2147, 'Botín Bloque Escultural', 'botin-bloque-escultural', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 520.00, 'BOTIN-NOIR-36', 1, 520.00, 520.00, 'Noir', '36');

INSERT IGNORE INTO order_status_history (order_id, from_status_id, to_status_id, notes, created_at) VALUES
(2, NULL, 1, 'Order created by customer via bank transfer', NOW() - INTERVAL 1 DAY);

-- =============================================================================
-- 12. Email Templates
-- =============================================================================

INSERT IGNORE INTO email_templates (code, name, subject, body_html, is_active) VALUES
('new_order_notification', 'New order notification', 'New Order #{{order_id}} — {{store_name}} Admin',
'<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>New Order #{{order_id}}</title></head><body style="margin:0;padding:0;background-color:#f5f3f0;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif"><div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden">{{preheader}}</div><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f3f0"><tr><td align="center" style="padding:40px 16px"><table class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%"><tr><td align="center" style="padding:0 0 32px 0"><table cellpadding="0" cellspacing="0" border="0"><tr><td style="background-color:#1a1a1a;padding:12px 32px;border-radius:2px">{{store_logo_block}}</td></tr></table></td></tr><tr><td style="background-color:#ffffff;padding:32px;border-radius:2px;box-shadow:0 1px 2px rgba(0,0,0,0.06)"><h1 style="font-family:''Playfair Display'',Georgia,serif;font-size:24px;color:#1a1a1a;margin:0 0 16px;font-weight:400">New Order #{{order_id}}</h1><table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px"><tr><td style="padding:4px 0;font-size:14px;color:#6b6b6b">Customer</td><td style="padding:4px 0;font-size:14px;color:#1a1a1a">{{customer_name}} ({{customer_email}})</td></tr><tr><td style="padding:4px 0;font-size:14px;color:#6b6b6b">Total</td><td style="padding:4px 0;font-size:14px;color:#1a1a1a;font-weight:600">{{currency_symbol}}{{order_total}}</td></tr></table>{{order_items_html}}<p style="margin:16px 0 0;font-size:13px;color:#9A9A9A">View in admin: <a href="{{admin_order_url}}" style="color:#1a1a1a">{{order_id}}</a></p></td></tr></table></td></tr></table></body></html>', TRUE),

('order_confirmation', 'Order confirmation', '{{status_subject}} — {{store_name}}',
'<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Order Confirmation #{{order_id}}</title></head><body style="margin:0;padding:0;background-color:#f5f3f0;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif"><div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden">{{preheader}}</div><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f3f0"><tr><td align="center" style="padding:40px 16px"><table class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%"><tr><td align="center" style="padding:0 0 32px 0">{{store_logo_block}}</td></tr><tr><td style="background-color:#ffffff;padding:32px;border-radius:2px;box-shadow:0 1px 2px rgba(0,0,0,0.06)"><h1 style="font-family:''Playfair Display'',Georgia,serif;font-size:24px;color:#1a1a1a;margin:0 0 8px;font-weight:400">Thank you, {{customer_name}}!</h1><p style="margin:0 0 24px;color:#6b6b6b">{{status_message}}</p>{{order_items_html}}{{transfer_details_block}}<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:4px 0;font-size:14px;color:#6b6b6b">Subtotal</td><td style="padding:4px 0;font-size:14px;color:#1a1a1a;text-align:right">{{currency_symbol}}{{order_subtotal}}</td></tr>{{coupon_discount_row}}<tr><td style="padding:4px 0;font-size:14px;color:#6b6b6b">Shipping</td><td style="padding:4px 0;font-size:14px;color:#1a1a1a;text-align:right">{{order_shipping}}</td></tr><tr><td style="padding:4px 0;font-size:14px;color:#6b6b6b">IVA</td><td style="padding:4px 0;font-size:14px;color:#1a1a1a;text-align:right">{{order_tax}}</td></tr><tr><td style="padding:12px 0 4px;border-top:2px solid #1a1a1a;font-size:16px;color:#1a1a1a;font-weight:600">Total</td><td style="padding:12px 0 4px;border-top:2px solid #1a1a1a;font-size:16px;color:#1a1a1a;font-weight:600;text-align:right">{{currency_symbol}}{{order_total}}</td></tr></table></td></tr></table></td></tr></table></body></html>', TRUE),

('contact_notification', 'Contact notification', '{{subject}} — {{name}} &lt;{{email}}&gt;',
'<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nuevo mensaje de contacto</title></head><body style="margin:0;padding:0;background-color:#f5f3f0;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f3f0"><tr><td align="center" style="padding:40px 16px"><table class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%"><tr><td align="center" style="padding:0 0 24px 0">{{store_logo_block}}</td></tr><tr><td style="background-color:#ffffff;border-radius:2px;padding:32px"><h1 style="margin:0 0 4px;font-family:''Playfair Display'',Georgia,serif;font-size:22px;font-weight:400;color:#1a1a1a">Nuevo mensaje de contacto</h1><p style="margin:0 0 20px;font-size:13px;color:#9a9a9a">Recibido desde el formulario de contacto de {{store_name}}</p><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:10px 0;border-bottom:1px solid #f0eeeb"><strong>Nombre:</strong> {{name}}</td></tr><tr><td style="padding:10px 0;border-bottom:1px solid #f0eeeb"><strong>Email:</strong> {{email}}</td></tr><tr><td style="padding:10px 0;border-bottom:1px solid #f0eeeb"><strong>Teléfono:</strong> {{phone}}</td></tr><tr><td style="padding:10px 0;border-bottom:1px solid #f0eeeb"><strong>Asunto:</strong> {{subject}}</td></tr></table><h3 style="margin:24px 0 8px;font-size:13px;color:#9a9a9a;text-transform:uppercase;letter-spacing:0.08em">Mensaje</h3><div style="font-size:14px;color:#1a1a1a;line-height:1.6;background-color:#faf9f8;padding:16px;border:1px solid #f0eeeb;white-space:pre-wrap">{{message}}</div></td></tr></table></td></tr></table></body></html>', TRUE),

('welcome', 'Welcome', 'Welcome to {{store_name}} — {{customer_name}}',
'<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Welcome to {{store_name}}</title></head><body style="margin:0;padding:0;background-color:#f5f3f0;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif"><div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden">{{preheader}}</div><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f3f0"><tr><td align="center" style="padding:40px 16px"><table class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%"><tr><td align="center" style="padding:0 0 32px 0">{{store_logo_block}}</td></tr><tr><td style="background-color:#ffffff;padding:32px;border-radius:2px;box-shadow:0 1px 2px rgba(0,0,0,0.06)"><h1 style="font-family:''Playfair Display'',Georgia,serif;font-size:24px;color:#1a1a1a;margin:0 0 16px;font-weight:400">Welcome, {{customer_name}}.</h1><p style="margin:0 0 16px">Thank you for creating an account at {{store_name}}.</p><table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px"><tr><td style="background-color:#1a1a1a;border-radius:2px;text-align:center"><a href="{{store_url}}" style="display:inline-block;padding:14px 36px;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif;font-size:14px;font-weight:600;letter-spacing:0.08em;color:#f5f3f0;text-decoration:none;text-transform:uppercase">Explore the Collection</a></td></tr></table></td></tr></table></td></tr></table></body></html>', TRUE),

('newsletter_welcome', 'Newsletter welcome', 'Welcome to {{store_name}} — {{store_slogan}}',
'<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Welcome to {{store_name}}</title></head><body style="margin:0;padding:0;background-color:#f5f3f0;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif"><div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden">{{preheader}}</div><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f3f0"><tr><td align="center" style="padding:40px 16px"><table class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%"><tr><td align="center" style="padding:0 0 32px 0">{{store_logo_block}}</td></tr><tr><td style="background-color:#ffffff;padding:32px;border-radius:2px;box-shadow:0 1px 2px rgba(0,0,0,0.06)"><h1 style="font-family:''Playfair Display'',Georgia,serif;font-size:24px;color:#1a1a1a;margin:0 0 8px;font-weight:400">Welcome to {{store_name}}, {{subscriber_name}}!</h1><p style="margin:0 0 24px;color:#6b6b6b">Thanks for joining our community.</p>{{discount_block}}<p style="margin:0 0 24px;color:#6b6b6b">Follow us on social media for daily inspiration:</p>{{social_links_block}}</td></tr></table></td></tr></table></body></html>', TRUE),

('newsletter_campaign', 'Newsletter campaign', '{{subject}}',
'<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{subject}}</title></head><body style="margin:0;padding:0;background-color:#f5f3f0;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif"><div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden">{{preheader}}</div><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f3f0"><tr><td align="center" style="padding:40px 16px"><table class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%"><tr><td align="center" style="padding:0 0 32px 0">{{store_logo_block}}</td></tr><tr><td style="background-color:#ffffff;padding:32px;border-radius:2px;box-shadow:0 1px 2px rgba(0,0,0,0.06)"><h1 style="font-family:''Playfair Display'',Georgia,serif;font-size:24px;color:#1a1a1a;margin:0 0 8px;font-weight:400">{{title}}</h1><p style="margin:0 0 24px;color:#6b6b6b">{{message}}</p>{{content_block}}</td></tr></table></td></tr></table></body></html>', TRUE),

('password_reset', 'Password reset', 'Reset Your Password — {{store_name}}',
'<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reset Your Password</title></head><body style="margin:0;padding:0;background-color:#f5f3f0;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif"><div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden">{{preheader}}</div><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f3f0"><tr><td align="center" style="padding:40px 16px"><table class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%"><tr><td align="center" style="padding:0 0 32px 0">{{store_logo_block}}</td></tr><tr><td style="background-color:#ffffff;padding:32px;border-radius:2px;box-shadow:0 1px 2px rgba(0,0,0,0.06)"><h1 style="font-family:''Playfair Display'',Georgia,serif;font-size:24px;color:#1a1a1a;margin:0 0 16px;font-weight:400">Reset Your Password</h1><p style="margin:0 0 16px">Hi {{customer_name}},</p><p style="margin:0 0 16px">Click the button below to set a new password. This link will expire in 1 hour.</p><table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px"><tr><td style="background-color:#1a1a1a;border-radius:2px;text-align:center"><a href="{{reset_url}}" style="display:inline-block;padding:14px 36px;font-family:''Hanken Grotesk'',Arial,Helvetica,sans-serif;font-size:14px;font-weight:600;letter-spacing:0.08em;color:#f5f3f0;text-decoration:none;text-transform:uppercase">Reset Password</a></td></tr></table></td></tr></table></td></tr></table></body></html>', TRUE);

COMMIT;
