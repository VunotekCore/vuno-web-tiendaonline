import type { Order } from "./types";

const FROM_EMAIL = import.meta.env.FROM_EMAIL || "noreply@ramlop.com";

export async function sendOrderConfirmation(order: Order): Promise<void> {
  const html = `
    <!DOCTYPE html>
    <html>
    <head><meta charset="utf-8"></head>
    <body style="font-family: 'Hanken Grotesk', sans-serif; background: #faf9f8; padding: 40px;">
      <div style="max-width: 600px; margin: 0 auto; background: white; padding: 40px;">
        <h1 style="font-family: 'Playfair Display', serif; font-size: 24px; color: #1A1A1A;">Ram;Lop</h1>
        <p style="color: #635e57;">Thank you for your order, ${order.customer.name}!</p>
        <p style="color: #635e57;">Order #${order.id}</p>
        <hr style="border: none; border-top: 1px solid #c4c7c7;" />
        <h2 style="font-size: 16px; color: #1A1A1A;">Order Summary</h2>
        ${order.items
          .map(
            (item) => `
          <div style="display: flex; justify-content: space-between; padding: 8px 0;">
            <span>${item.product.name} x${item.quantity}</span>
            <span>$${(item.product.price * item.quantity).toFixed(2)}</span>
          </div>
        `
          )
          .join("")}
        <hr style="border: none; border-top: 1px solid #c4c7c7;" />
        <div style="display: flex; justify-content: space-between; font-weight: bold;">
          <span>Total</span>
          <span>$${order.total.toFixed(2)}</span>
        </div>
      </div>
    </body>
    </html>
  `;

  try {
    const res = await fetch("http://localhost:4321/api/email/send", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        to: order.customer.email,
        subject: `Order Confirmation #${order.id} — Ram;Lop`,
        html,
      }),
    });

    if (!res.ok) {
      console.error("Failed to send email notification");
    }
  } catch (err) {
    console.error("Email service error:", err);
  }
}

export async function sendNewOrderNotification(order: Order): Promise<void> {
  const html = `
    <h2>New Order #${order.id}</h2>
    <p>Customer: ${order.customer.name} (${order.customer.email})</p>
    <p>Total: $${order.total.toFixed(2)}</p>
    <p>Payment: ${order.paymentMethod}</p>
  `;

  try {
    const res = await fetch("http://localhost:4321/api/email/send", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        to: FROM_EMAIL,
        subject: `New Order #${order.id} — Ram;Lop Admin`,
        html,
      }),
    });

    if (!res.ok) {
      console.error("Failed to send admin notification");
    }
  } catch (err) {
    console.error("Email service error:", err);
  }
}
