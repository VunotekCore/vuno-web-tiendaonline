<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'

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
const currentPage = ref(1)
const total = ref(0)
const perPage = 10
const deleteId = ref<number | null>(null)
const deleteName = ref('')
const confirmVisible = ref(false)
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

const isViewer = computed(() => (window as any).adminRole === 'viewer')

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

function onSearchInput() {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => { currentPage.value = 1; loadData() }, 300)
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
        <div class="flex flex-wrap items-center gap-4">
          <div class="relative max-w-xs">
            <span class="material-symbols-outlined absolute left-4 inset-y-0 flex items-center text-lg text-[#94a3b8] pointer-events-none">search</span>
            <input v-model="search" type="text" placeholder="Buscar clientes..." class="admin-input pl-12 w-full" @input="onSearchInput" />
          </div>
          <span class="text-sm text-[#94a3b8] whitespace-nowrap">{{ total }} cliente{{ total !== 1 ? 's' : '' }}</span>
        </div>
      </div>
    </div>

    <!-- Desktop table -->
    <div class="overflow-x-auto">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Cliente</th>
            <th>Email</th>
            <th>Teléfono</th>
            <th>Pedidos</th>
            <th>Registrado</th>
            <th class="w-32 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6" class="text-center py-8 text-[#94a3b8]">Cargando...</td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td colspan="6" class="text-center py-8 text-[#94a3b8]">No se encontraron clientes</td>
          </tr>
          <tr v-for="item in items" :key="item.id">
            <td class="font-medium">
              <a :href="'/admin/clientes/detalle?id=' + item.id" class="text-[#dae2fd] hover:text-[#42b883] transition-colors">{{ item.name }}</a>
            </td>
            <td class="text-[#94a3b8]">{{ item.email }}</td>
            <td class="text-[#94a3b8]">{{ item.phone || '—' }}</td>
            <td class="text-[#94a3b8]">{{ item.orders_count }}</td>
            <td class="text-sm text-[#94a3b8]">{{ formatDate(item.created_at) }}</td>
            <td class="text-right">
              <a :href="'/admin/clientes/detalle?id=' + item.id" class="admin-btn admin-btn-ghost admin-btn-xs" title="Ver detalle">
                <span class="material-symbols-outlined text-sm">visibility</span>
              </a>
              <button v-if="!isViewer" class="admin-btn admin-btn-danger admin-btn-xs" @click="openDelete(item)" title="Eliminar">
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
    <div v-if="confirmVisible" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-md">
      <div class="admin-card-lg w-full max-w-md mx-4">
        <div class="flex items-center gap-3 mb-4">
          <span class="material-symbols-outlined text-3xl text-[#DC2626]">warning</span>
          <h3 class="text-lg font-semibold text-[#dae2fd]">Eliminar Cliente</h3>
        </div>
        <p class="text-sm text-[#94a3b8] mb-4">
          ¿Estás seguro de eliminar a <strong class="text-[#dae2fd]">{{ deleteName }}</strong>?
        </p>
        <p class="text-xs text-[#DC2626]/70 mb-6 flex items-center gap-1">
          <span class="material-symbols-outlined text-sm">info</span>
          Los datos del cliente se eliminarán permanentemente. Los pedidos existentes quedarán sin asociación.
        </p>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-secondary" @click="closeDelete">Cancelar</button>
          <button class="admin-btn admin-btn-danger" @click="confirmDelete">Eliminar</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
