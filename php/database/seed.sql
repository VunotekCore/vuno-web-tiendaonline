-- =============================================================================
-- Seed: Valores por defecto para todas las secciones de configuración
-- Uso: mysql -u dail -p vuno_ramlop_ecommerce < php/database/seed.sql
-- Safe to run multiple times. INSERT IGNORE preserva datos existentes.
-- =============================================================================

BEGIN;

-- =============================================================================
-- Store — Información de la tienda
-- =============================================================================
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES
('store', 'name', 'Vunotek'),
('store', 'slogan', 'Architectural Minimalism in Footwear'),
('store', 'email', 'hola@vuno.com'),
('store', 'description', 'Calzado artesanal para damas con diseño minimalista arquitectónico. Cada pieza es seleccionada por su integridad arquitectónica — materiales, líneas y proporción.'),
('store', 'logo', ''),
('store', 'newsletter_discount_code', '');

-- =============================================================================
-- Receipt — Datos de facturación
-- =============================================================================
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES
('receipt', 'businessName', 'Vunotek LLC'),
('receipt', 'taxId', ''),
('receipt', 'address', ''),
('receipt', 'city', ''),
('receipt', 'state', ''),
('receipt', 'zip', ''),
('receipt', 'phone', '');

-- =============================================================================
-- ImageKit
-- =============================================================================
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES
('imagekit', 'publicKey', ''),
('imagekit', 'privateKey', ''),
('imagekit', 'urlEndpoint', '');

-- =============================================================================
-- Stripe — Pasarela de pago
-- =============================================================================
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES
('stripe', 'enabled', 'true'),
('stripe', 'publishableKey', ''),
('stripe', 'secretKey', ''),
('stripe', 'webhookSecret', '');

-- =============================================================================
-- Transfer — Transferencia bancaria
-- =============================================================================
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES
('transfer', 'enabled', 'true'),
('transfer', 'banks', '[]');

-- =============================================================================
-- SMTP — Email
-- =============================================================================
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES
('smtp', 'host', ''),
('smtp', 'port', '587'),
('smtp', 'user', ''),
('smtp', 'pass', ''),
('smtp', 'fromEmail', ''),
('smtp', 'fromName', 'Vunotek'),
('smtp', 'adminEmail', '');

-- =============================================================================
-- SEO — Configuración global
-- =============================================================================
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

-- =============================================================================
-- Size Guide — Guía de talles (calzado de dama)
-- =============================================================================
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES
('size_guide', 'title_es', 'Guía de Talles'),
('size_guide', 'title_en', 'Size Guide'),
('size_guide', 'footer_es', 'Medí tu pie desde el talón hasta el dedo más largo. Si estás entre dos medidas, elegí el talle superior.'),
('size_guide', 'footer_en', 'Measure your foot from heel to longest toe. If between sizes, choose the larger size.'),
('size_guide', 'rows', '[{"us":"5","eu":"35","uk":"2.5","cm":"22"},{"us":"5.5","eu":"35.5","uk":"3","cm":"22.5"},{"us":"6","eu":"36","uk":"3.5","cm":"23"},{"us":"6.5","eu":"36.5","uk":"4","cm":"23.5"},{"us":"7","eu":"37","uk":"4.5","cm":"24"},{"us":"7.5","eu":"37.5","uk":"5","cm":"24.5"},{"us":"8","eu":"38","uk":"5.5","cm":"25"},{"us":"8.5","eu":"38.5","uk":"6","cm":"25.5"},{"us":"9","eu":"39","uk":"6.5","cm":"26"},{"us":"9.5","eu":"39.5","uk":"7","cm":"26.5"},{"us":"10","eu":"40","uk":"7.5","cm":"27"},{"us":"10.5","eu":"40.5","uk":"8","cm":"27.5"},{"us":"11","eu":"41","uk":"8.5","cm":"28"}]');

-- =============================================================================
-- Policies — Políticas de tienda
-- =============================================================================
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES
('policies', 'shipping_es', 'Realizamos envíos a todo el país. El tiempo de entrega estimado es de 3 a 7 días hábiles. El costo de envío se calcula al finalizar la compra según tu dirección.'),
('policies', 'shipping_en', 'We ship nationwide. Estimated delivery time is 3 to 7 business days. Shipping cost is calculated at checkout based on your address.'),
('policies', 'returns_es', 'Aceptamos cambios y devoluciones dentro de los 30 días posteriores a la compra. El producto debe estar en su estado original, sin uso y con su empaque. Para iniciar un cambio, escribinos a <strong>hola@vuno.com</strong> con tu número de pedido.'),
('policies', 'returns_en', 'We accept exchanges and returns within 30 days of purchase. The item must be in its original condition, unworn, and in its packaging. To start a return, email us at <strong>hola@vuno.com</strong> with your order number.'),
('policies', 'privacy_es', 'Tus datos personales serán utilizados únicamente para procesar tu pedido y mejorar tu experiencia de compra. No compartimos tu información con terceros sin tu consentimiento explícito. Podés solicitar la eliminación de tus datos en cualquier momento escribiendo a <strong>hola@vuno.com</strong>.'),
('policies', 'privacy_en', 'Your personal data will only be used to process your order and improve your shopping experience. We do not share your information with third parties without your explicit consent. You may request deletion of your data at any time by emailing <strong>hola@vuno.com</strong>.');

-- =============================================================================
-- Moneda — Configuración de divisas (USD como base, tasas de ejemplo)
-- =============================================================================
INSERT IGNORE INTO settings (section, `key`, `value`) VALUES
('moneda', 'store_currency', 'USD'),
('moneda', 'rates', '[{"code":"USD","name":"US Dollar","symbol":"$","rate":"1.00","decimals":"2","active":"1"},{"code":"MXN","name":"Mexican Peso","symbol":"$","rate":"20.00","decimals":"2","active":"0"},{"code":"EUR","name":"Euro","symbol":"€","rate":"0.92","decimals":"2","active":"0"},{"code":"COP","name":"Colombian Peso","symbol":"$","rate":"4000.00","decimals":"0","active":"0"},{"code":"PEN","name":"Peruvian Sol","symbol":"S/","rate":"3.70","decimals":"2","active":"0"},{"code":"ARS","name":"Argentine Peso","symbol":"$","rate":"1000.00","decimals":"0","active":"0"},{"code":"CLP","name":"Chilean Peso","symbol":"$","rate":"950.00","decimals":"0","active":"0"}]');

COMMIT;
