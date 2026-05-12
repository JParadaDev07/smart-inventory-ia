<template>
  <th
    class="h-11 font-medium cursor-pointer select-none hover:bg-muted/50"
    :class="[align === 'right' ? 'text-right' : 'text-left']"
    @click="$emit('sort', column)"
  >
    <span
      class="inline-flex items-center gap-1"
      :class="[align === 'right' ? 'justify-end' : 'justify-start']"
    >
      <slot />
      <ArrowUpDown v-if="activeColumn !== column" class="h-3.5 w-3.5 text-muted-foreground/60" />
      <ChevronUp v-else-if="sortDir === 'asc'" class="h-3.5 w-3.5 text-muted-foreground" />
      <ChevronDown v-else class="h-3.5 w-3.5 text-muted-foreground" />
    </span>
  </th>
</template>

<script setup lang="ts">
import { ArrowUpDown, ChevronUp, ChevronDown } from 'lucide-vue-next'

defineProps<{
  column: string
  activeColumn: string
  sortDir: 'asc' | 'desc'
  align?: 'left' | 'right'
}>()

defineEmits<{ sort: [column: string] }>()
</script>
