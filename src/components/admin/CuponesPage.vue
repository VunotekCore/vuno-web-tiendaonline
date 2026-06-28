<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'

const api = useApi()
const toast = useToast()

interface Coupon {
  id: number
  code: string
  description: string
  discount_type: 'percentage' | 'fixed'
  discount_value: number
  min_order_amount: number | null
  max_uses: number | null
  max_uses_per_customer: number | null
  use_count: number
  is_active: number | boolean
  starts_at: string | null
  expires_at: string | null
}

const items = ref<Coupon[]>([])
const loading = ref(false)
const saving = ref(false)
const search = ref('')
const currentPage = ref(1)
const total = ref(0)
const perPage = 10
const modalVisible = ref(false)
const editingItem = ref<Coupon | null>(null)

const formCode = ref('')
const formDescription = ref('')
const formType = ref<'percentage' | 'fixed'>('percentage')
const formValue = ref<number | null>(null)
const formMinAmount = ref<number | null>(null)
const formMaxUses = ref<number | null>(null)
const formMaxPerCustomer = ref<number | null>(null)
const formStartsAt = ref('')
const formExpiresAt = ref('')
const formActive = ref(true)
const formError = ref('')

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

onMounted(loadData)

async function loadData() {
  loading.value = true
  try {
    const qs = new URLSearchParams()
    qs.set('limit', String(perPage))
    qs.set('offset', String((currentPage.value - 1) * perPage))
    if (search.value.trim()) qs.set('search', search.value.trim())
    const data = await api.get<{ items: Coupon[]; total: number }>(`/api/cupones/list.php?${qs}`)
    items.value = data.items || []
    total.value = data.total || items.value.length
  } catch { items.value = []; total.value = 0 } finally { loading.value = false }
}

function formatCurrency(n: number | null | undefined) {
  if (n == null) return '$0.00'
  return '$' + Number(n).toFixed(2)
}

function formatDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: 'numeric' })
}

function statusBadge(c: Coupon) {
  const now = new Date()
  const expires = c.expires_at ? new Date(c.expires_at) : null
  const starts = c.starts_at ? new Date(c.starts_at) : null
  if (!c.is_active) return { text: 'INACTIVO', cls: 'badge-draft' }
  if (expires && expires < now) return { text: 'VENCIDO', cls: 'badge-cancelled' }
  if (starts && starts > now) return { text: 'PROGRAMADO', cls: 'badge-pending' }
  return { text: 'ACTIVO', cls: 'badge-paid' }
}

function discountLabel(c: Coupon) {
  return c.discount_type === 'percentage' ? c.discount_value + '%' : formatCurrency(c.discount_value)
}

function openCreate() {
  editingItem.value = null
  formCode.value = ''
  formDescription.value = ''
  formType.value = 'percentage'
  formValue.value = null
  formMinAmount.value = null
  formMaxUses.value = null
  formMaxPerCustomer.value = null
  formStartsAt.value = ''
  formExpiresAt.value = ''
  formActive.value = true
  formError.value = ''
  modalVisible.value = true
}

function openEdit(item: Coupon) {
  editingItem.value = item
  formCode.value = item.code
  formDescription.value = item.description || ''
  formType.value = item.discount_type
  formValue.value = item.discount_value
  formMinAmount.value = item.min_order_amount
  formMaxUses.value = item.max_uses
  formMaxPerCustomer.value = item.max_uses_per_customer
  formStartsAt.value = item.starts_at ? item.starts_at.substring(0, 16) : ''
  formExpiresAt.value = item.expires_at ? item.expires_at.substring(0, 16) : ''
  formActive.value = !!item.is_active
  formError.value = ''
  modalVisible.value = true
}

function closeModal() {
  modalVisible.value = false
  editingItem.value = null
}

async function save() {
  formError.value = ''
  const code = formCode.value.trim().toUpperCase()
  if (!code) { formError.value = 'El código es obligatorio'; return }
  if (!formValue.value || formValue.value <= 0) { formError.value = 'El valor debe ser mayor a 0'; return }
  if (formType.value === 'percentage' && formValue.value > 100) { formError.value = 'El porcentaje no puede superar 100%'; return }
  if (formStartsAt.value && formExpiresAt.value && new Date(formExpiresAt.value) <= new Date(formStartsAt.value)) {
    formError.value = 'La fecha de fin debe ser posterior a la de inicio'; return
  }

  saving.value = true
  try {
    const payload: Record<string, any> = {
      code,
      description: formDescription.value.trim(),
      discount_type: formType.value,
      discount_value: formValue.value,
      min_order_amount: formMinAmount.value || null,
      max_uses: formMaxUses.value || null,
      max_uses_per_customer: formMaxPerCustomer.value || null,
      is_active: formActive.value,
      starts_at: formStartsAt.value ? new Date(formStartsAt.value).toISOString().slice(0, 19).replace('T', ' ') : null,
      expires_at: formExpiresAt.value ? new Date(formExpiresAt.value).toISOString().slice(0, 19).replace('T', ' ') : null,
    }

    if (editingItem.value) {
      payload.id = editingItem.value.id
      await api.post('/api/cupones/update.php', payload)
      toast.success('Cupón actualizado')
    } else {
      await api.post('/api/cupones/create.php', payload)
      toast.success('Cupón creado')
    }
    closeModal()
    await loadData()
  } catch (e: any) {
    formError.value = e.message || 'Error al guardar'
  } finally { saving.value = false }
}

async function remove(item: Coupon) {
  if (!(window as any).VunoModal) return
  const confirmed = await (window as any).VunoModal.confirm(`¿Eliminar cupón "${item.code}"?`)
  if (!confirmed) return
  try {
    await api.post('/api/cupones/delete.php', { id: item.id })
    toast.success('Cupón eliminado')
    await loadData()
  } catch (e: any) {
    toast.error(e.message || 'Error al eliminar')
  }
}
</script>

<template>
  <div class="admin-card">
    <div class="admin-card-header">
      <div>
        <h2 class="text-lg font-semibold text-[#dae2fd]">Cupones</h2>
        <p class="text-sm text-[#94a3b8] mt-1">Gestiona códigos de descuento</p>
      </div>
      <button class="admin-btn admin-btn-primary" @click="openCreate">
        <span class="material-symbols-outlined text-base">add</span>
        Nuevo Cupón
      </button>
    </div>

    <div class="px-6 pb-4">
      <input v-model="search" type="text" placeholder="Buscar cupones..." class="admin-input max-w-xs" @input="currentPage = 1; loadData()" />
    </div>

    <div class="overflow-x-auto">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Código</th>
            <th>Descuento</th>
            <th>Vigencia</th>
            <th>Usos</th>
            <th>Estado</th>
            <th class="w-24 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6" class="text-center py-8 text-[#94a3b8]">Cargando...</td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td colspan="6" class="text-center py-8 text-[#94a3b8]">No hay cupones</td>
          </tr>
          <tr v-for="item in items" :key="item.id">
            <td>
              <span class="font-mono font-medium">{{ item.code }}</span>
              <br v-if="item.description" /><span class="text-xs text-[#94a3b8]">{{ item.description }}</span>
            </td>
            <td class="font-semibold">{{ discountLabel(item) }}</td>
            <td class="text-sm text-[#94a3b8]">
              <template v-if="item.starts_at">Desde: {{ formatDate(item.starts_at) }}<br /></template>
              Hasta: {{ formatDate(item.expires_at) }}
            </td>
            <td class="text-sm">{{ item.use_count }}<template v-if="item.max_uses"> / {{ item.max_uses }}</template></td>
            <td><span class="badge" :class="statusBadge(item).cls">{{ statusBadge(item).text }}</span></td>
            <td class="text-right">
              <button class="admin-btn admin-btn-ghost admin-btn-xs" @click="openEdit(item)" title="Editar">
                <span class="material-symbols-outlined text-sm">edit</span>
              </button>
              <button class="admin-btn admin-btn-danger admin-btn-xs" @click="remove(item)" title="Eliminar">
                <span class="material-symbols-outlined text-sm">delete</span>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="totalPages > 1" class="admin-card-footer flex items-center justify-between">
      <span class="text-sm text-[#94a3b8]">Página {{ currentPage }} de {{ totalPages }}</span>
      <div class="flex gap-1">
        <button class="admin-btn admin-btn-ghost admin-btn-xs" :disabled="currentPage === 1" @click="currentPage--; loadData()">Anterior</button>
        <button v-for="p in pageWindow" :key="p" class="admin-btn admin-btn-xs" :class="p === currentPage ? 'admin-btn-primary' : 'admin-btn-ghost'" @click="currentPage = p; loadData()">{{ p }}</button>
        <button class="admin-btn admin-btn-ghost admin-btn-xs" :disabled="currentPage === totalPages" @click="currentPage++; loadData()">Siguiente</button>
      </div>
    </div>
  </div>

  <Teleport to="body">
    <div v-if="modalVisible" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-md p-4">
      <div class="admin-card-lg w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-lg font-semibold text-[#dae2fd]">{{ editingItem ? 'Editar' : 'Nuevo' }} Cupón</h3>
          <button class="admin-btn admin-btn-ghost admin-btn-xs" @click="closeModal">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div v-if="formError" class="text-sm text-[#DC2626] bg-[#DC2626]/10 p-3 mb-4">{{ formError }}</div>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-[#94a3b8] mb-1">Código *</label>
            <input v-model="formCode" type="text" class="admin-input uppercase" maxlength="50" placeholder="Ej. BIENVENIDO10" />
          </div>
          <div>
            <label class="block text-sm font-medium text-[#94a3b8] mb-1">Descripción</label>
            <input v-model="formDescription" type="text" class="admin-input" maxlength="255" placeholder="Descripción interna" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-[#94a3b8] mb-1">Tipo *</label>
              <select v-model="formType" class="admin-input">
                <option value="percentage">Porcentaje (%)</option>
                <option value="fixed">Monto fijo ($)</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-[#94a3b8] mb-1">Valor *</label>
              <input v-model.number="formValue" type="number" step="0.01" min="0" class="admin-input" placeholder="10" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-[#94a3b8] mb-1">Monto mínimo de pedido</label>
            <input v-model.number="formMinAmount" type="number" step="0.01" min="0" class="admin-input" placeholder="0 (sin mínimo)" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-[#94a3b8] mb-1">Usos máximos (total)</label>
              <input v-model.number="formMaxUses" type="number" min="0" class="admin-input" placeholder="Ilimitado" />
            </div>
            <div>
              <label class="block text-sm font-medium text-[#94a3b8] mb-1">Usos por cliente</label>
              <input v-model.number="formMaxPerCustomer" type="number" min="0" class="admin-input" placeholder="Ilimitado" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-[#94a3b8] mb-1">Válido desde</label>
              <input v-model="formStartsAt" type="datetime-local" class="admin-input" />
            </div>
            <div>
              <label class="block text-sm font-medium text-[#94a3b8] mb-1">Válido hasta</label>
              <input v-model="formExpiresAt" type="datetime-local" class="admin-input" />
            </div>
          </div>
          <label class="flex items-center gap-3 cursor-pointer py-2">
            <input v-model="formActive" type="checkbox" class="w-5 h-5 accent-[#42b883] cursor-pointer" />
            <span class="text-sm text-[#94a3b8]">Activo</span>
          </label>
        </div>

        <div class="flex justify-end gap-3 mt-6">
          <button class="admin-btn admin-btn-secondary" @click="closeModal">Cancelar</button>
          <button class="admin-btn admin-btn-primary" :disabled="saving" @click="save">
            {{ saving ? 'Guardando...' : (editingItem ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
