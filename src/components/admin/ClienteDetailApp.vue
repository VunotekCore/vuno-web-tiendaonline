<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApi } from './useApi'
import VunoIcon from './VunoIcon.vue'

const api = useApi()

interface Address {
  label: string
  address_line1: string
  address_line2?: string
  city: string
  state: string
  zip: string
  phone?: string
  is_default_shipping: boolean
  is_default_billing: boolean
}

interface WishlistItem {
  slug: string
  name: string
  price: number
  currency: string
}

interface Order {
  id: string
  order_number: string
  created_at: string
  status_code: string
  payment_method_code: string
  total: number
  display_total: number
  display_symbol: string
  currency: string
}

interface Cliente {
  name: string
  email: string
  phone: string | null
  is_verified: boolean
  created_at: string
  last_order_at: string | null
  notes: string | null
  addresses: Address[]
  orders: Order[]
  wishlist: WishlistItem[]
}

const loading = ref(true)
const error = ref('')
const customer = ref<Cliente | null>(null)
const customerId = ref('')

onMounted(async () => {
  const params = new URLSearchParams(window.location.search)
  customerId.value = params.get('id') || ''
  if (!customerId.value) {
    error.value = 'No customer ID specified.'
    loading.value = false
    return
  }
  try {
    customer.value = await api.get<Cliente>(`/api/clientes/get.php?id=${encodeURIComponent(customerId.value)}`)
  } catch (err: any) {
    error.value = err.message || 'Error loading customer'
  } finally {
    loading.value = false
  }
})

function formatDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('es-NI', { year: 'numeric', month: 'long', day: 'numeric' })
}

function formatPrice(val: number | null | undefined, symbol = '$') {
  if (val == null || isNaN(val)) val = 0
  return symbol + Number(val).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>

<template>
  <div v-if="loading" class="admin-card p-6">
    <div class="skeleton skeleton-title w-40 mb-4"></div>
    <div class="space-y-3">
      <div v-for="i in 5" :key="i" class="skeleton skeleton-text w-3/4"></div>
    </div>
  </div>

  <div v-else-if="error" class="font-body text-body-md text-[#DC2626] bg-[#DC2626]/10 p-3">
    {{ error }}
  </div>

  <div v-else-if="customer" class="space-y-8 admin-enter">
    <!-- Customer Info + Notes -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="glass-card overflow-hidden rounded-xl px-6 pt-5 pb-6">
        <h2 class="text-lg font-semibold text-[#dae2fd] mb-4 flex items-center gap-2">
          <VunoIcon icon="person" :size="24" />
          {{ customer.name }}
        </h2>
        <div class="space-y-3 text-sm">
          <div class="flex items-center gap-2">
            <VunoIcon icon="mail" :size="16" class="text-[#94a3b8]" />
            <span class="text-[#94a3b8]">Email:</span>
            <a :href="'mailto:' + customer.email" class="text-[#dae2fd] hover:text-[#42b883] transition-colors">{{ customer.email }}</a>
          </div>
          <div class="flex items-center gap-2">
            <VunoIcon icon="phone" :size="16" class="text-[#94a3b8]" />
            <span class="text-[#94a3b8]">Teléfono:</span>
            <span class="text-[#dae2fd]">{{ customer.phone || '—' }}</span>
          </div>
          <div class="flex items-center gap-2">
            <VunoIcon icon="verified" :size="16" class="text-[#94a3b8]" />
            <span class="text-[#94a3b8]">Verificado:</span>
            <span v-if="customer.is_verified" class="text-[#42b883] flex items-center gap-1">
              <VunoIcon icon="check_circle" :size="14" class="text-[#42b883]" />Sí
            </span>
            <span v-else class="text-[#94a3b8]">No</span>
          </div>
          <div class="flex items-center gap-2">
            <VunoIcon icon="calendar_today" :size="16" class="text-[#94a3b8]" />
            <span class="text-[#94a3b8]">Registrado:</span>
            <span class="text-[#dae2fd]">{{ formatDate(customer.created_at) }}</span>
          </div>
          <div class="flex items-center gap-2">
            <VunoIcon icon="history" :size="16" class="text-[#94a3b8]" />
            <span class="text-[#94a3b8]">Último pedido:</span>
            <span class="text-[#dae2fd]">{{ formatDate(customer.last_order_at) }}</span>
          </div>
        </div>
      </div>

      <div class="glass-card overflow-hidden rounded-xl px-6 pt-5 pb-6">
        <h2 class="text-lg font-semibold text-[#dae2fd] mb-4 flex items-center gap-2">
          <VunoIcon icon="notes" :size="24" />
          Notas internas
        </h2>
        <p class="text-sm text-[#94a3b8] leading-relaxed whitespace-pre-wrap">{{ customer.notes || 'Sin notas.' }}</p>
      </div>
    </div>

    <!-- Addresses -->
    <div class="glass-card overflow-hidden rounded-xl">
      <div class="px-6 pt-5 pb-4 border-b border-[#dae2fd]/5">
        <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
          <VunoIcon icon="home" :size="24" />
          Direcciones
        </h2>
      </div>
      <div v-if="customer.addresses?.length" class="px-6 py-4 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div v-for="(a, i) in customer.addresses" :key="i" class="border border-[#1e293b] rounded-lg p-4">
          <div v-if="a.is_default_shipping || a.is_default_billing" class="flex gap-1 mb-2">
            <span v-if="a.is_default_shipping" class="badge badge-paid text-[10px] px-1.5 py-0.5">Envío</span>
            <span v-if="a.is_default_billing" class="badge badge-paid text-[10px] px-1.5 py-0.5">Facturación</span>
          </div>
          <p class="text-sm font-medium text-[#dae2fd]">{{ a.label || 'Dirección' }}</p>
          <p class="text-xs text-[#94a3b8] mt-1">{{ a.address_line1 }}{{ a.address_line2 ? ', ' + a.address_line2 : '' }}</p>
          <p class="text-xs text-[#94a3b8]">{{ a.city ? a.city + ', ' : '' }}{{ a.state ? a.state + ' ' : '' }}{{ a.zip || '' }}</p>
          <p v-if="a.phone" class="text-xs text-[#94a3b8] mt-1">{{ a.phone }}</p>
        </div>
      </div>
      <div v-else class="empty-state">
        <VunoIcon icon="home" :size="36" class="empty-state-icon" />
        <p class="empty-state-title">Sin direcciones</p>
        <p class="empty-state-desc">El cliente no tiene direcciones guardadas.</p>
      </div>
    </div>

    <!-- Order History -->
    <div class="glass-card overflow-hidden rounded-xl">
      <div class="px-6 pt-5 pb-4 border-b border-[#dae2fd]/5">
        <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
          <VunoIcon icon="receipt_long" :size="24" />
          Historial de Pedidos
        </h2>
      </div>
      <template v-if="customer.orders?.length">
        <!-- Desktop table -->
        <div class="hidden md:block">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Pedido</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Pago</th>
                <th>Total</th>
                <th class="w-24 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="o in customer.orders" :key="o.id">
                <td class="font-medium">{{ o.order_number || o.id }}</td>
                <td class="text-sm text-[#94a3b8]">{{ formatDate(o.created_at) }}</td>
                <td>
                  <span class="badge" :class="'badge-' + o.status_code">{{ o.status_code }}</span>
                </td>
                <td class="text-sm text-[#94a3b8]">{{ o.payment_method_code || '—' }}</td>
                <td class="font-medium">{{ formatPrice(o.display_total ?? o.total, o.display_symbol) }}</td>
                <td class="text-right">
                  <a :href="'/admin/pedidos/detalle?id=' + o.id" class="admin-btn admin-btn-ghost admin-btn-xs">
                    <VunoIcon icon="visibility" :size="14" />
                  </a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Mobile cards -->
        <div class="md:hidden px-6 py-4 space-y-3">
          <div v-for="o in customer.orders" :key="o.id" class="glass-card overflow-hidden rounded-xl">
            <div class="px-4 pt-3 pb-2 border-b border-[#dae2fd]/5">
              <div class="flex items-center justify-between gap-2">
                <span class="font-medium text-[#dae2fd] text-sm">{{ o.order_number || o.id }}</span>
                <span class="badge shrink-0" :class="'badge-' + o.status_code">{{ o.status_code }}</span>
              </div>
            </div>
            <div class="px-4 py-3 flex items-center justify-between">
              <div class="text-sm text-[#94a3b8] space-y-1">
                <div>{{ formatDate(o.created_at) }}</div>
                <div>{{ o.payment_method_code || '—' }}</div>
              </div>
              <div class="flex items-center gap-2">
                <span class="font-medium text-[#dae2fd]">{{ formatPrice(o.display_total ?? o.total, o.display_symbol) }}</span>
                <a :href="'/admin/pedidos/detalle?id=' + o.id" class="admin-btn admin-btn-ghost admin-btn-xs">
                  <VunoIcon icon="visibility" :size="14" />
                </a>
              </div>
            </div>
          </div>
        </div>
      </template>
      <div v-else class="empty-state">
        <VunoIcon icon="receipt-long" :size="36" class="empty-state-icon" />
        <p class="empty-state-title">Sin pedidos</p>
        <p class="empty-state-desc">El cliente no ha realizado pedidos.</p>
      </div>
    </div>

    <!-- Wishlist -->
    <div class="glass-card overflow-hidden rounded-xl">
      <div class="px-6 pt-5 pb-4 border-b border-[#dae2fd]/5">
        <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
          <VunoIcon icon="favorite" :size="24" />
          Wishlist
        </h2>
      </div>
      <div v-if="customer.wishlist?.length" class="px-6 py-4 space-y-2">
        <div v-for="(w, i) in customer.wishlist" :key="i" class="flex items-center justify-between border-b border-[#1e293b] pb-2 last:border-0">
          <a :href="'/producto/' + w.slug" class="text-sm text-[#dae2fd] hover:text-[#42b883] transition-colors" target="_blank">{{ w.name }}</a>
          <span class="text-sm font-medium">{{ formatPrice(w.price, w.currency === 'NIO' ? 'C$' : '$') }}</span>
        </div>
      </div>
      <div v-else class="empty-state">
        <VunoIcon icon="favorite" :size="36" class="empty-state-icon" />
        <p class="empty-state-title">Sin favoritos</p>
        <p class="empty-state-desc">El cliente no tiene productos en su wishlist.</p>
      </div>
    </div>

    <div class="mt-6">
      <a href="/admin/clientes" class="inline-flex items-center gap-1.5 text-sm text-[#94a3b8] hover:text-[#dae2fd] transition-colors">
        <VunoIcon icon="arrow_back" :size="20" />
        Volver a Clientes
      </a>
    </div>
  </div>
</template>
