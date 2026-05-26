import type { ImageKitResponse } from "./types";

const IMAGEKIT_PRIVATE_KEY = import.meta.env.IMAGEKIT_PRIVATE_KEY || "";

async function imageKitRequest<T>(
  endpoint: string,
  options: RequestInit = {}
): Promise<T> {
  const auth = btoa(`${IMAGEKIT_PRIVATE_KEY}:`);
  const res = await fetch(`https://api.imagekit.io/v1${endpoint}`, {
    ...options,
    headers: {
      Authorization: `Basic ${auth}`,
      "Content-Type": "application/json",
      ...options.headers,
    },
  });

  if (!res.ok) {
    throw new Error(`ImageKit API error: ${res.status} ${res.statusText}`);
  }

  return res.json();
}

export async function uploadImage(
  file: File,
  folder?: string
): Promise<ImageKitResponse> {
  const formData = new FormData();
  formData.append("file", file);
  formData.append("fileName", file.name);
  if (folder) formData.append("folder", folder);
  formData.append("useUniqueFileName", "true");

  const auth = btoa(`${IMAGEKIT_PRIVATE_KEY}:`);
  const res = await fetch(
    `https://upload.imagekit.io/api/v1/files/upload`,
    {
      method: "POST",
      headers: { Authorization: `Basic ${auth}` },
      body: formData,
    }
  );

  if (!res.ok) {
    throw new Error(`ImageKit upload error: ${res.status} ${res.statusText}`);
  }

  return res.json();
}

export async function uploadBatch(
  files: File[],
  folder?: string
): Promise<ImageKitResponse[]> {
  return Promise.all(files.map((file) => uploadImage(file, folder)));
}

export async function getImage(
  fileId: string
): Promise<ImageKitResponse> {
  return imageKitRequest<ImageKitResponse>(`/files/${fileId}/details`);
}

export async function getImages(
  options?: {
    skip?: number;
    limit?: number;
    path?: string;
  }
): Promise<ImageKitResponse[]> {
  const params = new URLSearchParams();
  if (options?.skip) params.set("skip", String(options.skip));
  if (options?.limit) params.set("limit", String(options.limit));
  if (options?.path) params.set("path", options.path);

  const query = params.toString();
  return imageKitRequest<ImageKitResponse[]>(
    `/files${query ? `?${query}` : ""}`
  );
}

export async function deleteImage(fileId: string): Promise<void> {
  await imageKitRequest(`/files/${fileId}`, { method: "DELETE" });
}
