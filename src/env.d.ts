/// <reference path="../.astro/types.d.ts" />
/// <reference types="astro/client" />

interface CartItem {
  product: {
    id: string;
    name: string;
    slug: string;
    price: number;
    currency: string;
    images: string[];
    category: string;
  };
  quantity: number;
  selectedColor: string;
  selectedSize: string;
}

interface VunoCart {
  getItems(): CartItem[];
  getCount(): number;
  addItem(
    product: CartItem["product"],
    quantity?: number,
    selectedColor?: string,
    selectedSize?: string
  ): CartItem[];
  updateQuantity(
    productId: string,
    selectedColor: string,
    selectedSize: string,
    quantity: number
  ): void;
  removeItem(
    productId: string,
    selectedColor: string,
    selectedSize: string
  ): void;
  clear(): void;
  getSubtotal(): number;
}

interface VunoWishlist {
  getItems(): { product: CartItem["product"]; addedAt: number }[];
  getCount(): number;
  isInWishlist(productId: string): boolean;
  addItem(product: CartItem["product"]): { product: CartItem["product"]; addedAt: number }[];
  removeItem(productId: string): { product: CartItem["product"]; addedAt: number }[];
  toggleItem(product: CartItem["product"]): boolean;
  clear(): void;
}

type VunoModalType = "success" | "error" | "warning" | "info";

interface VunoModalAlertOptions {
  type?: VunoModalType;
  title: string;
  message?: string;
  buttonText?: string;
  onClose?: () => void;
}

interface VunoModalConfirmOptions {
  type?: VunoModalType;
  title: string;
  message: string;
  confirmText?: string;
  cancelText?: string;
  onConfirm: () => void;
  onCancel?: () => void;
}

interface VunoModalAction {
  label: string;
  onClick: () => void;
  variant?: "primary" | "secondary" | "danger";
}

interface VunoModalShowOptions {
  type?: VunoModalType;
  title: string;
  message?: string;
  body?: string | HTMLElement;
  actions?: VunoModalAction[];
  onClose?: () => void;
}

interface VunoModal {
  alert(options: VunoModalAlertOptions): void;
  confirm(options: VunoModalConfirmOptions): void;
  show(options: VunoModalShowOptions): void;
  close(): void;
}

interface VunoToastOptions {
  type?: VunoModalType;
  title: string;
  message?: string;
  duration?: number;
}

interface VunoToast {
  show(options: VunoToastOptions): void;
  success(title: string, message?: string): void;
  error(title: string, message?: string): void;
  warning(title: string, message?: string): void;
  info(title: string, message?: string): void;
}

interface Window {
  VunoCart: VunoCart;
  VunoWishlist: VunoWishlist;
  VunoModal: VunoModal;
  VunoToast: VunoToast;
  __vunoModalLoaded?: boolean;
}
