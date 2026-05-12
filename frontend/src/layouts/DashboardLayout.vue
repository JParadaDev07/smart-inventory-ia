<template>
  <div class="min-h-screen bg-background">
    <header class="sticky top-0 z-50 w-full border-b bg-card">
      <div class="container flex h-14 items-center justify-between gap-4 px-4 sm:px-6">
        <router-link to="/app" class="shrink-0 font-semibold text-lg">{{ t('nav.appName') }}</router-link>
        <!-- Desktop nav -->
        <nav class="hidden items-center gap-2 md:flex">
          <router-link
            to="/app"
            data-tour="nav-dashboard"
            class="text-muted-foreground hover:text-foreground whitespace-nowrap text-sm transition-colors"
          >
            {{ t('nav.dashboard') }}
          </router-link>
          <router-link
            to="/app/products"
            data-tour="nav-products"
            class="text-muted-foreground hover:text-foreground whitespace-nowrap text-sm transition-colors"
          >
            {{ t('nav.products') }}
          </router-link>
          <router-link
            to="/app/sales"
            data-tour="nav-sales"
            class="text-muted-foreground hover:text-foreground whitespace-nowrap text-sm transition-colors"
          >
            {{ t('nav.sales') }}
          </router-link>
          <router-link
            to="/app/alerts"
            data-tour="nav-alerts"
            class="text-muted-foreground hover:text-foreground whitespace-nowrap text-sm transition-colors"
          >
            {{ t('nav.alerts') }}
          </router-link>
          <router-link
            to="/app/subscription"
            data-tour="nav-subscription"
            class="text-muted-foreground hover:text-foreground whitespace-nowrap text-sm transition-colors"
          >
            {{ t('nav.subscription') }}
          </router-link>
          <div data-tour="tour-theme-toggle" class="ml-1 flex items-center gap-1 border-l pl-2">
            <ThemeToggle />
          </div>
          <div ref="userMenuRef" class="relative">
            <Button
              variant="ghost"
              size="sm"
              class="flex items-center gap-2"
              data-tour="user-menu-toggle"
              @click="userMenuOpen = !userMenuOpen"
            >
              <span class="max-w-[140px] truncate text-sm">{{ auth.user?.name || t('nav.account') }}</span>
              <ChevronDown class="h-4 w-4" />
            </Button>
            <div
              v-if="userMenuOpen"
              class="absolute right-0 mt-1 w-44 rounded-md border bg-card text-popover-foreground shadow-md"
            >
              <router-link
                to="/app/profile"
                class="block px-3 py-2 text-sm hover:bg-accent"
                @click="closeUserMenu"
              >
                {{ t('nav.profile') }}
              </router-link>
              <router-link
                to="/app/tickets"
                class="block px-3 py-2 text-sm hover:bg-accent"
                @click="closeUserMenu"
              >
                {{ t('nav.tickets') }}
              </router-link>
              <button
                type="button"
                class="block w-full px-3 py-2 text-left text-sm hover:bg-accent"
                @click="handleStartTourFromMenu"
              >
                {{ t('nav.startTour') }}
              </button>
              <button
                type="button"
                class="block w-full px-3 py-2 text-left text-sm text-destructive hover:bg-accent/60"
                @click="handleLogoutFromMenu"
              >
                {{ t('nav.logout') }}
              </button>
            </div>
          </div>
        </nav>
        <!-- Mobile menu button -->
        <div class="flex items-center gap-2 md:hidden">
          <div data-tour="tour-theme-toggle">
            <ThemeToggle />
          </div>
          <Button variant="ghost" size="icon" class="h-9 w-9" aria-label="Menu" @click="navOpen = !navOpen">
            <Menu v-if="!navOpen" class="h-5 w-5" />
            <X v-else class="h-5 w-5" />
          </Button>
        </div>
      </div>
      <!-- Mobile nav panel -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
      >
        <div v-show="navOpen" class="border-t bg-card md:hidden" role="dialog" aria-label="Navigation">
          <nav class="container flex flex-col gap-1 px-4 py-4 sm:px-6">
            <router-link
              to="/app"
              data-tour="nav-dashboard"
              class="rounded-md px-3 py-2.5 text-sm font-medium text-foreground hover:bg-accent"
              @click="navOpen = false"
            >
              {{ t('nav.dashboard') }}
            </router-link>
            <router-link
              to="/app/products"
              data-tour="nav-products"
              class="rounded-md px-3 py-2.5 text-sm text-muted-foreground hover:bg-accent hover:text-foreground"
              @click="navOpen = false"
            >
              {{ t('nav.products') }}
            </router-link>
            <router-link
              to="/app/sales"
              data-tour="nav-sales"
              class="rounded-md px-3 py-2.5 text-sm text-muted-foreground hover:bg-accent hover:text-foreground"
              @click="navOpen = false"
            >
              {{ t('nav.sales') }}
            </router-link>
            <router-link
              to="/app/alerts"
              data-tour="nav-alerts"
              class="rounded-md px-3 py-2.5 text-sm text-muted-foreground hover:bg-accent hover:text-foreground"
              @click="navOpen = false"
            >
              {{ t('nav.alerts') }}
            </router-link>
            <router-link
              to="/app/subscription"
              data-tour="nav-subscription"
              class="rounded-md px-3 py-2.5 text-sm text-muted-foreground hover:bg-accent hover:text-foreground"
              @click="navOpen = false"
            >
              {{ t('nav.subscription') }}
            </router-link>
            <router-link
              to="/app/profile"
              class="rounded-md px-3 py-2.5 text-sm text-muted-foreground hover:bg-accent hover:text-foreground"
              @click="navOpen = false"
            >
              {{ t('nav.profile') }}
            </router-link>
            <router-link
              to="/app/tickets"
              class="rounded-md px-3 py-2.5 text-sm text-muted-foreground hover:bg-accent hover:text-foreground"
              @click="navOpen = false"
            >
              {{ t('nav.tickets') }}
            </router-link>
            <div class="mt-2 border-t pt-2">
              <Button variant="ghost" class="w-full justify-start" @click="handleLogoutFromMenu">
                {{ t('nav.logout') }}
              </Button>
            </div>
          </nav>
        </div>
      </Transition>
    </header>
    <main class="container px-4 py-6 sm:px-6">
      <router-view />
    </main>
    <Teleport to="body">
      <div
        v-if="showOnboardingPrompt"
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
      >
        <div class="absolute inset-0 bg-black/40" @click="skipTourFromPrompt" />
        <div class="relative z-10 max-w-sm w-full rounded-lg bg-card p-6 shadow-lg">
          <h2 class="text-lg font-semibold mb-2">{{ t('onboarding.title') }}</h2>
          <p class="text-sm text-muted-foreground mb-4">
            {{ t('onboarding.description') }}
          </p>
          <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
            <Button variant="outline" class="w-full sm:w-auto" @click="skipTourFromPrompt">
              {{ t('onboarding.skip') }}
            </Button>
            <Button class="w-full sm:w-auto" @click="startTourFromPrompt">
              {{ t('onboarding.start') }}
            </Button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Menu, X, ChevronDown } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import Button from '@/components/ui/Button.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import { useOnboardingTour } from '@/composables/useOnboardingTour'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const navOpen = ref(false)
const userMenuOpen = ref(false)
const userMenuRef = ref<HTMLElement | null>(null)

const showOnboardingPrompt = ref(false)
const { startDashboardTour, hasSeenTour, skipTour } = useOnboardingTour()

function closeUserMenu() {
  userMenuOpen.value = false
}

function handleDocumentClick(event: MouseEvent) {
  if (!userMenuOpen.value) return
  const target = event.target as Node | null
  const el = userMenuRef.value
  if (el && target && !el.contains(target)) {
    closeUserMenu()
  }
}

onMounted(() => {
  auth.fetchUser()
  document.addEventListener('click', handleDocumentClick)

  if (!hasSeenTour()) {
    showOnboardingPrompt.value = true
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleDocumentClick)
})

function handleLogoutFromMenu() {
  userMenuOpen.value = false
  auth.logout()
  router.push('/login')
}

function startTourFromPrompt() {
  showOnboardingPrompt.value = false
  startDashboardTour()
}

function skipTourFromPrompt() {
  showOnboardingPrompt.value = false
  skipTour()
}

function handleStartTourFromMenu() {
  closeUserMenu()
  startDashboardTour()
}
</script>
