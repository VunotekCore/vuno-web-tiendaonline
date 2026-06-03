(function () {
  const STORAGE_KEY = "ramlop_wishlist";

  function getWishlist() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch {
      return [];
    }
  }

  function saveWishlist(items) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    dispatchWishlistEvent();
  }

  function dispatchWishlistEvent() {
    window.dispatchEvent(new CustomEvent("wishlist:updated", { detail: getWishlist() }));
  }

  window.RamLopWishlist = {
    getItems() {
      return getWishlist();
    },

    getCount() {
      return getWishlist().length;
    },

    isInWishlist(productId) {
      return getWishlist().some((item) => item.product.id === productId);
    },

    addItem(product) {
      const items = getWishlist();
      if (!items.some((item) => item.product.id === product.id)) {
        items.push({
          product: {
            id: product.id,
            name: product.name,
            slug: product.slug,
            price: product.price,
            currency: product.currency,
            images: product.images,
            category: product.category,
          },
          addedAt: Date.now(),
        });
        saveWishlist(items);
      }
      return items;
    },

    removeItem(productId) {
      const items = getWishlist().filter((item) => item.product.id !== productId);
      saveWishlist(items);
      return items;
    },

    toggleItem(product) {
      if (this.isInWishlist(product.id)) {
        this.removeItem(product.id);
        return false;
      } else {
        this.addItem(product);
        return true;
      }
    },

    clear() {
      localStorage.removeItem(STORAGE_KEY);
      dispatchWishlistEvent();
    },
  };

  document.addEventListener("click", function (e) {
    const btn = e.target.closest("[data-toggle-wishlist]");
    if (btn) {
      e.preventDefault();
      const product = JSON.parse(btn.dataset.product || "{}");
      const isNowIn = window.RamLopWishlist.toggleItem(product);

      const icon = btn.querySelector(".wishlist-icon");
      if (icon) {
        icon.textContent = isNowIn ? "favorite" : "favorite_border";
        icon.style.fontVariationSettings = isNowIn ? "'FILL' 1" : "'FILL' 0";
      }
      btn.classList.toggle("in-wishlist", isNowIn);
    }
  });
})();
