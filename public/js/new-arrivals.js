(function () {
  "use strict";

  window.NewArrivals = {
    init: function (lang) {
      var l = lang || "es";

      window.__api
        .get("/api/productos/list.php", { params: { lang: l } })
        .then(function (r) {
          var items = r.data.items || r.data || [];
          if (!items.length) return;

          var sorted = items.slice().sort(function (a, b) {
            return new Date(b.createdAt || 0).getTime() - new Date(a.createdAt || 0).getTime();
          });

          var top = sorted.slice(0, 8);
          renderProducts(top, l);
        })
        .catch(function () {
          // Silently fail — skeleton remains hidden, no error shown
        });
    },
  };

  function renderProducts(products, lang) {
    var grid = document.getElementById("newArrivalsGrid");
    if (!grid) return;

    grid.innerHTML = products
      .map(function (p, i) {
        var img = p.images && p.images[0] ? window.imgTransform(p.images[0], 480, 600) : "";
        var price =
          (p.display_symbol || "$") +
          (p.display_price || p.price || 0).toFixed(2);
        var colors = p.colors || [];
        var productJson = JSON.stringify({
          id: p.id,
          name: p.name,
          slug: p.slug,
          price: p.price,
          currency: p.currency || "USD",
          display_price: p.display_price,
          display_currency: p.display_currency,
          display_symbol: p.display_symbol,
          images: [p.images && p.images[0] || ""],
          category: p.category || "",
        });
        var hiddenClass = i === 0 ? "" : "reveal-delay-" + String(i + 1);

        return (
          '<div class="reveal ' +
          hiddenClass +
          '">' +
          '<div class="group flex flex-col cursor-pointer product-card">' +
          '<div class="relative bg-surface-container-lowest aspect-[4/5] overflow-hidden mb-4">' +
          '<a href="/' +
          lang +
          "/producto/" +
          p.slug +
          '" class="block w-full h-full overflow-hidden">' +
          (img
            ? '<img src="' +
              img +
              '" alt="' +
              escapeHtml(p.name) +
              '" width="480" height="600" class="w-full h-full object-cover object-center img-lift transition-transform duration-700 group-hover:scale-105" loading="lazy" />'
            : "") +
          "</a>" +
          '<div class="absolute inset-0 bg-gradient-to-t from-monolith-black/0 via-transparent to-monolith-black/0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>' +
          '<button data-toggle-wishlist data-product=\'' +
          productJson +
          '\' class="absolute top-3 right-3 z-20 w-9 h-9 flex items-center justify-center bg-off-white/80 backdrop-blur-sm rounded-full hover:bg-off-white transition-all hover:scale-110 border border-outline-variant/30" aria-label="Wishlist">' +
          '<span class="material-symbols-outlined wishlist-icon text-[18px] text-monolith-black" style="font-variation-settings: \'FILL\' 0">favorite_border</span>' +
          "</button>" +
          "</div>" +
          '<a href="/' +
          lang +
          "/producto/" +
          p.slug +
          '" class="block">' +
          '<h3 class="font-body-md text-body-md text-monolith-black mb-1 group-hover:text-clay-accent transition-colors duration-300">' +
          escapeHtml(p.name) +
          "</h3>" +
          '<p class="font-price-display text-price-display text-secondary">' +
          price +
          "</p>" +
          (colors.length > 0
            ? '<div class="flex gap-2 mt-3">' +
              colors
                .map(function (c) {
                  return (
                    '<span class="w-3 h-3 rounded-full border border-surface-variant transition-transform duration-300 group-hover:scale-110" style="background-color: ' +
                    c.hex +
                    '"></span>'
                  );
                })
                .join("") +
              "</div>"
            : "") +
          "</a>" +
          "</div>" +
          "</div>"
        );
      })
      .join("");

    // Re-run reveal observer for new elements
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
    grid.querySelectorAll(".reveal").forEach(function (el) {
      observer.observe(el);
    });

    // Sync wishlist hearts
    if (window.VunoWishlist) {
      grid.querySelectorAll("[data-toggle-wishlist]").forEach(function (btn) {
        var wp = JSON.parse(btn.dataset.product || "{}");
        if (window.VunoWishlist.isInWishlist(wp.id)) {
          var icon = btn.querySelector(".wishlist-icon");
          if (icon) {
            icon.textContent = "favorite";
            icon.style.fontVariationSettings = "'FILL' 1";
          }
          btn.classList.add("in-wishlist");
        }
      });
    }
  }

  function escapeHtml(str) {
    if (!str) return "";
    var div = document.createElement("div");
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }
})();
