<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'
import VunoIcon from './VunoIcon.vue'

const api = useApi()
const toast = useToast()

interface Review {
  id: number
  productName: string
  productSlug: string
  reviewerName: string
  rating: number
  title: string
  comment: string
  createdAt: string
  isApproved: number | boolean
}

const items = ref<Review[]>([])
const loading = ref(false)
const search = ref('')
const searchTimer = ref<ReturnType<typeof setTimeout> | null>(null)
const statusFilter = ref('')
const currentPage = ref(1)
const total = ref(0)
const perPage = 15
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

onMounted(loadReviews)

async function loadReviews() {
  loading.value = true
  try {
    const qs = new URLSearchParams()
    qs.set('limit', String(perPage))
    qs.set('offset', String((currentPage.value - 1) * perPage))
    if (statusFilter.value) qs.set('status', statusFilter.value)
    if (search.value.trim()) qs.set('search', search.value.trim())
    const data = await api.get<{ items: Review[]; total: number }>(`/api/resenas/admin-list.php?${qs}`)
    items.value = data.items || []
    total.value = data.total || items.value.length
  } catch { items.value = []; total.value = 0 } finally { loading.value = false }
}

function starsHtml(rating: number) {
  return Array(5).fill(0).map((_, i) =>
    `<span class="${i < rating ? 'text-[#B8956A]' : 'text-[#1e293b]'}"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="${i < rating ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>`
  ).join('')
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: 'numeric' })
}

function onSearchInput(val: string) {
  search.value = val
  if (searchTimer.value) clearTimeout(searchTimer.value)
  searchTimer.value = setTimeout(() => { currentPage.value = 1; loadReviews() }, 300)
}

function onStatusChange() {
  currentPage.value = 1
  loadReviews()
}

async function approve(item: Review) {
  try {
    await api.post('/api/resenas/approve.php', { id: item.id })
    toast.success('Reseña aprobada')
    await loadReviews()
  } catch {}
}

function confirmDelete(item: Review) {
  if (!(window as any).VunoModal) return
  ;(window as any).VunoModal.confirm({
    type: 'warning',
    title: 'Eliminar reseña',
    message: '¿Eliminar esta reseña?',
    confirmText: 'ELIMINAR',
    cancelText: 'CANCELAR',
    onConfirm: async () => {
      try {
        await api.post('/api/resenas/delete.php', { id: item.id })
        toast.success('Reseña eliminada')
        await loadReviews()
      } catch {}
    },
  })
}
</script>

<template>
  <div class="admin-card">
    <div class="admin-card-header">
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 w-full">
        <div class="flex-1 flex flex-wrap items-center gap-4">
          <div class="relative min-w-[200px] flex-1">
            <input :value="search" type="text" placeholder="Buscar reseñas..." class="admin-input pl-3 w-full" @input="onSearchInput(($event.target as HTMLInputElement).value)" />
          </div>
          <select v-model="statusFilter" class="admin-input w-full sm:w-auto sm:max-w-[160px]" @change="onStatusChange">
            <option value="">Todas</option>
            <option value="pending">Pendientes</option>
            <option value="approved">Aprobadas</option>
          </select>
          <span class="text-sm text-[#94a3b8] whitespace-nowrap">{{ total }} reseñas</span>
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
            <th>Producto</th>
            <th>Cliente</th>
            <th>Rating</th>
            <th>Comentario</th>
            <th>Fecha</th>
            <th>Estado</th>
            <th v-if="editMode" class="w-36 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="7" class="text-center py-8 text-[#94a3b8]">Cargando...</td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td colspan="7" class="empty-state px-6 py-10">
              <VunoIcon icon="star" :size="36" class="empty-state-icon" />
              <p class="empty-state-title">Sin reseñas</p>
              <p class="empty-state-desc">No hay reseñas de clientes.</p>
            </td>
          </tr>
          <tr v-for="item in items" :key="item.id">
            <td>
              <a :href="'/es/producto/' + item.productSlug" target="_blank" class="text-[#dae2fd] hover:underline text-sm font-medium">{{ item.productName }}</a>
            </td>
            <td class="text-sm text-[#94a3b8]">{{ item.reviewerName || 'Anónimo' }}</td>
            <td><span v-html="starsHtml(item.rating)"></span></td>
            <td class="max-w-xs">
              <p v-if="item.title" class="text-sm font-medium text-[#dae2fd]">{{ item.title }}</p>
              <p v-if="item.comment" class="text-sm text-[#94a3b8] line-clamp-2">{{ item.comment }}</p>
              <span v-else class="text-sm italic text-[#64748b]">Sin comentario</span>
            </td>
            <td class="text-sm text-[#94a3b8]">{{ formatDate(item.createdAt) }}</td>
            <td>
              <span class="badge" :class="item.isApproved ? 'badge-paid' : 'badge-pending'">{{ item.isApproved ? 'APROBADA' : 'PENDIENTE' }}</span>
            </td>
            <td v-if="editMode" class="text-right whitespace-nowrap">
              <button v-if="!item.isApproved" class="admin-btn admin-btn-ghost admin-btn-xs" @click="approve(item)" title="Aprobar">
                <VunoIcon icon="check" :size="14" />
              </button>
              <button class="admin-btn admin-btn-danger admin-btn-xs" @click="confirmDelete(item)" title="Eliminar">
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
        <VunoIcon icon="star" :size="36" class="empty-state-icon" />
        <p class="empty-state-title">Sin reseñas</p>
        <p class="empty-state-desc">No hay reseñas de clientes.</p>
      </div>
      <div v-for="item in items" :key="item.id" class="glass-card overflow-hidden rounded-xl">
        <div class="px-5 pt-4 pb-3 border-b border-[#dae2fd]/5">
          <div class="flex items-center justify-between gap-2">
            <a :href="'/es/producto/' + item.productSlug" target="_blank" class="text-[#dae2fd] hover:underline text-sm font-medium truncate">{{ item.productName }}</a>
            <span class="badge shrink-0" :class="item.isApproved ? 'badge-paid' : 'badge-pending'">{{ item.isApproved ? 'APROBADA' : 'PENDIENTE' }}</span>
          </div>
        </div>
        <div class="px-5 py-3 space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-sm text-[#94a3b8]">{{ item.reviewerName || 'Anónimo' }}</span>
            <span v-html="starsHtml(item.rating)"></span>
          </div>
          <p v-if="item.title" class="text-sm font-medium text-[#dae2fd]">{{ item.title }}</p>
          <p v-if="item.comment" class="text-sm text-[#94a3b8] line-clamp-2">{{ item.comment }}</p>
          <div class="flex items-center justify-between text-xs text-[#64748b]">
            <span>{{ formatDate(item.createdAt) }}</span>
            <div v-if="editMode" class="flex gap-1">
              <button v-if="!item.isApproved" class="admin-btn admin-btn-ghost admin-btn-xs" @click="approve(item)" title="Aprobar">
                <VunoIcon icon="check" :size="14" />
              </button>
              <button class="admin-btn admin-btn-danger admin-btn-xs" @click="confirmDelete(item)" title="Eliminar">
                <VunoIcon icon="delete" :size="14" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="totalPages > 1" class="admin-card-footer flex flex-col sm:flex-row items-center justify-between gap-3">
      <span class="text-sm text-[#94a3b8]">Página {{ currentPage }} de {{ totalPages }}</span>
      <div class="flex gap-1">
        <button class="admin-btn admin-btn-ghost admin-btn-xs" :disabled="currentPage <= 1" @click="currentPage--; loadReviews()">
          <VunoIcon icon="chevron_left" :size="14" />
        </button>
        <button v-for="p in pageWindow" :key="p" class="admin-btn admin-btn-xs" :class="p === currentPage ? 'admin-btn-primary' : 'admin-btn-ghost'" @click="currentPage = p; loadReviews()">{{ p }}</button>
        <button class="admin-btn admin-btn-ghost admin-btn-xs" :disabled="currentPage >= totalPages" @click="currentPage++; loadReviews()">
          <VunoIcon icon="chevron_right" :size="14" />
        </button>
      </div>
    </div>
  </div>
</template>
