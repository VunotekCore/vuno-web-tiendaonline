import type { APIRoute } from "astro";
import { verifyToken } from "../../../lib/admin";
import { getTokenFromCookies } from "../../../lib/cookie";

export const GET: APIRoute = async ({ request }) => {
  try {
    const token = getTokenFromCookies(request.headers.get("cookie"));
    const user = token ? verifyToken(token) : null;

    return new Response(
      JSON.stringify({ valid: !!user, email: user?.email }),
      {
        status: 200,
        headers: { "Content-Type": "application/json" },
      }
    );
  } catch {
    return new Response(
      JSON.stringify({ valid: false }),
      { status: 200, headers: { "Content-Type": "application/json" } }
    );
  }
};
