<?php
/**
 * Export SEO data from MySQL to JSON files for Astro build-time consumption.
 * Usage: php scripts/export-seo.php
 * Output: src/data/seo-settings.json, src/data/seo-products.json, src/data/seo-blog-posts.json
 */

declare(strict_types=1);

require_once __DIR__ . '/../php/config.php';

function exportJson(string $path, mixed $data): void {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo "  ✓ " . basename($path) . " (" . count(is_array($data) && isset($data[0]) ? $data : [$data]) . " entries)\n";
}

echo "Exporting SEO data...\n\n";

$db = getDb();

// 1. SEO settings
$settings = [];
$rows = $db->query("SELECT section, `key`, value FROM settings WHERE section = 'seo'");
while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['key']] = $row['value'];
}
exportJson(__DIR__ . '/../src/data/seo-settings.json', $settings);

// 2. Products with SEO fields
$products = $db->query("
    SELECT p.id, p.slug, p.name, p.price, p.currency,
           p.meta_title, p.meta_description, p.og_image_url,
           p.description,
           c.name AS category_name
    FROM products p
    LEFT JOIN product_categories pc ON pc.product_id = p.id
    LEFT JOIN categories c ON c.id = pc.category_id
    WHERE p.deleted_at IS NULL
    ORDER BY p.id
")->fetchAll(PDO::FETCH_ASSOC);

// Get images for each product
$productImages = $db->query("
    SELECT product_id, url
    FROM product_images
    WHERE is_active = 1
    ORDER BY sort_order ASC
")->fetchAll(PDO::FETCH_ASSOC);

$imagesByProduct = [];
foreach ($productImages as $img) {
    $imagesByProduct[$img['product_id']][] = $img['url'];
}

foreach ($products as &$p) {
    $p['images'] = $imagesByProduct[$p['id']] ?? [];
}
unset($p);

exportJson(__DIR__ . '/../src/data/seo-products.json', $products);

// 3. Published blog posts with SEO fields
$posts = $db->query("
    SELECT bp.id, bp.slug, bp.title, bp.excerpt, bp.thumbnail_image,
           bp.featured_image, bp.author, bp.meta_title, bp.meta_description,
           bp.published_at, bp.updated_at,
           bc.name AS category_name, bc.slug AS category_slug
    FROM blog_posts bp
    LEFT JOIN blog_categories bc ON bc.id = bp.category_id
    WHERE bp.status = 'published' AND bp.deleted_at IS NULL
    ORDER BY bp.published_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get English translations
$translations = $db->query("
    SELECT blog_post_id, lang, title, excerpt
    FROM blog_post_translations
    WHERE lang = 'en'
")->fetchAll(PDO::FETCH_ASSOC);

$transByPost = [];
foreach ($translations as $t) {
    $transByPost[$t['blog_post_id']] = $t;
}

foreach ($posts as &$post) {
    $enTrans = $transByPost[$post['id']] ?? null;
    $post['title_en'] = $enTrans['title'] ?? null;
    $post['excerpt_en'] = $enTrans['excerpt'] ?? null;
    $post['published_at'] = $post['published_at'] ?? $post['updated_at'];
}
unset($post);

exportJson(__DIR__ . '/../src/data/seo-blog-posts.json', $posts);

// 4. Blog categories (for breadcrumbs)
$categories = $db->query("
    SELECT id, name, slug
    FROM blog_categories
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

// Get English translations
$catTranslations = $db->query("
    SELECT category_id, lang, name
    FROM blog_category_translations
    WHERE lang = 'en'
")->fetchAll(PDO::FETCH_ASSOC);

$catTransByPost = [];
foreach ($catTranslations as $t) {
    $catTransByPost[$t['category_id']] = $t;
}

foreach ($categories as &$cat) {
    $enTrans = $catTransByPost[$cat['id']] ?? null;
    $cat['name_en'] = $enTrans['name'] ?? null;
}
unset($cat);

exportJson(__DIR__ . '/../src/data/seo-blog-categories.json', $categories);

// 5. Store name for OG site name
$storeName = $db->prepare("SELECT value FROM settings WHERE section = 'store' AND `key` = 'name'");
$storeName->execute();
$storeNameVal = $storeName->fetchColumn() ?: 'Vunotek';
exportJson(__DIR__ . '/../src/data/seo-store.json', ['name' => $storeNameVal]);

echo "\nDone. Run `pnpm build` to generate static pages with current SEO data.\n";
