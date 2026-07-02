(function () {
  "use strict";

  window.CategoryVitrines = {
    init: function (lang) {
      var l = lang || "es";

      window.__api
        .get("/api/productos/list.php", { params: { lang: l } })
        .then(function (r) {
          var items = r.data.items || r.data || [];
          if (!items.length) return;

          // Derive unique categories from products
          var seen = {};
          var categories = [];
          items.forEach(function (p) {
            var slug = p.category_slug;
            if (!slug || seen[slug]) return;
            seen[slug] = true;
            categories.push({
              name: p.category || slug,
              slug: slug,
              image: p.images && p.images[0] ? p.images[0] : "",
            });
          });

          if (!categories.length) return;
          var track = document.getElementById("categoryVitrinesTrack");
          if (!track) return;

          track.innerHTML = categories
            .map(function (cat, i) {
              var img = cat.image
                ? window.imgTransform(cat.image, 500, 650)
                : "";

              return (
                '<div class="snap-start shrink-0 w-[80vw] md:w-[38vw] lg:w-[30vw] xl:w-[calc(25%-1rem)]">' +
                '<a href="/' +
                l +
                "/catalogo?categoria=" +
                cat.slug +
                '" class="group relative aspect-[3/4] bg-surface-container overflow-hidden block">' +
                (img
                  ? '<img src="' +
                    img +
                    '" alt="' +
                    escapeHtml(cat.name) +
                    '" class="w-full h-full object-cover object-center transition-all duration-700 group-hover:scale-105" loading="' +
                    (i < 4 ? "eager" : "lazy") +
                    '" />'
                  : "") +
                '<div class="absolute inset-0 bg-gradient-to-t from-monolith-black/70 via-monolith-black/10 to-transparent opacity-80 group-hover:opacity-90 transition-opacity duration-500"></div>' +
                '<div class="absolute bottom-0 left-0 right-0 p-6 md:p-8">' +
                '<span class="font-label-caps text-label-caps text-clay-accent tracking-widest mb-2 block">' +
                (l === "es" ? "COLECCIÓN" : "COLLECTION") +
                '</span>' +
                '<h3 class="font-headline-sm text-headline-sm text-off-white">' +
                escapeHtml(cat.name) +
                "</h3>" +
                "</div>" +
                '<div class="absolute top-4 right-4 w-8 h-8 border border-off-white/30 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 translate-y-2 group-hover:translate-y-0">' +
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-off-white"><line x1="5" y1="19" x2="19" y2="5"/><polyline points="12 5 19 5 19 12"/></svg>' +
                "</div>" +
                "</a>" +
                "</div>"
              );
            })
            .join("");

          // Re-attach carousel arrows
          initCarousel();
        })
        .catch(function () {});
    },
  };

  function initCarousel() {
    var track = document.getElementById("categoryVitrinesTrack");
    if (!track) return;
    var container = track.parentElement;
    if (!container) return;
    var prev = container.querySelector(".carousel-prev");
    var next = container.querySelector(".carousel-next");
    if (!prev || !next) return;

    function updateArrows() {
      var maxScroll = track.scrollWidth - track.clientWidth;
      var atStart = track.scrollLeft <= 20;
      var atEnd = track.scrollLeft >= maxScroll - 20;

      prev.classList.toggle("opacity-0", atStart);
      prev.classList.toggle("pointer-events-none", atStart);
      next.classList.toggle("opacity-0", atEnd);
      next.classList.toggle("pointer-events-none", atEnd);
    }

    function getCardWidth() {
      var card = track.querySelector(".snap-start");
      return card ? card.offsetWidth + 16 : 300;
    }

    prev.addEventListener("click", function () {
      track.scrollBy({ left: -getCardWidth(), behavior: "smooth" });
    });

    next.addEventListener("click", function () {
      track.scrollBy({ left: getCardWidth(), behavior: "smooth" });
    });

    track.addEventListener("scroll", updateArrows);
    window.addEventListener("resize", updateArrows);
    updateArrows();
  }

  function escapeHtml(str) {
    if (!str) return "";
    var div = document.createElement("div");
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }
})();
