(function () {
  const STORAGE_KEY = "vuno_cart";

  function getCart() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch {
      return [];
    }
  }

  function saveCart(items) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    localStorage.removeItem("vuno_cart_backup");
    dispatchCartEvent();
  }

  function restoreFromBackup() {
    try {
      const backup = localStorage.getItem("vuno_cart_backup");
      if (!backup) return;
      const items = JSON.parse(backup);
      if (Array.isArray(items) && items.length > 0) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
        localStorage.removeItem("vuno_cart_backup");
        dispatchCartEvent();
      }
    } catch {
      localStorage.removeItem("vuno_cart_backup");
    }
  }

  function dispatchCartEvent() {
    window.dispatchEvent(new CustomEvent("cart:updated", { detail: getCart() }));
  }

  function isLoggedIn() {
    return window.VunoAuth && window.VunoAuth.isLoggedIn();
  }

  // Server sync helpers
  async function pushToServer(items) {
    if (!isLoggedIn()) return;
    try {
      await window.VunoAuth.authFetch("/api/cart/sync.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ items: items }),
      });
    } catch {}
  }

  async function fetchServerCart() {
    if (!isLoggedIn()) return [];
    try {
      const res = await window.VunoAuth.authFetch("/api/cart/sync.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ items: [] }),
      });
      if (!res.ok) return [];
      const data = await res.json();
      return (data.items || []).map(function (si) {
        return {
          product: {
            id: si.product_id,
            name: si.name,
            slug: si.slug,
            price: parseFloat(si.price),
            currency: si.currency,
            images: [],
            category: "",
          },
          quantity: parseInt(si.quantity, 10),
          selectedColor: si.selected_color || "",
          selectedSize: si.selected_size || "",
        };
      });
    } catch {
      return [];
    }
  }

  // Key function: cart item identity (by product id + color + size)
  function itemKey(item) {
    return item.product.id + "||" + (item.selectedColor || "") + "||" + (item.selectedSize || "");
  }

  // Bidirectional merge
  function mergeCarts(local, server) {
    var merged = [];
    var seen = {};

    // Index local items
    local.forEach(function (li) {
      var key = itemKey(li);
      seen[key] = li;
    });

    // Add server items not in local, or take higher quantity
    server.forEach(function (si) {
      var key = itemKey(si);
      if (seen[key]) {
        seen[key].quantity = Math.max(seen[key].quantity, si.quantity);
      } else {
        seen[key] = si;
      }
    });

    for (var k in seen) {
      if (seen.hasOwnProperty(k)) merged.push(seen[k]);
    }
    return merged;
  }

  function pushAndSave(items) {
    saveCart(items);
    pushToServer(items);
  }

  window.VunoCart = {
    getItems() {
      return getCart();
    },

    getCount() {
      return getCart().reduce(function (sum, item) { return sum + item.quantity; }, 0);
    },

    addItem(product, quantity, selectedColor, selectedSize) {
      if (quantity === undefined) quantity = 1;
      if (selectedColor === undefined) selectedColor = "";
      if (selectedSize === undefined) selectedSize = "";
      var items = getCart();
      var existing = items.find(function (i) {
        return i.product.id === product.id && i.selectedColor === selectedColor && i.selectedSize === selectedSize;
      });

      if (existing) {
        existing.quantity += quantity;
      } else {
        items.push({
          product: {
            id: product.id,
            name: product.name,
            slug: product.slug,
            price: product.price,
            currency: product.currency,
            display_price: product.display_price,
            display_currency: product.display_currency,
            display_symbol: product.display_symbol,
            images: product.images,
            category: product.category,
          },
          quantity: quantity,
          selectedColor: selectedColor,
          selectedSize: selectedSize,
        });
      }

      pushAndSave(items);
      return items;
    },

    updateQuantity(productId, selectedColor, selectedSize, quantity) {
      var items = getCart();
      var item = items.find(function (i) {
        return i.product.id === productId && i.selectedColor === selectedColor && i.selectedSize === selectedSize;
      });

      if (item) {
        item.quantity = Math.max(1, quantity);
        pushAndSave(items);
      }
    },

    removeItem(productId, selectedColor, selectedSize) {
      var items = getCart().filter(function (i) {
        return !(i.product.id === productId && i.selectedColor === selectedColor && i.selectedSize === selectedSize);
      });
      pushAndSave(items);
      // Also remove from server directly
      if (isLoggedIn()) {
        window.VunoAuth.authFetch("/api/cart/remove.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ product_id: productId, color: selectedColor, size: selectedSize }),
        }).catch(function () {});
      }
    },

    clear() {
      localStorage.removeItem(STORAGE_KEY);
      dispatchCartEvent();
      if (isLoggedIn()) {
        window.VunoAuth.authFetch("/api/cart/clear.php", { method: "POST" }).catch(function () {});
      }
    },

    getSubtotal() {
      return getCart().reduce(function (sum, item) { return sum + item.product.price * item.quantity; }, 0);
    },

    // Called on login to merge server cart with local
    async sync() {
      if (!isLoggedIn()) return;
      var local = getCart();
      var server = await fetchServerCart();
      var merged = mergeCarts(local, server);
      saveCart(merged);
      if (merged.length > 0) {
        pushToServer(merged);
      }
    },
  };

  // Auto-restore cart from backup if current cart is empty
  if (getCart().length === 0) restoreFromBackup();

  // Sync on login
  window.addEventListener("auth:changed", function (e) {
    if (e.detail && e.detail.customer) {
      window.VunoCart.sync();
    }
  });

  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-add-to-cart]");
    if (btn) {
      e.preventDefault();
      var product = JSON.parse(btn.dataset.product || "{}");
      var quantity = parseInt(btn.dataset.quantity || "1", 10);
      var color = btn.dataset.color || "";
      var size = btn.dataset.size || "";
      window.VunoCart.addItem(product, quantity, color, size);
      window.dispatchEvent(new CustomEvent("cart:item-added", {
        detail: { product: product, quantity: quantity, selectedColor: color, selectedSize: size },
      }));
    }

    var removeBtn = e.target.closest("[data-remove-item]");
    if (removeBtn) {
      e.preventDefault();
      window.VunoCart.removeItem(
        removeBtn.dataset.productId,
        removeBtn.dataset.color || "",
        removeBtn.dataset.size || ""
      );
    }
  });
})();
