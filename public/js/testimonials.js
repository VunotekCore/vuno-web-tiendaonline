(function () {
  "use strict";

  window.Testimonials = {
    init: function (lang) {
      var l = lang || "es";

      window.__api
        .get("/api/configuracion/public.php")
        .then(function (r) {
          var data = r.data;
          var sectionData =
            (data.landing && data.landing.testimonials) || {};
          if (sectionData.enabled === false) {
            var el = document.querySelector('[data-section="testimonials"]');
            if (el) el.style.display = "none";
            return;
          }

          // Update text fields
          var sectionEl = document.querySelector(
            '[data-section="testimonials"]'
          );
          if (sectionEl) {
            sectionEl.querySelectorAll("[data-field]").forEach(function (el) {
              var field = el.getAttribute("data-field");
              if (!field) return;
              var value = sectionData[field + "_" + l];
              if (!value) value = sectionData[field];
              if (value) el.textContent = value;
            });
          }

          var items = sectionData.items || [];
          var grid = document.getElementById("testimonialsGrid");
          if (!grid || !items.length) {
            if (grid) grid.style.display = "none";
            return;
          }

          grid.innerHTML = items
            .map(function (item, i) {
              var rating = item.rating || 5;
              var starsHtml = "";
              for (var j = 0; j < 5; j++) {
                var filled = j < rating;
                starsHtml +=
                  '<span class="material-symbols-outlined text-sm" style="font-variation-settings: \'FILL\' ' +
                  (filled ? 1 : 0) +
                  "; color: " +
                  (filled ? "#C18C7E" : "#D4CFC8") +
                  '">star</span>';
              }

              return (
                '<div class="border border-outline-variant p-6 md:p-8 h-full flex flex-col hover:border-monolith-black transition-colors duration-300 reveal" data-reveal-delay="' +
                (i + 1) +
                '">' +
                '<div class="flex items-center gap-1 mb-6">' +
                starsHtml +
                "</div>" +
                '<p class="font-body-md text-body-md text-secondary leading-relaxed flex-grow">"' +
                escapeHtml(item.text) +
                '"</p>' +
                '<p class="font-label-caps text-label-caps text-monolith-black mt-6 tracking-widest">\u2014 ' +
                escapeHtml(item.name) +
                "</p>" +
                "</div>"
              );
            })
            .join("");

          // Run reveal observer
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
            var delay = parseInt(el.getAttribute("data-reveal-delay") || "1");
            el.style.transitionDelay = delay * 0.1 + "s";
            observer.observe(el);
          });
        })
        .catch(function () {});
    },
  };

  function escapeHtml(str) {
    if (!str) return "";
    var div = document.createElement("div");
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }
})();
