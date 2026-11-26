// Farbschema: System ist die Voreinstellung, Hell und Dunkel bleiben als Wahl erhalten.

import { computed, ref, watch } from 'vue'
import { useTheme } from 'vuetify'

export const THEME_KEY = 'ansetzung-theme'
export const THEME_BG = { light: '#F3F7F4', dark: '#0C1612' } as const
export type ThemeChoice = 'system' | 'light' | 'dark'

export function storedTheme (): ThemeChoice {
  if (typeof localStorage === 'undefined') {
    return 'system'
  }
  try {
    const value = localStorage.getItem(THEME_KEY)
    return value === 'light' || value === 'dark' ? value : 'system'
  } catch {
    return 'system'
  }
}

const choice = ref<ThemeChoice>(storedTheme())
let watching = false

export function useThemeChoice () {
  const theme = useTheme()
  const dark = computed(() => theme.global.current.value.dark)

  function apply (next: ThemeChoice) {
    choice.value = next
    try {
      localStorage.setItem(THEME_KEY, next)
    } catch {
      // Sitzung ohne Speicher: die Wahl gilt nur bis zum Neuladen.
    }
    void theme.change(next)
  }

  if (!watching) {
    watching = true
    watch(dark, value => {
      const background = value ? THEME_BG.dark : THEME_BG.light
      document.documentElement.style.background = background
      document.documentElement.style.colorScheme = value ? 'dark' : 'light'
      document.querySelector('meta[name="theme-color"]')?.setAttribute('content', background)
    }, { immediate: true })
  }

  return { choice, dark, apply }
}
