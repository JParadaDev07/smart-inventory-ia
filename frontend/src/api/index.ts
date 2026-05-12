import axios from 'axios'
import type { Router } from 'vue-router'

export const TOKEN_KEY = 'smart_inventory_token'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

/**
 * Inyecta el router de Vue para que los interceptores puedan
 * navegar sin recargar la página (evita destruir el estado de Pinia).
 * Se llama una única vez desde main.ts después de crear el router.
 */
let _router: Router | null = null
export function setApiRouter(router: Router): void {
  _router = router
}

api.interceptors.response.use(
  (r) => r,
  (err) => {
    const status = err.response?.status
    const code = err.response?.data?.code

    if (status === 401) {
      localStorage.removeItem(TOKEN_KEY)
      _router ? _router.replace({ name: 'Login' }) : (window.location.href = '/login')
      return Promise.reject(err)
    }

    if (status === 403 && code === 'SUBSCRIPTION_EXPIRED') {
      const target = { name: 'Subscription', query: { expired: '1' } }
      const alreadyThere = _router?.currentRoute.value.name === 'Subscription'
      if (!alreadyThere) {
        _router ? _router.replace(target) : (window.location.href = '/app/subscription?expired=1')
      }
      return Promise.reject(err)
    }

    if (status === 403 && code === 'EMAIL_NOT_VERIFIED') {
      _router
        ? _router.replace({ name: 'VerifyEmail' })
        : (window.location.href = '/app/verify-email')
      return Promise.reject(err)
    }

    return Promise.reject(err)
  },
)

export default api
