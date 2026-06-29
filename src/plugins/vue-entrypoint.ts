import { createPinia } from 'pinia'
import type { App } from 'vue'

export default function (app: App) {
  ;(app.config as any).devtools = false
  app.use(createPinia())
}
