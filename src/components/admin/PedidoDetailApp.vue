<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'
import VunoIcon from './VunoIcon.vue'

const api = useApi()
const toast = useToast()

interface OrderItem {
  product: { name: string; price: number; display_price: number } | null
  selectedColor: string
  selectedSize: string
  quantity: number
}

interface Order {
  id: string
  createdAt: string
  status: string
  paymentMethod: string
  paymentStatus: string
  transferReceipt: string | null
  selectedBankName: string | null
  customer: {
    name: string
    email: string
    address: string
    city: string
    zip: string
  } | null
  items: OrderItem[]
  display_discountTotal: number
  display_shipping: number
  display_tax: number
  display_total: number
  display_symbol: string
  shipping: number
  tax: number
  total: number
}

const loading = ref(true)
const error = ref('')
const order = ref<Order | null>(null)
const orderId = ref('')
const statusUpdating = ref(false)

const statusStyles: Record<string, string> = {
  pending: 'badge-pending',
  paid: 'badge-paid',
  shipped: 'badge-shipped',
  delivered: 'badge-delivered',
  cancelled: 'badge-cancelled',
}

const payLabels: Record<string, string> = {
  stripe: 'Card (Stripe)',
  transfer: 'Bank Transfer',
  pos_cash: 'Efectivo (POS)',
  pos_card: 'Tarjeta (POS)',
  pos_transfer: 'Transferencia (POS)',
}

const validStatuses = ['pending', 'paid', 'shipped', 'delivered', 'cancelled']

const selectedStatus = ref('')

onMounted(async () => {
  const params = new URLSearchParams(window.location.search)
  orderId.value = params.get('id') || ''
  if (!orderId.value) {
    error.value = 'No order ID specified.'
    loading.value = false
    return
  }
  try {
    order.value = await api.get<Order>(`/api/pedidos/get.php?id=${encodeURIComponent(orderId.value)}`)
    selectedStatus.value = order.value.status || 'pending'
  } catch (err: any) {
    error.value = err.message || 'Error loading order'
  } finally {
    loading.value = false
  }
})

function formatDate(d: string) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatPrice(val: number | null | undefined, symbol = '$'): string {
  if (val == null || isNaN(val)) val = 0
  return symbol + Number(val).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function calcSubtotal(item: OrderItem): number {
  return ((item.product?.display_price ?? item.product?.price) || 0) * (item.quantity || 1)
}

async function updateStatus() {
  if (!order.value || selectedStatus.value === order.value.status) {
    toast.info('No changes', 'The order is already ' + selectedStatus.value)
    return
  }
  statusUpdating.value = true
  try {
    const data = await api.post<{ success: boolean }>('/api/pedidos/update-status.php', {
      id: orderId.value,
      status: selectedStatus.value,
    })
    if (!data.success) throw new Error('Failed to update status')
    order.value.status = selectedStatus.value
    toast.success('Status updated', 'Order status changed to ' + selectedStatus.value)
  } catch (err: any) {
    toast.error('Error', err.message)
  } finally {
    statusUpdating.value = false
  }
}
</script>

<template>
  <div v-if="loading" class="admin-card p-6">
    <div class="skeleton skeleton-title w-40 mb-4"></div>
    <div class="space-y-3">
      <div v-for="i in 6" :key="i" class="skeleton skeleton-text w-3/4"></div>
    </div>
  </div>

  <div v-else-if="error" class="font-body text-body-md text-[#DC2626] bg-[#DC2626]/10 p-3">
    {{ error }}
  </div>

  <div v-else-if="order" class="space-y-6 admin-enter">
    <!-- Order Info + Customer -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="glass-card overflow-hidden rounded-xl">
        <div class="px-6 pt-5 pb-4 border-b border-[#dae2fd]/5">
          <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
            <VunoIcon icon="receipt" :size="24" />
            Order <span class="text-[#dae2fd]">{{ order.id }}</span>
          </h2>
        </div>
        <div class="px-6 py-4 space-y-3 text-sm">
          <div class="flex items-center gap-2">
            <VunoIcon icon="calendar_today" :size="16" class="text-[#94a3b8]" />
            <span class="text-[#94a3b8]">Date:</span>
            <span class="text-[#dae2fd]">{{ formatDate(order.createdAt) }}</span>
          </div>
          <div class="flex items-center gap-2">
            <VunoIcon icon="info" :size="16" class="text-[#94a3b8]" />
            <span class="text-[#94a3b8]">Status:</span>
            <span class="badge" :class="statusStyles[order.status] || ''">{{ order.status }}</span>
          </div>
          <div class="flex items-center gap-2">
            <VunoIcon icon="credit_card" :size="16" class="text-[#94a3b8]" />
            <span class="text-[#94a3b8]">Payment:</span>
            <span class="text-[#dae2fd]">{{ payLabels[order.paymentMethod] || order.paymentMethod }}</span>
          </div>
          <div class="flex items-center gap-2">
            <VunoIcon icon="check_circle" :size="16" class="text-[#94a3b8]" />
            <span class="text-[#94a3b8]">Payment Status:</span>
            <span class="text-[#dae2fd]">{{ order.paymentStatus || 'N/A' }}</span>
          </div>
          <div v-if="order.transferReceipt" class="flex items-center gap-2">
            <VunoIcon icon="description" :size="16" class="text-[#94a3b8]" />
            <span class="text-[#94a3b8]">Comprobante:</span>
            <a :href="order.transferReceipt" target="_blank" class="text-[#42b883] underline hover:no-underline">Ver comprobante</a>
          </div>
          <div v-if="order.selectedBankName" class="flex items-center gap-2">
            <VunoIcon icon="account_balance" :size="16" class="text-[#94a3b8]" />
            <span class="text-[#94a3b8]">Bank:</span>
            <span class="text-[#dae2fd]">{{ order.selectedBankName }}</span>
          </div>
        </div>
      </div>

      <div class="glass-card overflow-hidden rounded-xl">
        <div class="px-6 pt-5 pb-4 border-b border-[#dae2fd]/5">
          <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
            <VunoIcon icon="person" :size="24" />
            Customer
          </h2>
        </div>
        <div class="px-6 py-4">
          <div v-if="order.customer" class="space-y-3 text-sm">
            <div class="flex items-center gap-2">
              <VunoIcon icon="badge" :size="16" class="text-[#94a3b8]" />
              <span class="text-[#94a3b8]">Name:</span>
              <span class="text-[#dae2fd]">{{ order.customer.name }}</span>
            </div>
            <div class="flex items-center gap-2">
              <VunoIcon icon="mail" :size="16" class="text-[#94a3b8]" />
              <span class="text-[#94a3b8]">Email:</span>
              <span class="text-[#dae2fd]">{{ order.customer.email }}</span>
            </div>
            <div class="flex items-start gap-2">
              <VunoIcon icon="home" :size="16" class="text-[#94a3b8] mt-0.5" />
              <span class="text-[#94a3b8]">Address:</span>
              <span class="text-[#dae2fd]">{{ order.customer.address }}</span>
            </div>
            <div class="flex items-center gap-2">
              <VunoIcon icon="location_city" :size="16" class="text-[#94a3b8]" />
              <span class="text-[#94a3b8]">City:</span>
              <span class="text-[#dae2fd]">{{ order.customer.city }}</span>
            </div>
            <div class="flex items-center gap-2">
              <VunoIcon icon="pin_drop" :size="16" class="text-[#94a3b8]" />
              <span class="text-[#94a3b8]">Zip:</span>
              <span class="text-[#dae2fd]">{{ order.customer.zip }}</span>
            </div>
          </div>
          <div v-else class="empty-state">
            <VunoIcon icon="person_off" :size="36" class="empty-state-icon" />
            <p class="empty-state-title">Sin datos</p>
            <p class="empty-state-desc">No hay datos del cliente.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Order Items -->
    <div class="glass-card overflow-hidden rounded-xl">
      <div class="px-6 pt-5 pb-4 border-b border-[#dae2fd]/5">
        <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
          <VunoIcon icon="shopping_bag" :size="24" />
          Order Items
        </h2>
      </div>
      <template v-if="order.items?.length">
        <!-- Desktop table -->
        <div class="overflow-x-auto px-6 hidden md:block">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Color</th>
                <th>Size</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, i) in order.items" :key="i">
                <td class="font-medium">{{ item.product?.name || '' }}</td>
                <td class="text-[#94a3b8]">{{ item.selectedColor || '—' }}</td>
                <td class="text-[#94a3b8]">{{ item.selectedSize || '—' }}</td>
                <td>{{ item.quantity || 1 }}</td>
                <td class="font-medium">{{ formatPrice(item.product?.display_price ?? item.product?.price, order.display_symbol) }}</td>
                <td class="font-medium">{{ formatPrice(calcSubtotal(item), order.display_symbol) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr v-if="order.display_discountTotal > 0" class="border-t border-[#dae2fd]/5">
                <td colspan="5" class="p-4 text-right text-sm text-[#B8956A]">Discount</td>
                <td class="p-4 font-medium text-[#B8956A]">-{{ formatPrice(order.display_discountTotal, order.display_symbol) }}</td>
              </tr>
              <tr class="border-t border-[#dae2fd]/5">
                <td colspan="5" class="p-4 text-right text-sm text-[#94a3b8]">Shipping</td>
                <td class="p-4 font-medium">{{ formatPrice(order.display_shipping ?? order.shipping, order.display_symbol) }}</td>
              </tr>
              <tr class="border-t border-[#dae2fd]/5">
                <td colspan="5" class="p-4 text-right text-sm text-[#94a3b8]">IVA</td>
                <td class="p-4 font-medium">{{ formatPrice(order.display_tax ?? order.tax, order.display_symbol) }}</td>
              </tr>
              <tr class="border-t-2 border-[#dae2fd]/10">
                <td colspan="5" class="p-4 text-right text-lg font-semibold text-[#dae2fd]">Total</td>
                <td class="p-4 text-lg font-bold text-[#dae2fd]">{{ formatPrice(order.display_total ?? order.total, order.display_symbol) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Mobile items -->
        <div class="md:hidden divide-y divide-[#dae2fd]/5">
          <div v-for="(item, i) in order.items" :key="i" class="px-6 py-4">
            <div class="font-medium text-[#dae2fd] mb-2">{{ item.product?.name || '' }}</div>
            <div class="flex items-center gap-3 text-sm text-[#94a3b8] mb-2">
              <span v-if="item.selectedColor" class="inline-flex items-center gap-1">
                <VunoIcon icon="palette" :size="14" />{{ item.selectedColor }}
              </span>
              <span v-if="item.selectedSize" class="inline-flex items-center gap-1">
                <VunoIcon icon="straighten" :size="14" />{{ item.selectedSize }}
              </span>
            </div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-[#94a3b8]">{{ item.quantity || 1 }} × {{ formatPrice(item.product?.display_price ?? item.product?.price, order.display_symbol) }}</span>
              <span class="font-medium text-[#dae2fd]">{{ formatPrice(calcSubtotal(item), order.display_symbol) }}</span>
            </div>
          </div>

          <!-- Mobile totals -->
          <div v-if="order.display_discountTotal > 0" class="flex items-center justify-between px-6 py-3 text-sm text-[#B8956A]">
            <span>Discount</span>
            <span class="font-medium">-{{ formatPrice(order.display_discountTotal, order.display_symbol) }}</span>
          </div>
          <div class="flex items-center justify-between px-6 py-3 text-sm text-[#94a3b8]">
            <span>Shipping</span>
            <span class="font-medium">{{ formatPrice(order.display_shipping ?? order.shipping, order.display_symbol) }}</span>
          </div>
          <div class="flex items-center justify-between px-6 py-3 text-sm text-[#94a3b8]">
            <span>IVA</span>
            <span class="font-medium">{{ formatPrice(order.display_tax ?? order.tax, order.display_symbol) }}</span>
          </div>
          <div class="flex items-center justify-between px-6 py-4 text-base font-bold text-[#dae2fd] border-t-2 border-[#dae2fd]/10">
            <span>Total</span>
            <span>{{ formatPrice(order.display_total ?? order.total, order.display_symbol) }}</span>
          </div>
        </div>
      </template>
      <div v-else class="empty-state">
        <VunoIcon icon="inventory_2" :size="36" class="empty-state-icon" />
        <p class="empty-state-title">Sin items</p>
        <p class="empty-state-desc">Este pedido no contiene productos.</p>
      </div>
    </div>

    <!-- Update Status -->
    <div class="glass-card overflow-hidden rounded-xl">
      <div class="px-6 pt-5 pb-4 border-b border-[#dae2fd]/5">
        <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
          <VunoIcon icon="update" :size="24" />
          Update Status
        </h2>
      </div>
      <div class="px-6 py-4">
        <form class="flex flex-col sm:flex-row items-stretch sm:items-end gap-4" @submit.prevent="updateStatus">
          <div class="flex-grow">
            <select v-model="selectedStatus" class="admin-input w-full">
              <option v-for="s in validStatuses" :key="s" :value="s">{{ s.charAt(0).toUpperCase() + s.slice(1) }}</option>
            </select>
          </div>
          <button
            type="submit"
            class="admin-btn admin-btn-primary w-full sm:w-auto justify-center"
            :disabled="statusUpdating || selectedStatus === order.status"
          >
            <VunoIcon :icon="statusUpdating ? 'progress_activity' : 'save'" :size="20" :class="{ 'animate-spin': statusUpdating }" />
            {{ statusUpdating ? 'UPDATING...' : 'UPDATE' }}
          </button>
        </form>
      </div>
    </div>

    <div class="mt-4">
      <a href="/admin/pedidos" class="inline-flex items-center gap-1.5 text-sm text-[#94a3b8] hover:text-[#dae2fd] transition-colors">
        <VunoIcon icon="arrow_back" :size="20" />
        Back to Orders
      </a>
    </div>
  </div>
</template>
