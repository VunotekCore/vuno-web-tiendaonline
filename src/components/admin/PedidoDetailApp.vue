<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'

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

  <div v-else-if="order" class="space-y-8 admin-enter">
    <!-- Order Info + Customer -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="admin-card p-6">
        <h2 class="text-lg font-semibold text-[#dae2fd] mb-4 flex items-center gap-2">
          <span class="material-symbols-outlined text-xl">receipt</span>
          Order <span class="text-[#dae2fd]">{{ order.id }}</span>
        </h2>
        <div class="space-y-3 text-sm">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-base text-[#94a3b8]">calendar_today</span>
            <span class="text-[#94a3b8]">Date:</span>
            <span class="text-[#dae2fd]">{{ formatDate(order.createdAt) }}</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-base text-[#94a3b8]">info</span>
            <span class="text-[#94a3b8]">Status:</span>
            <span class="badge" :class="statusStyles[order.status] || ''">{{ order.status }}</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-base text-[#94a3b8]">credit_card</span>
            <span class="text-[#94a3b8]">Payment:</span>
            <span class="text-[#dae2fd]">{{ payLabels[order.paymentMethod] || order.paymentMethod }}</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-base text-[#94a3b8]">check_circle</span>
            <span class="text-[#94a3b8]">Payment Status:</span>
            <span class="text-[#dae2fd]">{{ order.paymentStatus || 'N/A' }}</span>
          </div>
          <div v-if="order.transferReceipt" class="flex items-center gap-2">
            <span class="material-symbols-outlined text-base text-[#94a3b8]">description</span>
            <span class="text-[#94a3b8]">Comprobante:</span>
            <a :href="order.transferReceipt" target="_blank" class="text-[#42b883] underline hover:no-underline">Ver comprobante</a>
          </div>
          <div v-if="order.selectedBankName" class="flex items-center gap-2">
            <span class="material-symbols-outlined text-base text-[#94a3b8]">account_balance</span>
            <span class="text-[#94a3b8]">Bank:</span>
            <span class="text-[#dae2fd]">{{ order.selectedBankName }}</span>
          </div>
        </div>
      </div>

      <div class="admin-card p-6">
        <h2 class="text-lg font-semibold text-[#dae2fd] mb-4 flex items-center gap-2">
          <span class="material-symbols-outlined text-xl">person</span>
          Customer
        </h2>
        <div v-if="order.customer" class="space-y-3 text-sm">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-base text-[#94a3b8]">badge</span>
            <span class="text-[#94a3b8]">Name:</span>
            <span class="text-[#dae2fd]">{{ order.customer.name }}</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-base text-[#94a3b8]">mail</span>
            <span class="text-[#94a3b8]">Email:</span>
            <span class="text-[#dae2fd]">{{ order.customer.email }}</span>
          </div>
          <div class="flex items-start gap-2">
            <span class="material-symbols-outlined text-base text-[#94a3b8] mt-0.5">home</span>
            <span class="text-[#94a3b8]">Address:</span>
            <span class="text-[#dae2fd]">{{ order.customer.address }}</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-base text-[#94a3b8]">location_city</span>
            <span class="text-[#94a3b8]">City:</span>
            <span class="text-[#dae2fd]">{{ order.customer.city }}</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-base text-[#94a3b8]">pin_drop</span>
            <span class="text-[#94a3b8]">Zip:</span>
            <span class="text-[#dae2fd]">{{ order.customer.zip }}</span>
          </div>
        </div>
        <p v-else class="text-sm text-[#94a3b8]">No customer data.</p>
      </div>
    </div>

    <!-- Order Items -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
          <span class="material-symbols-outlined text-xl">shopping_bag</span>
          Order Items
        </h2>
      </div>
      <template v-if="order.items?.length">
        <div class="overflow-x-auto">
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
              <tr v-if="order.display_discountTotal > 0" class="border-t border-[#1e293b]">
                <td colspan="5" class="p-4 text-right text-sm text-[#B8956A]">Discount</td>
                <td class="p-4 font-medium text-[#B8956A]">-{{ formatPrice(order.display_discountTotal, order.display_symbol) }}</td>
              </tr>
              <tr class="border-t border-[#1e293b]">
                <td colspan="5" class="p-4 text-right text-sm text-[#94a3b8]">Shipping</td>
                <td class="p-4 font-medium">{{ formatPrice(order.display_shipping ?? order.shipping, order.display_symbol) }}</td>
              </tr>
              <tr class="border-t border-[#1e293b]">
                <td colspan="5" class="p-4 text-right text-sm text-[#94a3b8]">IVA</td>
                <td class="p-4 font-medium">{{ formatPrice(order.display_tax ?? order.tax, order.display_symbol) }}</td>
              </tr>
              <tr class="border-t-2 border-[#1e293b]">
                <td colspan="5" class="p-4 text-right text-lg font-semibold text-[#dae2fd]">Total</td>
                <td class="p-4 text-lg font-bold text-[#dae2fd]">{{ formatPrice(order.display_total ?? order.total, order.display_symbol) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </template>
      <div v-else class="admin-card-body text-sm text-[#94a3b8]">No items.</div>
    </div>

    <!-- Update Status -->
    <div class="admin-card p-6">
      <h2 class="text-lg font-semibold text-[#dae2fd] mb-4 flex items-center gap-2">
        <span class="material-symbols-outlined text-xl">update</span>
        Update Status
      </h2>
      <form class="flex items-end gap-4" @submit.prevent="updateStatus">
        <div class="flex-grow">
          <select v-model="selectedStatus" class="admin-input">
            <option v-for="s in validStatuses" :key="s" :value="s">{{ s.charAt(0).toUpperCase() + s.slice(1) }}</option>
          </select>
        </div>
        <button
          type="submit"
          class="admin-btn admin-btn-primary"
          :disabled="statusUpdating || selectedStatus === order.status"
        >
          <span class="material-symbols-outlined text-lg" :class="{ 'animate-spin': statusUpdating }">{{ statusUpdating ? 'progress_activity' : 'save' }}</span>
          {{ statusUpdating ? 'UPDATING...' : 'UPDATE' }}
        </button>
      </form>
    </div>

    <div class="mt-6">
      <a href="/admin/pedidos" class="inline-flex items-center gap-1.5 text-sm text-[#94a3b8] hover:text-[#dae2fd] transition-colors">
        <span class="material-symbols-outlined text-lg">arrow_back</span>
        Back to Orders
      </a>
    </div>
  </div>
</template>
