<template>
  <div :class="cn('rounded-lg border bg-card text-card-foreground shadow-sm', $attrs.class)">
    <div v-if="$slots.header" class="flex flex-col space-y-1.5 p-6">
      <slot name="header" />
    </div>
    <div v-if="$slots.default" :class="contentClass">
      <slot />
    </div>
    <div v-if="$slots.footer" class="p-6 pt-0">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { cn } from '@/lib/utils'

withDefaults(
  defineProps<{
    contentClass?: string
  }>(),
  { contentClass: 'p-6' }
)
</script>
