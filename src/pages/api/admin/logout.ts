import type { APIRoute } from "astro";
import { clearTokenCookie } from "../../../lib/cookie";

export const GET: APIRoute = async () => {
  return new Response(null, {
    status: 302,
    headers: {
      Location: "/admin/login",
      "Set-Cookie": clearTokenCookie(),
    },
  });
};
