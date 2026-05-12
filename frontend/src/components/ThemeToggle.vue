<template>
  <button
    type="button"
    aria-label="Toggle theme"
    class="inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
    @click="toggle"
  >
    <Sun class="h-4 w-4 dark:hidden" />
    <Moon class="h-4 w-4 hidden dark:block" />
  </button>
</template>

<script setup lang="ts">
import { Sun, Moon } from 'lucide-vue-next'
import { useThemeStore } from '@/stores/theme'

const theme = useThemeStore()

function toggle() {
  const isDark = document.documentElement.classList.contains('dark')
  theme.setTheme(isDark ? 'light' : 'dark')
}
</script>
