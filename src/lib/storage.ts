import type { Product, Order, Category, BlogPost } from "./types";
import type { Locale } from "../i18n/utils";
import axios from "axios";

export interface SocialPlatformConfig {
  enabled: boolean;
  url: string;
  image_url?: string;
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
  badge_number?: string;
  badge_es?: string;
  badge_en?: string;
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

const API_BASE = import.meta.env.PUBLIC_API_URL
  || (import.meta.env.SSR ? "http://127.0.0.1:8000/api" : "/api");

function apiUrl(path: string): string {
  return `${API_BASE}${path}`;
}

export async function getCategories(lang?: Locale): Promise<Category[]> {
  try {
    const res = await axios.get(apiUrl("/categorias/list.php"), { params: { lang } });
    return res.data.items || res.data;
  } catch {
    return [];
  }
}

export async function getProducts(lang?: Locale): Promise<Product[]> {
  try {
    const res = await axios.get(apiUrl("/productos/list.php"), { params: { lang } });
    return res.data.items || res.data;
  } catch {
    return [];
  }
}

export async function getProductBySlug(slug: string, lang?: Locale): Promise<Product | null> {
  try {
    const res = await axios.get(apiUrl("/productos/list.php"), { params: { lang } });
    const products: Product[] = res.data.items || res.data;
    return products.find((p) => p.slug === slug) || null;
  } catch {
    return null;
  }
}

export async function getProductById(id: string): Promise<Product | null> {
  try {
    const res = await axios.get(apiUrl("/productos/get.php"), { params: { id } });
    return res.data;
  } catch {
    return null;
  }
}

export async function saveProduct(product: Product): Promise<void> {
  await axios.post(apiUrl("/productos/create.php"), product);
}

export async function deleteProduct(id: string): Promise<void> {
  await axios.post(apiUrl("/productos/delete.php"), { id });
}

export async function getBlogPosts(lang?: Locale): Promise<BlogPost[]> {
  try {
    const res = await axios.get(apiUrl("/blog/list.php"), { params: { limit: 50, status: "published", lang } });
    return res.data.items || [];
  } catch {
    return [];
  }
}

export async function getOrders(): Promise<Order[]> {
  try {
    const res = await axios.get(apiUrl("/pedidos/list.php"));
    return res.data;
  } catch {
    return [];
  }
}

export async function saveOrder(order: Order): Promise<void> {
  await axios.post(apiUrl("/pedidos/create.php"), order);
}

export async function getLanding(): Promise<LandingData | null> {
  try {
    const res = await axios.get(apiUrl("/configuracion/public.php"));
    return res.data.landing || null;
  } catch {
    return null;
  }
}

export async function updateOrderStatus(
  orderId: string,
  status: Order["status"],
  paymentStatus?: Order["paymentStatus"]
): Promise<void> {
  await axios.post(apiUrl("/pedidos/update-status.php"), { id: orderId, status, paymentStatus });
}
