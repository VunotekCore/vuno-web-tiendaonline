<?php
/**
 * Blog — Public pages (dynamic PHP)
 *
 * List:  /blog/ or /blog?page=2
 * Detail: /blog/{slug}  (requires dev-router.php or Apache rewrite)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/storage.php';

$slug = $_GET['slug'] ?? '';

// ===== DETAIL VIEW =====
if ($slug) {
    $post = getBlogPostBySlug($slug);
    if (!$post || $post['status'] !== 'published') {
        http_response_code(404);
        $notFound = true;
    } else {
        $notFound = false;
        $img = !empty($post['featured_image']) ? $post['featured_image'] : 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=1200&q=80';
        $date = date('M j, Y', strtotime($post['published_at'] ?: $post['created_at']));
        $cat = !empty($post['category_name']) ? '<span class="font-label-caps text-label-caps text-clay-accent">' . htmlspecialchars($post['category_name']) . '</span>' : '';
    }
}

// ===== LIST VIEW =====
if (!$slug || isset($notFound)) {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 9;
    $categoryFilter = max(0, (int)($_GET['category'] ?? 0));
    $data = getBlogPosts($page, $limit, 'published', $categoryFilter);
    $posts = $data['items'];
    $totalPages = $data['pages'];
    $currentCategory = $categoryFilter;
    $categories = getBlogCategories();
}

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $slug && !$notFound ? htmlspecialchars($post['title']) . ' — Ram;Lop' : 'Blog — Ram;Lop' ?></title>
<meta name="description" content="<?= $slug && !$notFound ? htmlspecialchars(strip_tags($post['excerpt'] ?: $post['content'])) : 'Explora el mundo de Ram;Lop: tendencias, cuidados y el proceso artesanal detrás de cada diseño.' ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Hanken+Grotesk:wght@400;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0,1" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--monolith-black:#1A1A1A;--off-white:#F5F3F0;--sand-nude:#E6DED5;--clay-accent:#C18C7E;--stone-gray:#9A9A9A;--outline-variant:#E0DDD8;--background:#faf9f8;--section-gap:80px;--margin-mobile:16px;--margin-desktop:40px;--container-max:1280px}
body{font-family:'Hanken Grotesk',Arial,Helvetica,sans-serif;background:var(--background);color:var(--monolith-black);-webkit-font-smoothing:antialiased}
img{max-width:100%;height:auto}
a{text-decoration:none;color:inherit}
.font-headline{font-family:'Playfair Display',Georgia,serif}
.text-headline-md{font-size:1.5rem;line-height:1.3}
.text-body-md{font-size:0.9375rem;line-height:1.6}
.text-body-sm{font-size:0.8125rem;line-height:1.5}
.font-label-caps{font-size:0.75rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase}
.text-secondary{color:var(--stone-gray)}
.text-clay-accent{color:var(--clay-accent)}
.bg-off-white{background:var(--off-white)}
.border-outline-variant{border-color:var(--outline-variant)}
.max-w-container-max{max-width:var(--container-max)}
.mx-auto{margin-left:auto;margin-right:auto}
.px-margin-mobile{padding-left:var(--margin-mobile);padding-right:var(--margin-mobile)}
.py-section-gap{padding-top:var(--section-gap);padding-bottom:var(--section-gap)}
.mt-section-gap{margin-top:var(--section-gap)}
.mb-section-gap{margin-bottom:var(--section-gap)}
.grid{display:grid}
.grid-cols-1{grid-template-columns:repeat(1,1fr)}
.gap-4{gap:1rem}
.gap-6{gap:1.5rem}
.gap-8{gap:2rem}
.flex{display:flex}
.flex-wrap{flex-wrap:wrap}
.items-center{align-items:center}
.justify-center{justify-content:center}
.text-center{text-align:center}
.block{display:block}
.mb-2{margin-bottom:0.5rem}
.mb-3{margin-bottom:0.75rem}
.mb-4{margin-bottom:1rem}
.mb-8{margin-bottom:2rem}
.mb-12{margin-bottom:3rem}
.mt-1{margin-top:0.25rem}
.mt-8{margin-top:2rem}
.mt-12{margin-top:3rem}
.pt-12{padding-top:3rem}
.pb-section-gap{padding-bottom:var(--section-gap)}
.p-4{padding:1rem}
.p-5{padding:1.25rem}
.aspect-\[4\/3\]{aspect-ratio:4/3}
.overflow-hidden{overflow:hidden}
.rounded-sm{border-radius:2px}
.border{border:1px solid}
.shadow-sm{box-shadow:0 1px 2px rgba(0,0,0,0.06)}
.max-w-lg{max-width:32rem}
.max-w-2xl{max-width:42rem}
.space-y-2>*+*{margin-top:0.5rem}
.space-y-4>*+*{margin-top:1rem}
@media(min-width:768px){
.px-margin-desktop{padding-left:var(--margin-desktop);padding-right:var(--margin-desktop)}
.md\:grid-cols-2{grid-template-columns:repeat(2,1fr)}
.md\:grid-cols-3{grid-template-columns:repeat(3,1fr)}
}
.prose{font-size:1rem;line-height:1.8;color:var(--monolith-black)}
.prose h2{font-family:'Playfair Display',Georgia,serif;font-size:1.5rem;margin:2rem 0 1rem}
.prose h3{font-family:'Playfair Display',Georgia,serif;font-size:1.25rem;margin:1.5rem 0 0.75rem}
.prose p{margin-bottom:1rem}
.prose img{border-radius:2px;margin:1.5rem 0}
.prose blockquote{border-left:3px solid var(--clay-accent);padding-left:1rem;margin:1.5rem 0;color:var(--stone-gray);font-style:italic}
.prose ul,.prose ol{padding-left:1.5rem;margin-bottom:1rem}
.prose li{margin-bottom:0.25rem}
.prose a{color:var(--clay-accent);text-decoration:underline}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="w-full bg-off-white border-b border-outline-variant">
  <div class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop h-16 flex items-center justify-between">
    <a href="/" class="font-headline text-headline-md text-monolith-black tracking-tighter">Ram;Lop</a>
    <div class="hidden md:flex items-center gap-8">
      <a href="/catalogo" class="font-label-caps text-label-caps text-secondary hover:text-monolith-black transition-colors">CATÁLOGO</a>
      <a href="/blog" class="font-label-caps text-label-caps text-monolith-black border-b-2 border-monolith-black">BLOG</a>
    </div>
    <a href="/carrito" class="relative material-symbols-outlined text-2xl text-monolith-black">shopping_bag</a>
  </div>
</nav>

<?php if ($slug && !$notFound): ?>

  <!-- ===== DETAIL VIEW ===== -->
  <article class="max-w-2xl mx-auto px-margin-mobile md:px-margin-desktop pt-12 pb-section-gap">
    <?php if ($post['featured_image']): ?>
    <div class="aspect-[4/3] overflow-hidden rounded-sm mb-8">
      <img src="<?= htmlspecialchars($post['featured_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="w-full h-full object-cover" />
    </div>
    <?php endif; ?>

    <?= $cat ?? '' ?>
    <h1 class="font-headline text-headline-md text-monolith-black mt-2 mb-3" style="font-size:2.25rem"><?= htmlspecialchars($post['title']) ?></h1>
    <div class="flex items-center gap-4 font-body text-body-md text-secondary mb-8">
      <span>Por <?= htmlspecialchars($post['author'] ?? 'Ram;Lop') ?></span>
      <span>·</span>
      <span><?= $date ?></span>
    </div>

    <?php if (!empty($post['excerpt'])): ?>
    <p class="font-body text-body-md text-secondary text-lg mb-8"><?= htmlspecialchars($post['excerpt']) ?></p>
    <?php endif; ?>

    <div class="prose"><?= $post['content'] ?></div>

    <div class="border-t border-outline-variant mt-12 pt-8">
      <a href="/blog" class="inline-flex items-center gap-2 font-label-caps text-label-caps text-secondary hover:text-monolith-black transition-colors">
        <span class="material-symbols-outlined text-lg">arrow_back</span>
        VOLVER AL BLOG
      </a>
    </div>
  </article>

<?php else: ?>

  <!-- ===== LIST VIEW ===== -->
  <main class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop pt-12 pb-section-gap">
    <div class="mb-12">
      <h1 class="font-headline text-headline-md mb-3" style="font-size:2.25rem;color:var(--monolith-black)">Blog</h1>
      <p class="font-body text-body-md text-secondary max-w-lg">Explora el mundo de Ram;Lop: tendencias, cuidados y el proceso artesanal detrás de cada diseño.</p>
    </div>

    <?php if (!empty($categories)): ?>
    <div class="flex flex-wrap gap-2 mb-8">
      <a href="/blog" class="font-label-caps text-label-caps px-4 py-2 rounded-sm transition-colors <?= !$currentCategory ? 'bg-monolith-black text-off-white' : 'bg-off-white text-secondary border border-outline-variant hover:border-monolith-black' ?>">Todas</a>
      <?php foreach ($categories as $cat): ?>
      <a href="/blog?category=<?= $cat['id'] ?>" class="font-label-caps text-label-caps px-4 py-2 rounded-sm transition-colors <?= $currentCategory === (int)$cat['id'] ? 'bg-monolith-black text-off-white' : 'bg-off-white text-secondary border border-outline-variant hover:border-monolith-black' ?>"><?= htmlspecialchars($cat['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($posts)): ?>
    <div class="text-center py-20">
      <span class="material-symbols-outlined text-5xl" style="color:var(--stone-gray);display:block;margin-bottom:1rem">article</span>
      <p class="font-body text-body-md text-secondary"><?= $notFound ? 'Post no encontrado.' : 'No hay posts publicados aún.' ?></p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 md:grid-cols-3 gap-6">
      <?php foreach ($posts as $p):
        $pImg = !empty($p['thumbnail_image']) ? $p['thumbnail_image'] : (!empty($p['featured_image']) ? $p['featured_image'] : 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=600&q=80');
        $pDate = date('M j, Y', strtotime($p['published_at'] ?: $p['created_at']));
        $pCat = !empty($p['category_name']) ? '<span class="font-label-caps text-label-caps text-clay-accent">' . htmlspecialchars($p['category_name']) . '</span>' : '';
        $pExcerpt = htmlspecialchars(mb_strimwidth(strip_tags($p['excerpt'] ?: $p['content']), 0, 120, '...'));
      ?>
      <a href="/blog/<?= htmlspecialchars($p['slug']) ?>" class="group block bg-off-white border border-outline-variant rounded-sm overflow-hidden hover:shadow-md transition-all duration-300">
        <div class="aspect-[4/3] overflow-hidden">
          <img src="<?= $pImg ?>" alt="<?= htmlspecialchars($p['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" />
        </div>
        <div class="p-5">
          <?= $pCat ?>
          <h2 class="font-headline text-headline-md text-monolith-black mt-1 mb-2 group-hover:text-clay-accent transition-colors"><?= htmlspecialchars($p['title']) ?></h2>
          <p class="font-body text-body-md text-secondary mb-3"><?= $pExcerpt ?></p>
          <span class="font-label-caps text-label-caps text-secondary"><?= $pDate ?></span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-center gap-2 mt-12">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a href="/blog?page=<?= $i ?><?= $currentCategory ? '&category=' . $currentCategory : '' ?>" class="px-4 py-2 font-body text-body-md rounded-sm transition-colors <?= $i === $page ? 'bg-monolith-black text-off-white' : 'bg-off-white text-secondary border border-outline-variant hover:border-monolith-black' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </main>

<?php endif; ?>

<!-- Footer -->
<footer class="bg-monolith-black text-off-white mt-section-gap">
  <div class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop py-16">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
      <div class="md:col-span-2">
        <h3 class="font-headline text-headline-md mb-4">Ram;Lop</h3>
        <p class="font-body text-body-md text-stone-gray max-w-sm">Architectural Minimalism in Footwear. Calzado artesanal para damas, inspirado en la pureza de la arquitectura moderna.</p>
      </div>
      <div>
        <h4 class="font-label-caps text-label-caps text-stone-gray mb-4">NAVEGACIÓN</h4>
        <div class="space-y-2 font-body text-body-md">
          <a href="/catalogo" class="block text-off-white hover:text-stone-gray transition-colors">Catálogo</a>
          <a href="/blog" class="block text-off-white hover:text-stone-gray transition-colors">Blog</a>
        </div>
      </div>
      <div>
        <h4 class="font-label-caps text-label-caps text-stone-gray mb-4">CONTACTO</h4>
        <div class="space-y-2 font-body text-body-md text-stone-gray">
          <p>hola@vuno.com</p>
        </div>
      </div>
    </div>
    <div class="border-t border-white/10 mt-12 pt-8 text-center font-body text-body-sm text-stone-gray">
      &copy; <?= date('Y') ?> Ram;Lop. All rights reserved.
    </div>
  </div>
</footer>

</body>
</html>
