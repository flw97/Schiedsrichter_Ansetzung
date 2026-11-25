/**
 * plugins/vuetify.ts
 *
 * Farbwelt des Prototyps: Hallenboden-Grün, Bernstein, Moos und Rost auf warmem Papierton.
 */

import { createVuetify } from 'vuetify'
import { de } from 'vuetify/locale'
import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'

export default createVuetify({
  locale: { locale: 'de', messages: { de } },
  theme: {
    defaultTheme: 'court',
    themes: {
      court: {
        dark: false,
        colors: {
          'background': '#F5F3EC',
          'surface': '#FFFFFF',
          'surface-variant': '#F1EFE6',
          'on-surface-variant': '#5C685F',
          'primary': '#1F4B3F',
          'secondary': '#274472',
          'accent': '#D98F3E',
          'warning': '#B5782A',
          'error': '#B4533D',
          'success': '#5C8A72',
          'info': '#274472',
        },
        variables: {
          'border-color': '#E4DFD1',
          'border-opacity': 1,
        },
      },
    },
  },
  defaults: {
    VCard: { variant: 'flat', border: true, rounded: 'lg' },
    VBtn: { rounded: 'lg', class: 'text-none' },
    VTextField: { variant: 'outlined', density: 'compact', hideDetails: 'auto' },
    VTextarea: { variant: 'outlined', density: 'compact', hideDetails: 'auto', autoGrow: true, rows: 2 },
    VSelect: { variant: 'outlined', density: 'compact', hideDetails: 'auto' },
    VCheckbox: { density: 'compact', hideDetails: true, color: 'primary' },
    VSwitch: { density: 'compact', hideDetails: true, color: 'primary', inset: true },
    VChip: { size: 'small', label: true, variant: 'tonal' },
  },
})
