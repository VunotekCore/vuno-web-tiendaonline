<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'
import VunoIcon from './VunoIcon.vue'

const api = useApi()
const toast = useToast()

interface BlogCategory {
  id: number
  name: string
  slug: string
  description: string
}

const items = ref<BlogCategory[]>([])
const loading = ref(true)
const search = ref('')
const searchTimer = ref<ReturnType<typeof setTimeout> | null>(null)
const modalVisible = ref(false)
const editingItem = ref<BlogCategory | null>(null)
const formName = ref('')
const formDescription = ref('')
const saving = ref(false)
const editMode = ref(false)

const filteredItems = ref<BlogCategory[]>([])

onMounted(loadData)

async function loadData() {
  loading.value = true
  try {
    const data = await api.get<BlogCategory[]>('/api/blog/categories.php')
    items.value = Array.isArray(data) ? data : []
    applyFilter()
  } catch { items.value = [] } finally { loading.value = false }
}

function applyFilter() {
  const q = search.value.toLowerCase()
  filteredItems.value = q
    ? items.value.filter(c => c.name.toLowerCase().includes(q) || c.slug.toLowerCase().includes(q))
    : items.value
}

function onSearchInput(val: string) {
  search.value = val
  if (searchTimer.value) clearTimeout(searchTimer.value)
  searchTimer.value = setTimeout(applyFilter, 300)
}

function openCreate() {
  editingItem.value = null
  formName.value = ''
  formDescription.value = ''
  modalVisible.value = true
}

function openEdit(item: BlogCategory) {
  editingItem.value = item
  formName.value = item.name
  formDescription.value = item.description || ''
  modalVisible.value = true
}

function closeModal() {
  modalVisible.value = false
  editingItem.value = null
}

async function save() {
  saving.value = true
  try {
    if (editingItem.value) {
      await api.post('/api/blog/categories/update.php', { id: editingItem.value.id, name: formName.value })
      toast.success('Categoría actualizada')
    } else {
      await api.post('/api/blog/categories/create.php', { name: formName.value, description: formDescription.value })
      toast.success('Categoría creada')
    }
    closeModal()
    await loadData()
  } catch (e: any) {
    toast.error(e.message || 'Error al guardar')
  } finally { saving.value = false }
}

async function remove(item: BlogCategory) {
  if (!(window as any).VunoModal) return
  const confirmed = await (window as any).VunoModal.confirm(`¿Eliminar categoría "${item.name}"?`)
  if (!confirmed) return
  try {
    await api.post('/api/blog/categories/delete.php', { id: item.id })
    toast.success('Categoría eliminada')
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
        <div>
          <h2 class="text-lg font-semibold text-[#dae2fd]">Categorías del Blog</h2>
          <p class="text-sm text-[#94a3b8] mt-1">Gestiona las categorías de los posts</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button class="admin-btn admin-btn-edit w-full sm:w-auto justify-center" @click="editMode = !editMode">
            <VunoIcon :icon="editMode ? 'edit_off' : 'edit'" :size="16" />
            {{ editMode ? 'SALIR' : 'EDITAR' }}
          </button>
          <button v-if="editMode" class="admin-btn admin-btn-primary w-full sm:w-auto justify-center" @click="openCreate">
            <VunoIcon icon="add" :size="16" />
            Nueva Categoría
          </button>
        </div>
      </div>
    </div>
    <div class="px-6 pb-4">
      <input :value="search" type="text" placeholder="Buscar categorías..." class="admin-input pl-3 w-full" @input="onSearchInput(($event.target as HTMLInputElement).value)" />
    </div>

    <!-- Desktop table -->
    <div class="hidden md:block overflow-x-auto">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Slug</th>
            <th>Descripción</th>
            <th v-if="editMode" class="w-24 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="4" class="text-center py-8 text-[#94a3b8]">Cargando...</td>
          </tr>
          <tr v-else-if="filteredItems.length === 0">
            <td colspan="4" class="empty-state px-6 py-10">
              <VunoIcon icon="folder" :size="36" class="empty-state-icon" />
              <p class="empty-state-title">Sin categorías</p>
              <p class="empty-state-desc">No hay categorías aún. Crea la primera.</p>
            </td>
          </tr>
          <tr v-for="item in filteredItems" :key="item.id">
            <td class="font-medium">{{ item.name }}</td>
            <td class="font-mono text-xs text-[#94a3b8]">{{ item.slug }}</td>
            <td class="text-[#94a3b8]">{{ item.description || '—' }}</td>
            <td v-if="editMode" class="text-right whitespace-nowrap">
              <button class="admin-btn admin-btn-ghost admin-btn-xs" @click="openEdit(item)" title="Editar">
                <VunoIcon icon="edit" :size="14" />
              </button>
              <button class="admin-btn admin-btn-danger admin-btn-xs" @click="remove(item)" title="Eliminar">
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
      <div v-else-if="filteredItems.length === 0" class="empty-state">
        <VunoIcon icon="folder" :size="36" class="empty-state-icon" />
        <p class="empty-state-title">Sin categorías</p>
        <p class="empty-state-desc">No hay categorías aún. Crea la primera.</p>
      </div>
      <div v-for="item in filteredItems" :key="item.id" class="glass-card overflow-hidden rounded-xl">
        <div class="px-5 pt-4 pb-3 border-b border-[#dae2fd]/5">
          <div class="flex items-center justify-between gap-2">
            <span class="font-medium text-[#dae2fd] text-sm truncate">{{ item.name }}</span>
            <span class="font-mono text-xs text-[#94a3b8] shrink-0">{{ item.slug }}</span>
          </div>
        </div>
        <div class="px-5 py-3 flex items-center justify-between">
          <span class="text-sm text-[#94a3b8] truncate">{{ item.description || '—' }}</span>
          <div v-if="editMode" class="flex gap-1 shrink-0">
            <button class="admin-btn admin-btn-ghost admin-btn-xs" @click="openEdit(item)" title="Editar">
              <VunoIcon icon="edit" :size="14" />
            </button>
            <button class="admin-btn admin-btn-danger admin-btn-xs" @click="remove(item)" title="Eliminar">
              <VunoIcon icon="delete" :size="14" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <Teleport to="body">
    <Transition name="modal-slide">
      <div v-if="modalVisible" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-md p-4">
        <div class="admin-card-lg w-full max-w-lg mx-4">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-[#dae2fd]">{{ editingItem ? 'Editar' : 'Nueva' }} Categoría</h3>
            <button class="admin-btn admin-btn-ghost admin-btn-xs" @click="closeModal">
              <VunoIcon icon="close" :size="20" />
            </button>
          </div>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-[#94a3b8] mb-1">Nombre</label>
              <input v-model="formName" type="text" class="admin-input" required maxlength="200" />
            </div>
            <div v-if="!editingItem">
              <label class="block text-sm font-medium text-[#94a3b8] mb-1">Descripción</label>
              <input v-model="formDescription" type="text" class="admin-input" maxlength="300" placeholder="Opcional" />
            </div>
          </div>
          <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 mt-6">
            <button class="admin-btn admin-btn-secondary w-full sm:w-auto justify-center" @click="closeModal">Cancelar</button>
            <button class="admin-btn admin-btn-primary w-full sm:w-auto justify-center" :disabled="saving || !formName.trim()" @click="save">
              {{ saving ? 'Guardando...' : (editingItem ? 'Actualizar' : 'Crear') }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
