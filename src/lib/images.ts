export function imgTransform(url: string, w: number, h: number, extras?: string): string {
  if (!url) return url;
  if (!url.includes('ik.imagekit.io')) return url;
  const sep = url.includes('?') ? '&' : '?';
  const base = `${url}${sep}tr=w-${w},h-${h},c-crop,fo-auto`;
  return extras ? `${base},${extras}` : base;
}
