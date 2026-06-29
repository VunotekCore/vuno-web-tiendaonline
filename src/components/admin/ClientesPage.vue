<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'
import VunoIcon from './VunoIcon.vue'

const api = useApi()
const toast = useToast()

interface Cliente {
  id: number
  name: string
  email: string
  phone: string | null
  orders_count: number
  created_at: string
}

const items = ref<Cliente[]>([])
const loading = ref(false)
const search = ref('')
const searchTimer = ref<ReturnType<typeof setTimeout> | null>(null)
const currentPage = ref(1)
const total = ref(0)
const perPage = 10
const deleteId = ref<number | null>(null)
const deleteName = ref('')
const confirmVisible = ref(false)
const editMode = ref(false)

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
    const data = await api.get<{ items: Cliente[]; total: number }>(`/api/clientes/list.php?${qs}`)
    items.value = data.items || []
    total.value = data.total || items.value.length
  } catch { items.value = []; total.value = 0 } finally { loading.value = false }
}

function onSearchInput(val: string) {
  search.value = val
  if (searchTimer.value) clearTimeout(searchTimer.value)
  searchTimer.value = setTimeout(() => { currentPage.value = 1; loadData() }, 300)
}

function formatDate(d: string) {
  return d ? new Date(d).toLocaleDateString('es-NI', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
}

function openDelete(item: Cliente) {
  deleteId.value = item.id
  deleteName.value = item.name
  confirmVisible.value = true
}

function closeDelete() {
  confirmVisible.value = false
  deleteId.value = null
  deleteName.value = ''
}

async function confirmDelete() {
  if (!deleteId.value) return
  try {
    await api.post('/api/clientes/delete.php', { id: deleteId.value })
    toast.success('Cliente eliminado')
    closeDelete()
    await loadData()
  } catch (e: any) {
    toast.error(e.message || 'Error al eliminar')
  }
}
</script>

<template>
  <div class="admin-card">
    <div class="admin-card-header">
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 w-full">
        <div class="flex-1 flex flex-wrap items-center gap-4">
          <div class="relative min-w-[200px] flex-1">
            <input :value="search" type="text" placeholder="Buscar clientes..." class="admin-input pl-3 w-full" @input="onSearchInput(($event.target as HTMLInputElement).value)" />
          </div>
          <span class="text-sm text-[#94a3b8] whitespace-nowrap">{{ total }} cliente{{ total !== 1 ? 's' : '' }}</span>
        </div>
        <button class="admin-btn admin-btn-edit w-full sm:w-auto justify-center" @click="editMode = !editMode">
          <VunoIcon :icon="editMode ? 'edit_off' : 'edit'" :size="16" />
          {{ editMode ? 'SALIR' : 'EDITAR' }}
        </button>
      </div>
    </div>

    <!-- Desktop table -->
    <div class="hidden md:block overflow-x-auto">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Cliente</th>
            <th>Email</th>
            <th>Teléfono</th>
            <th>Pedidos</th>
            <th>Registrado</th>
            <th v-if="editMode" class="w-32 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6" class="text-center py-8 text-[#94a3b8]">Cargando...</td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td colspan="6" class="empty-state px-6 py-10">
              <VunoIcon icon="users" :size="36" class="empty-state-icon" />
              <p class="empty-state-title">Sin clientes</p>
              <p class="empty-state-desc">No hay clientes registrados.</p>
            </td>
          </tr>
          <tr v-for="item in items" :key="item.id">
            <td class="font-medium">
              <a :href="'/admin/clientes/detalle?id=' + item.id" class="text-[#dae2fd] hover:text-[#42b883] transition-colors">{{ item.name }}</a>
            </td>
            <td class="text-[#94a3b8]">{{ item.email }}</td>
            <td class="text-[#94a3b8]">{{ item.phone || '—' }}</td>
            <td class="text-[#94a3b8]">{{ item.orders_count }}</td>
            <td class="text-sm text-[#94a3b8]">{{ formatDate(item.created_at) }}</td>
            <td v-if="editMode" class="text-right whitespace-nowrap">
              <a :href="'/admin/clientes/detalle?id=' + item.id" class="admin-btn admin-btn-ghost admin-btn-xs" title="Ver detalle">
                <VunoIcon icon="visibility" :size="14" />
              </a>
              <button class="admin-btn admin-btn-danger admin-btn-xs" @click="openDelete(item)" title="Eliminar">
                <VunoIcon icon="delete" :size="14" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Mobile cards -->
    <div class="md:hidden px-6 pb-4 space-y-3">
      <div v-if="loading" class="text-center py-8 text-[#94a3b8]">Cargando...</div>
      <div v-else-if="items.length === 0" class="empty-state">
        <VunoIcon icon="users" :size="36" class="empty-state-icon" />
        <p class="empty-state-title">Sin clientes</p>
        <p class="empty-state-desc">No hay clientes registrados.</p>
      </div>
      <div v-for="item in items" :key="item.id" class="glass-card overflow-hidden rounded-xl">
        <div class="px-5 pt-4 pb-3 border-b border-[#dae2fd]/5">
          <div class="flex items-center justify-between gap-2">
            <a :href="'/admin/clientes/detalle?id=' + item.id" class="text-[#dae2fd] hover:text-[#42b883] font-medium text-sm truncate">{{ item.name }}</a>
            <span class="text-xs text-[#94a3b8] shrink-0">{{ item.orders_count }} pedidos</span>
          </div>
        </div>
        <div class="px-5 py-3 flex items-center justify-between">
          <div class="flex flex-col gap-1 text-sm text-[#94a3b8]">
            <span>{{ item.email }}</span>
            <span>{{ item.phone || '—' }}</span>
            <span class="text-xs">{{ formatDate(item.created_at) }}</span>
          </div>
          <div v-if="editMode" class="flex gap-1 shrink-0">
            <a :href="'/admin/clientes/detalle?id=' + item.id" class="admin-btn admin-btn-ghost admin-btn-xs" title="Ver detalle">
              <VunoIcon icon="visibility" :size="14" />
            </a>
            <button class="admin-btn admin-btn-danger admin-btn-xs" @click="openDelete(item)" title="Eliminar">
              <VunoIcon icon="delete" :size="14" />
            </button>
          </div>
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

  <Teleport to="body">
    <Transition name="modal-slide">
      <div v-if="confirmVisible" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-md p-4">
        <div class="admin-card-lg w-full max-w-md mx-4">
          <div class="flex items-center gap-3 mb-4">
            <VunoIcon icon="warning" :size="30" />
            <h3 class="text-lg font-semibold text-[#dae2fd]">Eliminar Cliente</h3>
          </div>
          <p class="text-sm text-[#94a3b8] mb-4">
            ¿Estás seguro de eliminar a <strong class="text-[#dae2fd]">{{ deleteName }}</strong>?
          </p>
          <p class="text-xs text-[#DC2626]/70 mb-6 flex items-center gap-1">
            <VunoIcon icon="info" :size="14" />
            Los datos del cliente se eliminarán permanentemente. Los pedidos existentes quedarán sin asociación.
          </p>
          <div class="flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button class="admin-btn admin-btn-secondary w-full sm:w-auto justify-center" @click="closeDelete">Cancelar</button>
            <button class="admin-btn admin-btn-danger w-full sm:w-auto justify-center" @click="confirmDelete">Eliminar</button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
