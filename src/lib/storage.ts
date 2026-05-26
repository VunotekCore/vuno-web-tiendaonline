import type { Product, Order } from "./types";
import productsData from "../data/products.json";
import ordersData from "../data/orders.json";

let productsCache: Product[] | null = null;
let ordersCache: Order[] | null = null;

export async function getProducts(): Promise<Product[]> {
  if (productsCache) return productsCache;
  productsCache = productsData as Product[];
  return productsCache;
}

export async function getProductBySlug(slug: string): Promise<Product | null> {
  const products = await getProducts();
  return products.find((p) => p.slug === slug) || null;
}

export async function getProductById(id: string): Promise<Product | null> {
  const products = await getProducts();
  return products.find((p) => p.id === id) || null;
}

export async function saveProduct(product: Product): Promise<void> {
  const products = await getProducts();
  const idx = products.findIndex((p) => p.id === product.id);

  if (idx >= 0) {
    products[idx] = product;
  } else {
    products.push(product);
  }

  try {
    const fs = await import("node:fs/promises");
    const path = await import("node:path");
    const filePath = path.resolve("src/data/products.json");
    await fs.writeFile(filePath, JSON.stringify(products, null, 2), "utf-8");
  } catch {
    console.warn("Cannot persist products.json (read-only filesystem)");
  }
  productsCache = products;
}

export async function deleteProduct(id: string): Promise<void> {
  const products = await getProducts();
  const filtered = products.filter((p) => p.id !== id);

  try {
    const fs = await import("node:fs/promises");
    const path = await import("node:path");
    const filePath = path.resolve("src/data/products.json");
    await fs.writeFile(filePath, JSON.stringify(filtered, null, 2), "utf-8");
  } catch {
    console.warn("Cannot persist products.json (read-only filesystem)");
  }
  productsCache = filtered;
}

export async function getOrders(): Promise<Order[]> {
  if (ordersCache) return ordersCache;
  ordersCache = ordersData as Order[];
  return ordersCache;
}

export async function saveOrder(order: Order): Promise<void> {
  const orders = await getOrders();
  orders.push(order);

  try {
    const fs = await import("node:fs/promises");
    const path = await import("node:path");
    const filePath = path.resolve("src/data/orders.json");
    await fs.writeFile(filePath, JSON.stringify(orders, null, 2), "utf-8");
  } catch {
    console.warn("Cannot persist orders.json (read-only filesystem)");
  }
  ordersCache = orders;
}

export async function updateOrderStatus(
  orderId: string,
  status: Order["status"],
  paymentStatus?: Order["paymentStatus"]
): Promise<void> {
  const orders = await getOrders();
  const order = orders.find((o) => o.id === orderId);

  if (!order) throw new Error(`Order ${orderId} not found`);

  order.status = status;
  if (paymentStatus) order.paymentStatus = paymentStatus;

  try {
    const fs = await import("node:fs/promises");
    const path = await import("node:path");
    const filePath = path.resolve("src/data/orders.json");
    await fs.writeFile(filePath, JSON.stringify(orders, null, 2), "utf-8");
  } catch {
    console.warn("Cannot persist orders.json (read-only filesystem)");
  }
  ordersCache = orders;
}
