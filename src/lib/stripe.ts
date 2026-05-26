import type { CartItem } from "./types";

const STRIPE_SECRET_KEY = import.meta.env.STRIPE_SECRET_KEY || "";
const STRIPE_PUBLISHABLE_KEY = import.meta.env.STRIPE_PUBLISHABLE_KEY || "";

export { STRIPE_PUBLISHABLE_KEY };

export async function createPaymentIntent(
  items: CartItem[],
  customerEmail: string
): Promise<{ clientSecret: string }> {
  const amount = Math.round(
    items.reduce((sum, item) => sum + item.product.price * item.quantity, 0) * 100
  );

  const res = await fetch("https://api.stripe.com/v1/payment_intents", {
    method: "POST",
    headers: {
      Authorization: `Bearer ${STRIPE_SECRET_KEY}`,
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: new URLSearchParams({
      amount: String(amount),
      currency: "usd",
      "automatic_payment_methods[enabled]": "true",
      receipt_email: customerEmail,
    }),
  });

  if (!res.ok) {
    const err = await res.json();
    throw new Error(err.error?.message || "Stripe payment intent failed");
  }

  const data = await res.json();
  return { clientSecret: data.client_secret };
}
