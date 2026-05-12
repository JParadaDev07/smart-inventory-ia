import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { VueQueryPlugin } from '@tanstack/vue-query'
import App from './App.vue'
import router from './router'
import i18n from '@/i18n'
import { useThemeStore } from '@/stores/theme'
import { useLocaleStore } from '@/stores/locale'
import { setApiRouter } from '@/api'
import './assets/main.css'

const app = createApp(App)
const pinia = createPinia()
app.use(pinia)
app.use(i18n)
app.use(router)
app.use(VueQueryPlugin)

// Inyecta el router en el interceptor de Axios para navegar sin recargar la SPA
setApiRouter(router)

const themeStore = useThemeStore()
themeStore.init()
const localeStore = useLocaleStore()
localeStore.init()

app.mount('#app')
