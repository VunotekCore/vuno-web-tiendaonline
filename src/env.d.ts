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

interface Window {
  VunoCart: VunoCart;
  VunoWishlist: VunoWishlist;
}
