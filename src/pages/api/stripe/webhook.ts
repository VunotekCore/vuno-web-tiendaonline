import type { APIRoute } from "astro";
import Stripe from "stripe";

export const POST: APIRoute = async ({ request }) => {
  try {
    const body = await request.text();
    const signature = request.headers.get("stripe-signature") || "";

    const STRIPE_SECRET_KEY = import.meta.env.STRIPE_SECRET_KEY;
    const STRIPE_WEBHOOK_SECRET = import.meta.env.STRIPE_WEBHOOK_SECRET;

    if (!STRIPE_SECRET_KEY || !STRIPE_WEBHOOK_SECRET) {
      return new Response(
        JSON.stringify({ error: "Stripe not configured" }),
        { status: 500, headers: { "Content-Type": "application/json" } }
      );
    }

    const stripe = new Stripe(STRIPE_SECRET_KEY);
    const event = stripe.webhooks.constructEvent(
      body,
      signature,
      STRIPE_WEBHOOK_SECRET
    );

    switch (event.type) {
      case "payment_intent.succeeded":
        console.log("Payment succeeded:", event.data.object.id);
        break;
      case "payment_intent.payment_failed":
        console.error("Payment failed:", event.data.object.id);
        break;
    }

    return new Response(JSON.stringify({ received: true }), { status: 200 });
  } catch (err) {
    console.error("Webhook error:", err);
    return new Response(
      JSON.stringify({ error: "Webhook signature verification failed" }),
      { status: 400 }
    );
  }
};
