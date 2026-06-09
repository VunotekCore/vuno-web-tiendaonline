(function () {
  "use strict";

  var state = {
    products: [],
    filtered: [],
    visibleCount: 0,
    perPage: 12,
    lang: "es",
    sortBy: "newest",
    filters: {
      sizes: [],
      colors: [],
      styles: [],
    },
  };

  function init(products, lang) {
    state.products = products;
    state.filtered = products.slice();
    state.lang = lang;
    state.visibleCount = state.perPage;

    bindSizeFilters();
    bindColorFilters();
    bindStyleFilters();
    bindSort();
    bindLoadMore();
    bindMobileFilterToggle();
    bindQuickView();
    bindWishlistToggle();
    render();
  }

  // =============================================================
  //                          FILTER
  // =============================================================

  function bindSizeFilters() {
    document.querySelectorAll("[data-filter-size]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var size = this.dataset.filterSize;
        var idx = state.filters.sizes.indexOf(size);
        if (idx > -1) {
          state.filters.sizes.splice(idx, 1);
          this.classList.remove("bg-monolith-black", "text-off-white", "border-monolith-black");
          this.classList.add("border-outline-variant", "text-secondary", "hover:border-monolith-black", "hover:text-monolith-black");
        } else {
          state.filters.sizes.push(size);
          this.classList.add("bg-monolith-black", "text-off-white", "border-monolith-black");
          this.classList.remove("border-outline-variant", "text-secondary", "hover:border-monolith-black", "hover:text-monolith-black");
        }
        state.visibleCount = state.perPage;
        applyFilters();
      });
    });
  }

  function bindColorFilters() {
    document.querySelectorAll("[data-filter-color]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var color = this.dataset.filterColor;
        var wasActive = this.dataset.active === "true";
        if (wasActive) {
          this.dataset.active = "false";
          this.classList.remove("ring-1", "ring-monolith-black");
          this.classList.add("ring-1", "ring-transparent");
          var idx = state.filters.colors.indexOf(color);
          if (idx > -1) state.filters.colors.splice(idx, 1);
        } else {
          this.dataset.active = "true";
          this.classList.remove("ring-transparent");
          this.classList.add("ring-1", "ring-monolith-black");
          state.filters.colors.push(color);
        }
        state.visibleCount = state.perPage;
        applyFilters();
      });
    });
  }

  function bindStyleFilters() {
    document.querySelectorAll("[data-filter-style]").forEach(function (cb) {
      cb.addEventListener("change", function () {
        var style = this.dataset.filterStyle;
        var idx = state.filters.styles.indexOf(style);
        if (this.checked) {
          if (idx === -1) state.filters.styles.push(style);
        } else {
          if (idx > -1) state.filters.styles.splice(idx, 1);
        }
        state.visibleCount = state.perPage;
        applyFilters();
      });
    });
  }

  function applyFilters() {
    var f = state.filters;
    state.filtered = state.products.filter(function (p) {
      if (f.sizes.length > 0) {
        var hasSize = p.sizes.some(function (s) { return f.sizes.indexOf(s.value) > -1 && s.inStock; });
        if (!hasSize) return false;
      }
      if (f.colors.length > 0) {
        var hasColor = p.colors.some(function (c) { return f.colors.indexOf(c.name.toLowerCase()) > -1; });
        if (!hasColor) return false;
      }
      if (f.styles.length > 0) {
        var category = (p.category || "").toLowerCase();
        var matched = f.styles.some(function (s) {
          var synonymMap = {
            "stiletto": "heels",
            "bloque arquitectónico": "heels",
            "kitten heel": "heels",
            "plataforma oculta": "sandals",
          };
          var target = synonymMap[s.toLowerCase()] || s.toLowerCase();
          return category.indexOf(target) > -1;
        });
        if (!matched) return false;
      }
      return true;
    });
    sortProducts();
  }

  // =============================================================
  //                          SORT
  // =============================================================

  function bindSort() {
    var sel = document.getElementById("sortSelect");
    if (!sel) return;
    sel.addEventListener("change", function () {
      state.sortBy = this.value;
      state.visibleCount = state.perPage;
      sortProducts();
    });
  }

  function sortProducts() {
    var arr = state.filtered;
    switch (state.sortBy) {
      case "price-asc":
        arr.sort(function (a, b) { return a.price - b.price; });
        break;
      case "price-desc":
        arr.sort(function (a, b) { return b.price - a.price; });
        break;
      case "name":
        arr.sort(function (a, b) { return a.name.localeCompare(b.name); });
        break;
      default:
        arr.sort(function (a, b) { return (a.createdAt || "").localeCompare(b.createdAt || "") || a.name.localeCompare(b.name); });
        break;
    }
    state.filtered = arr;
    render();
  }

  // =============================================================
  //                        LOAD MORE
  // =============================================================

  function bindLoadMore() {
    var btn = document.getElementById("loadMoreBtn");
    if (!btn) return;
    btn.addEventListener("click", function () {
      state.visibleCount += state.perPage;
      render();
    });
  }

  // =============================================================
  //                     MOBILE FILTER TOGGLE
  // =============================================================

  function bindMobileFilterToggle() {
    var toggle = document.getElementById("mobileFilterToggle");
    var panel = document.getElementById("mobileFilterPanel");
    var close = document.getElementById("mobileFilterClose");
    if (!toggle || !panel) return;

    toggle.addEventListener("click", function () {
      panel.classList.remove("translate-x-full");
      panel.classList.add("translate-x-0");
      document.body.style.overflow = "hidden";
    });

    function closePanel() {
      panel.classList.remove("translate-x-0");
      panel.classList.add("translate-x-full");
      document.body.style.overflow = "";
    }

    if (close) close.addEventListener("click", closePanel);
    panel.addEventListener("click", function (e) {
      if (e.target === panel) closePanel();
    });
  }

  // =============================================================
  //                     QUICK VIEW
  // =============================================================

  var currentQuickViewProduct = null;

  function bindQuickView() {
    document.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-quick-view]");
      if (btn) {
        e.preventDefault();
        var slug = btn.dataset.quickView;
        var product = state.products.find(function (p) { return p.slug === slug; });
        if (product) openQuickView(product);
      }
    });

    var overlay = document.getElementById("quickViewOverlay");
    var close = document.getElementById("quickViewClose");
    if (overlay) {
      overlay.addEventListener("click", function (e) {
        if (e.target === overlay) closeQuickView();
      });
    }
    if (close) close.addEventListener("click", closeQuickView);
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") closeQuickView();
    });

    // Quick view color selector
    document.addEventListener("click", function (e) {
      var swatch = e.target.closest("[data-qv-color]");
      if (!swatch) return;
      document.querySelectorAll("[data-qv-color]").forEach(function (s) {
        s.classList.remove("ring-1", "ring-offset-2", "ring-monolith-black", "scale-110");
        s.classList.add("border", "border-outline-variant");
      });
      swatch.classList.add("ring-1", "ring-offset-2", "ring-monolith-black", "scale-110");
      swatch.classList.remove("border", "border-outline-variant");
      updateQVStock(swatch.dataset.qvColor);
    });

    // Quick view size selector
    document.addEventListener("click", function (e) {
      var sizeBtn = e.target.closest("[data-qv-size]");
      if (!sizeBtn) return;
      document.querySelectorAll("[data-qv-size]").forEach(function (s) {
        s.classList.remove("bg-monolith-black", "text-off-white", "border-monolith-black");
        s.classList.add("border-outline-variant", "text-monolith-black");
      });
      sizeBtn.classList.add("bg-monolith-black", "text-off-white", "border-monolith-black");
      sizeBtn.classList.remove("border-outline-variant");
    });
  }

  function getQVSelectedColor() {
    var active = document.querySelector("[data-qv-color].ring-monolith-black");
    return active ? active.dataset.qvColor : null;
  }

  function getQVSelectedSize() {
    var active = document.querySelector("[data-qv-size].bg-monolith-black");
    return active ? active.dataset.qvSize : null;
  }

  function updateQVStock(colorName) {
    if (!currentQuickViewProduct) return;
    var sizes = currentQuickViewProduct.sizes;
    var variantMap = {};
    if (currentQuickViewProduct.variants) {
      currentQuickViewProduct.variants.forEach(function (v) {
        var key = (v.colorName || "") + "_" + (v.sizeValue || "");
        variantMap[key] = v.stock > 0;
      });
    }

    document.querySelectorAll("[data-qv-size]").forEach(function (btn) {
      var sizeVal = btn.dataset.qvSize;
      var hasStock = true;
      var key = (colorName || "") + "_" + sizeVal;
      if (variantMap.hasOwnProperty(key)) {
        hasStock = variantMap[key];
      } else {
        var size = sizes.find(function (s) { return s.value === sizeVal; });
        hasStock = size ? size.inStock : true;
      }
      if (!hasStock) {
        btn.classList.add("opacity-50", "cursor-not-allowed", "line-through");
        btn.classList.remove("hover:bg-monolith-black", "hover:text-off-white", "hover:border-monolith-black");
        btn.disabled = true;
      } else {
        btn.classList.remove("opacity-50", "cursor-not-allowed", "line-through");
        btn.classList.add("hover:bg-monolith-black", "hover:text-off-white", "hover:border-monolith-black");
        btn.disabled = false;
      }
    });
  }

  function openQuickView(product) {
    currentQuickViewProduct = product;
    var overlay = document.getElementById("quickViewOverlay");
    var body = document.getElementById("quickViewBody");
    if (!overlay || !body) return;

    var mainImage = product.images && product.images.length > 0 ? product.images[0] : "";
    var moreImages = product.images ? product.images.slice(1, 3) : [];
    var productUrl = "/" + state.lang + "/producto/" + product.slug;

    var html = '<div class="flex flex-col md:flex-row gap-6 md:gap-8">';
    // Gallery
    html += '<div class="md:w-1/2 flex flex-col gap-2">';
    html += '<div class="aspect-[4/5] bg-surface-container overflow-hidden">';
    html += '<img src="' + escapeHtml(window.imgTransform(mainImage, 400, 500)) + '" alt="' + escapeHtml(product.name) + '" class="w-full h-full object-cover" />';
    html += '</div>';
    if (moreImages.length > 0) {
      html += '<div class="flex gap-2">';
      moreImages.forEach(function (img) {
        html += '<div class="w-20 h-20 bg-surface-container overflow-hidden flex-shrink-0">';
        html += '<img src="' + escapeHtml(window.imgTransform(img, 160, 200)) + '" alt="" class="w-full h-full object-cover" />';
        html += '</div>';
      });
      html += '</div>';
    }
    html += '</div>';
    // Info
    html += '<div class="md:w-1/2 flex flex-col gap-5">';
    html += '<div>';
    html += '<p class="font-label-caps text-label-caps text-secondary mb-1">' + escapeHtml(product.category || "") + '</p>';
    html += '<h3 class="font-headline-md text-headline-md text-monolith-black">' + escapeHtml(product.name) + '</h3>';
    html += '<p class="font-price-display text-price-display text-monolith-black mt-2">' + (product.display_symbol || '$') + (product.display_price || product.price).toFixed(2) + ' ' + (product.display_currency || 'USD') + '</p>';
    html += '</div>';
    // Colors
    if (product.colors && product.colors.length > 0) {
      html += '<div>';
      html += '<span class="font-label-caps text-label-caps text-secondary block mb-2">COLOR</span>';
      html += '<div class="flex gap-2">';
      product.colors.forEach(function (c, i) {
        var active = i === 0 ? 'ring-1 ring-offset-2 ring-monolith-black scale-110' : 'border border-outline-variant';
        html += '<button data-qv-color="' + escapeHtml(c.name) + '" class="w-7 h-7 rounded-full transition-all duration-300 ' + active + '" style="background-color:' + escapeHtml(c.hex) + '" aria-label="' + escapeHtml(c.name) + '"></button>';
      });
      html += '</div>';
      html += '</div>';
    }
    // Sizes
    if (product.sizes && product.sizes.length > 0) {
      html += '<div>';
      html += '<span class="font-label-caps text-label-caps text-secondary block mb-2">TALLA</span>';
      html += '<div class="grid grid-cols-4 gap-2">';
      product.sizes.forEach(function (s) {
        var disabledCls = s.inStock ? 'hover:bg-monolith-black hover:text-off-white hover:border-monolith-black' : 'opacity-50 cursor-not-allowed line-through';
        html += '<button data-qv-size="' + escapeHtml(s.value) + '" class="py-2 px-1 border border-outline-variant text-center font-label-caps text-label-caps text-monolith-black transition-all duration-300 ' + disabledCls + '" ' + (s.inStock ? '' : 'disabled') + '>' + escapeHtml(s.label) + '</button>';
      });
      html += '</div>';
      html += '</div>';
    }
    // Description excerpt
    if (product.description) {
      var desc = product.description.length > 120 ? product.description.substring(0, 120) + "..." : product.description;
      html += '<p class="font-body-md text-body-md text-secondary text-sm leading-relaxed">' + escapeHtml(desc) + '</p>';
    }
    // Buttons
    html += '<div class="flex gap-3 pt-2">';
    html += '<button data-qv-add-to-cart class="flex-1 min-h-[48px] bg-monolith-black text-on-primary font-label-caps text-label-caps tracking-widest hover:bg-on-surface hover:tracking-[0.15em] transition-all duration-300 text-xs">' + (state.lang === "es" ? "AÑADIR AL CARRITO" : "ADD TO CART") + '</button>';
    html += '<a href="' + productUrl + '" class="min-h-[48px] px-5 border border-monolith-black text-monolith-black font-label-caps text-label-caps hover:bg-monolith-black hover:text-off-white transition-all duration-300 inline-flex items-center justify-center text-xs">' + (state.lang === "es" ? "VER DETALLE" : "VIEW DETAIL") + '</a>';
    html += '</div>';
    html += '</div></div>';

    body.innerHTML = html;
    overlay.classList.remove("hidden");
    overlay.classList.add("flex");
    document.body.style.overflow = "hidden";

    // Set initial
    if (product.colors && product.colors.length > 0 && window.getComputedStyle) {
      updateQVStock(product.colors[0].name);
    }
  }

  function closeQuickView() {
    var overlay = document.getElementById("quickViewOverlay");
    if (!overlay) return;
    overlay.classList.add("hidden");
    overlay.classList.remove("flex");
    document.body.style.overflow = "";
    currentQuickViewProduct = null;
  }

  // Quick view add to cart
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-qv-add-to-cart]");
    if (!btn || !currentQuickViewProduct) return;
    var color = getQVSelectedColor() || "";
    var size = getQVSelectedSize() || "";
    if (!size) {
      if (window.VunoModal) {
        window.VunoModal.alert({
          type: "warning",
          title: state.lang === "es" ? "Selecciona una talla" : "Please select a size",
        });
      }
      return;
    }
    var p = currentQuickViewProduct;
    var productData = {
      id: p.id,
      name: p.name,
      slug: p.slug,
      price: p.price,
      currency: p.currency || "USD",
      display_price: p.display_price,
      display_currency: p.display_currency,
      display_symbol: p.display_symbol,
      images: p.images || [],
      category: p.category || "",
    };
    window.VunoCart.addItem(productData, 1, color, size);
    if (window.VunoToast) {
      window.VunoToast.success(state.lang === "es" ? "¡Añadido al carrito!" : "Added to cart!");
    }
    closeQuickView();
  });

  // =============================================================
  //                      WISHLIST TOGGLE
  // =============================================================

  function bindWishlistToggle() {
    document.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-toggle-wishlist]");
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();
      try {
        var product = JSON.parse(btn.dataset.product);
        var wasAdded = window.VunoWishlist.toggleItem(product);
        var icon = btn.querySelector(".wishlist-icon");
        if (icon) {
          icon.textContent = wasAdded ? "favorite" : "favorite_border";
          icon.style.fontVariationSettings = wasAdded ? "'FILL' 1" : "'FILL' 0";
        }
        if (wasAdded) btn.classList.add("in-wishlist");
        else btn.classList.remove("in-wishlist");
      } catch (err) {
        // silently fail
      }
    });
  }

  // =============================================================
  //                        RENDER
  // =============================================================

  function render() {
    var grid = document.getElementById("productGrid");
    var resultsCount = document.getElementById("resultsCount");
    var loadMoreBtn = document.getElementById("loadMoreBtn");
    var loadMoreWrapper = document.getElementById("loadMoreWrapper");
    if (!grid) return;

    var items = state.filtered;
    var showCount = Math.min(state.visibleCount, items.length);

    // Update results count
    if (resultsCount) {
      resultsCount.textContent = items.length + " " + (state.lang === "es" ? "resultados" : "results");
    }

    if (items.length === 0) {
      grid.innerHTML = '<div class="col-span-full text-center py-20"><p class="font-body-lg text-body-lg text-secondary">' + (state.lang === "es" ? "No se encontraron productos." : "No products found.") + '</p></div>';
      if (loadMoreWrapper) loadMoreWrapper.classList.add("hidden");
      return;
    }

    var html = "";
    for (var i = 0; i < showCount; i++) {
      var p = items[i];
      var img = p.images && p.images.length > 0 ? p.images[0] : "";
      var productJson = escapeAttr(JSON.stringify({
        id: p.id,
        name: p.name,
        slug: p.slug,
        price: p.price,
        currency: p.currency || "USD",
        display_price: p.display_price,
        display_currency: p.display_currency,
        display_symbol: p.display_symbol,
        images: [img],
        category: p.category || "",
      }));

      html += '<div class="group flex flex-col cursor-pointer product-card reveal" style="transition-delay:' + (0.05 * (i % 12)) + 's">';
      html += '<div class="aspect-[4/5] bg-surface-container mb-4 overflow-hidden relative">';
      html += '<div class="absolute inset-0 bg-monolith-black/5 opacity-0 group-hover:opacity-100 transition-opacity duration-500 z-10 pointer-events-none"></div>';
      html += '<a href="/' + state.lang + '/producto/' + encodeURIComponent(p.slug) + '" class="block w-full h-full overflow-hidden">';
      html += '<img src="' + escapeHtml(window.imgTransform(img, 400, 500)) + '" alt="' + escapeHtml(p.name) + '" class="w-full h-full object-cover object-center img-lift" loading="lazy" />';
      html += '</a>';
      html += '<button data-toggle-wishlist data-product=\'' + productJson + '\' class="absolute top-3 right-3 z-20 w-9 h-9 flex items-center justify-center bg-off-white/80 backdrop-blur-sm rounded-full hover:bg-off-white hover:scale-110 transition-all border border-outline-variant/30" aria-label="' + (state.lang === "es" ? "Añadir a lista de deseos" : "Add to wishlist") + '">';
      html += '<span class="material-symbols-outlined wishlist-icon text-[18px] text-monolith-black" style="font-variation-settings: \'FILL\' 0">favorite_border</span>';
      html += '</button>';
      html += '<div class="absolute bottom-4 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-all duration-300 z-20 hidden md:block translate-y-2 group-hover:translate-y-0">';
      html += '<button data-quick-view="' + escapeHtml(p.slug) + '" class="bg-off-white/90 backdrop-blur-sm px-6 py-2 font-label-caps text-label-caps text-monolith-black hover:bg-monolith-black hover:text-off-white transition-all border border-outline-variant whitespace-nowrap">' + (state.lang === "es" ? "VISTA RÁPIDA" : "QUICK VIEW") + '</button>';
      html += '</div>';
      html += '</div>';
      html += '<a href="/' + state.lang + '/producto/' + encodeURIComponent(p.slug) + '" class="block">';
      html += '<h3 class="font-body-md text-body-md text-monolith-black group-hover:text-clay-accent transition-colors duration-300">' + escapeHtml(p.name) + '</h3>';
      html += '<p class="font-price-display text-price-display text-secondary mt-1">' + (p.display_symbol || '$') + (p.display_price || p.price).toFixed(2) + '</p>';
      if (p.colors && p.colors.length > 0) {
        html += '<div class="flex gap-2 mt-3">';
        p.colors.forEach(function (c) {
          html += '<span class="w-3 h-3 rounded-full border border-surface-variant transition-transform duration-300 group-hover:scale-110" style="background-color:' + escapeHtml(c.hex) + '"></span>';
        });
        html += '</div>';
      }
      html += '</a>';
      html += '</div>';
    }

    grid.innerHTML = html;

    // Load More button
    if (loadMoreBtn && loadMoreWrapper) {
      if (showCount < items.length) {
        loadMoreWrapper.classList.remove("hidden");
      } else {
        loadMoreWrapper.classList.add("hidden");
      }
    }

    // Trigger reveal animations
    requestAnimationFrame(function () {
      Array.from(grid.querySelectorAll(".reveal")).forEach(function (el) {
        el.classList.add("revealed");
      });
    });
  }

  // =============================================================
  //                       HELPERS
  // =============================================================

  function escapeHtml(str) {
    if (typeof str !== "string") return str || "";
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
  }

  function escapeAttr(str) {
    if (typeof str !== "string") return str || "";
    return str.replace(/&/g, "&amp;").replace(/'/g, "&#39;").replace(/"/g, "&quot;");
  }

  // =============================================================
  //                       EXPORT
  // =============================================================

  window.CatalogUI = {
    init: init,
  };
})();
