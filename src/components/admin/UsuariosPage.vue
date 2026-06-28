<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'

const api = useApi()
const toast = useToast()

interface User {
  id: number
  email: string
  name: string
  role: string
  roleName: string
  createdAt: string
}

interface Role {
  code: string
  name: string
}

const items = ref<User[]>([])
const roles = ref<Role[]>([])
const loading = ref(true)
const currentUserEmail = ref('')
const modalVisible = ref(false)
const editingItem = ref<User | null>(null)
const editMode = ref(false)

const formEmail = ref('')
const formName = ref('')
const formPassword = ref('')
const formRole = ref('')
const saving = ref(false)

onMounted(async () => {
  try {
    const res = await api.get<{ valid?: boolean; email?: string }>('/api/admin/verify.php')
    currentUserEmail.value = (res as any).email || ''
  } catch {}
  await loadData()
})

async function loadData() {
  loading.value = true
  try {
    const data = await api.get<{ items: User[]; roles: Role[] }>('/api/admin/users.php')
    items.value = data.items || []
    roles.value = data.roles || []
    if (roles.value.length > 0) formRole.value = roles.value[0].code
  } catch { items.value = []; roles.value = [] } finally { loading.value = false }
}

function openCreate() {
  editingItem.value = null
  formEmail.value = ''
  formName.value = ''
  formPassword.value = ''
  formRole.value = roles.value[0]?.code || ''
  modalVisible.value = true
}

function openEdit(item: User) {
  editingItem.value = item
  formEmail.value = item.email
  formName.value = item.name || ''
  formPassword.value = ''
  formRole.value = item.role
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
      await api.post('/api/admin/users.php', {
        user_id: editingItem.value.id,
        email: formEmail.value,
        name: formName.value,
        password: formPassword.value,
        role: formRole.value,
      })
      toast.success('Usuario actualizado')
    } else {
      await api.post('/api/admin/users.php', {
        action: 'create',
        email: formEmail.value,
        name: formName.value,
        password: formPassword.value,
        role: formRole.value,
      })
      toast.success('Usuario creado')
    }
    closeModal()
    await loadData()
  } catch (e: any) {
    toast.error(e.message || 'Error al guardar')
  } finally { saving.value = false }
}

async function remove(item: User) {
  if (!(window as any).VunoModal) return
  const confirmed = await (window as any).VunoModal.confirm(`¿Eliminar usuario "${item.email}"?`)
  if (!confirmed) return
  try {
    await api.del(`/api/admin/users.php?id=${item.id}`)
    toast.success('Usuario eliminado')
    await loadData()
  } catch (e: any) {
    toast.error(e.message || 'Error al eliminar')
  }
}

async function changeRole(userId: number, role: string) {
  try {
    await api.post('/api/admin/users.php', { user_id: userId, role })
    toast.success('Rol actualizado')
    await loadData()
  } catch (e: any) {
    toast.error(e.message || 'Error al actualizar rol')
  }
}
</script>

<template>
  <div class="admin-card">
    <div class="admin-card-header">
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 w-full">
        <div class="flex items-center gap-3">
          <div>
            <h2 class="text-lg font-semibold text-[#dae2fd]">Usuarios</h2>
            <p class="text-sm text-[#94a3b8] mt-1">{{ items.length }} usuarios</p>
          </div>
          <span v-if="editMode" class="badge badge-paid text-xs tracking-widest font-semibold">EDITANDO</span>
        </div>
        <div class="flex gap-2">
          <button class="admin-btn admin-btn-edit" @click="editMode = !editMode">
            <span class="material-symbols-outlined text-base">{{ editMode ? 'edit_off' : 'edit' }}</span>
            {{ editMode ? 'SALIR' : 'EDITAR' }}
          </button>
          <button v-if="editMode" class="admin-btn admin-btn-primary" @click="openCreate">
            <span class="material-symbols-outlined text-base">add</span>
            Nuevo Usuario
          </button>
        </div>
      </div>
    </div>

    <!-- Desktop table -->
    <div class="overflow-x-auto hidden md:block">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Email</th>
            <th>Nombre</th>
            <th>Rol</th>
            <th>Creado</th>
            <th class="w-32 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="5" class="text-center py-8 text-[#94a3b8]">Cargando...</td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td colspan="5" class="text-center py-8 text-[#94a3b8]">No hay usuarios</td>
          </tr>
          <tr v-for="item in items" :key="item.id">
            <td class="font-medium">{{ item.email }}</td>
            <td class="text-[#94a3b8]">{{ item.name || '—' }}</td>
            <td>
              <select
                class="bg-[#1e293b] border border-[#1e293b] rounded-sm px-2 py-1 text-sm text-[#dae2fd] focus:border-[#42b883] focus:outline-none"
                :value="item.role"
                @change="(e: any) => changeRole(item.id, e.target.value)"
              >
                <option v-for="r in roles" :key="r.code" :value="r.code">{{ r.name }}</option>
              </select>
            </td>
            <td class="text-sm text-[#94a3b8]">{{ new Date(item.createdAt).toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: 'numeric' }) }}</td>
            <td class="text-right">
              <div class="flex gap-1 flex-nowrap justify-end whitespace-nowrap">
                <button v-if="editMode" class="admin-btn admin-btn-ghost admin-btn-xs" @click="openEdit(item)" title="Editar">
                  <span class="material-symbols-outlined text-sm">edit</span>
                </button>
                <button
                  v-if="editMode && item.email !== currentUserEmail"
                  class="admin-btn admin-btn-danger admin-btn-xs"
                  @click="remove(item)"
                  title="Eliminar"
                >
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
      <div v-else-if="items.length === 0" class="text-center py-8 text-[#94a3b8]">No hay usuarios</div>
      <div v-for="item in items" :key="item.id" class="glass-card overflow-hidden rounded-xl">
        <div class="px-5 pt-4 pb-3 border-b border-[#dae2fd]/5">
          <div class="flex justify-between items-start gap-2">
            <div class="min-w-0 flex-1">
              <div class="font-medium text-[#dae2fd] truncate">{{ item.email }}</div>
              <div class="text-xs text-[#94a3b8] mt-0.5">{{ item.name || '—' }}</div>
            </div>
            <div v-if="item.email === currentUserEmail" class="text-xs text-[#42b883] font-semibold tracking-widest">TÚ</div>
          </div>
        </div>
        <div class="px-5 py-3 flex items-center justify-between">
          <span class="text-xs text-[#94a3b8] font-semibold tracking-widest uppercase">ROL</span>
          <select
            class="bg-[#162240] border border-[#1e293b] rounded-sm px-2 py-1 text-xs text-[#dae2fd] focus:border-[#42b883] focus:outline-none"
            :value="item.role"
            @change="(e: any) => changeRole(item.id, e.target.value)"
          >
            <option v-for="r in roles" :key="r.code" :value="r.code">{{ r.name }}</option>
          </select>
        </div>
        <div class="px-5 py-3 flex items-center justify-between border-t border-[#dae2fd]/5">
          <span class="text-xs text-[#94a3b8]">{{ new Date(item.createdAt).toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: 'numeric' }) }}</span>
          <div class="flex gap-2">
            <button v-if="editMode" class="admin-btn admin-btn-ghost admin-btn-xs" @click="openEdit(item)">
              <span class="material-symbols-outlined text-sm">edit</span>
            </button>
            <button
              v-if="editMode && item.email !== currentUserEmail"
              class="admin-btn admin-btn-danger admin-btn-xs"
              @click="remove(item)"
            >
              <span class="material-symbols-outlined text-sm">delete</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <Teleport to="body">
    <div v-if="modalVisible" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-md">
      <div class="admin-card-lg w-full max-w-lg mx-4">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-lg font-semibold text-[#dae2fd]">{{ editingItem ? 'Editar' : 'Nuevo' }} Usuario</h3>
          <button class="admin-btn admin-btn-ghost admin-btn-xs" @click="closeModal">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-[#94a3b8] mb-1">Email *</label>
            <input v-model="formEmail" type="email" class="admin-input" required maxlength="255" />
          </div>
          <div>
            <label class="block text-sm font-medium text-[#94a3b8] mb-1">Nombre</label>
            <input v-model="formName" type="text" class="admin-input" maxlength="200" placeholder="Opcional" />
          </div>
          <div>
            <label class="block text-sm font-medium text-[#94a3b8] mb-1">
              Contraseña
              <span class="text-xs text-[#64748b] font-normal">{{ editingItem ? '(dejar vacío para mantener)' : '(mín. 6 caracteres)' }}</span>
            </label>
            <input v-model="formPassword" type="password" class="admin-input" :required="!editingItem" maxlength="255" />
          </div>
          <div>
            <label class="block text-sm font-medium text-[#94a3b8] mb-1">Rol</label>
            <select v-model="formRole" class="admin-input">
              <option v-for="r in roles" :key="r.code" :value="r.code">{{ r.name }}</option>
            </select>
          </div>
        </div>
        <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 mt-6">
          <button class="admin-btn admin-btn-secondary w-full sm:w-auto justify-center" @click="closeModal">Cancelar</button>
          <button class="admin-btn admin-btn-primary w-full sm:w-auto justify-center" :disabled="saving || !formEmail.trim()" @click="save">
            {{ saving ? 'Guardando...' : (editingItem ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
