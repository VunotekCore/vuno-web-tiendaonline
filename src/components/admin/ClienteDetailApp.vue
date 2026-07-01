<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'
import VunoIcon from './VunoIcon.vue'

const api = useApi()
const toast = useToast()

interface Address {
  id?: number
  label: string
  address_line1: string
  address_line2?: string
  city: string
  state: string
  zip: string
  country?: string
  phone?: string
  is_default_shipping: boolean | number
  is_default_billing: boolean | number
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
  id: number
  name: string
  email: string
  phone: string | null
  is_verified: boolean | number
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

// Edit state
const editMode = ref(false)
const editData = ref({ name: '', email: '', phone: '', notes: '', is_verified: false })
const saving = ref(false)

// Address form state
const showAddressForm = ref(false)
const editingAddressIndex = ref<number | null>(null)
const addressForm = ref<Address>({
  label: '',
  address_line1: '',
  address_line2: '',
  city: '',
  state: '',
  zip: '',
  country: 'GT',
  phone: '',
  is_default_shipping: false,
  is_default_billing: false,
})
const savingAddress = ref(false)

onMounted(async () => {
  const params = new URLSearchParams(window.location.search)
  customerId.value = params.get('id') || ''
  if (!customerId.value) {
    error.value = 'No customer ID specified.'
    loading.value = false
    return
  }
  await loadCustomer()
})

async function loadCustomer() {
  loading.value = true
  try {
    customer.value = await api.get<Cliente>(`/api/clientes/get.php?id=${encodeURIComponent(customerId.value)}`)
  } catch (err: any) {
    error.value = err.message || 'Error loading customer'
  } finally {
    loading.value = false
  }
}

function formatDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('es-NI', { year: 'numeric', month: 'long', day: 'numeric' })
}

function formatPrice(val: number | null | undefined, symbol = '$') {
  if (val == null || isNaN(val)) val = 0
  return symbol + Number(val).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// Edit customer
function toggleEdit() {
  if (editMode.value) {
    cancelEdit()
    return
  }
  if (!customer.value) return
  editData.value = {
    name: customer.value.name,
    email: customer.value.email,
    phone: customer.value.phone || '',
    notes: customer.value.notes || '',
    is_verified: !!customer.value.is_verified,
  }
  editMode.value = true
}

function cancelEdit() {
  editMode.value = false
}

async function saveCustomer() {
  if (!customer.value) return
  saving.value = true
  try {
    const body: Record<string, unknown> = { id: customerId.value }
    if (editData.value.name !== customer.value.name) body.name = editData.value.name
    if (editData.value.email !== customer.value.email) body.email = editData.value.email
    if (editData.value.phone !== (customer.value.phone || '')) body.phone = editData.value.phone
    if (editData.value.notes !== (customer.value.notes || '')) body.notes = editData.value.notes
    if (editData.value.is_verified !== !!customer.value.is_verified) body.is_verified = editData.value.is_verified
    if (Object.keys(body).length <= 1) {
      toast.info('Sin cambios', 'No hay cambios para guardar.')
      editMode.value = false
      return
    }
    const updated = await api.post<Cliente>('/api/clientes/update.php', body)
    customer.value = updated
    editMode.value = false
    toast.success('Cliente actualizado')
  } catch (err: any) {
    toast.error('Error', err.message || 'No se pudo actualizar el cliente')
  } finally {
    saving.value = false
  }
}

// Address management
function openAddressForm(index: number | null = null) {
  if (index !== null && customer.value?.addresses[index]) {
    const a = customer.value.addresses[index]
    addressForm.value = {
      id: a.id || undefined,
      label: a.label || '',
      address_line1: a.address_line1 || '',
      address_line2: a.address_line2 || '',
      city: a.city || '',
      state: a.state || '',
      zip: a.zip || '',
      country: a.country || 'GT',
      phone: a.phone || '',
      is_default_shipping: !!a.is_default_shipping,
      is_default_billing: !!a.is_default_billing,
    }
    editingAddressIndex.value = index
  } else {
    addressForm.value = { label: '', address_line1: '', address_line2: '', city: '', state: '', zip: '', country: 'GT', phone: '', is_default_shipping: false, is_default_billing: false }
    editingAddressIndex.value = null
  }
  showAddressForm.value = true
}

function closeAddressForm() {
  showAddressForm.value = false
  editingAddressIndex.value = null
}

async function saveAddress() {
  if (!customer.value) return
  savingAddress.value = true
  try {
    const isEdit = editingAddressIndex.value !== null && typeof customer.value.addresses[editingAddressIndex.value]?.id === 'number'
    const body = {
      ...(isEdit && addressForm.value.id ? { id: addressForm.value.id } : {}),
      customer_id: Number(customerId.value),
      label: addressForm.value.label,
      address_line1: addressForm.value.address_line1,
      address_line2: addressForm.value.address_line2,
      city: addressForm.value.city,
      state: addressForm.value.state,
      zip: addressForm.value.zip,
      country: addressForm.value.country,
      phone: addressForm.value.phone,
      is_default_shipping: addressForm.value.is_default_shipping ? 1 : 0,
      is_default_billing: addressForm.value.is_default_billing ? 1 : 0,
    }
    if (isEdit) {
      await api.post('/api/clientes/update-address.php', body)
      toast.success('Dirección actualizada')
    } else {
      await api.post('/api/clientes/create-address.php', body)
      toast.success('Dirección agregada')
    }
    closeAddressForm()
    await loadCustomer()
  } catch (err: any) {
    toast.error('Error', err.message || 'No se pudo guardar la dirección')
  } finally {
    savingAddress.value = false
  }
}

async function deleteAddress(index: number) {
  if (!customer.value) return
  const address = customer.value.addresses[index]
  if (!address.id) return
  if (!confirm('¿Eliminar esta dirección definitivamente?')) return
  try {
    await api.post('/api/clientes/delete-address.php', { id: address.id, customer_id: Number(customerId.value) })
    toast.success('Dirección eliminada')
    await loadCustomer()
  } catch (err: any) {
    toast.error('Error', err.message || 'No se pudo eliminar la dirección')
  }
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
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
            <VunoIcon icon="person" :size="24" />
            <template v-if="!editMode">{{ customer.name }}</template>
            <input v-else v-model="editData.name" class="admin-input flex-1" placeholder="Nombre" />
          </h2>
          <button v-if="!editMode" @click="toggleEdit" class="admin-btn admin-btn-ghost admin-btn-xs" title="Editar cliente">
            <VunoIcon icon="edit" :size="16" />
          </button>
          <button v-else @click="cancelEdit" class="admin-btn admin-btn-ghost admin-btn-xs" title="Cancelar">
            <VunoIcon icon="close" :size="16" />
          </button>
        </div>

        <div class="space-y-3 text-sm">
          <div class="flex items-center gap-2">
            <VunoIcon icon="mail" :size="16" class="text-[#94a3b8]" />
            <span class="text-[#94a3b8] shrink-0">Email:</span>
            <template v-if="!editMode">
              <a :href="'mailto:' + customer.email" class="text-[#dae2fd] hover:text-[#42b883] transition-colors">{{ customer.email }}</a>
            </template>
            <input v-else v-model="editData.email" class="admin-input flex-1" type="email" placeholder="Email" />
          </div>
          <div class="flex items-center gap-2">
            <VunoIcon icon="phone" :size="16" class="text-[#94a3b8]" />
            <span class="text-[#94a3b8] shrink-0">Teléfono:</span>
            <template v-if="!editMode">
              <span class="text-[#dae2fd]">{{ customer.phone || '—' }}</span>
            </template>
            <input v-else v-model="editData.phone" class="admin-input flex-1" placeholder="Teléfono" />
          </div>
          <div class="flex items-center gap-2">
            <VunoIcon icon="verified" :size="16" class="text-[#94a3b8]" />
            <span class="text-[#94a3b8] shrink-0">Verificado:</span>
            <template v-if="!editMode">
              <span v-if="customer.is_verified" class="text-[#42b883] flex items-center gap-1">
                <VunoIcon icon="check_circle" :size="14" class="text-[#42b883]" />Sí
              </span>
              <span v-else class="text-[#94a3b8]">No</span>
            </template>
            <label v-else class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="editData.is_verified" class="w-4 h-4 accent-[#42b883]" />
              <span class="text-[#dae2fd] text-xs">{{ editData.is_verified ? 'Sí' : 'No' }}</span>
            </label>
          </div>
          <div class="flex items-center gap-2">
            <VunoIcon icon="calendar_today" :size="16" class="text-[#94a3b8]" />
            <span class="text-[#94a3b8] shrink-0">Registrado:</span>
            <span class="text-[#dae2fd]">{{ formatDate(customer.created_at) }}</span>
          </div>
          <div class="flex items-center gap-2">
            <VunoIcon icon="history" :size="16" class="text-[#94a3b8]" />
            <span class="text-[#94a3b8] shrink-0">Último pedido:</span>
            <span class="text-[#dae2fd]">{{ formatDate(customer.last_order_at) }}</span>
          </div>
        </div>

        <div v-if="editMode" class="mt-5 pt-4 border-t border-[#dae2fd]/5 flex gap-2 justify-end">
          <button @click="cancelEdit" class="admin-btn admin-btn-ghost admin-btn-sm" :disabled="saving">Cancelar</button>
          <button @click="saveCustomer" class="admin-btn admin-btn-primary admin-btn-sm" :disabled="saving">
            <VunoIcon v-if="saving" icon="progress_activity" :size="16" class="animate-spin" />
            <span v-else>Guardar</span>
          </button>
        </div>
      </div>

      <div class="glass-card overflow-hidden rounded-xl px-6 pt-5 pb-6">
        <h2 class="text-lg font-semibold text-[#dae2fd] mb-4 flex items-center gap-2">
          <VunoIcon icon="notes" :size="24" />
          Notas internas
        </h2>
        <template v-if="!editMode">
          <p class="text-sm text-[#94a3b8] leading-relaxed whitespace-pre-wrap">{{ customer.notes || 'Sin notas.' }}</p>
        </template>
        <textarea v-else v-model="editData.notes" class="admin-input w-full min-h-[100px]" placeholder="Notas internas..."></textarea>
      </div>
    </div>

    <!-- Addresses -->
    <div class="glass-card overflow-hidden rounded-xl">
      <div class="px-6 pt-5 pb-4 border-b border-[#dae2fd]/5 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
          <VunoIcon icon="home" :size="24" />
          Direcciones
        </h2>
        <button @click="openAddressForm()" class="admin-btn admin-btn-primary admin-btn-xs">
          <VunoIcon icon="add" :size="16" />
          Agregar
        </button>
      </div>

      <!-- Address form -->
      <div v-if="showAddressForm" class="px-6 py-4 border-b border-[#dae2fd]/5 bg-[#1e293b]/30">
        <h3 class="text-sm font-semibold text-[#dae2fd] mb-3">
          {{ editingAddressIndex !== null ? 'Editar dirección' : 'Nueva dirección' }}
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <input v-model="addressForm.label" class="admin-input" placeholder="Etiqueta (ej: Casa, Oficina)" />
          <input v-model="addressForm.phone" class="admin-input" placeholder="Teléfono" />
          <div class="md:col-span-2">
            <input v-model="addressForm.address_line1" class="admin-input w-full" placeholder="Dirección línea 1 *" />
          </div>
          <input v-model="addressForm.address_line2" class="admin-input" placeholder="Dirección línea 2" />
          <input v-model="addressForm.city" class="admin-input" placeholder="Ciudad" />
          <input v-model="addressForm.state" class="admin-input" placeholder="Departamento/Estado" />
          <input v-model="addressForm.zip" class="admin-input" placeholder="Código postal" />
          <input v-model="addressForm.country" class="admin-input" placeholder="País (GT)" />
          <div class="flex items-center gap-6">
            <label class="flex items-center gap-2 cursor-pointer text-xs text-[#94a3b8]">
              <input type="checkbox" v-model="addressForm.is_default_shipping" class="w-4 h-4 accent-[#42b883]" />
              Envío por defecto
            </label>
            <label class="flex items-center gap-2 cursor-pointer text-xs text-[#94a3b8]">
              <input type="checkbox" v-model="addressForm.is_default_billing" class="w-4 h-4 accent-[#42b883]" />
              Facturación por defecto
            </label>
          </div>
        </div>
        <div class="flex gap-2 justify-end mt-4">
          <button @click="closeAddressForm" class="admin-btn admin-btn-ghost admin-btn-sm" :disabled="savingAddress">Cancelar</button>
          <button @click="saveAddress" class="admin-btn admin-btn-primary admin-btn-sm" :disabled="savingAddress || !addressForm.address_line1">
            <VunoIcon v-if="savingAddress" icon="progress_activity" :size="16" class="animate-spin" />
            <span v-else>Guardar</span>
          </button>
        </div>
      </div>

      <div v-if="customer.addresses?.length" class="px-6 py-4 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div v-for="(a, i) in customer.addresses" :key="i" class="border border-[#1e293b] rounded-lg p-4 relative group">
          <div v-if="a.is_default_shipping || a.is_default_billing" class="flex gap-1 mb-2">
            <span v-if="a.is_default_shipping" class="badge badge-paid text-[10px] px-1.5 py-0.5">Envío</span>
            <span v-if="a.is_default_billing" class="badge badge-paid text-[10px] px-1.5 py-0.5">Facturación</span>
          </div>
          <p class="text-sm font-medium text-[#dae2fd]">{{ a.label || 'Dirección' }}</p>
          <p class="text-xs text-[#94a3b8] mt-1">{{ a.address_line1 }}{{ a.address_line2 ? ', ' + a.address_line2 : '' }}</p>
          <p class="text-xs text-[#94a3b8]">{{ a.city ? a.city + ', ' : '' }}{{ a.state ? a.state + ' ' : '' }}{{ a.zip || '' }}</p>
          <p v-if="a.phone" class="text-xs text-[#94a3b8] mt-1">{{ a.phone }}</p>
          <div class="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
            <button @click="openAddressForm(i)" class="admin-btn admin-btn-ghost admin-btn-xs" title="Editar dirección">
              <VunoIcon icon="edit" :size="14" />
            </button>
            <button @click="deleteAddress(i)" class="admin-btn admin-btn-ghost admin-btn-xs !text-[#DC2626] hover:!bg-[#DC2626]/10" title="Eliminar dirección">
              <VunoIcon icon="delete" :size="14" />
            </button>
          </div>
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
                <td><span class="badge" :class="'badge-' + o.status_code">{{ o.status_code }}</span></td>
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
        <VunoIcon icon="receipt_long" :size="36" class="empty-state-icon" />
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
          <a :href="'/es/producto/' + w.slug" class="text-sm text-[#dae2fd] hover:text-[#42b883] transition-colors" target="_blank">{{ w.name }}</a>
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
