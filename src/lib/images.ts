export function imgTransform(url: string, w: number, h: number): string {
  if (!url) return url;
  if (!url.includes('ik.imagekit.io')) return url;
  const sep = url.includes('?') ? '&' : '?';
  return `${url}${sep}tr=w-${w},h-${h},fo-auto`;
}
