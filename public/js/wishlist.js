(function () {
  const STORAGE_KEY = "vuno_wishlist";

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

  function isLoggedIn() {
    return window.VunoAuth && window.VunoAuth.isLoggedIn();
  }

  function getCustomer() {
    return window.VunoAuth ? window.VunoAuth.getCustomer() : null;
  }

  async function addToServer(product) {
    if (!isLoggedIn()) return;
    try {
      await window.VunoAuth.authFetch("/api/wishlist/add.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ product_id: product.id }),
      });
    } catch {}
  }

  async function removeFromServer(productId) {
    if (!isLoggedIn()) return;
    try {
      await window.VunoAuth.authFetch("/api/wishlist/remove.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ product_id: productId }),
      });
    } catch {}
  }

  async function fetchServerWishlist() {
    if (!isLoggedIn()) return [];
    try {
      const res = await window.VunoAuth.authFetch("/api/wishlist/list.php");
      if (!res.ok) return [];
      const data = await res.json();
      return (data.items || []).map(function (si) {
        return {
          product: {
            id: si.productId,
            name: si.name,
            slug: si.slug,
            price: si.price,
            currency: si.currency,
            images: [],
            category: "",
          },
          addedAt: Date.parse(si.createdAt) || Date.now(),
        };
      });
    } catch {
      return [];
    }
  }

  async function syncFromServer() {
    if (!isLoggedIn()) return;
    const serverItems = await fetchServerWishlist();
    const localItems = getWishlist();

    const localIds = new Set(localItems.map(function (i) { return i.product.id; }));
    const serverIds = new Set(serverItems.map(function (i) { return i.product.id; }));
    var changed = false;

    // Add server items not in local
    serverItems.forEach(function (si) {
      if (!localIds.has(si.product.id)) {
        localItems.push(si);
        changed = true;
      }
    });

    // Push local items not in server
    localItems.forEach(function (li) {
      if (!serverIds.has(li.product.id)) {
        addToServer(li.product);
      }
    });

    if (changed) {
      saveWishlist(localItems);
    }
  }

  window.VunoWishlist = {
    getItems() {
      return getWishlist();
    },

    getCount() {
      return getWishlist().length;
    },

    isInWishlist(productId) {
      return getWishlist().some(function (item) { return item.product.id === productId; });
    },

    addItem(product) {
      var items = getWishlist();
      if (!items.some(function (item) { return item.product.id === product.id; })) {
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
        addToServer(product);
      }
      return items;
    },

    removeItem(productId) {
      var items = getWishlist().filter(function (item) { return item.product.id !== productId; });
      saveWishlist(items);
      removeFromServer(productId);
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

    sync: syncFromServer,
  };

  // Sync on auth change
  window.addEventListener("auth:changed", function (e) {
    if (e.detail && e.detail.customer) {
      syncFromServer();
    }
  });

  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-toggle-wishlist]");
    if (btn) {
      e.preventDefault();
      var product = JSON.parse(btn.dataset.product || "{}");
      var isNowIn = window.VunoWishlist.toggleItem(product);

      var icon = btn.querySelector(".wishlist-icon");
      if (icon) {
        icon.textContent = isNowIn ? "favorite" : "favorite_border";
        icon.style.fontVariationSettings = isNowIn ? "'FILL' 1" : "'FILL' 0";
      }
      btn.classList.toggle("in-wishlist", isNowIn);
    }
  });
})();
