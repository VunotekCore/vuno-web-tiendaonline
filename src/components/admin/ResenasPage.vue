<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApi } from './useApi'

const api = useApi()

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
const statusFilter = ref('')
const currentOffset = ref(0)
const total = ref(0)
const perPage = 15
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const totalPages = () => Math.max(1, Math.ceil(total.value / perPage))
const currentPage = () => Math.floor(currentOffset.value / perPage) + 1

onMounted(loadReviews)

async function loadReviews() {
  loading.value = true
  try {
    const qs = new URLSearchParams()
    qs.set('limit', String(perPage))
    qs.set('offset', String(currentOffset.value))
    if (statusFilter.value) qs.set('status', statusFilter.value)
    if (search.value.trim()) qs.set('search', search.value.trim())
    const data = await api.get<{ items: Review[]; total: number }>(`/api/resenas/admin-list.php?${qs}`)
    items.value = data.items || []
    total.value = data.total || items.value.length
  } catch { items.value = []; total.value = 0 } finally { loading.value = false }
}

function starsHtml(rating: number) {
  return Array(5).fill(0).map((_, i) =>
    `<span class="material-symbols-outlined text-sm ${i < rating ? 'text-[#B8956A]' : 'text-[#1e293b]'}" style="font-variation-settings: 'FILL' ${i < rating ? 1 : 0}">star</span>`
  ).join('')
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: 'numeric' })
}

function filterReviews() {
  currentOffset.value = 0
  loadReviews()
}

function onSearchInput() {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(filterReviews, 300)
}

function goToPage(page: number) {
  currentOffset.value = (page - 1) * perPage
  loadReviews()
}

async function approve(item: Review) {
  try {
    await api.post('/api/resenas/approve.php', { id: item.id })
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
        await loadReviews()
      } catch {}
    },
  })
}
</script>

<template>
  <div class="admin-card">
    <div class="admin-card-header">
      <div class="flex flex-wrap items-center gap-4">
        <select v-model="statusFilter" class="admin-input max-w-[160px]" @change="filterReviews">
          <option value="">Todas</option>
          <option value="pending">Pendientes</option>
          <option value="approved">Aprobadas</option>
        </select>
        <div class="relative max-w-xs">
          <span class="material-symbols-outlined absolute left-4 inset-y-0 flex items-center text-lg text-[#94a3b8] pointer-events-none">search</span>
          <input v-model="search" type="text" placeholder="Buscar reseñas..." class="admin-input pl-12 w-full" @input="onSearchInput" />
        </div>
        <span class="text-sm text-[#94a3b8] whitespace-nowrap">{{ total }} reseñas</span>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Producto</th>
            <th>Cliente</th>
            <th>Rating</th>
            <th>Comentario</th>
            <th>Fecha</th>
            <th>Estado</th>
            <th class="w-36 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="7" class="text-center py-8 text-[#94a3b8]">Cargando...</td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td colspan="7" class="text-center py-8 text-[#94a3b8]">No hay reseñas</td>
          </tr>
          <tr v-for="item in items" :key="item.id">
            <td>
              <a :href="'/producto/' + item.productSlug" target="_blank" class="text-[#dae2fd] hover:underline text-sm font-medium">{{ item.productName }}</a>
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
            <td class="text-right">
              <button v-if="!item.isApproved" class="admin-btn admin-btn-ghost admin-btn-xs" @click="approve(item)" title="Aprobar">
                <span class="material-symbols-outlined text-sm">check</span>
              </button>
              <button class="admin-btn admin-btn-danger admin-btn-xs" @click="confirmDelete(item)" title="Eliminar">
                <span class="material-symbols-outlined text-sm">delete</span>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="totalPages() > 1" class="admin-card-footer flex items-center justify-center gap-2">
      <button class="admin-btn admin-btn-ghost admin-btn-xs" :disabled="currentPage() <= 1" @click="goToPage(currentPage() - 1)">
        <span class="material-symbols-outlined text-sm">chevron_left</span>
      </button>
      <button v-for="p in totalPages()" :key="p" class="admin-btn admin-btn-xs" :class="p === currentPage() ? 'admin-btn-primary' : 'admin-btn-ghost'" @click="goToPage(p)">{{ p }}</button>
      <button class="admin-btn admin-btn-ghost admin-btn-xs" :disabled="currentPage() >= totalPages()" @click="goToPage(currentPage() + 1)">
        <span class="material-symbols-outlined text-sm">chevron_right</span>
      </button>
    </div>
  </div>
</template>
