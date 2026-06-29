import { createPinia } from 'pinia'
import type { App } from 'vue'

export default function (app: App) {
  app.use(createPinia())
}
