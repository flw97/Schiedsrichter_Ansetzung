/**
 * main.ts
 *
 * Bootstraps Vuetify and other plugins then mounts the App
 */

import { createApp } from 'vue'
import { registerPlugins } from '@/plugins'
import App from './App.vue'
import '@fontsource/fraunces/500.css'
import '@fontsource/inter/400.css'
import '@fontsource/inter/500.css'
import '@fontsource/inter/600.css'
import '@fontsource/ibm-plex-mono/400.css'
import './styles/main.css'

const app = createApp(App)

registerPlugins(app)

app.mount('#app')
