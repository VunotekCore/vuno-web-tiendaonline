<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'

const api = useApi()
const toast = useToast()

interface Product {
  id: number
  name: string
  price: number
  display_price: number
  display_symbol: string
  category: string | null
  totalStock: number
}

const items = ref<Product[]>([])
const categories = ref<{ name: string }[]>([])
const loading = ref(false)
const search = ref('')
const categoryFilter = ref('')
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

onMounted(async () => {
  await loadCategories()
  await loadData()
})

async function loadCategories() {
  try {
    const data = await api.get<{ items: { name: string }[] }>('/api/categorias/list.php')
    categories.value = data.items || []
  } catch {
    categories.value = []
  }
}

async function loadData() {
  loading.value = true
  try {
    const qs = new URLSearchParams()
    qs.set('limit', String(perPage))
    qs.set('offset', String((currentPage.value - 1) * perPage))
    if (search.value.trim()) qs.set('search', search.value.trim())
    if (categoryFilter.value) qs.set('category', categoryFilter.value)
    const data = await api.get<{ items: Product[]; total: number }>(`/api/productos/list.php?${qs}`)
    items.value = data.items || []
    total.value = data.total || items.value.length
  } catch { items.value = []; total.value = 0 } finally { loading.value = false }
}

function onSearchInput() {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => { currentPage.value = 1; loadData() }, 300)
}

function onCategoryChange() {
  currentPage.value = 1
  loadData()
}

function formatPrice(val: number | null | undefined, symbol = '$'): string {
  if (val == null || isNaN(val)) val = 0
  return symbol + Number(val).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function stockBadge(stock: number) {
  return stock === 0 ? 'badge-out' : 'badge-in'
}

function openDelete(item: Product) {
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
    await api.post('/api/productos/delete.php', { id: deleteId.value })
    toast.success('Producto eliminado')
    closeDelete()
    currentPage.value = 1
    await loadData()
  } catch (e: any) {
    toast.error(e.message || 'Error al eliminar')
  }
}
</script>

<template>
  <div class="admin-card">
    <div class="admin-card-header">
      <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 w-full">
        <div class="flex flex-wrap items-center gap-4">
          <div class="relative max-w-xs">
            <span class="material-symbols-outlined absolute left-4 inset-y-0 flex items-center text-lg text-[#94a3b8] pointer-events-none">search</span>
            <input v-model="search" type="text" placeholder="Buscar productos..." class="admin-input pl-12 w-full" @input="onSearchInput" />
          </div>
          <select v-model="categoryFilter" class="admin-input max-w-[180px]" @change="onCategoryChange">
            <option value="">Todas las categorías</option>
            <option v-for="c in categories" :key="c.name" :value="c.name">{{ c.name }}</option>
          </select>
          <span class="text-sm text-[#94a3b8] whitespace-nowrap">{{ total }} producto{{ total !== 1 ? 's' : '' }}</span>
        </div>
        <a v-if="!isViewer" href="/admin/productos/nuevo" class="admin-btn admin-btn-primary shrink-0">
          <span class="material-symbols-outlined text-lg">add</span>
          NUEVO PRODUCTO
        </a>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Producto</th>
            <th>Precio</th>
            <th>Categoría</th>
            <th>Stock</th>
            <th class="w-40 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="5" class="text-center py-8 text-[#94a3b8]">Cargando...</td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td colspan="5" class="text-center py-8 text-[#94a3b8]">No se encontraron productos</td>
          </tr>
          <tr v-for="p in items" :key="p.id">
            <td class="font-medium text-[#dae2fd]">{{ p.name }}</td>
            <td>{{ formatPrice(p.display_price ?? p.price, p.display_symbol) }}</td>
            <td class="text-[#94a3b8]">{{ p.category || '—' }}</td>
            <td>
              <span class="badge" :class="stockBadge(p.totalStock)">{{ p.totalStock === 0 ? 'AGOTADO' : p.totalStock + ' uds.' }}</span>
            </td>
            <td class="text-right">
              <template v-if="isViewer">
                <span class="text-sm text-[#94a3b8] italic">Solo lectura</span>
              </template>
              <template v-else>
                <a :href="'/admin/productos/editar?id=' + encodeURIComponent(p.id)" class="admin-btn admin-btn-ghost admin-btn-xs">
                  <span class="material-symbols-outlined text-sm">edit</span>
                  EDIT
                </a>
                <button class="admin-btn admin-btn-danger admin-btn-xs" @click="openDelete(p)">
                  <span class="material-symbols-outlined text-sm">delete</span>
                  DELETE
                </button>
              </template>
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
          <h3 class="text-lg font-semibold text-[#dae2fd]">Eliminar Producto</h3>
        </div>
        <p class="text-sm text-[#94a3b8] mb-4">
          ¿Estás seguro de eliminar <strong class="text-[#dae2fd]">{{ deleteName }}</strong>?
        </p>
        <p class="text-xs text-[#DC2626]/70 mb-6 flex items-center gap-1">
          <span class="material-symbols-outlined text-sm">info</span>
          Esta acción no se puede deshacer.
        </p>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-secondary" @click="closeDelete">Cancelar</button>
          <button class="admin-btn admin-btn-danger" @click="confirmDelete">Eliminar</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
