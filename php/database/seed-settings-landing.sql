-- =============================================================================
-- Seed: Default landing section values for settings table
-- Safe to run multiple times. DELETE removes empty rows, INSERT IGNORE
-- preserves any existing data (social, newsletter, testimonials, brand_values).
-- =============================================================================

BEGIN;

DELETE FROM settings
WHERE section = 'landing'
  AND value = '[]';

INSERT IGNORE INTO settings (section, `key`, `value`) VALUES

-- Hero
('landing', 'hero',
 '{"label_es":"LA VIDRIERA","label_en":"THE STOREFRONT","title_es":"Minimalismo Arquitectónico.","title_en":"Architectural Minimalism.","subtitle_es":"Descubre la elegancia estructural de la nueva temporada. Calzado diseñado no solo para usarse, sino para definir el espacio y la forma.","subtitle_en":"Discover the new season''s structural elegance. Footwear designed not to be worn, but to define space and form.","cta_es":"EXPLORAR COLECCIÓN","cta_en":"EXPLORE COLLECTION"}'),

-- New Arrivals
('landing', 'new_arrivals',
 '{"label_es":"NUEVOS","label_en":"NEW","title_es":"Nuevos Lanzamientos","title_en":"New Arrivals","subtitle_es":"Los últimos estudios en forma y función.","subtitle_en":"The latest studies in form and function.","cta_es":"Ver Todo","cta_en":"View All"}'),

-- Categories
('landing', 'categories',
 '{"label_es":"VITRINES","label_en":"WINDOWS","title_es":"Navegar por Colección","title_en":"Browse by Collection"}'),

-- Brand Values (defaults — flat keys still override via PHP migration)
('landing', 'brand_values',
 '{"enabled":false,"image_url":"https://images.unsplash.com/photo-1549298916-b41d501d3772?w=700&h=900&fit=crop","label_es":"FILOSOFÍA","label_en":"PHILOSOPHY","title_es":"Diseño que define el espacio.","title_en":"Design that defines space.","paragraph_es":"Cada pieza es seleccionada por su integridad arquitectónica. Materiales, líneas y proporción — nada es accidental.","paragraph_en":"Every piece is selected for its architectural integrity. Materials, lines, and proportion — nothing is accidental.","cta_es":"CONOCER MÁS","cta_en":"LEARN MORE"}'),

-- Closing CTA
('landing', 'closing_cta',
 '{"label_es":"RAM;LOP","label_en":"RAM;LOP","title_es":"El minimalismo arquitectónico en cada paso.","title_en":"Architectural minimalism in every step.","subtitle_es":"Descubrí la colección completa donde la estructura se encuentra con la elegancia.","subtitle_en":"Discover the full collection where structure meets elegance.","cta_es":"EXPLORAR COLECCIÓN","cta_en":"EXPLORE COLLECTION"}'),

-- Social Media
('landing', 'social',
 '{"enabled":true,"title_es":"Seguinos en Redes","title_en":"Follow Us"}'),

-- Newsletter
('landing', 'newsletter',
 '{"enabled":false,"title_es":"Boletín","title_en":"Newsletter","subtitle_es":"Sé la primera en enterarte de nuevas colecciones, ediciones limitadas y eventos exclusivos.","subtitle_en":"Be the first to know about new collections, limited editions, and exclusive events.","placeholder_es":"tu@email.com","placeholder_en":"your@email.com","cta_es":"SUSCRIBIRME","cta_en":"SUBSCRIBE"}'),

-- Testimonials
('landing', 'testimonials',
 '{"enabled":false,"title_es":"Lo Que Dicen Nuestras Clientes","title_en":"What Our Clients Say","subtitle_es":"Historias reales de mujeres que caminan con Ram;Lop.","subtitle_en":"Real stories from women who walk with Ram;Lop.","items":[]}');

COMMIT;
