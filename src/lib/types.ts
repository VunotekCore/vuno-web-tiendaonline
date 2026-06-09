export interface Product {
  id: string;
  name: string;
  slug: string;
  description: string;
  details?: string[];
  careInstructions?: string;
  price: number;
  currency: string;
  display_price?: number;
  display_currency?: string;
  display_symbol?: string;
  images: string[];
  imagesByColor?: Record<string, string[]>;
  category: string;
  colors: ProductColor[];
  sizes: ProductSize[];
  createdAt: string;
}

export interface ProductColor {
  name: string;
  hex: string;
  image?: string;
}

export interface ProductSize {
  label: string;
  value: string;
  inStock: boolean;
}

export interface CartItem {
  product: Product;
  quantity: number;
  selectedColor: string;
  selectedSize: string;
}

export interface Order {
  id: string;
  items: CartItem[];
  subtotal: number;
  shipping: number;
  tax: number;
  total: number;
  display_total?: number;
  display_currency?: string;
  display_symbol?: string;
  status: OrderStatus;
  paymentMethod: "stripe" | "transfer";
  paymentStatus: PaymentStatus;
  transferReceipt?: string;
  customer: CustomerInfo;
  createdAt: string;
}

export interface CustomerInfo {
  name: string;
  email: string;
  phone?: string;
  address: string;
  city: string;
  state: string;
  zip: string;
  country: string;
}

export type OrderStatus = "pending" | "paid" | "shipped" | "delivered" | "cancelled";
export type PaymentStatus = "pending" | "completed" | "failed" | "refunded";

export interface ImageKitResponse {
  fileId: string;
  name: string;
  size: number;
  versionInfo: {
    id: string;
    name: string;
  };
  filePath: string;
  url: string;
  fileType: string;
  height: number;
  width: number;
  thumbnailUrl: string;
  AITags?: Array<{
    name: string;
    confidence: number;
  }>;
}
