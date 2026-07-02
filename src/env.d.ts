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

interface VunoAuthCustomer {
  id: number;
  name: string;
  email: string;
  memberSince?: string;
  lastOrderAt?: string | null;
}

interface VunoAuth {
  login(email: string, password: string): Promise<{ token: string; customer: VunoAuthCustomer }>;
  register(name: string, email: string, password: string): Promise<{ token: string; customer: VunoAuthCustomer }>;
  logout(): Promise<void>;
  verify(): Promise<VunoAuthCustomer | null>;
  getCustomer(): VunoAuthCustomer | null;
  isLoggedIn(): boolean;
  getToken(): string | null;
  authFetch(url: string, options?: RequestInit): Promise<Response>;
}

interface ApiInstance {
  get<T = any>(url: string, config?: any): Promise<{ data: T }>;
  post<T = any>(url: string, data?: any, config?: any): Promise<{ data: T }>;
  put<T = any>(url: string, data?: any, config?: any): Promise<{ data: T }>;
  delete<T = any>(url: string, config?: any): Promise<{ data: T }>;
  defaults: { baseURL?: string };
}

interface Window {
  VunoCart: VunoCart;
  VunoWishlist: VunoWishlist;
  VunoAuth: VunoAuth;
  VunoModal: VunoModal;
  VunoToast: VunoToast;
  __vunoModalLoaded?: boolean;
  __api: ApiInstance;
  formatPrice(value: number): string;
}
