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
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES
('store', 'name', 'Vunotek'),
('store', 'slogan', 'Architectural Minimalism in Footwear'),
('store', 'email', 'hola@vuno.com'),
('store', 'description', 'Calzado artesanal para damas con diseño minimalista arquitectónico. Cada pieza es seleccionada por su integridad arquitectónica — materiales, líneas y proporción.'),
('store', 'logo', ''),
('store', 'newsletter_discount_code', '');

-- Receipt
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES
('receipt', 'businessName', 'Vunotek LLC'),
('receipt', 'taxId', ''),
('receipt', 'address', ''),
('receipt', 'city', ''),
('receipt', 'state', ''),
('receipt', 'zip', ''),
('receipt', 'phone', '');

-- ImageKit
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES
('imagekit', 'publicKey', ''),
('imagekit', 'privateKey', ''),
('imagekit', 'urlEndpoint', '');

-- Stripe
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES
('stripe', 'enabled', 'true'),
('stripe', 'publishableKey', ''),
('stripe', 'secretKey', ''),
('stripe', 'stripeSecretKey', ''),
('stripe', 'webhookSecret', '');

-- Transfer
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES
('transfer', 'enabled', 'true'),
('transfer', 'banks', '[]');

-- SMTP
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES
('smtp', 'host', ''),
('smtp', 'port', '587'),
('smtp', 'user', ''),
('smtp', 'pass', ''),
('smtp', 'fromEmail', ''),
('smtp', 'fromName', 'Vunotek'),
('smtp', 'adminEmail', '');

-- SEO
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES
('seo', 'global_title', 'Vunotek | Calzado Artesanal para Damas'),
('seo', 'global_description', 'Calzado artesanal para damas con diseño minimalista arquitectónico. Descubrí nuestra colección de heels, sandals, mules, boots y flats. Envíos a todo el país.'),
('seo', 'og_default_image', ''),
('seo', 'twitter_site', ''),
('seo', 'facebook_page_id', ''),
('seo', 'google_site_verification', ''),
('seo', 'bing_site_verification', ''),
('seo', 'ga_id', ''),
('seo', 'robots_default', 'index,follow'),
('seo', 'theme_color', '#1A1A1A');

-- Size Guide
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES
('size_guide', 'title_es', 'Guía de Talles'),
('size_guide', 'title_en', 'Size Guide'),
('size_guide', 'footer_es', 'Medí tu pie desde el talón hasta el dedo más largo. Si estás entre dos medidas, elegí el talle superior.'),
('size_guide', 'footer_en', 'Measure your foot from heel to longest toe. If between sizes, choose the larger size.'),
('size_guide', 'rows', '[{"us":"5","eu":"35","uk":"2.5","cm":"22"},{"us":"5.5","eu":"35.5","uk":"3","cm":"22.5"},{"us":"6","eu":"36","uk":"3.5","cm":"23"},{"us":"6.5","eu":"36.5","uk":"4","cm":"23.5"},{"us":"7","eu":"37","uk":"4.5","cm":"24"},{"us":"7.5","eu":"37.5","uk":"5","cm":"24.5"},{"us":"8","eu":"38","uk":"5.5","cm":"25"},{"us":"8.5","eu":"38.5","uk":"6","cm":"25.5"},{"us":"9","eu":"39","uk":"6.5","cm":"26"},{"us":"9.5","eu":"39.5","uk":"7","cm":"26.5"},{"us":"10","eu":"40","uk":"7.5","cm":"27"},{"us":"10.5","eu":"40.5","uk":"8","cm":"27.5"},{"us":"11","eu":"41","uk":"8.5","cm":"28"}]');

-- Policies
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES
('policies', 'shipping_es', 'Realizamos envíos a todo el país. El tiempo de entrega estimado es de 3 a 7 días hábiles. El costo de envío se calcula al finalizar la compra según tu dirección.'),
('policies', 'shipping_en', 'We ship nationwide. Estimated delivery time is 3 to 7 business days. Shipping cost is calculated at checkout based on your address.'),
('policies', 'returns_es', 'Aceptamos cambios y devoluciones dentro de los 30 días posteriores a la compra. El producto debe estar en su estado original, sin uso y con su empaque. Para iniciar un cambio, escribinos a <strong>hola@vuno.com</strong> con tu número de pedido.'),
('policies', 'returns_en', 'We accept exchanges and returns within 30 days of purchase. The item must be in its original condition, unworn, and in its packaging. To start a return, email us at <strong>hola@vuno.com</strong> with your order number.'),
('policies', 'privacy_es', 'Tus datos personales serán utilizados únicamente para procesar tu pedido y mejorar tu experiencia de compra. No compartimos tu información con terceros sin tu consentimiento explícito. Podés solicitar la eliminación de tus datos en cualquier momento escribiendo a <strong>hola@vuno.com</strong>.'),
('policies', 'privacy_en', 'Your personal data will only be used to process your order and improve your shopping experience. We do not share your information with third parties without your explicit consent. You may request deletion of your data at any time by emailing <strong>hola@vuno.com</strong>.');

-- Currency
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES
('moneda', 'store_currency', 'USD'),
('moneda', 'rates', '[{"code":"USD","name":"US Dollar","symbol":"$","rate":"1.00","decimals":"2","active":"1"},{"code":"MXN","name":"Mexican Peso","symbol":"$","rate":"20.00","decimals":"2","active":"0"},{"code":"EUR","name":"Euro","symbol":"€","rate":"0.92","decimals":"2","active":"0"},{"code":"COP","name":"Colombian Peso","symbol":"$","rate":"4000.00","decimals":"0","active":"0"},{"code":"PEN","name":"Peruvian Sol","symbol":"S/","rate":"3.70","decimals":"2","active":"0"},{"code":"ARS","name":"Argentine Peso","symbol":"$","rate":"1000.00","decimals":"0","active":"0"},{"code":"CLP","name":"Chilean Peso","symbol":"$","rate":"950.00","decimals":"0","active":"0"}]');

-- =============================================================================
-- 3. Landing page settings
-- =============================================================================

INSERT IGNORE INTO settings (section, `key`, `value`) VALUES
('landing', 'hero',
 '{"enabled":true,"label_es":"LA VIDRIERA","label_en":"THE STOREFRONT","title_es":"Minimalismo Arquitectónico.","title_en":"Architectural Minimalism.","subtitle_es":"Descubre la elegancia estructural de la nueva temporada. Calzado diseñado no solo para usarse, sino para definir el espacio y la forma.","subtitle_en":"Discover the new season''s structural elegance. Footwear designed not to be worn, but to define space and form.","cta_es":"EXPLORAR COLECCIÓN","cta_en":"EXPLORE COLLECTION"}'),
('landing', 'new_arrivals',
 '{"enabled":true,"label_es":"NUEVOS","label_en":"NEW","title_es":"Nuevos Lanzamientos","title_en":"New Arrivals","subtitle_es":"Los últimos estudios en forma y función.","subtitle_en":"The latest studies in form and function.","cta_es":"Ver Todo","cta_en":"View All"}'),
('landing', 'categories',
 '{"enabled":true,"label_es":"VITRINES","label_en":"WINDOWS","title_es":"Navegar por Colección","title_en":"Browse by Collection"}'),
('landing', 'brand_values',
 '{"enabled":false,"image_url":"https://images.unsplash.com/photo-1549298916-b41d501d3772?w=700&h=900&fit=crop","label_es":"FILOSOFÍA","label_en":"PHILOSOPHY","title_es":"Diseño que define el espacio.","title_en":"Design that defines space.","paragraph_es":"Cada pieza es seleccionada por su integridad arquitectónica. Materiales, líneas y proporción — nada es accidental.","paragraph_en":"Every piece is selected for its architectural integrity. Materials, lines, and proportion — nothing is accidental.","cta_es":"CONOCER MÁS","cta_en":"LEARN MORE"}'),
('landing', 'closing_cta',
 '{"enabled":true,"label_es":"VUNOTEK","label_en":"VUNOTEK","title_es":"El minimalismo arquitectónico en cada paso.","title_en":"Architectural minimalism in every step.","subtitle_es":"Descubrí la colección completa donde la estructura se encuentra con la elegancia.","subtitle_en":"Discover the full collection where structure meets elegance.","cta_es":"EXPLORAR COLECCIÓN","cta_en":"EXPLORE COLLECTION"}'),
('landing', 'social',
 '{"enabled":true,"title_es":"Seguinos en Redes","title_en":"Follow Us"}'),
('landing', 'newsletter',
 '{"enabled":false,"title_es":"Boletín","title_en":"Newsletter","subtitle_es":"Sé la primera en enterarte de nuevas colecciones, ediciones limitadas y eventos exclusivos.","subtitle_en":"Be the first to know about new collections, limited editions, and exclusive events.","placeholder_es":"tu@email.com","placeholder_en":"your@email.com","cta_es":"SUSCRIBIRME","cta_en":"SUBSCRIBE"}'),
('landing', 'testimonials',
 '{"enabled":false,"title_es":"Lo Que Dicen Nuestras Clientes","title_en":"What Our Clients Say","subtitle_es":"Historias reales de mujeres que caminan con Vunotek.","subtitle_en":"Real stories from women who walk with Vunotek.","items":[]}'),
('landing', 'blog',
 '{"enabled":true,"label_es":"DEL JOURNAL","label_en":"FROM THE JOURNAL","title_es":"Últimas Publicaciones","title_en":"Latest Posts","desc_es":"Explorá nuestras historias, tendencias y el arte detrás de cada diseño.","desc_en":"Explore our stories, trends, and the art behind every design.","view_all_es":"Ver Todo","view_all_en":"View All"}');

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

-- Product 1: Stiletto Noir Arquitectónico
INSERT IGNORE INTO products (id, sku, name, slug, description, price, currency, is_featured) VALUES
('prod-001', 'SKU-STIL-001', 'Stiletto Noir Arquitectónico', 'stiletto-noir-arquitectonico',
 'Redefiniendo la silueta clásica, este stiletto presenta líneas arquitectónicas afiladas y un tacón esculpido de 90mm. Fabricado en Italia con piel de becerro lisa de primera calidad, su diseño minimalista elimina cualquier costura innecesaria para un acabado purista.',
 450.00, 'USD', TRUE);

INSERT IGNORE INTO product_details (product_id, detail_text, sort_order) VALUES
('prod-001', '100% Piel de becerro exterior', 1),
('prod-001', 'Forro y suela interior de piel', 2),
('prod-001', 'Tacón escultural de 90mm', 3),
('prod-001', 'Hecho en Italia', 4),
('prod-001', 'Limpiar con paño suave y seco', 5);

INSERT IGNORE INTO product_categories (product_id, category_id) VALUES ('prod-001', 'cat-heels');

INSERT IGNORE INTO product_colors (id, product_id, name, hex, sort_order) VALUES
(1, 'prod-001', 'Noir', '#1A1A1A', 1),
(2, 'prod-001', 'Sand', '#E6DED5', 2),
(3, 'prod-001', 'White', '#FFFFFF', 3);

INSERT IGNORE INTO product_sizes (id, product_id, label, value, sort_order) VALUES
(1, 'prod-001', '35', '35', 1),
(2, 'prod-001', '36', '36', 2),
(3, 'prod-001', '37', '37', 3),
(4, 'prod-001', '38', '38', 4),
(5, 'prod-001', '39', '39', 5),
(6, 'prod-001', '40', '40', 6),
(7, 'prod-001', '41', '41', 7);

INSERT IGNORE INTO product_variants (product_id, color_id, size_id, sku, stock, is_active) VALUES
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

INSERT IGNORE INTO product_images (product_id, url, alt_text, sort_order, is_primary) VALUES
('prod-001', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 'Stiletto Noir vista frontal', 1, TRUE),
('prod-001', 'https://images.unsplash.com/photo-1605812860427-4024433a70fd?w=800&q=80', 'Stiletto Noir vista lateral', 2, FALSE),
('prod-001', 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=800&q=80', 'Stiletto Noir detalle tacón', 3, FALSE);

-- Product 2: Sandalia Estructural Nude
INSERT IGNORE INTO products (id, sku, name, slug, description, price, currency, is_featured) VALUES
('prod-002', 'SKU-SAND-001', 'Sandalia Estructural Nude', 'sandalia-estructural-nude',
 'Una sandalia que abraza la forma del pie con líneas depuradas y una estética escultórica. Su construcción en piel nude se funde con la piel para un efecto visual alargador y sofisticado.',
 380.00, 'USD', TRUE);

INSERT IGNORE INTO product_details (product_id, detail_text, sort_order) VALUES
('prod-002', '100% Piel de becerro', 1),
('prod-002', 'Suela de cuero', 2),
('prod-002', 'Hebilla ajustable en metal dorado', 3),
('prod-002', 'Tacón bloque 60mm', 4),
('prod-002', 'Hecho a mano en España', 5);

INSERT IGNORE INTO product_categories (product_id, category_id) VALUES ('prod-002', 'cat-sandals');

INSERT IGNORE INTO product_colors (id, product_id, name, hex, sort_order) VALUES
(4, 'prod-002', 'Nude', '#E6DED5', 1);

INSERT IGNORE INTO product_sizes (id, product_id, label, value, sort_order) VALUES
(8,  'prod-002', '36', '36', 1),
(9,  'prod-002', '37', '37', 2),
(10, 'prod-002', '38', '38', 3),
(11, 'prod-002', '39', '39', 4),
(12, 'prod-002', '40', '40', 5);

INSERT IGNORE INTO product_variants (product_id, color_id, size_id, sku, stock, is_active) VALUES
('prod-002', 4, 8,  'SAND-NUDE-36', 5, TRUE),
('prod-002', 4, 9,  'SAND-NUDE-37', 5, TRUE),
('prod-002', 4, 10, 'SAND-NUDE-38', 5, TRUE),
('prod-002', 4, 11, 'SAND-NUDE-39', 5, TRUE),
('prod-002', 4, 12, 'SAND-NUDE-40', 0, FALSE);

INSERT IGNORE INTO product_images (product_id, url, alt_text, sort_order, is_primary) VALUES
('prod-002', 'https://images.unsplash.com/photo-1605812860427-4024433a70fd?w=800&q=80', 'Sandalia Nude vista frontal', 1, TRUE),
('prod-002', 'https://images.unsplash.com/photo-1604176354204-9268737828e4?w=800&q=80', 'Sandalia Nude vista lateral', 2, FALSE);

-- Product 3: Pump Clásico Punta Fina
INSERT IGNORE INTO products (id, sku, name, slug, description, price, currency, is_featured) VALUES
('prod-003', 'SKU-PUMP-001', 'Pump Clásico Punta Fina', 'pump-clasico-punta-fina',
 'El pump definitivo para la mujer de poder. Silueta alargada con punta fina y tacón de 85mm. Su construcción en piel de becerro ofrece un ajuste perfecto y una elegancia atemporal.',
 420.00, 'USD', TRUE);

INSERT IGNORE INTO product_details (product_id, detail_text, sort_order) VALUES
('prod-003', 'Piel de becerro italiana', 1),
('prod-003', 'Forro de piel de cordero', 2),
('prod-003', 'Tacón aguja 85mm', 3),
('prod-003', 'Suela de cuero con insignia', 4),
('prod-003', 'Hecho en Italia', 5);

INSERT IGNORE INTO product_categories (product_id, category_id) VALUES ('prod-003', 'cat-heels');

INSERT IGNORE INTO product_colors (id, product_id, name, hex, sort_order) VALUES
(5, 'prod-003', 'Arcilla', '#C18C7E', 1),
(6, 'prod-003', 'Noir', '#1A1A1A', 2),
(7, 'prod-003', 'Blanco', '#FFFFFF', 3);

INSERT IGNORE INTO product_sizes (id, product_id, label, value, sort_order) VALUES
(13, 'prod-003', '36', '36', 1),
(14, 'prod-003', '37', '37', 2),
(15, 'prod-003', '38', '38', 3),
(16, 'prod-003', '39', '39', 4),
(17, 'prod-003', '40', '40', 5);

INSERT IGNORE INTO product_variants (product_id, color_id, size_id, sku, stock, is_active) VALUES
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

INSERT IGNORE INTO product_images (product_id, url, alt_text, sort_order, is_primary) VALUES
('prod-003', 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=800&q=80', 'Pump Punta Fina vista frontal', 1, TRUE),
('prod-003', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 'Pump Punta Fina vista lateral', 2, FALSE);

-- Product 4: Mule Bloque Geométrico
INSERT IGNORE INTO products (id, sku, name, slug, description, price, currency, is_featured) VALUES
('prod-004', 'SKU-MULE-001', 'Mule Bloque Geométrico', 'mule-bloque-geometrico',
 'Una mule de declaración arquitectónica. Su tacón bloque esculpido y su silueta minimalista la convierten en la pieza central de cualquier conjunto. Confeccionada en piel negra de alta resistencia.',
 395.00, 'USD', TRUE);

INSERT IGNORE INTO product_details (product_id, detail_text, sort_order) VALUES
('prod-004', 'Piel de becerro negra', 1),
('prod-004', 'Tacón bloque geométrico 70mm', 2),
('prod-004', 'Suela de caucho grabada', 3),
('prod-004', 'Plataforma oculta 20mm', 4),
('prod-004', 'Hecho en Portugal', 5);

INSERT IGNORE INTO product_categories (product_id, category_id) VALUES ('prod-004', 'cat-mules');

INSERT IGNORE INTO product_colors (id, product_id, name, hex, sort_order) VALUES
(8, 'prod-004', 'Noir', '#1A1A1A', 1);

INSERT IGNORE INTO product_sizes (id, product_id, label, value, sort_order) VALUES
(18, 'prod-004', '36', '36', 1),
(19, 'prod-004', '37', '37', 2),
(20, 'prod-004', '38', '38', 3),
(21, 'prod-004', '39', '39', 4),
(22, 'prod-004', '40', '40', 5),
(23, 'prod-004', '41', '41', 6);

INSERT IGNORE INTO product_variants (product_id, color_id, size_id, sku, stock, is_active) VALUES
('prod-004', 8, 18, 'MULE-NOIR-36', 5, TRUE),
('prod-004', 8, 19, 'MULE-NOIR-37', 5, TRUE),
('prod-004', 8, 20, 'MULE-NOIR-38', 5, TRUE),
('prod-004', 8, 21, 'MULE-NOIR-39', 5, TRUE),
('prod-004', 8, 22, 'MULE-NOIR-40', 5, TRUE),
('prod-004', 8, 23, 'MULE-NOIR-41', 5, TRUE);

INSERT IGNORE INTO product_images (product_id, url, alt_text, sort_order, is_primary) VALUES
('prod-004', 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=800&q=80', 'Mule Geométrico vista frontal', 1, TRUE),
('prod-004', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 'Mule Geométrico vista lateral', 2, FALSE);

-- Product 5: Kitten Heel Minimal
INSERT IGNORE INTO products (id, sku, name, slug, description, price, currency, is_featured) VALUES
('prod-005', 'SKU-KITTEN-001', 'Kitten Heel Minimal', 'kitten-heel-minimal',
 'Elegancia discreta con un tacón kitten de 50mm que alarga la silueta sin sacrificar confort. Su diseño depurado y la piel blanca inmaculada la convierten en un básico de colección.',
 350.00, 'USD', TRUE);

INSERT IGNORE INTO product_details (product_id, detail_text, sort_order) VALUES
('prod-005', 'Piel de becerro blanca', 1),
('prod-005', 'Tacón kitten 50mm', 2),
('prod-005', 'Puntera redondeada', 3),
('prod-005', 'Forro de piel natural', 4),
('prod-005', 'Hecho en Italia', 5);

INSERT IGNORE INTO product_categories (product_id, category_id) VALUES ('prod-005', 'cat-heels');

INSERT IGNORE INTO product_colors (id, product_id, name, hex, sort_order) VALUES
(9,  'prod-005', 'Blanco', '#FFFFFF', 1),
(10, 'prod-005', 'Noir', '#1A1A1A', 2);

INSERT IGNORE INTO product_sizes (id, product_id, label, value, sort_order) VALUES
(24, 'prod-005', '35', '35', 1),
(25, 'prod-005', '36', '36', 2),
(26, 'prod-005', '37', '37', 3),
(27, 'prod-005', '38', '38', 4),
(28, 'prod-005', '39', '39', 5);

INSERT IGNORE INTO product_variants (product_id, color_id, size_id, sku, stock, is_active) VALUES
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

INSERT IGNORE INTO product_images (product_id, url, alt_text, sort_order, is_primary) VALUES
('prod-005', 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=800&q=80', 'Kitten Heel vista frontal', 1, TRUE),
('prod-005', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 'Kitten Heel vista lateral', 2, FALSE);

-- Product 6: Sandalia Jaula Arquitectónica
INSERT IGNORE INTO products (id, sku, name, slug, description, price, currency, is_featured) VALUES
('prod-006', 'SKU-JAULA-001', 'Sandalia Jaula Arquitectónica', 'sandalia-jaula-arquitectonica',
 'Una exploración del espacio negativo. Esta sandalia de jaula entrelaza tiras de piel negra en una estructura geométrica que envuelve el pie. Tacón escultural de 100mm para una silueta imponente.',
 480.00, 'USD', TRUE);

INSERT IGNORE INTO product_details (product_id, detail_text, sort_order) VALUES
('prod-006', 'Tiras de piel de becerro', 1),
('prod-006', 'Tacón escultural 100mm', 2),
('prod-006', 'Cierre de hebilla ajustable', 3),
('prod-006', 'Planta anatómica acolchada', 4),
('prod-006', 'Hecho a mano en España', 5);

INSERT IGNORE INTO product_categories (product_id, category_id) VALUES ('prod-006', 'cat-sandals');

INSERT IGNORE INTO product_colors (id, product_id, name, hex, sort_order) VALUES
(11, 'prod-006', 'Noir', '#1A1A1A', 1);

INSERT IGNORE INTO product_sizes (id, product_id, label, value, sort_order) VALUES
(29, 'prod-006', '36', '36', 1),
(30, 'prod-006', '37', '37', 2),
(31, 'prod-006', '38', '38', 3),
(32, 'prod-006', '39', '39', 4),
(33, 'prod-006', '40', '40', 5);

INSERT IGNORE INTO product_variants (product_id, color_id, size_id, sku, stock, is_active) VALUES
('prod-006', 11, 29, 'JAULA-NOIR-36', 5, TRUE),
('prod-006', 11, 30, 'JAULA-NOIR-37', 5, TRUE),
('prod-006', 11, 31, 'JAULA-NOIR-38', 5, TRUE),
('prod-006', 11, 32, 'JAULA-NOIR-39', 0, FALSE),
('prod-006', 11, 33, 'JAULA-NOIR-40', 0, FALSE);

INSERT IGNORE INTO product_images (product_id, url, alt_text, sort_order, is_primary) VALUES
('prod-006', 'https://images.unsplash.com/photo-1604176354204-9268737828e4?w=800&q=80', 'Sandalia Jaula vista frontal', 1, TRUE),
('prod-006', 'https://images.unsplash.com/photo-1605812860427-4024433a70fd?w=800&q=80', 'Sandalia Jaula vista lateral', 2, FALSE);

-- Product 7: Botín Bloque Escultural
INSERT IGNORE INTO products (id, sku, name, slug, description, price, currency, is_featured) VALUES
('prod-007', 'SKU-BOTIN-001', 'Botín Bloque Escultural', 'botin-bloque-escultural',
 'Un botín de bloque que desafía las convenciones. Tacón esculpido en ángulo y silueta recortada para un look vanguardista. La piel negra de alta resistencia y la cremallera trasera facilitan el calce.',
 520.00, 'USD', TRUE);

INSERT IGNORE INTO product_details (product_id, detail_text, sort_order) VALUES
('prod-007', 'Piel de becerro negra', 1),
('prod-007', 'Tacón bloque angular 80mm', 2),
('prod-007', 'Cremallera trasera dorada', 3),
('prod-007', 'Puntera almendrada', 4),
('prod-007', 'Hecho en Portugal', 5);

INSERT IGNORE INTO product_categories (product_id, category_id) VALUES ('prod-007', 'cat-boots');

INSERT IGNORE INTO product_colors (id, product_id, name, hex, sort_order) VALUES
(12, 'prod-007', 'Noir', '#1A1A1A', 1);

INSERT IGNORE INTO product_sizes (id, product_id, label, value, sort_order) VALUES
(34, 'prod-007', '36', '36', 1),
(35, 'prod-007', '37', '37', 2),
(36, 'prod-007', '38', '38', 3),
(37, 'prod-007', '39', '39', 4),
(38, 'prod-007', '40', '40', 5);

INSERT IGNORE INTO product_variants (product_id, color_id, size_id, sku, stock, is_active) VALUES
('prod-007', 12, 34, 'BOTIN-NOIR-36', 5, TRUE),
('prod-007', 12, 35, 'BOTIN-NOIR-37', 5, TRUE),
('prod-007', 12, 36, 'BOTIN-NOIR-38', 5, TRUE),
('prod-007', 12, 37, 'BOTIN-NOIR-39', 5, TRUE),
('prod-007', 12, 38, 'BOTIN-NOIR-40', 5, TRUE);

INSERT IGNORE INTO product_images (product_id, url, alt_text, sort_order, is_primary) VALUES
('prod-007', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 'Botín Bloque vista frontal', 1, TRUE),
('prod-007', 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=800&q=80', 'Botín Bloque vista lateral', 2, FALSE);

-- Product translations (EN)
INSERT IGNORE INTO product_translations (product_id, lang, name, description, details) VALUES
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
 'A sculptural sandal that wraps the foot in fine calf leather straps. The geometric cage design evokes architectural frameworks while the stiletto heel anchors the composition.',
 '["Calf leather straps","Adjustable ankle buckle","90mm stiletto heel","Leather sole","Made in Italy"]'),
('prod-007', 'en',
 'Sculptural Block Bootie',
 'An architectural bootie with a sculptural block heel and clean silhouette. Crafted in structured calf leather, its minimalist aesthetic balances volume and precision.',
 '["Structured calf leather","Inner zip closure","Sculptural 60mm block heel","Leather lining","Made in Portugal"]');

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
(1, 'prod-001', 1, 'Stiletto Noir Arquitectónico', 'stiletto-noir-arquitectonico', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 450.00, 'STIL-NOIR-36', 1, 450.00, 450.00, 'Noir', '36'),
(1, 'prod-004', 18, 'Mule Bloque Geométrico', 'mule-bloque-geometrico', 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=800&q=80', 395.00, 'MULE-NOIR-36', 1, 395.00, 395.00, 'Noir', '36');

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
(2, 'prod-007', 34, 'Botín Bloque Escultural', 'botin-bloque-escultural', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 520.00, 'BOTIN-NOIR-36', 1, 520.00, 520.00, 'Noir', '36');

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
