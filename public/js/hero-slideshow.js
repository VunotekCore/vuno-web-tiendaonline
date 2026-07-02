(function () {
  "use strict";

  window.HeroSlideshow = {
    init: function (lang) {
      var l = lang || "es";

      window.__api
        .get("/api/productos/list.php", { params: { lang: l } })
        .then(function (r) {
          var items = r.data.items || r.data || [];
          var featured = items.filter(function (p) {
            return p.isFeatured;
          });
          if (featured.length === 0) featured = items.slice(0, 4);
          if (featured.length === 0) return;

          var container = document.getElementById("heroImageContainer");
          if (!container) return;

          // Clear skeleton
          var wrapContainer = container.querySelector("#heroSlidesWrap");
          if (!wrapContainer) return;
          wrapContainer.innerHTML = "";

          // Build slides
          featured.forEach(function (p, i) {
            var imgUrl = p.images && p.images[0]
              ? window.imgTransform(p.images[0], 800, 1200, "cm-pad_resize,bg-1A1A1A")
              : "";
            var wrap = document.createElement("div");
            wrap.className =
              "hero-slide-wrap absolute inset-0 bg-monolith-black transition-opacity duration-1000";
            wrap.style.opacity = i === 0 ? "1" : "0";
            if (i === 0) wrap.classList.add("is-active");
            wrap.innerHTML =
              '<img src="' +
              (imgUrl || "") +
              '" alt="' +
              escapeHtml(p.name) +
              '" class="w-full h-full object-cover object-center block" loading="' +
              (i === 0 ? "eager" : "lazy") +
              '" />';
            wrapContainer.appendChild(wrap);
          });

          // Rebuild dots
          var dotsContainer = document.getElementById("heroDots");
          if (dotsContainer) {
            dotsContainer.innerHTML = "";
            featured.forEach(function (_, i) {
              var dot = document.createElement("button");
              dot.className = "hero-dot w-2 h-2 rounded-full transition-all duration-300";
              dot.style.backgroundColor =
                i === 0 ? "#C18C7E" : "rgba(255,255,255,0.35)";
              if (i === 0) dot.style.transform = "scale(1.4)";
              dot.setAttribute("data-index", String(i));
              dot.setAttribute("aria-label", "Slide " + (i + 1));
              dotsContainer.appendChild(dot);
            });
          }

          // Build slidesData for CTA links
          var slidesData = featured.map(function (p) {
            return { url: "/" + l + "/producto/" + (p.slug || "") };
          });

          // Update data-slides attribute
          var section = document.getElementById("heroSlideshow");
          if (section) {
            section.setAttribute("data-slides", JSON.stringify(slidesData));
          }

          // Re-init slideshow
          initSlideshow(featured);
        })
        .catch(function () {
          // Silently fail, skeleton remains hidden
        });
    },
  };

  function initSlideshow(products) {
    var wraps = document.querySelectorAll(".hero-slide-wrap");
    var dots = document.querySelectorAll(".hero-dot");
    var cta = document.getElementById("heroCta");
    var section = document.getElementById("heroSlideshow");

    if (wraps.length < 1) return;

    var current = 0;
    var paused = false;
    var timer = null;

    var slidesData = [];
    try {
      slidesData = JSON.parse(section?.getAttribute("data-slides") || "[]");
    } catch (e) {
      slidesData = [];
    }

    function activateSlide(idx) {
      wraps.forEach(function (w, i) {
        var img = w.querySelector("img");
        if (i === idx) {
          w.style.opacity = "1";
          w.classList.remove("is-active");
          void w.offsetWidth;
          w.classList.add("is-active");
          if (img) {
            img.style.animation = "none";
            void img.offsetWidth;
            img.style.animation = "";
          }
        } else {
          w.style.opacity = "0";
          w.classList.remove("is-active");
          if (img) {
            img.style.animation = "none";
            img.style.transform = "";
          }
        }
      });

      dots.forEach(function (d, i) {
        d.style.backgroundColor =
          i === idx ? "#C18C7E" : "rgba(255,255,255,0.35)";
        d.style.transform = i === idx ? "scale(1.4)" : "scale(1)";
      });

      if (cta && slidesData[idx]) {
        cta.href = slidesData[idx].url;
      }

      current = idx;
    }

    function next() {
      if (!paused) activateSlide((current + 1) % wraps.length);
    }

    function start() {
      stop();
      timer = setInterval(next, 5000);
    }
    function stop() {
      if (timer !== null) {
        clearInterval(timer);
        timer = null;
      }
    }

    dots.forEach(function (d) {
      d.addEventListener("click", function () {
        var idx = parseInt(this.getAttribute("data-index") || "0", 10);
        activateSlide(idx);
        start();
      });
    });

    if (section) {
      section.addEventListener("mouseenter", function () {
        paused = true;
        stop();
      });
      section.addEventListener("mouseleave", function () {
        paused = false;
        start();
      });
    }

    activateSlide(0);
    start();
  }

  function escapeHtml(str) {
    if (!str) return "";
    var div = document.createElement("div");
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }
})();
