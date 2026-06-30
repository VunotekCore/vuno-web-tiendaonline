import es from "./es.json";
import en from "./en.json";

export type Locale = "es" | "en";
export const defaultLocale: Locale = "es";
export const locales: Locale[] = ["es", "en"];

const translations: Record<Locale, Record<string, string>> = { es, en };

export function t(key: string, locale: Locale): string {
  return translations[locale]?.[key] || key;
}

export function localizedPath(path: string, fromLang: Locale, toLang: Locale): string {
  if (fromLang === toLang) return path;
  return path.replace(`/${fromLang}/`, `/${toLang}/`);
}

export function stripLang(path: string): string {
  return path.replace(/^\/(es|en)(\/|$)/, "/$2");
}
