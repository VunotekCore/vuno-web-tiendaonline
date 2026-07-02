(function () {
  "use strict";

  var translations = {};
  var currentLang = "es";
  var currentSlug = "";

  window.ProductDetail = {
    init: function (slug, lang, i18n) {
      currentSlug = slug;
      currentLang = lang || "es";
      if (i18n) {
        Object.keys(i18n).forEach(function (k) {
          translations[k] = i18n[k];
        });
      }

      if (!currentSlug) {
        showError();
        return;
      }

      var container = document.getElementById("productApp");
      var skeleton = document.getElementById("productSkeleton");
      var errorEl = document.getElementById("productError");

      function showError() {
        if (skeleton) skeleton.classList.add("hidden");
        if (errorEl) errorEl.classList.remove("hidden");
      }

      function showContent() {
        if (skeleton) skeleton.classList.add("hidden");
      }

      window.__api
        .get("/api/productos/get.php", {
          params: { slug: currentSlug, lang: currentLang },
        })
        .then(function (r) {
          return r.data;
        })
        .then(function (product) {
          if (!product) {
            showError();
            return;
          }
          showContent();
          renderProduct(product, currentLang);
        })
        .catch(function () {
          showError();
        });
    },
  };

  // =============================================================
  //                      RENDER PRODUCT
  // =============================================================

  function renderProduct(product, lang) {
    var images = product.images || [];
    var colors = product.colors || [];
    var sizes = product.sizes || [];
    var variants = product.variants || [];
    var totalStock = product.totalStock || 0;
    var colorGalleryMap = product.imagesByColor || {};

    var galleryMap = {};
    for (var ci = 0; ci < colors.length; ci++) {
      var cName = colors[ci].name;
      var perColor = colorGalleryMap[cName] || [];
      galleryMap[cName] = perColor.length ? perColor.slice() : images.slice();
    }

    var currentImages = images.slice();
    var currentImageIndex = 0;
    var selectedColor = colors.length > 0 ? colors[0].name || "" : "";
    var selectedSize = "";
    var addToCartTimer = null;

    var galleryHTML = buildGalleryHTML(images);
    var infoHTML = buildInfoHTML(product, colors, sizes, lang);
    var lightboxHTML = buildLightboxHTML();
    var reviewsSectionHTML = buildReviewsSectionHTML();

    var container = document.getElementById("productApp");
    container.innerHTML =
      galleryHTML + lightboxHTML + reviewsSectionHTML + buildRelatedHTML(product);

    var infoInner = document.getElementById("productInfoInner");
    if (infoInner) infoInner.innerHTML = infoHTML;

    initRelatedProducts(lang);

    var mainImage = document.getElementById("mainImage");
    var thumbnailStrip = document.getElementById("thumbnailStrip");
    var imageCounter = document.getElementById("imageCounter");
    var magnifierLens = document.getElementById("magnifierLens");
    var mainImageWrap = document.getElementById("mainImageWrap");
    var colorSelector = document.getElementById("colorSelector");
    var sizeSelector = document.getElementById("sizeSelector");
    var selectedColorName = document.getElementById("selectedColorName");
    var stockInfo = document.getElementById("stockInfo");
    var stockDot = document.getElementById("stockDot");
    var stockText = document.getElementById("stockText");
    var addToCartBtn = document.getElementById("addToCartBtn");
    var addToCartText = document.getElementById("addToCartText");
    var addToCartSpinner = document.getElementById("addToCartSpinner");
    var lightboxEl = document.getElementById("lightbox");
    var lightboxImg = document.getElementById("lightboxImg");
    var lightboxCounterEl = document.getElementById("lightboxCounter");
    var zoomBtn = document.getElementById("zoomBtn");

    // =============================================================
    //                      GALLERY
    // =============================================================

    function updateGallery(imgArray, index) {
      currentImages = imgArray;
      currentImageIndex = Math.min(index, imgArray.length - 1);
      renderMainImage();
      renderThumbnails();
      renderCounter();
      updateMagnifier();
    }

    function renderMainImage() {
      if (mainImage && currentImages[currentImageIndex]) {
        mainImage.src = window.imgTransform(
          currentImages[currentImageIndex],
          800,
          1000
        );
        mainImage.classList.remove("opacity-100");
        mainImage.classList.add("opacity-0");
        requestAnimationFrame(function () {
          mainImage.classList.remove("opacity-0");
          mainImage.classList.add("opacity-100");
        });
      }
    }

    function renderThumbnails() {
      if (!thumbnailStrip) return;
      thumbnailStrip.innerHTML = currentImages
        .map(function (src, i) {
          var active = i === currentImageIndex ? "active" : "";
          return (
            '<button data-thumb="' +
            i +
            '" class="' +
            active +
            '">' +
            '<img src="' +
            window.imgTransform(src, 80, 100) +
            '" alt="" width="80" height="100" class="w-full h-full object-cover" loading="lazy" />' +
            "</button>"
          );
        })
        .join("");

      thumbnailStrip.querySelectorAll("[data-thumb]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var idx = parseInt(this.dataset.thumb);
          if (!isNaN(idx) && idx >= 0 && idx < currentImages.length) {
            currentImageIndex = idx;
            renderMainImage();
            renderThumbnails();
            renderCounter();
          }
        });
      });
    }

    function renderCounter() {
      if (imageCounter) {
        imageCounter.innerHTML =
          '<span class="bg-off-white/90 backdrop-blur-sm px-3 py-1.5 font-label-caps text-label-caps text-monolith-black text-xs">' +
          String(currentImageIndex + 1).padStart(2, "0") +
          " / " +
          String(currentImages.length).padStart(2, "0") +
          "</span>";
      }
    }

    // =============================================================
    //                      MAGNIFIER LENS
    // =============================================================

    var zoomFactor = 2.5;
    var lensSize = 160;

    function updateMagnifier() {
      if (!magnifierLens || !currentImages[currentImageIndex]) return;
      magnifierLens.style.backgroundImage =
        "url(" +
        window.imgTransform(currentImages[currentImageIndex], 1200, 1500) +
        ")";
      magnifierLens.style.backgroundSize =
        lensSize * zoomFactor + "px " + lensSize * zoomFactor + "px";
    }

    if (magnifierLens && mainImageWrap) {
      updateMagnifier();

      mainImageWrap.addEventListener("mousemove", function (e) {
        var rect = this.getBoundingClientRect();
        var x = e.clientX - rect.left;
        var y = e.clientY - rect.top;
        var half = lensSize / 2;
        var left = Math.max(0, Math.min(x - half, rect.width - lensSize));
        var top = Math.max(0, Math.min(y - half, rect.height - lensSize));
        var pctX = left / (rect.width - lensSize) * 100;
        var pctY = top / (rect.height - lensSize) * 100;
        magnifierLens.style.left = left + "px";
        magnifierLens.style.top = top + "px";
        magnifierLens.style.backgroundPosition = pctX + "% " + pctY + "%";
      });

      mainImageWrap.addEventListener("mouseleave", function () {
        magnifierLens.style.opacity = "0";
      });

      mainImageWrap.addEventListener("mouseenter", function () {
        magnifierLens.style.opacity = "1";
      });
    }

    // =============================================================
    //                   COLOR SELECTOR
    // =============================================================

    if (colorSelector) {
      colorSelector.addEventListener("click", function (e) {
        var btn = e.target.closest(".color-swatch");
        if (!btn) return;
        colorSelector.querySelectorAll(".color-swatch").forEach(function (b) {
          b.className =
            "color-swatch w-7 h-7 rounded-full transition-all duration-300 border border-outline-variant hover:border-monolith-black hover:scale-110";
          b.classList.remove(
            "ring-1",
            "ring-offset-2",
            "ring-monolith-black",
            "ring-offset-background",
            "scale-110"
          );
        });
        btn.classList.add(
          "ring-1",
          "ring-offset-2",
          "ring-monolith-black",
          "ring-offset-background",
          "scale-110"
        );
        selectedColor = btn.dataset.color;
        if (selectedColorName) selectedColorName.textContent = selectedColor;

        var colorImages = galleryMap[selectedColor] || images;
        updateGallery(colorImages, 0);
        updateStockDisplay();
      });
    }

    // =============================================================
    //                      SIZE SELECTOR
    // =============================================================

    if (sizeSelector) {
      sizeSelector.addEventListener("click", function (e) {
        var btn = e.target.closest(".size-btn");
        if (!btn || btn.disabled) return;
        sizeSelector.querySelectorAll(".size-btn").forEach(function (b) {
          if (b.disabled) return;
          b.classList.remove(
            "bg-monolith-black",
            "text-off-white",
            "border-monolith-black"
          );
          b.classList.add(
            "border-outline-variant",
            "text-monolith-black",
            "hover:border-monolith-black",
            "hover:bg-monolith-black",
            "hover:text-off-white"
          );
        });
        btn.classList.add(
          "bg-monolith-black",
          "text-off-white",
          "border-monolith-black"
        );
        btn.classList.remove(
          "border-outline-variant",
          "hover:border-monolith-black",
          "hover:bg-monolith-black",
          "hover:text-off-white"
        );
        selectedSize = btn.dataset.size;
        updateStockDisplay();
      });
    }

    // =============================================================
    //                      STOCK DISPLAY
    // =============================================================

    function updateStockDisplay() {
      if (!stockInfo || !stockDot || !stockText) return;
      var qty = 0;

      if (selectedColor && selectedSize) {
        for (var i = 0; i < variants.length; i++) {
          var v = variants[i];
          if (v.color_name === selectedColor && v.size_value === selectedSize) {
            qty = v.stock;
            break;
          }
        }
      } else if (selectedColor) {
        for (var i = 0; i < variants.length; i++) {
          if (variants[i].color_name === selectedColor)
            qty += variants[i].stock;
        }
      } else if (selectedSize) {
        for (var i = 0; i < sizes.length; i++) {
          if (sizes[i].value === selectedSize) {
            qty = sizes[i].stock;
            break;
          }
        }
      } else {
        qty = totalStock;
      }

      stockInfo.classList.remove("hidden");

      if (qty > 5) {
        stockDot.className =
          "w-2 h-2 rounded-full inline-flex flex-shrink-0 bg-green-500";
        stockText.textContent = "En stock \u00b7 " + qty;
        stockText.className = "text-green-700";
      } else if (qty > 0) {
        stockDot.className =
          "w-2 h-2 rounded-full inline-flex flex-shrink-0 bg-amber-500";
        stockText.textContent =
          qty === 1 ? "Solo queda 1" : "Solo quedan " + qty;
        stockText.className = "text-amber-700";
      } else {
        stockDot.className =
          "w-2 h-2 rounded-full inline-flex flex-shrink-0 bg-red-500";
        stockText.textContent = "Agotado";
        stockText.className = "text-error";
      }

      if (!addToCartBtn || !addToCartText) return;
      if (addToCartTimer) {
        clearTimeout(addToCartTimer);
        addToCartTimer = null;
      }
      if (addToCartBtn.dataset.processing === "1") return;
      if (addToCartSpinner) addToCartSpinner.style.display = "none";
      var outOfStock = selectedSize && qty <= 0;
      addToCartBtn.disabled = outOfStock;
      addToCartText.textContent = outOfStock
        ? "Agotado"
        : translations.addToCart || "Añadir al carrito";
    }

    // =============================================================
    //                      ADD TO CART
    // =============================================================

    if (addToCartBtn) {
      addToCartBtn.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (this.dataset.processing === "1" || this.disabled) return;
        if (!selectedSize) {
          window.VunoModal.alert({
            type: "warning",
            title: translations.selectSize || "Selecciona una talla",
          });
          return;
        }

        var stock = 0;
        for (var si = 0; si < variants.length; si++) {
          var sv = variants[si];
          if (
            sv.color_name === selectedColor &&
            sv.size_value === selectedSize
          ) {
            stock = sv.stock;
            break;
          }
        }

        if (stock <= 0) {
          window.VunoModal.alert({
            type: "warning",
            title: "Producto agotado",
          });
          return;
        }

        var cartItems = window.VunoCart.getItems();
        var cartQty = 0;
        for (var cii = 0; cii < cartItems.length; cii++) {
          if (
            cartItems[cii].product.id === product.id &&
            cartItems[cii].selectedColor === selectedColor &&
            cartItems[cii].selectedSize === selectedSize
          ) {
            cartQty += cartItems[cii].quantity;
          }
        }

        if (cartQty + 1 > stock) {
          window.VunoModal.alert({
            type: "warning",
            title:
              stock === 1
                ? "Solo queda 1 unidad"
                : "Solo quedan " + stock + " unidades",
          });
          this.disabled = true;
          if (addToCartText) addToCartText.textContent = "Agotado";
          return;
        }

        this.dataset.processing = "1";
        this.disabled = true;
        if (addToCartText) addToCartText.textContent = "";
        if (addToCartSpinner)
          addToCartSpinner.style.display = "inline-block";

        if (addToCartTimer) {
          clearTimeout(addToCartTimer);
          addToCartTimer = null;
        }

        var cartProduct = {
          id: product.id,
          name: product.name,
          slug: product.slug,
          price: product.price,
          currency: product.currency,
          display_price: product.display_price,
          display_currency: product.display_currency,
          display_symbol: product.display_symbol,
          images: [images[0]],
          category: product.category,
        };

        var self = this;
        setTimeout(function () {
          try {
            window.VunoCart.addItem(
              cartProduct,
              1,
              selectedColor,
              selectedSize
            );
          } catch (err) {
            console.error("Add to cart error:", err);
          }
          if (addToCartSpinner) addToCartSpinner.style.display = "none";
          if (addToCartText)
            addToCartText.textContent =
              translations.addedToCart || "¡Añadido al carrito!";
          addToCartTimer = setTimeout(function () {
            if (addToCartText)
              addToCartText.textContent =
                translations.addToCart || "Añadir al carrito";
            addToCartTimer = null;
          }, 1500);
          self.disabled = false;
          delete self.dataset.processing;
        }, 1000);
      });
    }

    // =============================================================
    //                      LIGHTBOX
    // =============================================================

    function openLightbox(index) {
      if (!lightboxEl || !lightboxImg || !lightboxCounterEl) return;
      currentImageIndex = Math.min(index, currentImages.length - 1);
      lightboxImg.src = currentImages[currentImageIndex];
      lightboxCounterEl.textContent =
        String(currentImageIndex + 1) + " / " + currentImages.length;
      lightboxEl.classList.remove("hidden");
      lightboxEl.classList.add("flex");
      document.body.style.overflow = "hidden";
    }

    function closeLightbox() {
      if (!lightboxEl) return;
      lightboxEl.classList.add("hidden");
      lightboxEl.classList.remove("flex");
      document.body.style.overflow = "";
    }

    function navigateLightbox(dir) {
      if (!lightboxImg || !lightboxCounterEl) return;
      currentImageIndex =
        (currentImageIndex + dir + currentImages.length) % currentImages.length;
      lightboxImg.src = currentImages[currentImageIndex];
      lightboxCounterEl.textContent =
        String(currentImageIndex + 1) + " / " + currentImages.length;
    }

    if (mainImageWrap) {
      mainImageWrap.addEventListener("click", function () {
        openLightbox(currentImageIndex);
      });
    }
    if (zoomBtn) {
      zoomBtn.addEventListener("click", function (e) {
        e.stopPropagation();
        openLightbox(currentImageIndex);
      });
    }
    var lightboxClose = document.getElementById("lightboxClose");
    var lightboxPrev = document.getElementById("lightboxPrev");
    var lightboxNext = document.getElementById("lightboxNext");

    if (lightboxClose)
      lightboxClose.addEventListener("click", closeLightbox);
    if (lightboxPrev)
      lightboxPrev.addEventListener("click", function () {
        navigateLightbox(-1);
      });
    if (lightboxNext)
      lightboxNext.addEventListener("click", function () {
        navigateLightbox(1);
      });

    if (lightboxEl) {
      lightboxEl.addEventListener("click", function (e) {
        if (e.target === lightboxEl) closeLightbox();
      });
    }

    document.addEventListener("keydown", function (e) {
      if (lightboxEl && !lightboxEl.classList.contains("hidden")) {
        if (e.key === "Escape") {
          closeLightbox();
          e.preventDefault();
        }
        if (e.key === "ArrowLeft") {
          navigateLightbox(-1);
          e.preventDefault();
        }
        if (e.key === "ArrowRight") {
          navigateLightbox(1);
          e.preventDefault();
        }
      }
    });

    // =============================================================
    //                      WISHLIST TOGGLE
    // =============================================================

    (function () {
      var btn = document.querySelector("[data-toggle-wishlist]");
      if (!btn) return;
      var wp = JSON.parse(btn.dataset.product || "{}");
      if (window.VunoWishlist && window.VunoWishlist.isInWishlist(wp.id)) {
        var icon = btn.querySelector(".wishlist-icon");
        if (icon) {
          icon.textContent = "favorite";
          icon.style.fontVariationSettings = "'FILL' 1";
        }
        btn.classList.add("in-wishlist");
      }
    })();

    // =============================================================
    //                SHIPPING & RETURNS
    // =============================================================

    (function () {
      window.__api
        .get("/api/configuracion/public.php")
        .then(function (r) {
          return r.data;
        })
        .then(function (config) {
          if (!config || !config.policies) return;
          var shippingEl = document.querySelector("[data-policy-shipping]");
          var returnsEl = document.querySelector("[data-policy-returns]");
          var shippingKey = "shipping_" + lang;
          var returnsKey = "returns_" + lang;
          if (shippingEl && config.policies[shippingKey]) {
            shippingEl.textContent = config.policies[shippingKey];
          }
          if (returnsEl && config.policies[returnsKey]) {
            returnsEl.textContent = config.policies[returnsKey];
          }
        })
        .catch(function () {});
    })();

    // =============================================================
    //                    SIZE GUIDE
    // =============================================================

    var sizeGuideBtn = document.getElementById("sizeGuideBtn");
    if (sizeGuideBtn) {
      sizeGuideBtn.addEventListener("click", function () {
        var btn = this;
        btn.disabled = true;

        function buildTable(data) {
          if (!data || !data.rows || !data.rows.length) return null;
          var title =
            lang === "es"
              ? data.title_es || data.title_en || "Size Guide"
              : data.title_en || data.title_es || "Size Guide";
          var footer = lang === "es" ? data.footer_es : data.footer_en;
          var html =
            '<h3 class="font-headline text-headline-md mb-4">' +
            title +
            "</h3>";
          html +=
            '<table class="w-full text-left border-collapse"><thead><tr class="border-b border-outline-variant">';
          html +=
            '<th class="font-label-caps text-label-caps text-secondary pb-2 pr-4">US</th>';
          html +=
            '<th class="font-label-caps text-label-caps text-secondary pb-2 pr-4">EU</th>';
          html +=
            '<th class="font-label-caps text-label-caps text-secondary pb-2 pr-4">UK</th>';
          html +=
            '<th class="font-label-caps text-label-caps text-secondary pb-2">CM</th>';
          html += "</tr></thead><tbody>";
          for (var i = 0; i < data.rows.length; i++) {
            var r = data.rows[i];
            var cls =
              i < data.rows.length - 1
                ? ' class="border-b border-outline-variant/50"'
                : "";
            html += "<tr" + cls + ">";
            html += '<td class="py-2 pr-4 font-medium">' + r.us + "</td>";
            html +=
              '<td class="py-2 pr-4 text-secondary">' + r.eu + "</td>";
            html +=
              '<td class="py-2 pr-4 text-secondary">' + r.uk + "</td>";
            html +=
              '<td class="py-2 text-secondary">' + r.cm + "</td>";
            html += "</tr>";
          }
          html += "</tbody></table>";
          if (footer) {
            html +=
              '<p class="font-body text-body-md text-secondary mt-4 text-sm">' +
              footer +
              "</p>";
          }
          return html;
        }

        window.__api
          .get("/api/size-guide/public.php")
          .then(function (r) {
            return r.data;
          })
          .then(function (data) {
            var html = buildTable(data);
            if (html && window.VunoModal && window.VunoModal.show) {
              window.VunoModal.show({
                type: "info",
                title:
                  lang === "es" ? "Gu\u00eda de Talles" : "Size Guide",
                body: html,
              });
            }
          })
          .catch(function () {})
          .finally(function () {
            btn.disabled = false;
          });
      });
    }

    // =============================================================
    //                      INIT GALLERY
    // =============================================================

    if (images.length > 0) {
      currentImages = images.slice();
      currentImageIndex = 0;
      renderMainImage();
      renderThumbnails();
      renderCounter();
      updateStockDisplay();
    }

    // =============================================================
    //                      INTERSECTION OBSERVER
    // =============================================================

    (function () {
      var observer = new IntersectionObserver(
        function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              entry.target.classList.add("revealed");
              observer.unobserve(entry.target);
            }
          });
        },
        { threshold: 0.1, rootMargin: "0px 0px -40px 0px" }
      );
      document.querySelectorAll(".reveal").forEach(function (el) {
        observer.observe(el);
      });
    })();

    // =============================================================
    //                      REVIEWS SYSTEM
    // =============================================================

    (function () {
      function starsHtml(rating, filled) {
        if (filled === undefined) filled = true;
        var color = filled ? "text-clay-accent" : "text-outline";
        return Array(5)
          .fill(0)
          .map(function (_, i) {
            return (
              '<span class="material-symbols-outlined text-sm ' +
              (i < rating ? color : "text-outline") +
              '" style="font-variation-settings: \'FILL\' ' +
              (i < rating ? 1 : 0) +
              '">star</span>'
            );
          })
          .join("");
      }

      function renderStats(stats) {
        var countLabel =
          stats.total === 1
            ? translations.reviewsReview || "reseña"
            : translations.reviewsReviews || "reseñas";
        var reviewStarsEl = document.getElementById("reviewStars");
        var reviewCountEl = document.getElementById("reviewCount");
        if (reviewStarsEl) {
          reviewStarsEl.innerHTML =
            stats.total > 0
              ? starsHtml(Math.round(stats.average))
              : starsHtml(0);
        }
        if (reviewCountEl) {
          reviewCountEl.textContent =
            stats.total > 0
              ? stats.average +
                " " +
                (translations.reviewsAverage || "promedio") +
                " \u00b7 " +
                stats.total +
                " " +
                countLabel
              : "0 " + countLabel;
        }
      }

      function renderReviews(reviews) {
        var el = document.getElementById("reviewsList");
        if (!el) return;
        if (reviews.length === 0) {
          el.innerHTML =
            '<div class="col-span-full text-center py-8"><p class="font-body-md text-body-md text-secondary">' +
            (translations.reviewsEmpty || "No hay reseñas aún") +
            "</p></div>";
          return;
        }
        el.innerHTML = reviews
          .map(function (r) {
            return (
              '<div class="border border-outline-variant p-4 hover:border-monolith-black transition-colors duration-300">' +
              '<div class="flex items-center gap-2 mb-2">' +
              '<div class="flex items-center gap-0.5">' +
              starsHtml(r.rating) +
              "</div>" +
              '<span class="font-label-caps text-label-caps text-secondary text-xs">' +
              (r.reviewerName ||
                translations.reviewsAnonymous || "Anónimo") +
              "</span>" +
              "</div>" +
              (r.title
                ? '<p class="font-body-md text-body-md text-monolith-black font-medium mb-1">' +
                  r.title +
                  "</p>"
                : "") +
              (r.comment
                ? '<p class="font-body-md text-body-md text-secondary">' +
                  r.comment +
                  "</p>"
                : "") +
              "</div>"
            );
          })
          .join("");
      }

      async function loadReviews() {
        try {
          var res = await window.__api.get("/api/resenas/list.php", {
            params: { product_id: product.id },
          });
          var result = res.data;
          renderStats(result.stats);
          renderReviews(result.reviews);
        } catch (err) {
          var el = document.getElementById("reviewsList");
          if (el) {
            el.innerHTML =
              '<div class="col-span-full text-center py-8"><p class="font-body-md text-body-md text-error">' +
              (translations.reviewsError || "Error al cargar reseñas") +
              "</p></div>";
          }
        }
      }

      var selectedRating = 0;

      function bindReviewStars() {
        document.querySelectorAll(".review-star").forEach(function (btn) {
          btn.addEventListener("click", function () {
            selectedRating = parseInt(btn.dataset.reviewStar);
            var ratingInput = document.getElementById("reviewRating");
            if (ratingInput) ratingInput.value = selectedRating;
            document
              .querySelectorAll(".review-star")
              .forEach(function (s, i) {
                var icon = s.querySelector(".material-symbols-outlined");
                if (icon) {
                  if (i < selectedRating) {
                    icon.style.fontVariationSettings = "'FILL' 1";
                    s.classList.remove("text-outline");
                    s.classList.add("text-clay-accent");
                  } else {
                    icon.style.fontVariationSettings = "'FILL' 0";
                    s.classList.remove("text-clay-accent");
                    s.classList.add("text-outline");
                  }
                }
              });
          });
        });
      }

      var reviewForm = document.getElementById("reviewForm");
      if (reviewForm) {
        bindReviewStars();

        reviewForm.addEventListener("submit", async function (e) {
          e.preventDefault();
          var msg = document.getElementById("reviewMsg");
          var nameEl = document.getElementById("reviewName");
          var ratingVal = selectedRating;
          if (!nameEl || !nameEl.value.trim()) {
            if (msg) {
              msg.textContent =
                translations.reviewsNameRequired ||
                "El nombre es obligatorio";
              msg.className =
                "font-body-md text-body-md text-sm text-error block";
            }
            return;
          }
          if (ratingVal < 1) {
            if (msg) {
              msg.textContent =
                translations.reviewsRatingRequired ||
                "Selecciona una puntuación";
              msg.className =
                "font-body-md text-body-md text-sm text-error block";
            }
            return;
          }
          var submitBtn = document.getElementById("reviewSubmitBtn");
          if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent =
              translations.reviewsSending || "Enviando...";
          }
          try {
            var res = await window.__api.post(
              "/api/resenas/create.php",
              {
                product_id: product.id,
                reviewer_name: nameEl.value.trim(),
                reviewer_email:
                  (document.getElementById("reviewEmail") || {}).value ||
                  "",
                rating: ratingVal,
                title:
                  (document.getElementById("reviewTitle") || {}).value ||
                  "",
                comment:
                  (document.getElementById("reviewComment") || {})
                    .value || "",
              }
            );
            var result = res.data;
            if (result.error) throw new Error(result.error);
            if (msg) {
              msg.textContent =
                translations.reviewsSuccess || "Reseña enviada";
              msg.className =
                "font-body-md text-body-md text-sm text-monolith-black block";
            }
            var form = document.getElementById("reviewForm");
            if (form) form.reset();
            selectedRating = 0;
            var ratingInput = document.getElementById("reviewRating");
            if (ratingInput) ratingInput.value = 0;
            document
              .querySelectorAll(
                ".review-star .material-symbols-outlined"
              )
              .forEach(function (icon) {
                icon.style.fontVariationSettings = "'FILL' 0";
              });
            document
              .querySelectorAll(".review-star")
              .forEach(function (s) {
                s.classList.remove("text-clay-accent");
                s.classList.add("text-outline");
              });
            loadReviews();
          } catch (err) {
            if (msg) {
              msg.textContent =
                err.message ||
                (translations.reviewsError ||
                  "Error al enviar reseña");
              msg.className =
                "font-body-md text-body-md text-sm text-error block";
            }
          } finally {
            if (submitBtn) {
              submitBtn.disabled = false;
              submitBtn.textContent =
                translations.reviewsSubmit || "Enviar reseña";
            }
          }
        });
      }

      loadReviews();
    })();
  }

  // =============================================================
  //                HTML BUILDERS
  // =============================================================

  function buildGalleryHTML(images) {
    return (
      '<section class="grid grid-cols-1 md:grid-cols-12 gap-gutter relative">' +
      '<div class="md:col-span-7 relative" id="galleryColumn">' +
      '<div class="md:sticky md:top-20 md:pb-section-gap">' +
      '<div class="flex gap-2">' +
      '<div class="flex-1 min-w-0">' +
      '<div class="w-full bg-surface-container overflow-hidden relative cursor-zoom-in group flex items-center justify-center" id="mainImageWrap" style="max-height: min(65dvh, 550px); height: min(65dvh, 550px);">' +
      '<img id="mainImage" src="' +
      window.imgTransform(images[0] || "", 600, 600) +
      '" alt="" width="600" height="600" class="max-w-full max-h-full object-contain transition-all duration-500" style="view-transition-name: product-main" />' +
      '<div class="absolute inset-0 bg-monolith-black/0 group-hover:bg-monolith-black/5 transition-colors duration-300"></div>' +
      '<div class="product-magnifier" id="magnifierLens"></div>' +
      '<div id="imageCounter" class="absolute top-4 left-4 z-10"><span class="bg-off-white/90 backdrop-blur-sm px-3 py-1.5 font-label-caps text-label-caps text-monolith-black text-xs">01 / ' +
      String(images.length).padStart(2, "0") +
      '</span></div>' +
      '<button id="zoomBtn" class="absolute bottom-4 right-4 z-10 w-9 h-9 bg-off-white/80 backdrop-blur-sm flex items-center justify-center hover:bg-off-white transition-all border border-outline-variant/30" aria-label="Zoom"><span class="material-symbols-outlined text-lg">fullscreen</span></button>' +
      "</div>" +
      "</div>" +
      '<div class="flex gallery-vertical-thumbs" id="thumbnailStrip"></div>' +
      "</div>" +
      "</div>" +
      "</div>" +
      '<div class="md:col-span-4 md:col-start-9 relative" id="productInfo">' +
      '<div class="sticky top-20" id="productInfoInner"></div>' +
      "</div>" +
      "</section>"
    );
  }

  function buildInfoHTML(product, colors, sizes, lang) {
    var html = "";

    html +=
      '<nav class="flex items-center gap-2 font-label-caps text-label-caps text-secondary">';
    html +=
      '<a href="/' +
      lang +
      '/catalogo" class="hover:text-monolith-black transition-colors">' +
      (translations.collection || "COLECCIÓN") +
      "</a>";
    html += '<span class="text-outline">/</span>';
    html +=
      '<span class="text-monolith-black">' +
      escapeHtml(product.category || "") +
      "</span>";
    html += "</nav>";

    html += '<div class="flex flex-col gap-2">';
    html +=
      '<h1 class="font-headline-lg-mobile md:font-headline-lg text-headline-lg-mobile md:text-headline-lg text-monolith-black leading-tight">' +
      escapeHtml(product.name || "") +
      "</h1>";
    var displayPrice = product.display_symbol || "$";
    displayPrice += (
      product.display_price || product.price || 0
    ).toFixed(2);
    var displayCurrency =
      product.display_currency || product.currency || "USD";
    html +=
      '<p class="font-price-display text-price-display text-monolith-black mt-2">' +
      displayPrice +
      " " +
      displayCurrency +
      "</p>";
    html += "</div>";

    html += '<div class="flex flex-col gap-3">';
    html +=
      '<span class="font-label-caps text-label-caps text-secondary tracking-widest">' +
      (translations.colorLabel || "COLOR") +
      ': <span class="text-monolith-black ml-1" id="selectedColorName">' +
      (colors.length > 0 ? escapeHtml(colors[0].name) : "") +
      "</span></span>";
    html += '<div class="flex gap-3" id="colorSelector">';
    for (var ci = 0; ci < colors.length; ci++) {
      var activeClass =
        ci === 0
          ? "ring-1 ring-offset-2 ring-monolith-black ring-offset-background scale-110"
          : "border border-outline-variant hover:border-monolith-black";
      html +=
        '<button data-color="' +
        escapeHtml(colors[ci].name) +
        '" aria-label="' +
        escapeHtml(colors[ci].name) +
        '" class="color-swatch w-7 h-7 rounded-full transition-all duration-300 hover:scale-110 ' +
        activeClass +
        '" style="background-color: ' +
        (colors[ci].hex || "#000") +
        '"></button>';
    }
    html += "</div></div>";

    html += '<div class="flex flex-col gap-3">';
    html += '<div class="flex justify-between items-end">';
    html +=
      '<span class="font-label-caps text-label-caps text-secondary tracking-widest">' +
      (translations.sizeLabel || "TALLA") +
      " " +
      (product.size_prefix || "EU") +
      "</span>";
    html +=
      '<button id="sizeGuideBtn" class="font-body-md text-body-md text-secondary underline hover:text-monolith-black transition-colors text-sm">' +
      (translations.sizeGuideLabel || "Guía de talles") +
      "</button>";
    html += "</div>";
    html += '<div class="grid grid-cols-4 gap-2" id="sizeSelector">';
    for (var si = 0; si < sizes.length; si++) {
      if (sizes[si].inStock) {
        html +=
          '<button data-size="' +
          escapeHtml(sizes[si].value) +
          '" class="size-btn py-3 px-1 border border-outline-variant text-center font-label-caps text-label-caps text-monolith-black hover:border-monolith-black hover:bg-monolith-black hover:text-off-white transition-all duration-300">' +
          escapeHtml(sizes[si].label) +
          "</button>";
      } else {
        html +=
          '<button data-size="' +
          escapeHtml(sizes[si].value) +
          '" class="size-btn py-3 px-1 border border-outline-variant text-center font-label-caps text-label-caps text-outline opacity-50 cursor-not-allowed line-through" disabled>' +
          escapeHtml(sizes[si].label) +
          "</button>";
      }
    }
    html += "</div></div>";

    html += '<div id="stockInfo" class="hidden mt-2">';
    html +=
      '<span class="inline-flex items-center gap-2 px-3 py-1.5 border border-outline-variant bg-off-white/80 rounded-sm font-label-caps text-label-caps text-xs" id="stockBadge">';
    html +=
      '<span class="w-2 h-2 rounded-full inline-flex flex-shrink-0" id="stockDot"></span>';
    html += '<span id="stockText"></span>';
    html += "</span></div>";

    html += '<div class="flex gap-3 mt-4">';
    html +=
      '<button id="addToCartBtn" class="flex-1 min-h-[56px] bg-monolith-black text-on-primary font-label-caps text-label-caps tracking-widest hover:bg-on-surface hover:tracking-[0.15em] transition-all duration-300 flex items-center justify-center gap-2">';
    html +=
      '<span id="addToCartText">' +
      (translations.addToCart || "Añadir al carrito") +
      "</span>";
    html +=
      '<span id="addToCartSpinner" class="material-symbols-outlined text-sm animate-spin" style="display: none; font-variation-settings: \'FILL\' 0">refresh</span>';
    html += "</button>";
    html +=
      '<button data-toggle-wishlist data-product=\'' +
      JSON.stringify({
        id: product.id,
        name: product.name,
        slug: product.slug,
        price: product.price,
        currency: product.currency,
        display_price: product.display_price,
        display_currency: product.display_currency,
        display_symbol: product.display_symbol,
        images: [(product.images || [])[0] || ""],
        category: product.category,
      }) +
      '\' class="w-[56px] min-h-[56px] border border-outline-variant flex items-center justify-center hover:border-monolith-black hover:bg-monolith-black/5 transition-all duration-300 group" aria-label="Wishlist">';
    html +=
      '<span class="material-symbols-outlined wishlist-icon text-2xl text-monolith-black group-hover:scale-110 transition-transform duration-300" style="font-variation-settings: \'FILL\' 0">favorite_border</span>';
    html += "</button></div>";

    html += '<div class="flex flex-col mt-8 border-t border-outline-variant">';
    html +=
      '<details class="border-b border-outline-variant group open:pb-5">';
    html +=
      '<summary class="w-full py-5 flex justify-between items-center text-left cursor-pointer list-none">';
    html +=
      '<span class="font-label-caps text-label-caps text-monolith-black tracking-widest">' +
      (translations.descriptionLabel || "DESCRIPCIÓN") +
      "</span>";
    html +=
      '<span class="material-symbols-outlined text-secondary transition-transform duration-300 group-open:rotate-45" data-icon="add">add</span>';
    html += "</summary>";
    html +=
      '<div class="pb-5 font-body-md text-body-md text-secondary leading-relaxed" data-product-description>' +
      escapeHtml(product.description || "") +
      "</div>";
    html += "</details>";

    html +=
      '<details class="border-b border-outline-variant group open:pb-5">';
    html +=
      '<summary class="w-full py-5 flex justify-between items-center text-left cursor-pointer list-none">';
    html +=
      '<span class="font-label-caps text-label-caps text-monolith-black tracking-widest">' +
      (translations.careLabel || "CUIDADOS") +
      "</span>";
    html +=
      '<span class="material-symbols-outlined text-secondary transition-transform duration-300 group-open:rotate-45" data-icon="add">add</span>';
    html += "</summary>";
    html +=
      '<div class="pb-5 font-body-md text-body-md text-secondary leading-relaxed">';
    html += '<ul class="list-disc pl-4 space-y-1" data-product-details>';
    if (product.details && product.details.length) {
      for (var di = 0; di < product.details.length; di++) {
        html += "<li>" + escapeHtml(product.details[di]) + "</li>";
      }
    }
    html += "</ul></div></details>";

    html +=
      '<details class="border-b border-outline-variant group open:pb-5">';
    html +=
      '<summary class="w-full py-5 flex justify-between items-center text-left cursor-pointer list-none">';
    html +=
      '<span class="font-label-caps text-label-caps text-monolith-black tracking-widest">' +
      (translations.shippingLabel || "ENVÍOS") +
      "</span>";
    html +=
      '<span class="material-symbols-outlined text-secondary transition-transform duration-300 group-open:rotate-45" data-icon="add">add</span>';
    html += "</summary>";
    html +=
      '<div class="pb-5 font-body-md text-body-md text-secondary leading-relaxed">';
    html +=
      '<p data-policy-shipping>' +
      (translations.shippingText || "Envíos a todo el país") +
      "</p>";
    html += '<p data-policy-returns class="mt-2"></p>';
    html += "</div></details></div>";

    return html;
  }

  function buildLightboxHTML() {
    return (
      '<div id="lightbox" class="hidden fixed inset-0 z-[200] bg-black/95 items-center justify-center">' +
      '<button id="lightboxClose" class="absolute top-6 right-6 z-10 w-10 h-10 flex items-center justify-center text-off-white/70 hover:text-off-white transition-colors" aria-label="Close"><span class="material-symbols-outlined text-3xl">close</span></button>' +
      '<button id="lightboxPrev" class="absolute left-4 md:left-8 top-1/2 -translate-y-1/2 z-10 w-10 h-10 flex items-center justify-center text-off-white/50 hover:text-off-white transition-colors"><span class="material-symbols-outlined text-3xl">chevron_left</span></button>' +
      '<button id="lightboxNext" class="absolute right-4 md:right-8 top-1/2 -translate-y-1/2 z-10 w-10 h-10 flex items-center justify-center text-off-white/50 hover:text-off-white transition-colors"><span class="material-symbols-outlined text-3xl">chevron_right</span></button>' +
      '<div class="w-full h-full flex items-center justify-center p-8 md:p-16">' +
      '<img id="lightboxImg" src="" alt="" width="600" height="600" class="max-w-full max-h-full object-contain select-none" />' +
      "</div>" +
      '<div class="absolute bottom-6 left-1/2 -translate-x-1/2 font-label-caps text-label-caps text-off-white/50" id="lightboxCounter"></div>' +
      "</div>"
    );
  }

  function buildReviewsSectionHTML() {
    return (
      '<section class="mt-section-gap w-full border-t border-outline-variant pt-12">' +
      '<div class="max-w-4xl">' +
      '<h2 class="font-headline-md text-headline-md text-monolith-black mb-2">' +
      (translations.reviewsTitle || "Reseñas") +
      "</h2>" +
      '<div id="reviewStats" class="flex items-center gap-4 mb-8">' +
      '<div class="flex items-center gap-1" id="reviewStars"></div>' +
      '<span class="font-body-md text-body-md text-secondary" id="reviewCount"></span>' +
      "</div>" +
      '<div id="reviewsList" class="grid grid-cols-1 md:grid-cols-2 gap-gutter mb-12"></div>' +
      '<div class="bg-surface-container-low p-6 md:p-8 max-w-lg">' +
      '<h3 class="font-label-caps text-label-caps text-monolith-black mb-4 tracking-widest">' +
      (translations.reviewsLeave || "Deja tu reseña") +
      "</h3>" +
      '<form id="reviewForm" class="flex flex-col gap-4">' +
      '<div class="grid grid-cols-2 gap-4">' +
      '<input type="text" id="reviewName" placeholder="' +
      (translations.reviewsName || "Nombre") +
      '" required class="w-full bg-transparent border-b border-outline pb-2 text-monolith-black focus:border-monolith-black focus:outline-none font-body-md text-body-md transition-colors" />' +
      '<input type="email" id="reviewEmail" placeholder="' +
      (translations.reviewsEmail || "Email") +
      '" class="w-full bg-transparent border-b border-outline pb-2 text-monolith-black focus:border-monolith-black focus:outline-none font-body-md text-body-md transition-colors" />' +
      "</div>" +
      '<div><div class="flex items-center gap-1 mb-2"><span class="font-body-md text-body-md text-secondary mr-2">' +
      (translations.reviewsRating || "Puntuación") +
      "</span>" +
      [1, 2, 3, 4, 5]
        .map(function (s) {
          return (
            '<button type="button" data-review-star="' +
            s +
            '" class="review-star text-2xl text-outline hover:text-clay-accent transition-colors cursor-pointer" aria-label="' +
            s +
            ' star"><span class="material-symbols-outlined" style="font-variation-settings: \'FILL\' 0">star</span></button>'
          );
        })
        .join("") +
      '</div><input type="hidden" id="reviewRating" value="0" /></div>' +
      '<input type="text" id="reviewTitle" placeholder="' +
      (translations.reviewsTitlePlaceholder ||
        "Título (opcional)") +
      '" class="w-full bg-transparent border-b border-outline pb-2 text-monolith-black focus:border-monolith-black focus:outline-none font-body-md text-body-md transition-colors" />' +
      '<textarea id="reviewComment" placeholder="' +
      (translations.reviewsComment || "Comentario") +
      '" rows="4" class="w-full bg-transparent border border-outline p-3 text-monolith-black focus:border-monolith-black focus:outline-none font-body-md text-body-md resize-none transition-colors"></textarea>' +
      '<div id="reviewMsg" class="hidden font-body-md text-body-md text-sm"></div>' +
      '<button type="submit" id="reviewSubmitBtn" class="bg-monolith-black text-on-primary font-label-caps text-label-caps h-12 px-8 hover:bg-on-surface hover:tracking-[0.15em] transition-all duration-300 self-start tracking-widest">' +
      (translations.reviewsSubmit || "Enviar reseña") +
      "</button>" +
      "</form></div></div></section>"
    );
  }

  function buildRelatedHTML(product) {
    return (
      '<section class="mt-section-gap w-full">' +
      '<div class="flex justify-between items-end mb-8 border-b border-outline-variant pb-4">' +
      '<h2 class="font-headline-md text-headline-md text-monolith-black">' +
      (translations.relatedTitle || "También te puede gustar") +
      "</h2>" +
      '<a href="/' +
      currentLang +
      '/catalogo" class="font-label-caps text-label-caps text-secondary hover:text-monolith-black transition-colors hidden md:block">' +
      (translations.relatedViewAll || "VER TODO") +
      "</a>" +
      "</div>" +
      '<div class="grid grid-cols-2 gap-x-gutter gap-y-12" id="relatedProductsGrid">' +
      '<p class="font-body-md text-body-md text-secondary col-span-full text-center py-8">' +
      (currentLang === "es"
        ? "Cargando productos relacionados..."
        : "Loading related products...") +
      "</p>" +
      "</div>" +
      "</section>"
    );
  }

  // =============================================================
  //                      RELATED PRODUCTS
  // =============================================================

  function initRelatedProducts(productLang) {
    window.__api
      .get("/api/productos/list.php", {
        params: { lang: productLang, limit: 4 },
      })
      .then(function (r) {
        return r.data;
      })
      .then(function (data) {
        if (!data || !data.items) return;
        var grid = document.getElementById("relatedProductsGrid");
        if (!grid) return;
        var filtered = data.items
          .filter(function (p) {
            return p.slug !== currentSlug;
          })
          .slice(0, 4);
        if (!filtered.length) {
          grid.innerHTML = "";
          return;
        }
        grid.innerHTML = filtered
          .map(function (p, i) {
            var hiddenClass = i === 3 ? "hidden md:flex" : "";
            var price =
              (p.display_symbol || "$") +
              (p.display_price || p.price || 0).toFixed(2) +
              " " +
              (p.display_currency || p.currency || "USD");
            return (
              '<a href="/' +
              productLang +
              "/producto/" +
              p.slug +
              '" class="group flex flex-col gap-4 product-card reveal ' +
              hiddenClass +
              '">' +
              '<div class="w-full bg-surface-container aspect-[3/4] overflow-hidden relative">' +
              '<img src="' +
              window.imgTransform(p.images[0] || "", 400, 500) +
              '" alt="' +
              escapeHtml(p.name || "") +
              '" width="400" height="500" class="w-full h-full object-cover img-lift" loading="lazy" />' +
              "</div>" +
              '<div class="flex flex-col gap-1">' +
              '<h3 class="font-body-md text-body-md text-monolith-black group-hover:text-clay-accent transition-colors duration-300">' +
              escapeHtml(p.name || "") +
              "</h3>" +
              '<p class="font-price-display text-price-display text-secondary">' +
              price +
              "</p>" +
              "</div>" +
              "</a>"
            );
          })
          .join("");
        var observer = new IntersectionObserver(
          function (entries) {
            entries.forEach(function (entry) {
              if (entry.isIntersecting) {
                entry.target.classList.add("revealed");
                observer.unobserve(entry.target);
              }
            });
          },
          { threshold: 0.1, rootMargin: "0px 0px -40px 0px" }
        );
        grid
          .querySelectorAll(".reveal")
          .forEach(function (el) {
            observer.observe(el);
          });
      })
      .catch(function () {});
  }

  // =============================================================
  //                      UTILITY
  // =============================================================

  function escapeHtml(str) {
    if (!str) return "";
    var div = document.createElement("div");
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }
})();
