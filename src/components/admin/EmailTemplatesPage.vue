<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'

const api = useApi()
const toast = useToast()

interface Template {
  id: number
  code: string
  name: string
  subject: string
  is_active: number | boolean
}

const items = ref<Template[]>([])
const loading = ref(false)
const search = ref('')
const currentPage = ref(1)
const total = ref(0)
const perPage = 10
const deleteId = ref<number | null>(null)
const deleteName = ref('')
const confirmVisible = ref(false)
const editMode = ref(false)
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

onMounted(loadData)

async function loadData() {
  loading.value = true
  try {
    const qs = new URLSearchParams()
    qs.set('page', String(currentPage.value))
    qs.set('limit', String(perPage))
    if (search.value.trim()) qs.set('search', search.value.trim())
    const data = await api.get<{ items: Template[]; total: number; pages: number }>(`/api/email-templates/list.php?${qs}`)
    items.value = data.items || []
    total.value = data.total || items.value.length
  } catch { items.value = []; total.value = 0 } finally { loading.value = false }
}

function onSearchInput() {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => { currentPage.value = 1; loadData() }, 300)
}

function openDelete(item: Template) {
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
    await api.post('/api/email-templates/delete.php', { id: deleteId.value })
    toast.success('Plantilla eliminada')
    closeDelete()
    await loadData()
  } catch (e: any) {
    toast.error(e.message || 'Error al eliminar')
  }
}

async function reseed() {
  if (!(window as any).VunoModal) return
  ;(window as any).VunoModal.confirm({
    type: 'warning',
    title: 'Reseed Plantillas',
    message: '¿Restaurar plantillas desde archivos originales? Las plantillas existentes serán actualizadas.',
    confirmText: 'RESEED',
    cancelText: 'CANCELAR',
    onConfirm: async () => {
      try {
        const data = await api.post<{ seeded: number; updated: number; errors: string[] }>('/api/email-templates/seed.php', {})
        ;(window as any).VunoModal.alert({
          type: 'success',
          title: 'Reseed completado',
          message: `${data.seeded} nuevas, ${data.updated} actualizadas${data.errors?.length ? '. Errores: ' + data.errors.join(', ') : ''}`,
        })
        await loadData()
      } catch (e: any) {
        ;(window as any).VunoModal.alert({ type: 'error', title: 'Error al reseed', message: e.message })
      }
    },
  })
}
</script>

<template>
  <div class="admin-card">
    <div class="admin-card-header">
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 w-full">
        <div class="flex flex-wrap items-center gap-4">
          <div class="relative w-full md:max-w-xs">
            <span class="material-symbols-outlined absolute left-4 inset-y-0 flex items-center text-lg text-[#94a3b8] pointer-events-none">search</span>
            <input v-model="search" type="text" placeholder="Buscar plantillas..." class="admin-input pl-12 w-full" @input="onSearchInput" />
          </div>
          <span class="text-sm text-[#94a3b8] whitespace-nowrap">{{ total }} plantillas</span>
          <span v-if="editMode" class="badge badge-paid text-xs tracking-widest font-semibold">EDITANDO</span>
        </div>
        <div class="flex gap-2">
          <button class="admin-btn admin-btn-edit" @click="editMode = !editMode">
            <span class="material-symbols-outlined text-base">{{ editMode ? 'edit_off' : 'edit' }}</span>
            {{ editMode ? 'SALIR' : 'EDITAR' }}
          </button>
          <button class="admin-btn admin-btn-secondary" @click="reseed">
            <span class="material-symbols-outlined text-base">refresh</span>
            RESEED
          </button>
          <a v-if="editMode" href="/admin/email-templates/nuevo" class="admin-btn admin-btn-primary">
            <span class="material-symbols-outlined text-base">add</span>
            NUEVA PLANTILLA
          </a>
        </div>
      </div>
    </div>

    <!-- Desktop table -->
    <div class="overflow-x-auto hidden md:block">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Asunto</th>
            <th>Estado</th>
            <th class="w-32 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="5" class="text-center py-8 text-[#94a3b8]">Cargando...</td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td colspan="5" class="text-center py-8 text-[#94a3b8]">No hay plantillas</td>
          </tr>
          <tr v-for="item in items" :key="item.id">
            <td class="font-mono text-xs text-[#B8956A] font-medium">{{ item.code || '—' }}</td>
            <td class="font-medium max-w-xs truncate">{{ item.name || 'Untitled' }}</td>
            <td class="text-[#94a3b8] max-w-sm truncate">{{ item.subject || '—' }}</td>
            <td>
              <span class="badge" :class="item.is_active ? 'badge-paid' : 'badge-draft'">{{ item.is_active ? 'ACTIVA' : 'INACTIVA' }}</span>
            </td>
            <td class="text-right">
              <div class="flex gap-1 flex-nowrap justify-end whitespace-nowrap">
                <a v-if="editMode" :href="'/admin/email-templates/editar?id=' + item.id" class="admin-btn admin-btn-ghost admin-btn-xs" title="Editar">
                  <span class="material-symbols-outlined text-sm">edit</span>
                </a>
                <button v-if="editMode" class="admin-btn admin-btn-danger admin-btn-xs" @click="openDelete(item)" title="Eliminar">
                  <span class="material-symbols-outlined text-sm">delete</span>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Mobile cards -->
    <div class="md:hidden space-y-3 px-6 pb-4">
      <div v-if="loading" class="text-center py-8 text-[#94a3b8]">Cargando...</div>
      <div v-else-if="items.length === 0" class="text-center py-8 text-[#94a3b8]">No hay plantillas</div>
      <div v-for="item in items" :key="item.id" class="glass-card overflow-hidden rounded-xl">
        <div class="px-5 pt-4 pb-3 border-b border-[#dae2fd]/5">
          <div class="flex justify-between items-start gap-2">
            <div class="min-w-0 flex-1">
              <div class="font-medium text-[#dae2fd] truncate">{{ item.name || 'Untitled' }}</div>
              <div class="text-xs text-[#B8956A] font-mono mt-0.5">{{ item.code || '—' }}</div>
            </div>
            <span class="badge shrink-0" :class="item.is_active ? 'badge-paid' : 'badge-draft'">{{ item.is_active ? 'ACTIVA' : 'INACTIVA' }}</span>
          </div>
        </div>
        <div class="px-5 py-3">
          <div class="text-xs text-[#94a3b8] truncate">{{ item.subject || '—' }}</div>
        </div>
        <div class="px-5 py-3 border-t border-[#dae2fd]/5 flex gap-2">
          <a v-if="editMode" :href="'/admin/email-templates/editar?id=' + item.id" class="admin-btn admin-btn-ghost admin-btn-xs flex-1 justify-center">
            <span class="material-symbols-outlined text-sm">edit</span>
            Editar
          </a>
          <button v-if="editMode" class="admin-btn admin-btn-danger admin-btn-xs flex-1 justify-center" @click="openDelete(item)">
            <span class="material-symbols-outlined text-sm">delete</span>
            Eliminar
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
    <div v-if="confirmVisible" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-md">
      <div class="admin-card-lg w-full max-w-md mx-4">
        <div class="flex items-center gap-3 mb-4">
          <span class="material-symbols-outlined text-3xl text-[#DC2626]">warning</span>
          <h3 class="text-lg font-semibold text-[#dae2fd]">Eliminar Plantilla</h3>
        </div>
        <p class="text-sm text-[#94a3b8] mb-4">
          ¿Estás seguro de eliminar <strong class="text-[#dae2fd]">{{ deleteName }}</strong>?
        </p>
        <p class="text-xs text-[#DC2626]/70 mb-6 flex items-center gap-1">
          <span class="material-symbols-outlined text-sm">info</span>
          Esta acción no se puede deshacer.
        </p>
        <div class="flex flex-col-reverse sm:flex-row justify-end gap-3">
          <button class="admin-btn admin-btn-secondary w-full sm:w-auto justify-center" @click="closeDelete">Cancelar</button>
          <button class="admin-btn admin-btn-danger w-full sm:w-auto justify-center" @click="confirmDelete">Eliminar</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
