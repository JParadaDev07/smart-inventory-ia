<template>
  <div class="flex min-h-screen items-center justify-center bg-muted/30 px-4 py-8">
    <Card class="w-full max-w-md">
      <template #header>
        <h1 class="text-2xl font-semibold">{{ t('auth.createAccount') }}</h1>
        <p class="text-muted-foreground text-sm">{{ t('auth.registerSubtitle') }}</p>
      </template>
      <form class="space-y-4" @submit.prevent="submit">
        <div class="space-y-2">
          <Label>{{ t('auth.yourName') }}</Label>
          <Input v-model="form.name" required />
        </div>
        <div class="space-y-2">
          <Label>{{ t('auth.email') }}</Label>
          <Input v-model="form.email" type="email" required />
        </div>
        <div class="space-y-2">
          <Label>{{ t('auth.password') }}</Label>
          <div class="relative">
            <Input
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              required
              minlength="8"
              class="pr-16"
            />
            <button
              type="button"
              class="absolute inset-y-0 right-2 flex items-center text-xs text-muted-foreground hover:text-primary"
              @click="showPassword = !showPassword"
            >
              {{ showPassword ? 'Ocultar' : 'Mostrar' }}
            </button>
          </div>
        </div>
        <div class="space-y-2">
          <Label>{{ t('auth.confirmPassword') }}</Label>
          <div class="relative">
            <Input
              v-model="form.password_confirmation"
              :type="showPasswordConfirm ? 'text' : 'password'"
              required
              class="pr-16"
            />
            <button
              type="button"
              class="absolute inset-y-0 right-2 flex items-center text-xs text-muted-foreground hover:text-primary"
              @click="showPasswordConfirm = !showPasswordConfirm"
            >
              {{ showPasswordConfirm ? 'Ocultar' : 'Mostrar' }}
            </button>
          </div>
        </div>
        <div class="space-y-2">
          <Label>{{ t('auth.businessName') }}</Label>
          <Input v-model="form.business_name" required />
        </div>
        <div class="space-y-2">
          <Label>{{ t('auth.addressOptional') }}</Label>
          <Input v-model="form.address" />
        </div>
        <div class="space-y-2">
          <Label>{{ t('auth.phoneOptional') }}</Label>
          <Input v-model="form.phone" />
        </div>
        <p v-if="error" class="text-destructive text-sm">{{ error }}</p>
        <Button type="submit" class="w-full" :disabled="loading">{{ t('auth.registerButton') }}</Button>
      </form>
      <template #footer>
        <p class="text-center text-muted-foreground text-sm">
          {{ t('auth.haveAccount') }}
          <router-link to="/login" class="text-primary hover:underline">{{ t('auth.signInLink') }}</router-link>
        </p>
      </template>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/api'
import { useAuthStore } from '@/stores/auth'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Label from '@/components/ui/Label.vue'
import type { User } from '@/types'

const router = useRouter()
const { t } = useI18n()
const auth = useAuthStore()
const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  business_name: '',
  address: '',
  phone: '',
})
const error = ref('')
const loading = ref(false)
const showPassword = ref(false)
const showPasswordConfirm = ref(false)

async function submit() {
  error.value = ''
  loading.value = true
  try {
    const { data } = await api.post<{ token: string; user: User }>('/auth/register', { ...form })
    auth.setAuth(data.token)
    auth.setUser(data.user)
    router.push('/app')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const msg = err.response?.data?.errors
      ? Object.values(err.response.data.errors).flat().join(' ')
      : (err.response?.data?.message ?? t('auth.registerFailed'))
    error.value = msg
  } finally {
    loading.value = false
  }
}
</script>
