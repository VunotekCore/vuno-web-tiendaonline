import type { APIRoute } from "astro";

export const POST: APIRoute = async ({ request }) => {
  try {
    const formData = await request.formData();
    const file = formData.get("file") as File | null;
    const folder = (formData.get("folder") as string) || "/products";

    if (!file) {
      return new Response(
        JSON.stringify({ error: "No file provided" }),
        { status: 400, headers: { "Content-Type": "application/json" } }
      );
    }

    const IMAGEKIT_PRIVATE_KEY = import.meta.env.IMAGEKIT_PRIVATE_KEY;

    if (!IMAGEKIT_PRIVATE_KEY) {
      return new Response(
        JSON.stringify({ error: "ImageKit not configured" }),
        { status: 500, headers: { "Content-Type": "application/json" } }
      );
    }

    const uploadFormData = new FormData();
    uploadFormData.append("file", file);
    uploadFormData.append("fileName", file.name);
    uploadFormData.append("folder", folder);
    uploadFormData.append("useUniqueFileName", "true");

    const auth = btoa(`${IMAGEKIT_PRIVATE_KEY}:`);
    const res = await fetch("https://upload.imagekit.io/api/v1/files/upload", {
      method: "POST",
      headers: { Authorization: `Basic ${auth}` },
      body: uploadFormData,
    });

    const data = await res.json();

    if (!res.ok) {
      return new Response(
        JSON.stringify({ error: data.message || "Upload failed" }),
        { status: 500, headers: { "Content-Type": "application/json" } }
      );
    }

    return new Response(JSON.stringify(data), {
      status: 200,
      headers: { "Content-Type": "application/json" },
    });
  } catch (err) {
    console.error("Upload error:", err);
    return new Response(
      JSON.stringify({ error: "Upload failed" }),
      { status: 500, headers: { "Content-Type": "application/json" } }
    );
  }
};
