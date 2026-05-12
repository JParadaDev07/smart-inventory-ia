import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    guest?: boolean
  }
}

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'Landing', component: () => import('@/views/LandingView.vue') },
    { path: '/login', name: 'Login', component: () => import('@/views/LoginView.vue'), meta: { guest: true } },
    { path: '/forgot-password', name: 'ForgotPassword', component: () => import('@/views/ForgotPasswordView.vue'), meta: { guest: true } },
    { path: '/reset-password', name: 'ResetPassword', component: () => import('@/views/ResetPasswordView.vue'), meta: { guest: true } },
    { path: '/register', name: 'Register', component: () => import('@/views/RegisterView.vue'), meta: { guest: true } },
    {
      path: '/app',
      component: () => import('@/layouts/DashboardLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: '', name: 'Dashboard', component: () => import('@/views/DashboardView.vue') },
        { path: 'products', name: 'Products', component: () => import('@/views/ProductsView.vue') },
        { path: 'sales', name: 'Sales', component: () => import('@/views/SalesView.vue') },
        // Alertas de inventario
        { path: 'alerts', name: 'Alerts', component: () => import('@/views/AlertsView.vue') },
        { path: 'sales/:id', name: 'SaleDetail', component: () => import('@/views/SaleDetailView.vue') },
        { path: 'products/:id/intelligence', name: 'ProductIntelligence', component: () => import('@/views/ProductIntelligenceView.vue') },
        { path: 'subscription', name: 'Subscription', component: () => import('@/views/SubscriptionView.vue') },
        { path: 'profile', name: 'Profile', component: () => import('@/views/ProfileView.vue') },
        { path: 'tickets', name: 'Tickets', component: () => import('@/views/TicketsView.vue') },
        { path: 'verify-email', name: 'VerifyEmail', component: () => import('@/views/VerifyEmailView.vue') },
        { path: 'payment/return', name: 'PaymentReturn', component: () => import('@/views/PaymentReturnView.vue') },
        { path: 'wompi-checkout', name: 'WompiCheckout', component: () => import('@/views/WompiCheckoutView.vue') },
        // Catch-all dentro de /app para mostrar 404 dentro del layout
        { path: ':pathMatch(.*)*', name: 'AppNotFound', component: () => import('@/views/NotFoundView.vue') },
      ],
    },
    // Catch-all 404 para rutas fuera de /app
    { path: '/:pathMatch(.*)*', name: 'NotFound', component: () => import('@/views/NotFoundView.vue') },
  ],
})

router.beforeEach((to, _from, next) => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.token) {
    next({ name: 'Login' })
  } else if (to.meta.guest && auth.token) {
    next({ path: '/app' })
  } else {
    next()
  }
})

export default router
