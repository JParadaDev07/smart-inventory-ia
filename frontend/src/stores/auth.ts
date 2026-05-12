import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api, { TOKEN_KEY } from '@/api'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem(TOKEN_KEY))
  const user = ref<User | null>(null)

  const isAuthenticated = computed(() => !!token.value)
  const isPro = computed(() => user.value?.subscription?.is_pro ?? false)

  function setAuth(t: string | null): void {
    token.value = t
    if (t) localStorage.setItem(TOKEN_KEY, t)
    else localStorage.removeItem(TOKEN_KEY)
  }

  function setUser(u: User | null): void {
    user.value = u
  }

  async function fetchUser(): Promise<void> {
    if (!token.value) return
    const { data } = await api.get<{ user: User }>('/auth/user')
    user.value = data.user
  }

  function logout(): void {
    setAuth(null)
    setUser(null)
  }

  return {
    token,
    user,
    isAuthenticated,
    isPro,
    setAuth,
    setUser,
    fetchUser,
    logout,
  }
})
