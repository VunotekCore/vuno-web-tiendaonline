<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'

export interface Column {
  key: string
  label: string
  render?: (item: any) => string
  class?: string
}

export interface FormField {
  key: string
  label: string
  type: 'text' | 'number' | 'select' | 'textarea' | 'hidden' | 'custom' | 'email' | 'datetime-local' | 'checkbox'
  required?: boolean
  maxlength?: number
  placeholder?: string
  options?: { value: string; label: string }[]
  custom?: boolean
}

export interface CrudConfig {
  title: string
  description?: string
  entityLabel: string
  entityLabelPlural: string
  apiEndpoint: string
  columns: Column[]
  formFields: FormField[]
  idKey?: string
  onFormOpen?: (item: any) => void
  onBeforeSave?: (data: any) => any
}

const props = defineProps<{ config: CrudConfig }>()

const api = useApi()
const toast = useToast()

const items = ref<any[]>([])
const loading = ref(false)
const saving = ref(false)
const search = ref('')
const currentPage = ref(1)
const total = ref(0)
const perPage = 15
const searchTimer = ref<ReturnType<typeof setTimeout> | null>(null)
const modalVisible = ref(false)
const editingItem = ref<any>(null)
const formValues = ref<Record<string, any>>({})
const originalValues = ref<Record<string, any>>({})

const idKey = computed(() => props.config.idKey || 'id')

const editMode = ref(false)

function enableEdit() { editMode.value = true }
function disableEdit() { editMode.value = false }

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage)))

const pageWindow = computed(() => {
  const total = totalPages.value
  const current = currentPage.value
  if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1)
  let start = Math.max(1, current - 2)
  let end = Math.min(total, start + 4)
  if (end - start < 4) {
    start = Math.max(1, end - 4)
  }
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})

async function loadData() {
  loading.value = true
  try {
    const qs = new URLSearchParams()
    qs.set('limit', String(perPage))
    qs.set('offset', String((currentPage.value - 1) * perPage))
    if (search.value.trim()) qs.set('search', search.value.trim())
    const data = await api.get<{ items?: any[]; total?: number }>(`/api/${props.config.apiEndpoint}/list.php?${qs}`)
    items.value = (data as any).items || (Array.isArray(data) ? data : [])
    total.value = (data as any).total || items.value.length
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingItem.value = null
  formValues.value = {}
  originalValues.value = {}
  props.config.onFormOpen?.(null)
  modalVisible.value = true
}

function openEdit(item: any) {
  editingItem.value = item
  const vals: Record<string, any> = {}
  props.config.formFields.forEach((f) => {
    vals[f.key] = item[f.key] ?? ''
  })
  formValues.value = vals
  originalValues.value = JSON.parse(JSON.stringify(vals))
  props.config.onFormOpen?.(item)
  modalVisible.value = true
}

function isDirty() {
  return JSON.stringify(formValues.value) !== JSON.stringify(originalValues.value)
}

function closeModal() {
  modalVisible.value = false
  editingItem.value = null
  formValues.value = {}
  originalValues.value = {}
}

async function save() {
  if (!isDirty()) {
    toast.info('Sin cambios para guardar')
    return
  }
  saving.value = true
  try {
    const payload = props.config.onBeforeSave
      ? props.config.onBeforeSave({ ...formValues.value })
      : { ...formValues.value }
    if (editingItem.value) {
      await api.post(`/api/${props.config.apiEndpoint}/update.php`, { id: editingItem.value[idKey.value], ...payload })
      toast.success(`${props.config.entityLabel} actualizado`)
    } else {
      await api.post(`/api/${props.config.apiEndpoint}/create.php`, payload)
      toast.success(`${props.config.entityLabel} creado`)
    }
    closeModal()
    await loadData()
  } catch (e: any) {
    toast.error(e.message || 'Error al guardar')
  } finally {
    saving.value = false
  }
}

async function remove(item: any) {
  if (!(window as any).VunoModal) return
  const confirmed = await (window as any).VunoModal.confirm(
    `¿Eliminar ${props.config.entityLabel.toLowerCase()} "${item.name || item.id}"?`
  )
  if (!confirmed) return
  try {
    await api.post(`/api/${props.config.apiEndpoint}/delete.php`, { id: item[idKey.value] })
    toast.success(`${props.config.entityLabel} eliminado`)
    await loadData()
  } catch (e: any) {
    toast.error(e.message || 'Error al eliminar')
  }
}

watch(search, () => {
  currentPage.value = 1
  if (searchTimer.value) clearTimeout(searchTimer.value)
  searchTimer.value = setTimeout(() => { loadData() }, 300)
})

loadData()
</script>

<template>
  <div class="admin-card">
    <div class="admin-card-header flex-col items-start gap-3 md:flex-row md:items-center md:justify-between">
      <div>
        <h2 class="text-lg font-semibold text-[#dae2fd]">{{ config.title }}</h2>
        <p v-if="config.description" class="text-sm text-[#94a3b8] mt-1">{{ config.description }}</p>
      </div>
      <div class="flex items-center gap-3 w-full md:w-auto">
        <button v-if="!editMode" class="admin-btn admin-btn-edit h-11 px-5 w-full md:w-auto justify-center" @click="enableEdit">
          <span class="material-symbols-outlined text-base">edit</span>
          EDITAR
        </button>
        <span v-else class="badge badge-paid shrink-0">EDITANDO</span>
        <button v-if="editMode" class="admin-btn admin-btn-primary w-full md:w-auto justify-center" @click="openCreate">
          <span class="material-symbols-outlined text-base">add</span>
          Nuevo {{ config.entityLabel }}
        </button>
      </div>
    </div>

    <div class="px-6 pb-4">
      <input
        v-model="search"
        type="text"
        :placeholder="`Buscar ${config.entityLabelPlural.toLowerCase()}...`"
        class="admin-input w-full md:max-w-xs"
      />
    </div>

    <!-- Desktop: table -->
    <div class="hidden md:block overflow-x-auto">
      <table class="admin-table">
        <thead>
          <tr>
            <th v-for="col in config.columns" :key="col.key" :class="col.class">{{ col.label }}</th>
            <th class="w-24 text-right whitespace-nowrap">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td :colspan="config.columns.length + 1" class="text-center py-8 text-[#94a3b8]">Cargando...</td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td :colspan="config.columns.length + 1" class="text-center py-8 text-[#94a3b8]">
              No hay {{ config.entityLabelPlural.toLowerCase() }}
            </td>
          </tr>
          <tr v-for="item in items" :key="item[idKey]">
            <td v-for="col in config.columns" :key="col.key" :class="col.class">
              <span v-if="col.render" v-html="col.render(item)"></span>
              <span v-else>{{ item[col.key] }}</span>
            </td>
            <td class="text-right whitespace-nowrap">
              <div v-if="editMode" class="flex gap-1 justify-end flex-nowrap">
                <button class="admin-btn admin-btn-ghost admin-btn-xs" @click="openEdit(item)" title="Editar">
                  <span class="material-symbols-outlined text-sm">edit</span>
                </button>
                <button class="admin-btn admin-btn-danger admin-btn-xs" @click="remove(item)" title="Eliminar">
                  <span class="material-symbols-outlined text-sm">delete</span>
                </button>
              </div>
              <span v-else class="text-xs text-[#94a3b8]">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Mobile: cards -->
    <div class="md:hidden px-6 pb-4">
      <div v-if="loading" class="text-center py-8 text-[#94a3b8]">Cargando...</div>
      <div v-else-if="items.length === 0" class="text-center py-8 text-[#94a3b8]">No hay {{ config.entityLabelPlural.toLowerCase() }}</div>
      <div v-else class="space-y-3">
        <div v-for="item in items" :key="item[idKey]" class="glass-card overflow-hidden rounded-xl">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-[#dae2fd]/5">
            <div class="flex flex-col gap-0.5 min-w-0">
              <span class="text-sm font-semibold text-[#dae2fd] truncate">{{ item[config.columns[0]?.key] ?? item.name ?? item.id }}</span>
              <span v-if="config.columns.length > 1 && item[config.columns[1]?.key]" class="text-xs text-[#94a3b8] truncate">{{ item[config.columns[1]?.key] }}</span>
            </div>
            <span class="text-[#42b883]/30 material-symbols-outlined text-2xl shrink-0 ml-2">inventory_2</span>
          </div>
          <!-- Body -->
          <div class="px-6 py-4 space-y-2.5">
            <div v-for="col in config.columns" :key="col.key" class="flex flex-col gap-0.5">
              <span class="text-[10px] font-semibold tracking-widest text-[#94a3b8] uppercase">{{ col.label }}</span>
              <span v-if="col.render" v-html="col.render(item)" class="text-[#dae2fd] text-sm"></span>
              <span v-else class="text-[#dae2fd] text-sm">{{ item[col.key] ?? '—' }}</span>
            </div>
          </div>
          <!-- Footer -->
          <div class="flex items-center gap-2 px-6 pb-5 pt-4 border-t border-[#dae2fd]/5">
            <button v-if="editMode" class="admin-btn admin-btn-edit flex-1 justify-center gap-1.5 py-2.5" @click="openEdit(item)">
              <span class="material-symbols-outlined text-base">edit</span>
              <span class="text-xs font-semibold">Editar</span>
            </button>
            <button v-if="editMode" class="admin-btn admin-btn-danger flex-1 justify-center gap-1.5 py-2.5" @click="remove(item)">
              <span class="material-symbols-outlined text-base">delete</span>
              <span class="text-xs font-semibold">Eliminar</span>
            </button>
            <span v-if="!editMode" class="text-xs text-[#94a3b8] w-full text-center py-2">Vista previa — Editar para modificar</span>
          </div>
        </div>
      </div>
    </div>

    <div v-if="totalPages > 1" class="admin-card-footer flex flex-col sm:flex-row items-center justify-between gap-3">
      <span class="text-sm text-[#94a3b8]">Página {{ currentPage }} de {{ totalPages }}</span>
      <div class="flex gap-1 flex-wrap justify-center">
        <button
          class="admin-btn admin-btn-ghost admin-btn-xs px-3"
          :disabled="currentPage === 1"
          @click="currentPage--; loadData()"
        >
          Anterior
        </button>
        <button
          v-for="p in pageWindow"
          :key="p"
          class="admin-btn admin-btn-xs min-w-[32px]"
          :class="p === currentPage ? 'admin-btn-primary' : 'admin-btn-ghost'"
          @click="currentPage = p; loadData()"
        >
          {{ p }}
        </button>
        <button
          class="admin-btn admin-btn-ghost admin-btn-xs px-3"
          :disabled="currentPage === totalPages"
          @click="currentPage++; loadData()"
        >
          Siguiente
        </button>
      </div>
    </div>
  </div>

  <Teleport to="body">
    <div v-if="modalVisible" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-md">
      <div class="admin-card-lg w-full max-w-lg mx-4">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-lg font-semibold text-[#dae2fd]">
            {{ editingItem ? 'Editar' : 'Nuevo' }} {{ config.entityLabel }}
          </h3>
          <button class="admin-btn admin-btn-ghost admin-btn-xs" @click="closeModal">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="space-y-4">
          <div v-for="field in config.formFields" :key="field.key">
            <div v-if="field.type === 'custom'">
              <slot :name="field.key" :item="editingItem" :values="formValues" />
            </div>
            <label v-else-if="field.type === 'checkbox'" class="flex items-center gap-3 cursor-pointer py-2">
              <input v-model="formValues[field.key]" type="checkbox" class="w-5 h-5 accent-[#42b883] cursor-pointer" :true-value="1" :false-value="0" />
              <span class="text-sm text-[#94a3b8]">{{ field.label }}</span>
            </label>
            <div v-else>
              <label class="block text-sm font-medium text-[#94a3b8] mb-1">{{ field.label }}</label>
              <input
                v-if="field.type === 'text'"
                v-model="formValues[field.key]"
                type="text"
                class="admin-input"
                :required="field.required"
                :maxlength="field.maxlength"
                :placeholder="field.placeholder"
              />
              <input
                v-else-if="field.type === 'number'"
                v-model.number="formValues[field.key]"
                type="number"
                class="admin-input"
                :required="field.required"
                :placeholder="field.placeholder"
              />
              <select
                v-else-if="field.type === 'select'"
                v-model="formValues[field.key]"
                class="admin-input"
                :required="field.required"
              >
                <option value="" disabled>Seleccionar...</option>
                <option v-for="opt in field.options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
              <textarea
                v-else-if="field.type === 'textarea'"
                v-model="formValues[field.key]"
                class="admin-input"
                rows="3"
                :required="field.required"
                :placeholder="field.placeholder"
              ></textarea>
              <input
                v-else-if="field.type === 'email'"
                v-model="formValues[field.key]"
                type="email"
                class="admin-input"
                :required="field.required"
                :maxlength="field.maxlength"
                :placeholder="field.placeholder"
              />
              <input
                v-else-if="field.type === 'datetime-local'"
                v-model="formValues[field.key]"
                type="datetime-local"
                class="admin-input"
                :required="field.required"
              />
              <input v-else-if="field.type === 'hidden'" v-model="formValues[field.key]" type="hidden" />
            </div>
          </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
          <button class="admin-btn admin-btn-secondary" @click="closeModal">Cancelar</button>
          <button class="admin-btn admin-btn-primary" :disabled="saving" @click="save">
            <span v-if="saving" class="material-symbols-outlined text-base animate-spin">progress_activity</span>
            {{ saving ? 'Guardando...' : (editingItem ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
