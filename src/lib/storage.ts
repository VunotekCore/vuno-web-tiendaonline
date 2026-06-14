import type { Product, Order, Category, BlogPost } from "./types";
import type { Locale } from "../i18n/utils";

export interface SocialPlatformConfig {
  enabled: boolean;
  url: string;
}

export interface SocialImageItem {
  image_url: string;
  platform: string;
}

export interface LandingSectionData {
  label_es?: string;
  label_en?: string;
  title_es?: string;
  title_en?: string;
  subtitle_es?: string;
  subtitle_en?: string;
  paragraph_es?: string;
  paragraph_en?: string;
  cta_es?: string;
  cta_en?: string;
  cta_link?: string;
  cta_category_slug?: string;
  image_url?: string;
  enabled?: boolean;
  facebook_url?: string;
  instagram_url?: string;
  tiktok_url?: string;
  placeholder_es?: string;
  placeholder_en?: string;
  items?: TestimonialItem[];
  platforms?: Record<string, SocialPlatformConfig>;
  images?: SocialImageItem[];
}

export interface TestimonialItem {
  name: string;
  text: string;
  rating: number;
}

export interface LandingData {
  hero: LandingSectionData;
  new_arrivals: LandingSectionData;
  categories: LandingSectionData;
  brand_values: LandingSectionData;
  closing_cta: LandingSectionData;
  social: LandingSectionData;
  newsletter: LandingSectionData;
  testimonials: LandingSectionData;
  blog: LandingSectionData;
}

const API_BASE = import.meta.env.PUBLIC_API_URL || "/api";

export async function getCategories(lang?: Locale): Promise<Category[]> {
  try {
    const url = lang ? `${API_BASE}/categorias/list.php?lang=${lang}` : `${API_BASE}/categorias/list.php`;
    const res = await fetch(url);
    if (!res.ok) return [];
    const data = await res.json();
    return data.items || data;
  } catch {
    return [];
  }
}

export async function getProducts(lang?: Locale): Promise<Product[]> {
  try {
    const url = lang ? `${API_BASE}/productos/list.php?lang=${lang}` : `${API_BASE}/productos/list.php`;
    const res = await fetch(url);
    if (!res.ok) return [];
    const data = await res.json();
    return data.items || data;
  } catch {
    return [];
  }
}

export async function getProductBySlug(slug: string, lang?: Locale): Promise<Product | null> {
  try {
    const url = lang
      ? `${API_BASE}/productos/list.php?lang=${lang}`
      : `${API_BASE}/productos/list.php`;
    const res = await fetch(url);
    if (!res.ok) return null;
    const data = await res.json();
    const products: Product[] = data.items || data;
    return products.find((p) => p.slug === slug) || null;
  } catch {
    return null;
  }
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

export async function getBlogPosts(lang?: Locale): Promise<BlogPost[]> {
  try {
    const url = lang
      ? `${API_BASE}/blog/list.php?limit=50&status=published&lang=${lang}`
      : `${API_BASE}/blog/list.php?limit=50&status=published`
    const res = await fetch(url)
    if (!res.ok) return []
    const data = await res.json()
    return data.items || []
  } catch {
    return []
  }
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

export async function getLanding(): Promise<LandingData | null> {
  try {
    const res = await fetch(`${API_BASE}/configuracion/public.php`);
    if (!res.ok) return null;
    const data = await res.json();
    return data.landing || null;
  } catch {
    return null;
  }
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
