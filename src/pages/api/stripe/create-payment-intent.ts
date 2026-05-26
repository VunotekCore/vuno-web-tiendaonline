import type { APIRoute } from "astro";

export const POST: APIRoute = async ({ request }) => {
  try {
    const { items, customerEmail } = await request.json();

    if (!items?.length || !customerEmail) {
      return new Response(
        JSON.stringify({ error: "Missing required fields" }),
        { status: 400, headers: { "Content-Type": "application/json" } }
      );
    }

    const STRIPE_SECRET_KEY = import.meta.env.STRIPE_SECRET_KEY;

    if (!STRIPE_SECRET_KEY) {
      return new Response(
        JSON.stringify({ error: "Stripe not configured" }),
        { status: 500, headers: { "Content-Type": "application/json" } }
      );
    }

    const amount = Math.round(
      items.reduce(
        (sum: number, item: { product: { price: number }; quantity: number }) =>
          sum + item.product.price * item.quantity,
        0
      ) * 100
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

    const data = await res.json();

    if (!res.ok) {
      return new Response(
        JSON.stringify({ error: data.error?.message || "Stripe error" }),
        { status: 500, headers: { "Content-Type": "application/json" } }
      );
    }

    return new Response(
      JSON.stringify({ clientSecret: data.client_secret }),
      { status: 200, headers: { "Content-Type": "application/json" } }
    );
  } catch (err) {
    console.error("Stripe error:", err);
    return new Response(
      JSON.stringify({ error: "Internal server error" }),
      { status: 500, headers: { "Content-Type": "application/json" } }
    );
  }
};
