<template>
  <div class="flex min-h-[60vh] flex-col items-center justify-center gap-4 text-center">
    <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">404</h1>
    <p class="text-muted-foreground max-w-md text-sm sm:text-base">
      La página que estás buscando no existe o ya no está disponible.
    </p>
    <div class="flex flex-wrap justify-center gap-2">
      <Button variant="default" @click="$router.push({ name: 'Dashboard' })">
        Volver al panel
      </Button>
      <Button variant="outline" @click="$router.push({ name: 'Landing' })">
        Ir al inicio
      </Button>
    </div>
  </div>
</template>

<script setup lang="ts">
import Button from '@/components/ui/Button.vue'
</script>

