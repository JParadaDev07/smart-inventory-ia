<template>
  <Teleport to="body">
    <div
      class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6 sm:py-12"
      role="alertdialog"
      :aria-label="t('products.deleteTitle')"
      aria-modal="true"
    >
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="$emit('cancel')" />
      <div class="relative z-10 w-full max-w-sm mx-auto">
        <Card content-class="p-6 space-y-4">
          <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-destructive/10">
              <Trash2 class="h-5 w-5 text-destructive" />
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="text-base font-semibold">{{ t('products.deleteTitle') }}</h3>
              <p class="mt-1 text-sm text-muted-foreground">
                {{ t('products.deleteConfirm', { name: product.name }) }}
              </p>
            </div>
          </div>
          <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
            <Button variant="outline" class="w-full sm:w-auto" @click="$emit('cancel')">
              {{ t('products.cancel') }}
            </Button>
            <Button
              variant="destructive"
              class="w-full sm:w-auto"
              :disabled="isPending"
              @click="$emit('confirm')"
            >
              {{ t('products.del') }}
            </Button>
          </div>
        </Card>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { Trash2 } from 'lucide-vue-next'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import type { Product } from '@/types'

const { t } = useI18n()

defineProps<{
  product: Product
  isPending: boolean
}>()

defineEmits<{
  confirm: []
  cancel: []
}>()
</script>
