<template>
  <Teleport to="body">
    <div
      class="fixed inset-0 z-40 flex items-center justify-center px-4 py-6 sm:py-12"
      role="alertdialog"
      :aria-label="product ? t('products.editProduct') : t('products.newProduct')"
      aria-modal="true"
    >
      <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="$emit('close')" />
      <div class="relative z-10 w-full max-w-xl mx-auto">
        <Card content-class="space-y-4 p-6">
          <template #header>
            <h2 class="text-base font-medium sm:text-lg">
              {{ product ? t('products.editProduct') : t('products.newProduct') }}
            </h2>
          </template>
          <form @submit.prevent="handleSubmit" class="space-y-4">
            <p v-if="error" class="text-destructive text-sm">{{ error }}</p>
            <div class="grid gap-4 grid-cols-1 sm:grid-cols-2">
              <div class="space-y-2">
                <Label for="product-name">{{ t('products.name') }}</Label>
                <Input id="product-name" v-model="form.name" required />
              </div>
              <div class="space-y-2">
                <Label for="product-sku">{{ t('products.sku') }}</Label>
                <Input id="product-sku" v-model="form.sku" />
              </div>
              <div class="space-y-2">
                <Label for="product-cost">{{ t('products.costPrice') }}</Label>
                <Input id="product-cost" v-model.number="form.cost_price" type="number" step="0.01" min="0" required />
              </div>
              <div class="space-y-2">
                <Label for="product-sale">{{ t('products.salePrice') }}</Label>
                <Input id="product-sale" v-model.number="form.sale_price" type="number" step="0.01" min="0" required />
              </div>
              <div class="space-y-2">
                <Label for="product-stock">{{ t('products.currentStock') }}</Label>
                <Input
                  id="product-stock"
                  v-model.number="form.current_stock"
                  type="number"
                  min="0"
                  :disabled="form.perecedero"
                />
              </div>
              <div class="space-y-2">
                <Label for="product-min">{{ t('products.minimumStock') }}</Label>
                <Input id="product-min" v-model.number="form.minimum_stock" type="number" min="0" />
              </div>
              <div class="space-y-2 sm:col-span-2">
                <Label for="product-lead">{{ t('products.supplierLeadTime') }}</Label>
                <Input id="product-lead" v-model.number="form.supplier_lead_time_days" type="number" min="0" />
              </div>
              <div class="space-y-2 sm:col-span-2">
                <div class="flex items-center gap-3">
                  <input id="product-perecedero" type="checkbox" v-model="form.perecedero" class="h-4 w-4" />
                  <Label for="product-perecedero">{{ t('products.perecedero') }}</Label>
                </div>
              </div>
              <div class="space-y-2">
                <Label for="product-expired-mode">{{ t('products.expiredMode') }}</Label>
                <select
                  id="product-expired-mode"
                  v-model="form.expired_mode"
                  :disabled="!form.perecedero"
                  class="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                >
                  <option value="bloquear">{{ t('products.expiredModeBlock') }}</option>
                  <option value="alerta">{{ t('products.expiredModeAlert') }}</option>
                  <option value="permitir">{{ t('products.expiredModeAllow') }}</option>
                </select>
              </div>
              <div class="space-y-2">
                <Label for="product-expiration-alert-days">{{ t('products.expirationAlertDays') }}</Label>
                <Input
                  id="product-expiration-alert-days"
                  v-model.number="form.expiration_alert_days"
                  type="number"
                  min="0"
                  :disabled="!form.perecedero"
                />
              </div>
            </div>
            <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row">
              <Button
                type="submit"
                class="w-full sm:w-auto"
                data-tour="products-save-product"
                :disabled="isPending"
              >
                {{ product ? t('products.update') : t('products.create') }}
              </Button>
              <Button
                type="button"
                variant="outline"
                class="w-full sm:w-auto"
                data-tour="products-modal-cancel"
                @click="$emit('close')"
              >
                {{ t('products.cancel') }}
              </Button>
            </div>
          </form>
        </Card>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Label from '@/components/ui/Label.vue'
import type { Product } from '@/types'

const { t } = useI18n()

const props = defineProps<{
  product: Product | null   // null = create mode, Product = edit mode
  error: string
  isPending: boolean
}>()

const emit = defineEmits<{
  close: []
  submit: [form: typeof form]
}>()

const form = reactive({
  name: '',
  sku: '',
  cost_price: 0,
  sale_price: 0,
  current_stock: 0,
  minimum_stock: 0,
  supplier_lead_time_days: 0,
  perecedero: false,
  expired_mode: 'bloquear',
  expiration_alert_days: 7,
})

// Populate form when editing
watch(
  () => props.product,
  (p) => {
    if (p) {
      form.name = p.name
      form.sku = p.sku ?? ''
      form.cost_price = p.cost_price
      form.sale_price = p.sale_price
      form.current_stock = p.current_stock
      form.minimum_stock = p.minimum_stock
      form.supplier_lead_time_days = p.supplier_lead_time_days ?? 0
      form.perecedero = !!p.perecedero
      form.expired_mode = p.expired_mode ?? 'bloquear'
      form.expiration_alert_days = p.expiration_alert_days ?? 7
    } else {
      Object.assign(form, {
        name: '', sku: '', cost_price: 0, sale_price: 0,
        current_stock: 0, minimum_stock: 0, supplier_lead_time_days: 0,
        perecedero: false, expired_mode: 'bloquear', expiration_alert_days: 7,
      })
    }
  },
  { immediate: true },
)

function handleSubmit() {
  emit('submit', { ...form })
}
</script>
