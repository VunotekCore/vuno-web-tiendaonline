<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useApi } from './useApi'
import VunoIcon from './VunoIcon.vue'

interface Notification {
  id: number
  type: string
  title: string
  message: string | null
  reference_type: string | null
  reference_id: string | null
  is_read: number
  created_at: string
}

const api = useApi()
const notifications = ref<Notification[]>([])
const unreadCount = ref(0)
const showDropdown = ref(false)
let pollTimer: ReturnType<typeof setInterval> | null = null

function formatTime(dateStr: string): string {
  try {
    const d = new Date(dateStr)
    const now = new Date()
    const diffMs = now.getTime() - d.getTime()
    const diffMin = Math.floor(diffMs / 60000)
    if (diffMin < 1) return 'ahora'
    if (diffMin < 60) return `hace ${diffMin} min`
    const diffH = Math.floor(diffMin / 60)
    if (diffH < 24) return `hace ${diffH}h`
    return d.toLocaleDateString('es', { day: 'numeric', month: 'short' })
  } catch {
    return ''
  }
}

async function fetchCount() {
  try {
    const data = await api.get<{ count: number }>('/api/notificaciones/count.php')
    unreadCount.value = data.count
  } catch {}
}

async function fetchList() {
  try {
    const data = await api.get<{ items: Notification[]; count: number }>('/api/notificaciones/list.php')
    notifications.value = data.items || []
    unreadCount.value = data.count ?? 0
  } catch {}
}

async function markAsRead(n: Notification) {
  if (n.reference_type === 'order' && n.reference_id) {
    fetch('/api/notificaciones/read.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: n.id }),
      credentials: 'include',
      keepalive: true,
    })
    showDropdown.value = false
    window.location.href = `/admin/pedidos/detalle?id=${n.reference_id}`
    return
  }
  try {
    await api.post('/api/notificaciones/read.php', { id: n.id })
    n.is_read = 1
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  } catch {}
}

async function markAllAsRead() {
  try {
    await api.post('/api/notificaciones/read-all.php', {})
    notifications.value = []
    unreadCount.value = 0
  } catch (e) {
    console.error('markAllAsRead failed:', e)
  }
}

function toggleDropdown() {
  showDropdown.value = !showDropdown.value
  if (showDropdown.value) {
    fetchList()
  }
}

function handleClickOutside(e: MouseEvent) {
  const target = e.target as HTMLElement
  if (!target.closest('.notification-bell-wrapper')) {
    showDropdown.value = false
  }
}

onMounted(() => {
  fetchCount()
  pollTimer = setInterval(fetchCount, 900000)
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <div class="notification-bell-wrapper relative">
    <button
      @click="toggleDropdown"
      class="relative flex items-center justify-center w-9 h-9 rounded-lg text-[#94a3b8] hover:text-[#dae2fd] hover:bg-white/5 transition-colors"
      aria-label="Notificaciones"
    >
      <VunoIcon icon="notifications" :size="22" />
      <span
        v-if="unreadCount > 0"
        class="absolute -top-0.5 -right-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-[#42b883] px-1 text-[10px] font-bold text-white leading-none"
      >{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
    </button>

    <div
      v-if="showDropdown"
      class="absolute right-0 top-full mt-2 w-80 sm:w-96 rounded-xl overflow-hidden z-50"
      style="background: rgba(11,19,38,0.96); backdrop-filter: blur(16px); border: 1px solid rgba(218,226,253,0.08); box-shadow: 0 8px 32px rgba(0,0,0,0.5);"
    >
      <div class="flex items-center justify-between px-4 py-3 border-b border-[#dae2fd]/5">
        <h3 class="text-sm font-semibold text-[#dae2fd]">Notificaciones</h3>
        <button
          v-if="unreadCount > 0"
          @click.stop="markAllAsRead"
          class="text-xs text-[#42b883] hover:text-[#42b883]/80 transition-colors"
        >Marcar todas leídas</button>
      </div>

      <div class="max-h-80 overflow-y-auto">
        <div
          v-for="n in notifications"
          :key="n.id"
          @click="markAsRead(n)"
          class="flex gap-3 px-4 py-3 border-b border-[#dae2fd]/5 cursor-pointer transition-colors"
          :class="n.is_read ? 'hover:bg-white/5' : 'bg-[#42b883]/5 hover:bg-[#42b883]/10'"
        >
          <div class="mt-1.5 flex-shrink-0 w-2 h-2 rounded-full" :class="n.is_read ? 'bg-[#dae2fd]/20' : 'bg-[#42b883]'"></div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-[#dae2fd] truncate">{{ n.title }}</p>
            <p v-if="n.message" class="text-xs text-[#94a3b8] mt-0.5 truncate">{{ n.message }}</p>
            <p class="text-[10px] text-[#94a3b8]/50 mt-1">{{ formatTime(n.created_at) }}</p>
          </div>
        </div>
        <div v-if="!notifications.length" class="px-4 py-10 text-center text-sm text-[#94a3b8]">
          Sin notificaciones
        </div>
      </div>
    </div>
  </div>
</template>
