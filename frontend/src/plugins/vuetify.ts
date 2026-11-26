/**
 * plugins/vuetify.ts
 *
 * Farbwelt: Vereinsgrün und Weiß, Gold nur als kleines Akzent (Logo).
 * `light` / `dark` heißen so, weil Vuetify bei `defaultTheme: 'system'`
 * genau diese beiden Namen über prefers-color-scheme tauscht.
 * Seitenflächen folgen dem Schema; `brand` bleibt in beiden das Vereinsgrün der Navigation.
 */

import { createVuetify } from 'vuetify'
import { de } from 'vuetify/locale'
import { storedTheme, THEME_BG } from '@/theme'
import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'

const brand = '#1B4336'

export default createVuetify({
  locale: { locale: 'de', messages: { de } },
  theme: {
    defaultTheme: storedTheme(),
    themes: {
      light: {
        dark: false,
        colors: {
          'background': THEME_BG.light,
          'surface': '#FFFFFF',
          'surface-variant': '#E7F2EC',
          'on-background': '#13261F',
          'on-surface': '#13261F',
          'on-surface-variant': '#4B6156',
          'primary': brand,
          'on-primary': '#FFFFFF',
          'secondary': '#2D6A4F',
          brand,
          'on-brand': '#FFFFFF',
          'accent': '#C4A15A',
          'warning': '#A9782A',
          'error': '#C23B32',
          'success': '#2D6A4F',
          'info': brand,
          'amber': '#8A6416',
          'star': '#B7791F',
          'chart-distance': brand,
          'chart-avail': '#3E8F6A',
          'chart-fair': '#C4A15A',
          'card-schwarz': '#1B241F',
        },
        variables: {
          'border-color': '#D5E4DB',
          'border-opacity': 1,
          'hover-opacity': 0.05,
        },
      },
      dark: {
        dark: true,
        colors: {
          'background': THEME_BG.dark,
          'surface': '#16241D',
          'surface-bright': '#20362B',
          'surface-light': '#20362B',
          'surface-variant': '#24382E',
          'on-background': '#E7F2EC',
          'on-surface': '#E7F2EC',
          'on-surface-variant': '#A8C4B4',
          'primary': '#8FCBB0',
          'on-primary': THEME_BG.dark,
          'secondary': '#6FBF96',
          'on-secondary': THEME_BG.dark,
          brand,
          'on-brand': '#FFFFFF',
          'accent': '#D4B56A',
          'on-accent': THEME_BG.dark,
          'warning': '#E0A84A',
          'on-warning': THEME_BG.dark,
          'error': '#F07167',
          'on-error': THEME_BG.dark,
          'success': '#6FBF96',
          'on-success': THEME_BG.dark,
          'info': '#8FCBB0',
          'on-info': THEME_BG.dark,
          'amber': '#E4C07A',
          'star': '#E4C07A',
          'chart-distance': '#9ED9BE',
          'chart-avail': '#5EBF8A',
          'chart-fair': '#E0C07A',
          'card-schwarz': '#E4E4E4',
        },
        variables: {
          'border-color': '#2C4538',
          'border-opacity': 1,
          'hover-opacity': 0.08,
        },
      },
    },
  },
  defaults: {
    VCard: { variant: 'flat', border: true, rounded: 'lg' },
    VBtn: { rounded: 'lg', class: 'text-none', elevation: 0 },
    VTextField: { variant: 'outlined', density: 'compact', hideDetails: 'auto' },
    VTextarea: { variant: 'outlined', density: 'compact', hideDetails: 'auto', autoGrow: true, rows: 2 },
    VSelect: { variant: 'outlined', density: 'compact', hideDetails: 'auto' },
    VCheckbox: { density: 'compact', hideDetails: true, color: 'primary' },
    VSwitch: { density: 'compact', hideDetails: true, color: 'primary', inset: true },
    VChip: { size: 'small', rounded: 'lg', variant: 'tonal' },
  },
})
