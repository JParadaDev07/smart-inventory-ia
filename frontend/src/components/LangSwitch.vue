<template>
  <div class="relative" ref="rootRef">
    <button
      type="button"
      :aria-label="t('lang.label')"
      class="inline-flex h-9 items-center gap-1.5 rounded-md px-2.5 text-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
      @click="open = !open"
    >
      <Globe class="h-4 w-4" />
      <span class="uppercase">{{ localeStore.locale }}</span>
      <ChevronDown class="h-3.5 w-3 opacity-70" />
    </button>
    <Transition
      enter-active-class="transition duration-100 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-75 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-show="open"
        class="absolute right-0 top-full z-50 mt-1 min-w-[8rem] rounded-md border bg-card py-1 shadow-lg"
      >
        <button
          v-for="id in locales"
          :key="id"
          type="button"
          class="w-full px-3 py-2 text-left text-sm hover:bg-accent"
          :class="localeStore.locale === id ? 'bg-accent/50 font-medium' : ''"
          @click="select(id)"
        >
          {{ id === 'en' ? t('lang.en') : t('lang.es') }}
        </button>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { Globe, ChevronDown } from 'lucide-vue-next'
import { useLocaleStore } from '@/stores/locale'
import type { LocaleId } from '@/i18n'

const { t } = useI18n()
const localeStore = useLocaleStore()
const open = ref(false)
const rootRef = ref<HTMLElement | null>(null)
const locales: LocaleId[] = ['en', 'es']

function select(id: LocaleId) {
  localeStore.setLocale(id)
  open.value = false
}

function onDocClick(e: MouseEvent) {
  if (rootRef.value && !rootRef.value.contains(e.target as Node)) {
    open.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', onDocClick)
})
onUnmounted(() => {
  document.removeEventListener('click', onDocClick)
})
</script>
