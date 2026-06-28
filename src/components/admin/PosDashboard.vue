<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApi } from './useApi'

interface StatsData {
  currency?: { symbol?: string }
  today?: {
    display_total_sales?: number
    order_count?: number
    total_items?: number
    display_avg_ticket?: number
  }
  cancelled?: { count?: number; display_total?: number }
  recent_orders?: Array<{
    order_number: string
    customer_name?: string
    display_symbol?: string
    display_total?: number
    items_count?: number
    time?: string
    payment_method_code?: string
  }>
  payment_methods?: Array<{
    code?: string
    name?: string
    count?: number
    display_total?: number
  }>
}

const api = useApi()
const loading = ref(true)
const error = ref('')
const data = ref<StatsData>({})

interface Card {
  label: string
  value: string
  icon: string
  muted?: boolean
  danger?: boolean
}

const cards = ref<Card[]>([])

const paymentIcons: Record<string, string> = {
  pos_cash: 'payments',
  pos_card: 'credit_card',
  pos_transfer: 'account_balance',
  stripe: 'credit_card',
  transfer: 'account_balance',
}

function paymentIcon(code?: string): string {
  return paymentIcons[code || ''] || 'receipt'
}

function formatPrice(val: number | string): string {
  const fixed = (parseFloat(String(val)) || 0).toFixed(2)
  const parts = fixed.split('.')
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',')
  return parts.join('.')
}

function esc(str?: string | number | null): string {
  const d = document.createElement('div')
  d.textContent = String(str ?? '')
  return d.innerHTML
}

onMounted(async () => {
  try {
    const res = await api.get<StatsData>('/api/pos/stats.php')
    data.value = res
    const sym = (res.currency?.symbol) || '$'
    const cancelled = res.cancelled || {}

    cards.value = [
      {
        label: 'Ventas Hoy',
        value: sym + formatPrice(res.today?.display_total_sales || 0),
        icon: 'payments',
      },
      {
        label: 'Pedidos Hoy',
        value: String(res.today?.order_count || 0),
        icon: 'receipt_long',
      },
      {
        label: 'Productos Vendidos',
        value: String(res.today?.total_items || 0),
        icon: 'inventory_2',
      },
      {
        label: 'Ticket Promedio',
        value: sym + formatPrice(res.today?.display_avg_ticket || 0),
        icon: 'trending_up',
      },
      {
        label: 'Canceladas',
        value: cancelled.count && cancelled.count > 0
          ? `${cancelled.count} (${sym}${formatPrice(cancelled.display_total || 0)})`
          : '0',
        icon: 'cancel',
        muted: !cancelled.count || cancelled.count === 0,
        danger: cancelled.count && cancelled.count > 0,
      },
    ]
  } catch (err: any) {
    error.value = err.message
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-if="loading" class="text-center py-8">
    <span class="material-symbols-outlined text-3xl block mb-2 animate-spin text-[#dae2fd]">progress_activity</span>
    <p class="text-[#94a3b8]">Cargando dashboard...</p>
  </div>

  <div v-else-if="error" class="bg-[#DC2626]/10 text-[#DC2626] p-3 rounded-sm">
    Error al cargar dashboard: {{ error }}
  </div>

  <div v-else class="space-y-8 admin-enter">
    <!-- Stats cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 md:gap-6">
      <div v-for="card in cards" :key="card.label"
           class="admin-card p-4 sm:p-5 flex flex-col gap-2"
           :class="card.danger ? 'border-[#DC2626]/30' : ''">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold tracking-widest"
                :class="card.muted ? 'text-[#94a3b8]/40' : 'text-[#94a3b8]'">{{ card.label }}</span>
          <span class="material-symbols-outlined text-lg sm:text-xl"
                :class="card.muted ? 'text-[#94a3b8]/20' : 'text-[#94a3b8]'">{{ card.icon }}</span>
        </div>
        <span class="text-xl sm:text-2xl font-semibold truncate text-[#dae2fd]"
              :class="card.muted ? 'text-[#94a3b8]/40' : ''">{{ card.value }}</span>
      </div>
    </div>

    <!-- Quick actions -->
    <div class="flex items-center gap-4">
      <a href="/admin/pos"
         class="admin-btn admin-btn-primary inline-flex items-center gap-2">
        <span class="material-symbols-outlined text-lg">point_of_sale</span>
        Ir a Mostrador POS
      </a>
    </div>

    <!-- Two-column layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">
      <!-- Recent orders -->
      <div class="lg:col-span-2 admin-card">
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-[#1e293b]/50">
          <h2 class="text-lg font-semibold text-[#dae2fd]">Últimas Ventas POS</h2>
        </div>
        <div class="p-3 sm:p-4">
          <div v-if="(data.recent_orders?.length || 0) === 0" class="text-[#94a3b8]">Sin ventas recientes.</div>
          <template v-else>
            <div v-for="o in data.recent_orders" :key="o.order_number"
                 class="flex items-center justify-between py-2.5 sm:py-3 border-b border-[#1e293b]/30 last:border-0">
              <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                <span class="material-symbols-outlined text-base sm:text-lg text-[#94a3b8]/40 shrink-0">{{ paymentIcon(o.payment_method_code) }}</span>
                <div class="min-w-0">
                  <a :href="'/admin/pedidos/detalle?id=' + encodeURIComponent(o.order_number)"
                     class="text-sm sm:text-base text-[#dae2fd] hover:underline truncate block max-w-[140px] sm:max-w-none">{{ esc(o.order_number) }}</a>
                  <p class="text-xs sm:text-sm text-[#94a3b8] truncate">{{ esc(o.customer_name || 'Mostrador') }}</p>
                </div>
              </div>
              <div class="text-right shrink-0 ml-3 sm:ml-4">
                <span class="text-sm sm:text-base font-semibold text-[#dae2fd]">{{ o.display_symbol || data.currency?.symbol || '$' }}{{ formatPrice(o.display_total || 0) }}</span>
                <p class="text-[10px] sm:text-xs font-semibold tracking-widest text-[#94a3b8] mt-0.5">{{ o.items_count }} ítem(s) · {{ o.time }}</p>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- Payment method breakdown -->
      <div class="admin-card">
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-[#1e293b]/50">
          <h2 class="text-lg font-semibold text-[#dae2fd]">Hoy por Método</h2>
        </div>
        <div class="p-3 sm:p-4">
          <div v-if="(data.payment_methods?.length || 0) === 0" class="text-[#94a3b8] text-center py-6">No hay ventas hoy.</div>
          <template v-else>
            <div v-for="pm in data.payment_methods" :key="pm.code"
                 class="py-2.5 sm:py-3 border-b border-[#1e293b]/30 last:border-0">
              <div class="flex items-center justify-between mb-1">
                <div class="flex items-center gap-2">
                  <span class="material-symbols-outlined text-base sm:text-lg text-[#94a3b8]">{{ paymentIcon(pm.code) }}</span>
                  <span class="text-sm sm:text-base text-[#dae2fd]">{{ esc(pm.name) }}</span>
                </div>
                <span class="text-xs sm:text-sm font-semibold text-[#dae2fd]">{{ data.currency?.symbol || '$' }}{{ formatPrice(pm.display_total || 0) }}</span>
              </div>
              <div class="flex items-center gap-2">
                <div class="flex-1 h-1.5 sm:h-2 bg-[#1e293b] rounded-sm overflow-hidden">
                  <div class="h-full bg-[#42b883] rounded-sm transition-all"
                       :style="{ width: ((pm.count || 0) / (data.payment_methods?.reduce((s, p) => s + (p.count || 0), 0) || 1) * 100) + '%' }"></div>
                </div>
                <span class="text-[10px] sm:text-xs font-semibold tracking-widest text-[#94a3b8] w-12 text-right tabular-nums">{{ pm.count }} · {{ Math.round((pm.count || 0) / (data.payment_methods?.reduce((s, p) => s + (p.count || 0), 0) || 1) * 100) }}%</span>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>
