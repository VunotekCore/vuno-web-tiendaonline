<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'
import VunoIcon from './VunoIcon.vue'

const api = useApi()
const toast = useToast()

interface Subscriber {
  id: number
  email: string
  subscribed_at: string
  is_active: number | boolean
}

const items = ref<Subscriber[]>([])
const loading = ref(false)
const search = ref('')
const searchTimer = ref<ReturnType<typeof setTimeout> | null>(null)
const currentPage = ref(1)
const total = ref(0)
const perPage = 10
const unsubId = ref<number | null>(null)
const unsubEmail = ref('')
const confirmVisible = ref(false)

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
    const data = await api.get<{ items: Subscriber[]; total: number }>(`/api/suscriptores/list.php?${qs}`)
    items.value = data.items || []
    total.value = data.total || items.value.length
  } catch { items.value = []; total.value = 0 } finally { loading.value = false }
}

function onSearchInput(val: string) {
  search.value = val
  if (searchTimer.value) clearTimeout(searchTimer.value)
  searchTimer.value = setTimeout(() => { currentPage.value = 1; loadData() }, 300)
}

function formatDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: 'numeric' })
}

function openUnsub(item: Subscriber) {
  unsubId.value = item.id
  unsubEmail.value = item.email
  confirmVisible.value = true
}

function closeUnsub() {
  confirmVisible.value = false
  unsubId.value = null
  unsubEmail.value = ''
}

async function confirmUnsub() {
  if (!unsubId.value) return
  try {
    await api.post('/api/suscriptores/unsubscribe.php', { id: unsubId.value })
    toast.success(`Suscriptor "${unsubEmail.value}" desuscrito`)
    closeUnsub()
    await loadData()
  } catch (e: any) {
    toast.error(e.message || 'Error al desuscribir')
  }
}
</script>

<template>
  <div class="admin-card">
    <div class="admin-card-header">
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 w-full">
        <div>
          <h2 class="text-lg font-semibold text-[#dae2fd]">Suscriptores</h2>
          <p class="text-sm text-[#94a3b8] mt-1">
            {{ Math.min((currentPage - 1) * perPage + items.length, total) }} de {{ total }} suscriptores
          </p>
        </div>
        <a href="/api/suscriptores/export.php" class="admin-btn admin-btn-primary w-full sm:w-auto justify-center">
          <VunoIcon icon="download" :size="16" />
          Exportar CSV
        </a>
      </div>
    </div>

    <div class="px-6 pb-4">
      <input
        :value="search"
        type="text"
        placeholder="Buscar por email..."
        class="admin-input pl-3 w-full"
        @input="onSearchInput(($event.target as HTMLInputElement).value)"
      />
    </div>

    <!-- === Desktop table === -->
    <div class="hidden md:block overflow-x-auto">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Email</th>
            <th>Fecha Suscripción</th>
            <th>Estado</th>
            <th class="w-32 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="4" class="text-center py-8 text-[#94a3b8]">Cargando...</td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td colspan="4" class="empty-state px-6 py-10">
              <VunoIcon icon="contacts" :size="36" class="empty-state-icon" />
              <p class="empty-state-title">Sin suscriptores</p>
              <p class="empty-state-desc">No hay suscriptores en la lista.</p>
            </td>
          </tr>
          <tr v-for="item in items" :key="item.id">
            <td class="font-medium">{{ item.email }}</td>
            <td class="text-sm text-[#94a3b8]">{{ formatDate(item.subscribed_at) }}</td>
            <td>
              <span v-if="item.is_active" class="badge badge-paid">ACTIVO</span>
              <span v-else class="badge badge-draft">INACTIVO</span>
            </td>
            <td class="text-right">
              <button
                v-if="item.is_active"
                class="admin-btn admin-btn-danger admin-btn-xs"
                @click="openUnsub(item)"
                title="Desuscribir"
              >
                <VunoIcon icon="block" :size="14" />
              </button>
              <span v-else class="text-sm text-[#94a3b8]">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- === Mobile cards === -->
    <div class="md:hidden px-6 pb-4 space-y-3">
      <div v-if="loading" class="text-center py-8 text-[#94a3b8]">Cargando...</div>
      <div v-else-if="items.length === 0" class="empty-state">
        <VunoIcon icon="contacts" :size="36" class="empty-state-icon" />
        <p class="empty-state-title">Sin suscriptores</p>
        <p class="empty-state-desc">No hay suscriptores en la lista.</p>
      </div>
      <div
        v-for="item in items"
        :key="item.id"
        class="glass-card overflow-hidden rounded-xl"
      >
        <div class="px-5 pt-4 pb-3 border-b border-[#dae2fd]/5">
          <div class="flex items-center justify-between gap-2">
            <span class="font-medium text-[#dae2fd] text-sm truncate">{{ item.email }}</span>
            <span v-if="item.is_active" class="badge badge-paid shrink-0">ACTIVO</span>
            <span v-else class="badge badge-draft shrink-0">INACTIVO</span>
          </div>
        </div>
        <div class="px-5 py-3 flex items-center justify-between">
          <span class="text-sm text-[#94a3b8]">{{ formatDate(item.subscribed_at) }}</span>
          <button
            v-if="item.is_active"
            class="admin-btn admin-btn-danger admin-btn-xs"
            @click="openUnsub(item)"
            title="Desuscribir"
          >
            <VunoIcon icon="block" :size="14" />
          </button>
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
            <VunoIcon icon="contact_mail" :size="32" class="text-[#B8956A]" />
            <h3 class="text-lg font-semibold text-[#dae2fd]">Desuscribir</h3>
          </div>
          <p class="text-sm text-[#94a3b8] mb-4">
            ¿Estás seguro de desuscribir a <strong class="text-[#dae2fd]">{{ unsubEmail }}</strong>?
          </p>
          <div class="flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button class="admin-btn admin-btn-secondary w-full sm:w-auto justify-center" @click="closeUnsub">Cancelar</button>
            <button class="admin-btn admin-btn-danger w-full sm:w-auto justify-center" @click="confirmUnsub">
              Desuscribir
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
