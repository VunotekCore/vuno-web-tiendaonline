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

interface RamLopCart {
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

interface Window {
  RamLopCart: RamLopCart;
}
