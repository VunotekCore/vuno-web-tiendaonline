(function () {
  const VARIANTS = {
    success: { icon: "check_circle", color: "text-green-600" },
    error: { icon: "error", color: "text-error" },
    warning: { icon: "warning", color: "text-amber-600" },
    info: { icon: "info", color: "text-monolith-black" },
  };

  function getVariant(type) {
    return VARIANTS[type] || VARIANTS.info;
  }

  function applyVariantToIcon(iconEl, type) {
    const v = getVariant(type);
    iconEl.textContent = v.icon;
    iconEl.className = iconEl.className.replace(/text-(green-600|error|amber-600|monolith-black)/g, "").trim();
    iconEl.classList.add(v.color);
  }

  // --- Body scroll lock helper (per-modal) ---
  let scrollLockCount = 0;
  function lockScroll() {
    if (scrollLockCount === 0) {
      const scrollbarW = window.innerWidth - document.documentElement.clientWidth;
      document.body.style.overflow = "hidden";
      if (scrollbarW > 0) document.body.style.paddingRight = scrollbarW + "px";
    }
    scrollLockCount++;
  }
  function unlockScroll() {
    if (scrollLockCount > 0) scrollLockCount--;
    if (scrollLockCount === 0) {
      document.body.style.overflow = "";
      document.body.style.paddingRight = "";
    }
  }

  // ==============================================================
  //                          MODAL
  // ==============================================================
  const modal = document.getElementById("vunoModal");
  const modalTitle = document.getElementById("vunoModalTitle");
  const modalMessage = document.getElementById("vunoModalMessage");
  const modalBody = document.getElementById("vunoModalBody");
  const modalIcon = document.getElementById("vunoModalIcon");
  const modalActions = document.getElementById("vunoModalActions");
  const modalClose = document.getElementById("vunoModalClose");
let modalCancel = document.getElementById("vunoModalCancel");
let modalConfirm = document.getElementById("vunoModalConfirm");

  let modalState = { onClose: null, onCancel: null, isOpen: false };

  function showModalBackdrop() {
    if (!modal) return;
    modal.classList.remove("hidden");
    modal.classList.add("flex");
    modalState.isOpen = true;
    lockScroll();
    requestAnimationFrame(() => modal.focus());
  }

  function hideModalBackdrop() {
    if (!modal) return;
    modal.classList.add("hidden");
    modal.classList.remove("flex");
    modalState.isOpen = false;
    unlockScroll();
    modalBody.innerHTML = "";
  }

  function closeModal() {
    const onClose = modalState.onClose;
    modalState = { onClose: null, onCancel: null, isOpen: false };
    hideModalBackdrop();
    if (typeof onClose === "function") {
      try { onClose(); } catch (e) { console.error(e); }
    }
  }

  if (modalClose) modalClose.addEventListener("click", closeModal);

  if (modal) {
    modal.addEventListener("click", (e) => {
      if (e.target === modal) closeModal();
    });
  }

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && modalState.isOpen) closeModal();
  });

  const VunoModal = {
    /**
     * Bloqueante con un solo botón. Reemplazo directo de alert().
     * @param {Object} opts
     * @param {"success"|"error"|"warning"|"info"} [opts.type="info"]
     * @param {string} opts.title
     * @param {string} [opts.message]
     * @param {string} [opts.buttonText="OK"]
     * @param {() => void} [opts.onClose]
     */
    alert(opts) {
      const { type = "info", title, message = "", buttonText = "OK", onClose } = opts || {};
      if (!modal || !title) return;
      modalTitle.textContent = title;
      if (message) {
        modalMessage.textContent = message;
        modalMessage.classList.remove("hidden");
      } else {
        modalMessage.textContent = "";
        modalMessage.classList.add("hidden");
      }
      modalBody.classList.add("hidden");
      modalBody.innerHTML = "";
      applyVariantToIcon(modalIcon, type);
      modalCancel.classList.add("hidden");
      modalConfirm.textContent = buttonText;
      modalConfirm.className = "font-label-caps text-label-caps bg-monolith-black text-off-white rounded-md px-5 h-10 hover:bg-monolith-black/90 transition-all";
      const newConfirm = modalConfirm.cloneNode(true);
      modalConfirm.parentNode.replaceChild(newConfirm, modalConfirm);
      modalConfirm = newConfirm;
      newConfirm.addEventListener("click", closeModal);
      modalState = { onClose, onCancel: null, isOpen: true };
      showModalBackdrop();
      requestAnimationFrame(() => newConfirm.focus());
    },

    /**
     * Bloqueante con dos botones. Reemplazo de confirm().
     * @param {Object} opts
     * @param {"success"|"error"|"warning"|"info"} [opts.type="warning"]
     * @param {string} opts.title
     * @param {string} opts.message
     * @param {string} [opts.confirmText="Confirmar"]
     * @param {string} [opts.cancelText="Cancelar"]
     * @param {() => void} opts.onConfirm
     * @param {() => void} [opts.onCancel]
     */
    confirm(opts) {
      const { type = "warning", title, message, confirmText = "Confirmar", cancelText = "Cancelar", onConfirm, onCancel } = opts || {};
      if (!modal || !title || !message) return;
      modalTitle.textContent = title;
      modalMessage.textContent = message;
      modalMessage.classList.remove("hidden");
      modalBody.classList.add("hidden");
      modalBody.innerHTML = "";
      applyVariantToIcon(modalIcon, type);
      modalCancel.textContent = cancelText;
      modalCancel.classList.remove("hidden");
      modalConfirm.textContent = confirmText;
      const confirmClass = type === "error"
        ? "font-label-caps text-label-caps bg-error text-off-white rounded-md px-5 h-10 hover:bg-error/90 transition-all"
        : "font-label-caps text-label-caps bg-monolith-black text-off-white rounded-md px-5 h-10 hover:bg-monolith-black/90 transition-all";
      modalConfirm.className = confirmClass;
      const newCancel = modalCancel.cloneNode(true);
      const newConfirm = modalConfirm.cloneNode(true);
      modalCancel.parentNode.replaceChild(newCancel, modalCancel);
      modalCancel = newCancel;
      modalConfirm.parentNode.replaceChild(newConfirm, modalConfirm);
      modalConfirm = newConfirm;
      newCancel.addEventListener("click", () => {
        const cb = onCancel;
        closeModal();
        if (typeof cb === "function") {
          try { cb(); } catch (e) { console.error(e); }
        }
      });
      newConfirm.addEventListener("click", () => {
        const cb = onConfirm;
        closeModal();
        if (typeof cb === "function") {
          try { cb(); } catch (e) { console.error(e); }
        }
      });
      modalState = { onClose: null, onCancel, isOpen: true };
      showModalBackdrop();
      requestAnimationFrame(() => newConfirm.focus());
    },

    /**
     * Bajo nivel: muestra un modal con cuerpo y acciones custom.
     * Útil cuando se necesita HTML arbitrario en el cuerpo (ej. inputs).
     * @param {Object} opts
     * @param {"success"|"error"|"warning"|"info"} [opts.type="info"]
     * @param {string} opts.title
     * @param {string} [opts.message]
     * @param {string|HTMLElement} [opts.body]
     * @param {Array<{label:string, onClick:Function, variant?:"primary"|"secondary"|"danger"}>} [opts.actions]
     * @param {() => void} [opts.onClose]
     */
    show(opts) {
      const { type = "info", title, message = "", body = null, actions = null, onClose } = opts || {};
      if (!modal || !title) return;
      modalTitle.textContent = title;
      if (message) {
        modalMessage.textContent = message;
        modalMessage.classList.remove("hidden");
      } else {
        modalMessage.textContent = "";
        modalMessage.classList.add("hidden");
      }
      if (body) {
        modalBody.innerHTML = "";
        if (typeof body === "string") {
          modalBody.innerHTML = body;
        } else if (body instanceof HTMLElement) {
          modalBody.appendChild(body);
        }
        modalBody.classList.remove("hidden");
      } else {
        modalBody.classList.add("hidden");
      }
      applyVariantToIcon(modalIcon, type);

      // Render actions
      while (modalActions.firstChild) modalActions.removeChild(modalActions.firstChild);
      if (Array.isArray(actions) && actions.length > 0) {
        actions.forEach((act) => {
          const btn = document.createElement("button");
          btn.type = "button";
          btn.textContent = act.label;
          if (act.variant === "danger") {
            btn.className = "font-label-caps text-label-caps bg-error text-off-white rounded-md px-5 h-10 hover:bg-error/90 transition-all";
          } else if (act.variant === "secondary") {
            btn.className = "font-label-caps text-label-caps text-secondary border border-monolith-black/20 rounded-md px-5 h-10 hover:border-monolith-black hover:text-monolith-black transition-all";
          } else {
            btn.className = "font-label-caps text-label-caps bg-monolith-black text-off-white rounded-md px-5 h-10 hover:bg-monolith-black/90 transition-all";
          }
          btn.addEventListener("click", () => {
            if (typeof act.onClick === "function") {
              try { act.onClick(); } catch (e) { console.error(e); }
            }
            closeModal();
          });
          modalActions.appendChild(btn);
        });
      } else {
        const ok = document.createElement("button");
        ok.type = "button";
        ok.textContent = "OK";
        ok.className = "font-label-caps text-label-caps bg-monolith-black text-off-white rounded-md px-5 h-10 hover:bg-monolith-black/90 transition-all";
        ok.addEventListener("click", closeModal);
        modalActions.appendChild(ok);
      }
      modalState = { onClose, onCancel: null, isOpen: true };
      showModalBackdrop();
    },

    close: closeModal,
  };

  // ==============================================================
  //                          TOAST
  // ==============================================================
  const toastContainer = document.getElementById("vunoToastContainer");
  const toastTemplate = document.getElementById("vunoToastTemplate");
  let toastSeq = 0;

  function dismissToast(toast) {
    toast.classList.remove("translate-x-0", "opacity-100");
    toast.classList.add("translate-x-full", "opacity-0");
    setTimeout(() => {
      if (toast.parentNode) toast.parentNode.removeChild(toast);
    }, 300);
  }

  const VunoToast = {
    /**
     * Muestra un toast no bloqueante. Auto-dismiss por defecto a 4s.
     * @param {Object} opts
     * @param {"success"|"error"|"warning"|"info"} [opts.type="info"]
     * @param {string} opts.title
     * @param {string} [opts.message]
     * @param {number} [opts.duration=4000] 0 = no auto-dismiss
     */
    show(opts) {
      if (!toastContainer || !toastTemplate) return;
      const { type = "info", title, message = "", duration = 4000 } = opts || {};
      if (!title) return;
      const node = toastTemplate.content.firstElementChild.cloneNode(true);
      node.dataset.toastId = String(++toastSeq);
      const iconEl = node.querySelector(".vuno-toast-icon");
      const titleEl = node.querySelector(".vuno-toast-title");
      const messageEl = node.querySelector(".vuno-toast-message");
      const closeEl = node.querySelector(".vuno-toast-close");
      applyVariantToIcon(iconEl, type);
      titleEl.textContent = title;
      if (message) {
        messageEl.textContent = message;
        messageEl.classList.remove("hidden");
      }
      closeEl.addEventListener("click", () => dismissToast(node));
      node.addEventListener("click", (e) => {
        if (e.target.closest(".vuno-toast-close")) return;
        dismissToast(node);
      });
      toastContainer.appendChild(node);
      // Trigger slide-in on next frame
      requestAnimationFrame(() => {
        node.classList.remove("translate-x-full", "opacity-0");
        node.classList.add("translate-x-0", "opacity-100");
      });
      if (duration > 0) {
        setTimeout(() => dismissToast(node), duration);
      }
    },

    success(title, message) { this.show({ type: "success", title, message }); },
    error(title, message) { this.show({ type: "error", title, message }); },
    warning(title, message) { this.show({ type: "warning", title, message }); },
    info(title, message) { this.show({ type: "info", title, message }); },
  };

  window.VunoModal = VunoModal;
  window.VunoToast = VunoToast;
})();
