import { defineConfig } from 'astro/config'
import tailwindcss from '@tailwindcss/vite'
import sitemap from '@astrojs/sitemap'

export default defineConfig({
  site: 'https://shop.anicasolucionesintegrales.com',
  output: 'static',
  trailingSlash: 'always',
  compressHTML: true,
  vite: {
    plugins: [tailwindcss()]
  },
  integrations: [
    sitemap({
      i18n: {
        defaultLocale: 'es',
        locales: {
          es: 'es-NI',
          en: 'en-US'
        }
      },
      serialize: (entry) => ({
        ...entry,
        changefreq: entry.changefreq || 'weekly',
        priority: entry.priority || 0.7
      })
    })
  ]
})
