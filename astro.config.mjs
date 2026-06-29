import { defineConfig } from 'astro/config'
import tailwindcss from '@tailwindcss/vite'
import sitemap from '@astrojs/sitemap'
import vue from '@astrojs/vue'

export default defineConfig({
  site: 'https://shop.anicasolucionesintegrales.com',
  output: 'static',
  trailingSlash: 'ignore',
  compressHTML: true,
  devToolbar: {
    enabled: false,
  },
  integrations: [
    vue({ appEntrypoint: '/src/plugins/vue-entrypoint' }),
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
  ],
  vite: {
    plugins: [tailwindcss()],
    define: {
      __VUE_PROD_DEVTOOLS__: false,
    },
    server: {
      proxy: {
        '/api': 'http://127.0.0.1:8000'
      }
    }
  }
})
