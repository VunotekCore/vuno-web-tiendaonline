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

  window.VunoCart = {
    getItems() {
      return getCart();
    },

    getCount() {
      return getCart().reduce((sum, item) => sum + item.quantity, 0);
    },

    addItem(product, quantity = 1, selectedColor = "", selectedSize = "") {
      const items = getCart();
      const existing = items.find(
        (i) =>
          i.product.id === product.id &&
          i.selectedColor === selectedColor &&
          i.selectedSize === selectedSize
      );

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
            images: product.images,
            category: product.category,
          },
          quantity,
          selectedColor,
          selectedSize,
        });
      }

      saveCart(items);
      return items;
    },

    updateQuantity(productId, selectedColor, selectedSize, quantity) {
      const items = getCart();
      const item = items.find(
        (i) =>
          i.product.id === productId &&
          i.selectedColor === selectedColor &&
          i.selectedSize === selectedSize
      );

      if (item) {
        item.quantity = Math.max(1, quantity);
        saveCart(items);
      }
    },

    removeItem(productId, selectedColor, selectedSize) {
      const items = getCart().filter(
        (i) =>
          !(
            i.product.id === productId &&
            i.selectedColor === selectedColor &&
            i.selectedSize === selectedSize
          )
      );
      saveCart(items);
    },

    clear() {
      localStorage.removeItem(STORAGE_KEY);
      dispatchCartEvent();
    },

    getSubtotal() {
      return getCart().reduce(
        (sum, item) => sum + item.product.price * item.quantity,
        0
      );
    },
  };

  // Auto-restore cart from backup if current cart is empty
  if (getCart().length === 0) restoreFromBackup();

  document.addEventListener("click", function (e) {
    const btn = e.target.closest("[data-add-to-cart]");
    if (btn) {
      e.preventDefault();
      const product = JSON.parse(btn.dataset.product || "{}");
      const quantity = parseInt(btn.dataset.quantity || "1", 10);
      const color = btn.dataset.color || "";
      const size = btn.dataset.size || "";
      window.VunoCart.addItem(product, quantity, color, size);
      alert("Added to cart!");
    }

    const removeBtn = e.target.closest("[data-remove-item]");
    if (removeBtn) {
      e.preventDefault();
      window.VunoCart.removeItem(
        removeBtn.dataset.productId,
        removeBtn.dataset.color || "",
        removeBtn.dataset.size || ""
      );
      location.reload();
    }
  });
})();
