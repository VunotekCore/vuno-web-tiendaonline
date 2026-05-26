const TOKEN_NAME = "ramlop_admin_token";

export function setTokenCookie(token: string): string {
  return `${TOKEN_NAME}=${token}; Path=/; HttpOnly; SameSite=Strict; Max-Age=86400`;
}

export function clearTokenCookie(): string {
  return `${TOKEN_NAME}=; Path=/; HttpOnly; SameSite=Strict; Max-Age=0`;
}

export function getTokenFromCookies(cookieHeader: string | null): string | null {
  if (!cookieHeader) return null;

  const cookies = cookieHeader.split(";").map((c) => c.trim());
  for (const cookie of cookies) {
    const [name, ...rest] = cookie.split("=");
    if (name === TOKEN_NAME) {
      return rest.join("=");
    }
  }

  return null;
}
