/**
 * plugins/index.ts
 *
 * Automatically included in `./src/main.ts`
 */

import type { App } from 'vue'
import { createPinia } from 'pinia'
import Vue3Toastify, { toast } from 'vue3-toastify'
import router from '@/router'
import vuetify from './vuetify'
import 'vue3-toastify/dist/index.css'

export function registerPlugins (app: App) {
  app.use(vuetify)
  app.use(createPinia())
  app.use(router)
  app.use(Vue3Toastify, {
    autoClose: 4500,
    clearOnUrlChange: false,
    closeOnClick: true,
    newestOnTop: true,
    pauseOnHover: true,
    position: toast.POSITION.TOP_RIGHT,
  })
}
