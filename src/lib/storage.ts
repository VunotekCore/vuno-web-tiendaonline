import type { Product, Order } from "./types";

const API_BASE = import.meta.env.PUBLIC_API_URL || "/api";

export async function getProducts(): Promise<Product[]> {
  const res = await fetch(`${API_BASE}/productos/list.php`);
  if (!res.ok) return [];
  const data = await res.json();
  return data.items || data;
}

export async function getProductBySlug(slug: string): Promise<Product | null> {
  const res = await fetch(`${API_BASE}/productos/list.php`);
  if (!res.ok) return null;
  const data = await res.json();
  const products: Product[] = data.items || data;
  return products.find((p) => p.slug === slug) || null;
}

export async function getProductById(id: string): Promise<Product | null> {
  const res = await fetch(`${API_BASE}/productos/get.php?id=${encodeURIComponent(id)}`);
  if (!res.ok) return null;
  return res.json();
}

export async function saveProduct(product: Product): Promise<void> {
  await fetch(`${API_BASE}/productos/create.php`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(product),
  });
}

export async function deleteProduct(id: string): Promise<void> {
  await fetch(`${API_BASE}/productos/delete.php`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id }),
  });
}

export async function getOrders(): Promise<Order[]> {
  const res = await fetch(`${API_BASE}/pedidos/list.php`);
  if (!res.ok) return [];
  return res.json();
}

export async function saveOrder(order: Order): Promise<void> {
  await fetch(`${API_BASE}/pedidos/create.php`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(order),
  });
}

export async function updateOrderStatus(
  orderId: string,
  status: Order["status"],
  paymentStatus?: Order["paymentStatus"]
): Promise<void> {
  await fetch(`${API_BASE}/pedidos/update-status.php`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id: orderId, status, paymentStatus }),
  });
}
