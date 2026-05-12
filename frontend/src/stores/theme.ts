import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

const STORAGE_KEY = 'smart_inventory_theme'

export type Theme = 'light' | 'dark' | 'system'

function getSystemDark(): boolean {
  if (typeof window === 'undefined') return false
  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

function applyTheme(theme: Theme): void {
  if (typeof document === 'undefined') return
  const root = document.documentElement
  const isDark =
    theme === 'dark' || (theme === 'system' && getSystemDark())
  if (isDark) {
    root.classList.add('dark')
  } else {
    root.classList.remove('dark')
  }
}

export const useThemeStore = defineStore('theme', () => {
  const stored = localStorage.getItem(STORAGE_KEY) as Theme | null
  const theme = ref<Theme>(
    stored === 'light' || stored === 'dark' || stored === 'system' ? stored : 'system'
  )

  function setTheme(value: Theme): void {
    theme.value = value
    localStorage.setItem(STORAGE_KEY, value)
    applyTheme(value)
  }

  function init(): void {
    applyTheme(theme.value)
    if (typeof window !== 'undefined') {
      window
        .matchMedia('(prefers-color-scheme: dark)')
        .addEventListener('change', () => {
          if (theme.value === 'system') applyTheme('system')
        })
    }
  }

  watch(theme, (v) => applyTheme(v), { immediate: false })

  return {
    theme,
    setTheme,
    init,
  }
})
