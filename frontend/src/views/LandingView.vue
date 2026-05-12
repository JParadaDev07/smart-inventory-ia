<template>
  <div class="min-h-screen bg-background">
    <!-- Sticky Nav -->
    <header class="sticky top-0 z-50 w-full border-b bg-card/95 backdrop-blur supports-[backdrop-filter]:bg-card/80">
      <div class="container flex h-14 items-center justify-between gap-4 px-4 sm:px-6">
        <router-link to="/" class="flex shrink-0 items-center gap-2 font-semibold text-lg">
          <Package class="h-6 w-6 text-primary" />
          {{ t('nav.appName') }}
        </router-link>
        <!-- Desktop nav -->
        <nav class="hidden items-center gap-2 md:flex">
          <a href="#how-it-works" class="text-muted-foreground hover:text-foreground whitespace-nowrap text-sm transition-colors">{{ t('nav.howItWorks') }}</a>
          <a href="#features" class="text-muted-foreground hover:text-foreground whitespace-nowrap text-sm transition-colors">{{ t('nav.features') }}</a>
          <a href="#pricing" class="text-muted-foreground hover:text-foreground whitespace-nowrap text-sm transition-colors">{{ t('nav.pricing') }}</a>
          <div class="ml-1 flex items-center gap-1 border-l pl-2">
            <ThemeToggle />
          </div>
          <router-link to="/login" class="text-muted-foreground hover:text-foreground text-sm transition-colors">{{ t('nav.signIn') }}</router-link>
          <router-link
            to="/register"
            class="inline-flex h-9 items-center justify-center rounded-md px-4 text-sm font-medium bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
          >
            {{ t('nav.startFreeTrial') }}
          </router-link>
        </nav>
        <!-- Mobile: theme + menu -->
        <div class="flex items-center gap-2 md:hidden">
          <ThemeToggle />
          <button
            type="button"
            aria-label="Menu"
            class="inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
            @click="landingNavOpen = !landingNavOpen"
          >
            <Menu v-if="!landingNavOpen" class="h-5 w-5" />
            <X v-else class="h-5 w-5" />
          </button>
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
        <div v-show="landingNavOpen" class="border-t bg-card md:hidden" role="dialog" aria-label="Navigation">
          <nav class="container flex flex-col gap-1 px-4 py-4 sm:px-6">
            <a href="#how-it-works" class="rounded-md px-3 py-2.5 text-sm text-muted-foreground hover:bg-accent hover:text-foreground" @click="landingNavOpen = false">{{ t('nav.howItWorks') }}</a>
            <a href="#features" class="rounded-md px-3 py-2.5 text-sm text-muted-foreground hover:bg-accent hover:text-foreground" @click="landingNavOpen = false">{{ t('nav.features') }}</a>
            <a href="#pricing" class="rounded-md px-3 py-2.5 text-sm text-muted-foreground hover:bg-accent hover:text-foreground" @click="landingNavOpen = false">{{ t('nav.pricing') }}</a>
            <router-link to="/login" class="rounded-md px-3 py-2.5 text-sm text-muted-foreground hover:bg-accent hover:text-foreground" @click="landingNavOpen = false">{{ t('nav.signIn') }}</router-link>
            <router-link to="/register" class="rounded-md px-3 py-2.5 text-sm font-medium text-primary-foreground bg-primary hover:bg-primary/90" @click="landingNavOpen = false">{{ t('nav.startFreeTrial') }}</router-link>
          </nav>
        </div>
      </Transition>
    </header>

    <main class="overflow-x-hidden">
      <!-- 1) HERO -->
      <section class="relative px-4 pt-12 pb-20 sm:pt-16 sm:pb-24 md:pt-24 md:pb-32">
        <div class="absolute inset-0 bg-gradient-to-br from-primary/8 via-background to-primary/5" aria-hidden="true" />
        <div class="container relative mx-auto max-w-6xl">
          <div class="grid gap-12 lg:grid-cols-2 lg:gap-16 lg:items-center">
            <div class="space-y-8">
              <h1 class="text-4xl font-bold tracking-tight text-foreground sm:text-5xl lg:text-6xl">
                {{ t('landing.hero.headline') }}
              </h1>
              <p class="text-lg text-muted-foreground max-w-xl">
                {{ t('landing.hero.subheadline') }}
              </p>
              <div class="flex flex-wrap gap-4">
                <router-link
                  to="/register"
                  class="inline-flex items-center justify-center rounded-md text-sm font-medium bg-primary text-primary-foreground hover:bg-primary/90 h-11 px-8 transition-colors shadow-lg shadow-primary/25"
                >
                  {{ t('landing.hero.ctaPrimary') }}
                </router-link>
                <a
                  href="#how-it-works"
                  class="inline-flex items-center justify-center rounded-md border border-input bg-background hover:bg-accent hover:text-accent-foreground h-11 px-6 text-sm font-medium transition-colors"
                >
                  {{ t('landing.hero.ctaSecondary') }}
                </a>
              </div>
              <div class="flex flex-wrap gap-6 text-sm text-muted-foreground">
                <span class="flex items-center gap-2">
                  <ShieldCheck class="h-4 w-4 text-primary" />
                  {{ t('landing.hero.secure') }}
                </span>
                <span class="flex items-center gap-2">
                  <Cloud class="h-4 w-4 text-primary" />
                  {{ t('landing.hero.cloud') }}
                </span>
                <span class="flex items-center gap-2">
                  <Zap class="h-4 w-4 text-primary" />
                  {{ t('landing.hero.easySetup') }}
                </span>
              </div>
            </div>
            <div class="relative">
              <!-- Dashboard mockup -->
              <div class="rounded-xl border bg-card shadow-2xl shadow-primary/10 p-4 md:p-6">
                <div class="mb-4 flex items-center gap-2 border-b pb-3">
                  <div class="h-2 w-2 rounded-full bg-destructive" />
                  <div class="h-2 w-2 rounded-full bg-muted-foreground/50" />
                  <div class="h-2 w-2 rounded-full bg-muted-foreground/50" />
                  <span class="ml-2 text-xs text-muted-foreground">{{ t('landing.hero.dashboard') }}</span>
                </div>
                <div class="grid grid-cols-3 gap-2 md:gap-4 mb-4">
                  <div v-for="i in 3" :key="i" class="rounded-lg bg-muted/60 p-3">
                    <div class="h-2 w-12 rounded bg-muted-foreground/30 mb-2" />
                    <div class="h-5 w-16 rounded bg-primary/20" />
                  </div>
                </div>
                <div class="space-y-2">
                  <div class="h-3 w-full rounded bg-muted/80" style="width: 85%" />
                  <div class="h-3 w-full rounded bg-muted/80" style="width: 70%" />
                  <div class="h-3 w-full rounded bg-muted/80" style="width: 92%" />
                  <div class="h-3 w-full rounded bg-primary/30" style="width: 45%" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- 2) PROBLEM -->
      <section id="problem" class="border-t bg-muted/30 px-4 py-16 md:py-24">
        <div class="container mx-auto max-w-6xl">
          <h2 class="text-center text-3xl font-bold tracking-tight text-foreground md:text-4xl">
            {{ t('landing.problem.title') }}
          </h2>
          <p class="mx-auto mt-4 max-w-2xl text-center text-muted-foreground">
            {{ t('landing.problem.subtitle') }}
          </p>
          <ul class="mx-auto mt-12 grid max-w-3xl gap-4 sm:grid-cols-2">
            <li
              v-for="(pain, i) in painPoints"
              :key="i"
              class="flex gap-4 rounded-lg border bg-card p-4 shadow-sm"
            >
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-destructive/10">
                <AlertTriangle class="h-5 w-5 text-destructive" />
              </div>
              <div>
                <p class="font-medium text-foreground">{{ pain.title }}</p>
                <p class="text-sm text-muted-foreground mt-0.5">{{ pain.desc }}</p>
              </div>
            </li>
          </ul>
        </div>
      </section>

      <!-- 3) SOLUTION -->
      <section id="solution" class="px-4 py-16 md:py-24">
        <div class="container mx-auto max-w-6xl">
          <h2 class="text-center text-3xl font-bold tracking-tight text-foreground md:text-4xl">
            {{ t('landing.solution.title') }}
          </h2>
          <p class="mx-auto mt-4 max-w-2xl text-center text-muted-foreground">
            {{ t('landing.solution.subtitle') }}
          </p>
          <div class="mt-12 grid gap-6 md:grid-cols-3">
            <Card
              v-for="(card, i) in solutionCards"
              :key="i"
              class="flex h-full flex-col"
              content-class="flex-1 pt-0 px-6 pb-6"
            >
              <template #header>
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10 text-primary">
                  <component :is="card.icon" class="h-6 w-6" />
                </div>
                <h3 class="mt-3 text-lg font-semibold leading-tight text-foreground">{{ card.title }}</h3>
              </template>
              <p class="text-muted-foreground text-sm leading-relaxed">{{ card.desc }}</p>
            </Card>
          </div>
        </div>
      </section>

      <!-- 4) HOW IT WORKS -->
      <section id="how-it-works" class="border-t bg-muted/30 px-4 py-16 md:py-24">
        <div class="container mx-auto max-w-6xl">
          <h2 class="text-center text-3xl font-bold tracking-tight text-foreground md:text-4xl">
            {{ t('landing.howItWorks.title') }}
          </h2>
          <p class="mx-auto mt-4 max-w-xl text-center text-muted-foreground">
            {{ t('landing.howItWorks.subtitle') }}
          </p>
          <div class="mt-16 grid gap-8 md:grid-cols-3">
            <div
              v-for="(step, i) in steps"
              :key="i"
              class="relative flex flex-col items-center text-center"
            >
              <div class="flex h-14 w-14 items-center justify-center rounded-full bg-primary text-primary-foreground text-xl font-bold">
                {{ i + 1 }}
              </div>
              <h3 class="mt-4 text-lg font-semibold text-foreground">{{ step.title }}</h3>
              <p class="mt-2 text-sm text-muted-foreground">{{ step.desc }}</p>
              <div v-if="i < steps.length - 1" class="absolute top-7 left-[calc(50%+2rem)] hidden h-0.5 w-[calc(100%-4rem)] bg-border md:block" aria-hidden="true" />
            </div>
          </div>
          <div class="mt-12 text-center">
            <router-link
              to="/register"
              class="inline-flex items-center justify-center rounded-md text-sm font-medium bg-primary text-primary-foreground hover:bg-primary/90 h-11 px-8 transition-colors"
            >
              {{ t('nav.startFreeTrial') }}
            </router-link>
          </div>
        </div>
      </section>

      <!-- 5) FEATURES GRID -->
      <section id="features" class="px-4 py-16 md:py-24">
        <div class="container mx-auto max-w-6xl">
          <h2 class="text-center text-3xl font-bold tracking-tight text-foreground md:text-4xl">
            {{ t('landing.featuresSection.title') }}
          </h2>
          <p class="mx-auto mt-4 max-w-xl text-center text-muted-foreground">
            {{ t('landing.featuresSection.subtitle') }}
          </p>
          <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card
              v-for="(f, i) in features"
              :key="i"
              class="flex h-full flex-col"
              content-class="flex-1 pt-0 px-6 pb-6"
            >
              <template #header>
                <div class="flex items-start gap-3">
                  <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <component :is="f.icon" class="h-5 w-5" />
                  </div>
                  <h3 class="font-semibold text-foreground leading-tight pt-0.5">{{ f.title }}</h3>
                </div>
              </template>
              <p class="text-muted-foreground text-sm leading-relaxed">{{ f.desc }}</p>
            </Card>
          </div>
        </div>
      </section>

      <!-- 6) PRICING -->
      <section id="pricing" class="border-t bg-muted/30 px-4 py-16 md:py-24">
        <div class="container mx-auto max-w-6xl">
          <h2 class="text-center text-3xl font-bold tracking-tight text-foreground md:text-4xl">
            {{ t('landing.pricingSection.title') }}
          </h2>
          <p class="mx-auto mt-4 max-w-xl text-center text-muted-foreground">
            {{ t('landing.pricingSection.subtitle') }}
          </p>
          <div class="mt-12 grid gap-8 md:grid-cols-3 md:max-w-5xl md:mx-auto">
            <Card class="relative flex flex-col">
              <template #header>
                <h3 class="text-xl font-semibold text-foreground">{{ t('landing.pricingSection.basic') }}</h3>
                <p class="text-muted-foreground text-sm mt-1">{{ t('landing.pricingSection.basicDesc') }}</p>
              </template>
              <ul class="space-y-3 text-sm text-muted-foreground">
                <li class="flex items-center gap-2">
                  <Check class="h-4 w-4 text-primary shrink-0" /> {{ t('landing.pricingSection.salesTracking') }}
                </li>
                <li class="flex items-center gap-2">
                  <Check class="h-4 w-4 text-primary shrink-0" /> {{ t('landing.pricingSection.stockAlerts') }}
                </li>
                <li class="flex items-center gap-2">
                  <Check class="h-4 w-4 text-primary shrink-0" /> {{ t('landing.pricingSection.reorderCalc') }}
                </li>
              </ul>
              <template #footer>
                <router-link
                  to="/register"
                  class="block w-full rounded-md border border-input bg-background py-2.5 text-center text-sm font-medium hover:bg-accent transition-colors"
                >
                  {{ t('nav.startFreeTrial') }}
                </router-link>
              </template>
            </Card>
            <Card class="relative flex flex-col border-primary shadow-lg shadow-primary/10">
              <div class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-primary px-3 py-0.5 text-xs font-medium text-primary-foreground">
                {{ t('landing.pricingSection.mostPopular') }}
              </div>
              <template #header>
                <h3 class="text-xl font-semibold text-foreground">{{ t('landing.pricingSection.pro') }}</h3>
                <p class="text-muted-foreground text-sm mt-1">{{ t('landing.pricingSection.proDesc') }}</p>
              </template>
              <ul class="space-y-3 text-sm text-muted-foreground">
                <li class="flex items-center gap-2">
                  <Check class="h-4 w-4 text-primary shrink-0" /> {{ t('landing.pricingSection.everythingInBasic') }}
                </li>
                <li class="flex items-center gap-2">
                  <Check class="h-4 w-4 text-primary shrink-0" /> {{ t('landing.pricingSection.aiPrediction') }}
                </li>
                <li class="flex items-center gap-2">
                  <Check class="h-4 w-4 text-primary shrink-0" /> {{ t('landing.pricingSection.advancedForecasting') }}
                </li>
                <li class="flex items-center gap-2">
                  <Check class="h-4 w-4 text-primary shrink-0" /> {{ t('landing.pricingSection.purchaseOptimization') }}
                </li>
              </ul>
              <template #footer>
                <router-link
                  to="/register"
                  class="block w-full rounded-md bg-primary py-2.5 text-center text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
                >
                  {{ t('nav.startFreeTrial') }}
                </router-link>
              </template>
            </Card>
            <Card class="relative flex flex-col">
              <template #header>
                <h3 class="text-xl font-semibold text-foreground">{{ t('landing.pricingSection.enterprise') }}</h3>
                <p class="text-muted-foreground text-sm mt-1">
                  {{ t('landing.pricingSection.enterpriseDesc') }}
                </p>
              </template>
              <ul class="space-y-3 text-sm text-muted-foreground">
                <li class="flex items-center gap-2">
                  <Check class="h-4 w-4 text-primary shrink-0" /> {{ t('landing.pricingSection.everythingInPro') }}
                </li>
                <li class="flex items-center gap-2">
                  <Check class="h-4 w-4 text-primary shrink-0" />
                  {{ t('landing.pricingSection.multiLocationAdvanced') }}
                </li>
                <li class="flex items-center gap-2">
                  <Check class="h-4 w-4 text-primary shrink-0" />
                  {{ t('landing.pricingSection.prioritySupport') }}
                </li>
              </ul>
              <template #footer>
                <router-link
                  to="/register"
                  class="block w-full rounded-md border border-input bg-background py-2.5 text-center text-sm font-medium hover:bg-accent transition-colors"
                >
                  {{ t('subscription.subscribeEnterprise') }}
                </router-link>
              </template>
            </Card>
          </div>
        </div>
      </section>

      <!-- 7) TRUST -->
      <section class="px-4 py-16 md:py-24">
        <div class="container mx-auto max-w-6xl">
          <h2 class="text-center text-2xl font-bold tracking-tight text-foreground md:text-3xl">
            {{ t('landing.trust.title') }}<br />{{ t('landing.trust.title2') }}
          </h2>
          <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <Card class="flex h-full flex-col" content-class="flex-1 p-6">
              <p class="text-muted-foreground text-sm italic leading-relaxed">{{ t('landing.trust.testimonial1') }}</p>
              <p class="mt-3 text-sm font-medium text-foreground">{{ t('landing.trust.author1') }}</p>
            </Card>
            <Card class="flex h-full flex-col" content-class="flex-1 p-6">
              <p class="text-muted-foreground text-sm italic leading-relaxed">{{ t('landing.trust.testimonial2') }}</p>
              <p class="mt-3 text-sm font-medium text-foreground">{{ t('landing.trust.author2') }}</p>
            </Card>
            <Card class="flex h-full flex-col" content-class="flex-1 p-6">
              <p class="text-muted-foreground text-sm italic leading-relaxed">{{ t('landing.trust.testimonial3') }}</p>
              <p class="mt-3 text-sm font-medium text-foreground">{{ t('landing.trust.author3') }}</p>
            </Card>
          </div>
          <div class="mt-12 flex flex-wrap items-center justify-center gap-6 text-muted-foreground">
            <span class="flex items-center gap-2">
              <ShieldCheck class="h-5 w-5 text-primary" />
              {{ t('landing.trust.secureCloud') }}
            </span>
            <span class="flex items-center gap-2">
              <Lock class="h-5 w-5 text-primary" />
              {{ t('landing.trust.dataIsolation') }}
            </span>
          </div>
        </div>
      </section>

      <!-- 8) FINAL CTA -->
      <section class="border-t bg-gradient-to-br from-primary/10 via-background to-primary/5 px-4 py-20 md:py-28">
        <div class="container mx-auto max-w-3xl text-center">
          <h2 class="text-3xl font-bold tracking-tight text-foreground md:text-4xl">
            {{ t('landing.finalCta.headline') }}
          </h2>
          <p class="mt-4 text-lg text-muted-foreground">
            {{ t('landing.finalCta.subheadline') }}
          </p>
          <router-link
            to="/register"
            class="mt-8 inline-flex w-full sm:w-auto items-center justify-center rounded-md text-sm font-medium bg-primary text-primary-foreground hover:bg-primary/90 h-12 px-10 transition-colors shadow-lg shadow-primary/25"
          >
            {{ t('landing.finalCta.cta') }}
          </router-link>
        </div>
      </section>

      <!-- Footer -->
      <footer class="border-t px-4 py-8">
        <div class="container mx-auto max-w-6xl flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-muted-foreground">
          <div class="flex items-center gap-2">
            <Package class="h-4 w-4" />
            {{ t('nav.appName') }}
          </div>
          <div class="flex gap-6">
            <router-link to="/login" class="hover:text-foreground transition-colors">{{ t('nav.signIn') }}</router-link>
            <router-link to="/register" class="hover:text-foreground transition-colors">{{ t('nav.startTrial') }}</router-link>
          </div>
        </div>
      </footer>
    </main>
  </div>
</template>

<script setup lang="ts">
import {
  Package,
  ShieldCheck,
  Cloud,
  Zap,
  AlertTriangle,
  Target,
  TrendingDown,
  Sparkles,
  Store,
  BarChart3,
  Lock,
  Check,
  Menu,
  X,
} from 'lucide-vue-next'
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'

const { t } = useI18n()
const landingNavOpen = ref(false)

const painPoints = computed(() => [
  { title: t('landing.problem.pains.0.title'), desc: t('landing.problem.pains.0.desc') },
  { title: t('landing.problem.pains.1.title'), desc: t('landing.problem.pains.1.desc') },
  { title: t('landing.problem.pains.2.title'), desc: t('landing.problem.pains.2.desc') },
  { title: t('landing.problem.pains.3.title'), desc: t('landing.problem.pains.3.desc') },
])

const solutionCards = computed(() => [
  { icon: Target, title: t('landing.solution.reorder.title'), desc: t('landing.solution.reorder.desc') },
  { icon: TrendingDown, title: t('landing.solution.stockOut.title'), desc: t('landing.solution.stockOut.desc') },
  { icon: Sparkles, title: t('landing.solution.aiForecast.title'), desc: t('landing.solution.aiForecast.desc') },
])

const steps = computed(() => [
  { title: t('landing.howItWorks.step1.title'), desc: t('landing.howItWorks.step1.desc') },
  { title: t('landing.howItWorks.step2.title'), desc: t('landing.howItWorks.step2.desc') },
  { title: t('landing.howItWorks.step3.title'), desc: t('landing.howItWorks.step3.desc') },
])

const features = computed(() => [
  { icon: Store, title: t('landing.featuresSection.multiStore.title'), desc: t('landing.featuresSection.multiStore.desc') },
  { icon: Cloud, title: t('landing.featuresSection.cloud.title'), desc: t('landing.featuresSection.cloud.desc') },
  { icon: Lock, title: t('landing.featuresSection.secure.title'), desc: t('landing.featuresSection.secure.desc') },
  { icon: Package, title: t('landing.featuresSection.lowStock.title'), desc: t('landing.featuresSection.lowStock.desc') },
  { icon: BarChart3, title: t('landing.featuresSection.analytics.title'), desc: t('landing.featuresSection.analytics.desc') },
  { icon: Sparkles, title: t('landing.featuresSection.aiPro.title'), desc: t('landing.featuresSection.aiPro.desc') },
])
</script>
