const ADMIN_EMAIL = import.meta.env.ADMIN_EMAIL || "admin@ramlop.com";
const ADMIN_PASSWORD = import.meta.env.ADMIN_PASSWORD || "ramlop2024";
const TOKEN_SECRET = import.meta.env.TOKEN_SECRET || "ramlop-secret-key-change-in-production";

export function validateCredentials(
  email: string,
  password: string
): boolean {
  return email === ADMIN_EMAIL && password === ADMIN_PASSWORD;
}

export function generateToken(email: string): string {
  const payload = JSON.stringify({ email, exp: Date.now() + 86400000 });
  const encoded = Buffer.from(payload).toString("base64");
  const sig = Buffer.from(`${encoded}.${TOKEN_SECRET}`).toString("base64");
  return `${encoded}.${sig}`;
}

export function verifyToken(token: string): { email: string } | null {
  try {
    const parts = token.split(".");
    if (parts.length !== 2) return null;

    const payload = JSON.parse(Buffer.from(parts[0], "base64").toString());
    const expectedSig = Buffer.from(`${parts[0]}.${TOKEN_SECRET}`).toString("base64");

    if (parts[1] !== expectedSig) return null;
    if (payload.exp < Date.now()) return null;

    return { email: payload.email };
  } catch {
    return null;
  }
}
