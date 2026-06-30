<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApi } from './useApi'
import VunoIcon from './VunoIcon.vue'

const api = useApi()

function formatPrice(value: number | null | undefined): string {
  if (value == null || isNaN(value)) value = 0
  return Number(value).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

interface Stats {
  totalProducts: number
  totalOrders: number
  monthlyRevenue: number
  displaySymbol: string
  displayMonthlyRevenue: number
  monthlyOrderCount: number
  recentOrders: Array<{
    id: string
    customerName: string
    total: number
    displayTotal: number
    displaySymbol: string
    status: string
  }>
  lowStockProducts: Array<{
    name: string
    sizes: Array<{ inStock: boolean }>
  }>
}

const stats = ref<Stats | null>(null)
const error = ref('')
const loading = ref(true)

onMounted(async () => {
  try {
    stats.value = await api.get<Stats>('/api/dashboard/stats.php')
  } catch (err: any) {
    error.value = err.message || 'Error loading dashboard'
  } finally {
    loading.value = false
  }
})

const statCards = [
  { key: 'totalProducts' as const, label: 'Productos', icon: 'inventory_2' },
  { key: 'totalOrders' as const, label: 'Pedidos Total', icon: 'receipt_long' },
  { key: 'monthlyRevenue' as const, label: 'Ingresos del Mes', icon: 'trending_up', isCurrency: true },
  { key: 'monthlyOrderCount' as const, label: 'Pedidos del Mes', icon: 'shopping_cart' },
]
</script>

<template>
  <div v-if="loading" class="space-y-8">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
      <div v-for="i in 4" :key="i" class="glass-card overflow-hidden rounded-xl p-5">
        <div class="skeleton skeleton-title w-20 mb-4"></div>
        <div class="skeleton skeleton-text w-16"></div>
      </div>
    </div>
    <div class="glass-card overflow-hidden rounded-xl">
      <div class="px-6 pt-5 pb-4 border-b border-[#dae2fd]/5">
        <div class="skeleton skeleton-title w-40"></div>
      </div>
      <div class="px-6 py-4 space-y-3">
        <div v-for="i in 3" :key="i" class="skeleton skeleton-text"></div>
      </div>
    </div>
  </div>

  <div v-else-if="error" class="font-body text-body-md text-[#DC2626] bg-[#DC2626]/10 p-3">
    {{ error }}
  </div>

  <div v-else-if="stats" class="space-y-8 admin-enter">
    <!-- Stats cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 admin-stagger">
      <div
        v-for="card in statCards"
        :key="card.key"
        class="glass-card overflow-hidden rounded-xl p-5 flex flex-col gap-2 transition-all duration-300 hover:-translate-y-0.5 hover:border-[#42b883]/35 hover:shadow-[0_12px_40px_rgba(0,0,0,0.45),0_0_30px_rgba(66,184,131,0.06)]"
      >
        <div class="flex items-center justify-between">
          <span class="font-label-caps text-label-caps text-[#94a3b8]">{{ card.label }}</span>
          <VunoIcon :icon="card.icon" :size="24" class="text-[#94a3b8]" />
        </div>
        <span class="text-2xl font-bold text-[#dae2fd] tracking-tight">
          <template v-if="card.isCurrency">
            {{ stats.displaySymbol || '$' }}{{ formatPrice(stats.displayMonthlyRevenue ?? stats.monthlyRevenue) }}
          </template>
          <template v-else>
            {{ stats[card.key] ?? 0 }}
          </template>
        </span>
      </div>
    </div>

    <!-- Charts / secondary -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Recent orders -->
      <div class="glass-card overflow-hidden rounded-xl">
        <div class="px-6 pt-5 pb-4 border-b border-[#dae2fd]/5">
          <h2 class="text-lg font-semibold text-[#dae2fd]">Pedidos Recientes</h2>
        </div>
        <div class="px-6 py-4">
          <template v-if="stats.recentOrders?.length">
            <div
              v-for="order in stats.recentOrders"
              :key="order.id"
              class="flex items-center justify-between py-3 border-b border-[#dae2fd]/5 last:border-0"
            >
              <div>
                <a
                  :href="'/admin/pedidos/detalle?id=' + encodeURIComponent(order.id)"
                  class="text-[#dae2fd] hover:text-[#42b883] text-sm font-medium transition-colors"
                >{{ order.id }}</a>
                <p class="text-sm text-[#94a3b8]">{{ order.customerName || '—' }}</p>
              </div>
              <div class="text-right">
                <span class="text-sm font-semibold text-[#dae2fd]">
                  {{ order.displaySymbol || '$' }}{{ formatPrice(order.displayTotal ?? order.total) }}
                </span>
                <span class="block text-xs text-[#94a3b8] capitalize mt-0.5">{{ order.status }}</span>
              </div>
            </div>
          </template>
          <p v-else class="text-sm text-[#94a3b8]">No recent orders.</p>
        </div>
      </div>

      <!-- Stock alerts -->
      <div class="glass-card overflow-hidden rounded-xl">
        <div class="px-6 pt-5 pb-4 border-b border-[#dae2fd]/5">
          <h2 class="text-lg font-semibold text-[#dae2fd]">Alertas de Stock</h2>
        </div>
        <div class="px-6 py-4">
          <template v-if="stats.lowStockProducts?.length">
            <div
              v-for="p in stats.lowStockProducts"
              :key="p.name"
              class="flex items-center justify-between py-3 border-b border-[#dae2fd]/5 last:border-0"
            >
              <span class="text-sm text-[#dae2fd]">{{ p.name }}</span>
              <span
                class="text-xs font-semibold uppercase tracking-wider"
                :class="(p.sizes || []).filter(s => s.inStock).length === 0 ? 'text-[#DC2626]' : 'text-[#B8956A]'"
              >
                {{ (p.sizes || []).filter(s => s.inStock).length === 0 ? 'AGOTADO' : (p.sizes || []).filter(s => s.inStock).length + ' talles' }}
              </span>
            </div>
          </template>
          <p v-else class="text-sm text-[#94a3b8]">All products well stocked.</p>
        </div>
      </div>
    </div>
  </div>
</template>
