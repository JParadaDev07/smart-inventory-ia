import { defineStore } from 'pinia'
import { ref } from 'vue'
import i18n, { type LocaleId } from '@/i18n'

const DEFAULT_LOCALE: LocaleId = 'es'

export const useLocaleStore = defineStore('locale', () => {
  const locale = ref<LocaleId>(DEFAULT_LOCALE)

  function setLocale(value: LocaleId): void {
    locale.value = value
    i18n.global.locale.value = value
  }

  function init(): void {
    i18n.global.locale.value = DEFAULT_LOCALE
    locale.value = DEFAULT_LOCALE
  }

  return {
    locale,
    setLocale,
    init,
  }
})
