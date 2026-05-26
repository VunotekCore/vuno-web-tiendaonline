import type { CartItem, Product } from "./types";

export function calculateSubtotal(items: CartItem[]): number {
  return items.reduce(
    (sum, item) => sum + item.product.price * item.quantity,
    0
  );
}

export function calculateTotal(items: CartItem[]): number {
  return calculateSubtotal(items);
}

export function formatCartItem(
  product: Product,
  quantity: number,
  selectedColor: string,
  selectedSize: string
): CartItem {
  return {
    product: {
      id: product.id,
      name: product.name,
      slug: product.slug,
      description: product.description,
      price: product.price,
      currency: product.currency,
      images: product.images,
      category: product.category,
      colors: product.colors,
      sizes: product.sizes,
      details: product.details,
      createdAt: product.createdAt || new Date().toISOString(),
    },
    quantity,
    selectedColor,
    selectedSize,
  };
}
