<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useApi } from './useApi'

const api = useApi()

interface OrderItem {
  id: string
  order_number?: string
  customer: { name: string } | null
  createdAt: string
  total: number
  display_total: number
  display_symbol: string
  status: string
  paymentMethod: string
}

const items = ref<OrderItem[]>([])
const loading = ref(false)
const search = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const total = ref(0)
const perPage = 10
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage)))

const pageWindow = computed(() => {
  const t = totalPages.value
  const c = currentPage.value
  if (t <= 5) return Array.from({ length: t }, (_, i) => i + 1)
  let start = Math.max(1, c - 2)
  let end = Math.min(t, start + 4)
  if (end - start < 4) start = Math.max(1, end - 4)
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})

const statusStyles: Record<string, string> = {
  pending: 'badge-pending',
  paid: 'badge-paid',
  shipped: 'badge-shipped',
  delivered: 'badge-delivered',
  cancelled: 'badge-cancelled',
}

const paymentLabels: Record<string, string> = {
  stripe: 'Card',
  transfer: 'Transfer',
  pos_cash: 'Efectivo (POS)',
  pos_card: 'Tarjeta (POS)',
  pos_transfer: 'Transferencia (POS)',
}

const paymentIcons: Record<string, string> = {
  stripe: 'credit_card',
  transfer: 'account_balance',
  pos_cash: 'payments',
  pos_card: 'credit_card',
  pos_transfer: 'account_balance',
}

onMounted(loadData)

async function loadData() {
  loading.value = true
  try {
    const qs = new URLSearchParams()
    qs.set('limit', String(perPage))
    qs.set('offset', String((currentPage.value - 1) * perPage))
    if (search.value.trim()) qs.set('search', search.value.trim())
    if (statusFilter.value) qs.set('status', statusFilter.value)
    const data = await api.get<{ items: OrderItem[]; total: number }>(`/api/pedidos/list.php?${qs}`)
    items.value = data.items || []
    total.value = data.total || items.value.length
  } catch { items.value = []; total.value = 0 } finally { loading.value = false }
}

function onSearchInput() {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => { currentPage.value = 1; loadData() }, 300)
}

function onStatusChange() {
  currentPage.value = 1
  loadData()
}

function formatDate(d: string) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatPrice(val: number | null | undefined): string {
  if (val == null || isNaN(val)) val = 0
  return Number(val).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>

<template>
  <div class="admin-card">
    <div class="admin-card-header">
      <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 w-full">
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4 w-full">
          <div class="relative flex-1 min-w-0">
            <span class="material-symbols-outlined absolute left-3 inset-y-0 flex items-center text-[#94a3b8] pointer-events-none">search</span>
            <input v-model="search" type="text" placeholder="Buscar por orden, cliente o email..." class="admin-input pl-12 w-full" @input="onSearchInput" />
          </div>
          <select v-model="statusFilter" class="admin-input w-full sm:w-auto sm:max-w-[160px]" @change="onStatusChange">
            <option value="">Todos los estados</option>
            <option value="pending">Pending</option>
            <option value="paid">Paid</option>
            <option value="shipped">Shipped</option>
            <option value="delivered">Delivered</option>
            <option value="cancelled">Cancelled</option>
          </select>
          <span class="text-sm text-[#94a3b8] whitespace-nowrap">{{ total }} pedidos</span>
        </div>
      </div>
    </div>

    <!-- Desktop table -->
    <div class="overflow-x-auto hidden md:block">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Order</th>
            <th>Customer</th>
            <th>Date</th>
            <th>Total</th>
            <th>Status</th>
            <th>Payment</th>
            <th class="w-24 text-right"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="7" class="text-center py-8 text-[#94a3b8]">Cargando...</td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td colspan="7" class="text-center py-12">
              <div class="flex flex-col items-center gap-3">
                <span class="material-symbols-outlined text-5xl text-[#94a3b8]/30">receipt_long</span>
                <p class="text-sm text-[#94a3b8]">No hay pedidos aún</p>
                <p class="text-xs text-[#94a3b8]/60">Los pedidos aparecerán aquí cuando los clientes realicen compras</p>
              </div>
            </td>
          </tr>
          <tr v-for="o in items" :key="o.id">
            <td class="font-medium">
              <a :href="'/admin/pedidos/detalle?id=' + encodeURIComponent(o.id)" class="text-[#dae2fd] hover:underline">{{ o.id }}</a>
            </td>
            <td class="text-[#94a3b8]">{{ o.customer?.name || '—' }}</td>
            <td class="text-sm text-[#94a3b8]">{{ formatDate(o.createdAt) }}</td>
            <td class="font-medium">{{ o.display_symbol || '$' }}{{ formatPrice(o.display_total ?? o.total) }}</td>
            <td>
              <span class="badge" :class="statusStyles[o.status] || ''">{{ o.status }}</span>
            </td>
            <td>
              <span class="inline-flex items-center gap-1.5 text-sm text-[#94a3b8] capitalize">
                <span class="material-symbols-outlined text-base">{{ paymentIcons[o.paymentMethod] || 'payments' }}</span>
                {{ paymentLabels[o.paymentMethod] || o.paymentMethod }}
              </span>
            </td>
            <td class="text-right">
              <a :href="'/admin/pedidos/detalle?id=' + encodeURIComponent(o.id)" class="admin-btn admin-btn-ghost admin-btn-xs whitespace-nowrap">
                <span class="material-symbols-outlined text-sm">visibility</span>
                VIEW
              </a>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Mobile cards -->
    <div class="md:hidden space-y-3 px-6 pb-4">
      <div v-if="loading" class="text-center py-8 text-[#94a3b8]">Cargando...</div>
      <div v-else-if="items.length === 0" class="text-center py-12">
        <div class="flex flex-col items-center gap-3">
          <span class="material-symbols-outlined text-5xl text-[#94a3b8]/30">receipt_long</span>
          <p class="text-sm text-[#94a3b8]">No hay pedidos aún</p>
        </div>
      </div>
      <div v-for="o in items" :key="o.id" class="glass-card overflow-hidden rounded-xl">
        <div class="px-5 pt-4 pb-3 border-b border-[#dae2fd]/5">
          <div class="flex justify-between items-start gap-2">
            <div class="min-w-0 flex-1">
              <a :href="'/admin/pedidos/detalle?id=' + encodeURIComponent(o.id)" class="font-medium text-[#dae2fd] hover:underline truncate block">{{ o.id }}</a>
              <div class="text-xs text-[#94a3b8] mt-0.5">{{ o.customer?.name || '—' }}</div>
            </div>
            <span class="badge shrink-0" :class="statusStyles[o.status] || ''">{{ o.status }}</span>
          </div>
        </div>
        <div class="px-5 py-3 flex items-center justify-between">
          <div class="flex items-center gap-2 text-sm text-[#94a3b8]">
            <span class="material-symbols-outlined text-base">{{ paymentIcons[o.paymentMethod] || 'payments' }}</span>
            {{ paymentLabels[o.paymentMethod] || o.paymentMethod }}
          </div>
          <div class="text-right">
            <div class="text-xs text-[#94a3b8]">{{ formatDate(o.createdAt) }}</div>
            <div class="font-medium text-[#dae2fd]">{{ o.display_symbol || '$' }}{{ formatPrice(o.display_total ?? o.total) }}</div>
          </div>
        </div>
        <div class="px-5 py-3 border-t border-[#dae2fd]/5">
          <a :href="'/admin/pedidos/detalle?id=' + encodeURIComponent(o.id)" class="admin-btn admin-btn-ghost admin-btn-xs w-full justify-center">
            <span class="material-symbols-outlined text-sm">visibility</span>
            VER DETALLE
          </a>
        </div>
      </div>
    </div>

    <div v-if="totalPages > 1" class="admin-card-footer flex flex-col sm:flex-row items-center justify-between gap-3">
      <span class="text-sm text-[#94a3b8]">Página {{ currentPage }} de {{ totalPages }}</span>
      <div class="flex gap-1">
        <button class="admin-btn admin-btn-ghost admin-btn-xs" :disabled="currentPage === 1" @click="currentPage--; loadData()">Anterior</button>
        <button v-for="p in pageWindow" :key="p" class="admin-btn admin-btn-xs" :class="p === currentPage ? 'admin-btn-primary' : 'admin-btn-ghost'" @click="currentPage = p; loadData()">{{ p }}</button>
        <button class="admin-btn admin-btn-ghost admin-btn-xs" :disabled="currentPage === totalPages" @click="currentPage++; loadData()">Siguiente</button>
      </div>
    </div>
  </div>
</template>
