<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import VunoIcon from './VunoIcon.vue'

interface NavChild {
  href: string
  label: string
  icon?: string
  class?: string
}

interface NavItem {
  href: string
  label: string
  icon: string
  class?: string
  children?: NavChild[]
}

const props = defineProps<{
  navItems: NavItem[]
  currentPath: string
}>()

const role = ref('')
const isMobileOpen = ref(false)
const expandedParents = ref<Record<string, boolean>>({})

onMounted(async () => {
  let resolvedRole = (window as any).adminRole || ''

  if (!resolvedRole) {
    for (let i = 0; i < 20; i++) {
      await new Promise(r => setTimeout(r, 50))
      resolvedRole = (window as any).adminRole || ''
      if (resolvedRole) break
    }
  }

  if (!resolvedRole) {
    try {
      const res = await fetch('/api/admin/verify.php', { credentials: 'include' })
      const data = await res.json()
      if (!data.valid) { window.location.href = '/admin/login'; return }
      window.__csrfToken = data.csrf_token || ''
      window.adminRole = data.role
      resolvedRole = data.role
    } catch {
      window.location.href = '/admin/login'; return
    }
  }

  role.value = resolvedRole

  if (resolvedRole === 'cashier') {
    const allowed = ['/admin/pos', '/admin/pedidos', '/admin/login']
    const cur = window.location.pathname
    const ok = allowed.some((p: string) => cur === p || cur.startsWith(p + '/'))
    if (!ok) {
      window.location.href = '/admin/pos/dashboard'
      return
    }
  }

  props.navItems.forEach(item => {
    if (!item.children) return
    const expand = props.currentPath === item.href ||
      item.children.some(c => props.currentPath === c.href ||
        (c.href !== '/admin' && props.currentPath.startsWith(c.href + '/')))
    if (expand) expandedParents.value[item.href] = true
  })
})

const filteredNavItems = computed(() => {
  if (!role.value) return []

  return props.navItems.filter(item => {
    if (item.class?.includes('nav-superadmin') && role.value !== 'superadmin') return false
    if (item.class?.includes('nav-admin-hide') && role.value === 'superadmin') return false
    if (item.class?.includes('nav-cashier-hide') && role.value === 'cashier') return false

    if (role.value === 'cashier' && item.children) {
      const ok = item.children.filter(c => !c.class?.includes('nav-cashier-hide'))
      if (!ok.length) return false
    }

    return true
  })
})

function isActive(href: string): boolean {
  if (props.currentPath === href) return true
  if (href !== '/admin' && props.currentPath.startsWith(href + '/')) return true
  return false
}

function toggleSubmenu(href: string) {
  expandedParents.value = { ...expandedParents.value, [href]: !expandedParents.value[href] }
}

function handleLogout() {
  fetch('/api/admin/logout.php').then(() => {
    window.location.href = '/admin/login'
  })
}

function closeMobile() {
  isMobileOpen.value = false
}
</script>

<template>
  <!-- Mobile header -->
  <div class="fixed top-0 left-0 right-0 z-50 flex items-center justify-between h-14 px-4 bg-[#0b1326] border-b border-[#1e293b] lg:hidden">
    <a href="/admin" class="flex items-center gap-2">
      <svg width="24" height="15.5" viewBox="100 105 280 180" fill="none" aria-hidden="true" class="shrink-0">
        <path fill="#449FD9" d="M239.5,234.1c0,10.2,0,20,0,30.5c-8.7,0-17.1,0.2-25.5-0.2c-1.3-0.1-2.8-2.4-3.6-3.9c-15.6-28.5-31.2-57.1-46.8-85.6c-8.5-15.7-17.1-31.3-26.2-47.9c6.7,0,12.8-0.1,18.9,0.1c1,0,2.1,1.7,2.7,2.9c11.6,21.3,23.1,42.6,34.6,63.9c9.5,17.5,19.1,35,28.6,52.5c0.3,0.6,0.8,1.2,2,3.1c0-7.2,0-13,0-19C227.9,233.2,233.5,234.4,239.5,234.1z"/>
        <path fill="#FFFFFF" d="M251.2,143.4c0,5.2,0,9.2,0,13.6c-4-3.4-10.6-4.7-14.7-4.4c0-8.3,0-16.6,0-25.9c6.2,0,12.3,0.3,18.4-0.1c4.6-0.3,7.3,1,9.9,5.2c22.2,34.3,44.7,68.3,67.2,102.4c0.8,1.3,1.8,2.4,3.5,4.6c0-38,0-74.7,0-111.8c6.5,0,12.5,0,18.8,0c0,45.8,0,91.4,0,137.6c-7.7,0-15.2,0.2-22.6-0.2c-1.3-0.1-2.8-1.9-3.7-3.2c-25.1-38.6-50.2-77.3-75.2-116C252.6,144.9,252.4,144.7,251.2,143.4z"/>
        <path fill="#61C3DB" d="M265,194c-0.1,16.1-13.4,29.3-29.4,29.1c-16-0.2-28.7-13.2-28.6-29.3c0.1-16.3,13.1-29.4,29.1-29.4C252.2,164.4,265.1,177.6,265,194z"/>
      </svg>
      <span class="font-headline text-[15px] text-[#dae2fd] tracking-tight">VUNO<span class="text-[#00A8FF]">-ECOMERCE</span></span>
    </a>
    <button type="button" class="cursor-pointer text-[#dae2fd] p-2 -mr-2" aria-label="Menú" @click="isMobileOpen = !isMobileOpen">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <template v-if="!isMobileOpen">
          <line x1="4" y1="12" x2="20" y2="12"/>
          <line x1="4" y1="6" x2="20" y2="6"/>
          <line x1="4" y1="18" x2="20" y2="18"/>
        </template>
        <template v-else>
          <line x1="18" y1="6" x2="6" y2="18"/>
          <line x1="6" y1="6" x2="18" y2="18"/>
        </template>
      </svg>
    </button>
  </div>

  <!-- Mobile overlay -->
  <transition name="overlay-fade">
    <div v-if="isMobileOpen" class="fixed inset-0 z-40 bg-[#0b1326]/70 backdrop-blur-md lg:hidden" @click="closeMobile"></div>
  </transition>

  <!-- Sidebar -->
  <aside
    transition:persist
    class="fixed left-0 top-0 bottom-0 z-50 flex w-64 flex-col bg-[#0b1326] border-r border-[#1e293b] transition-transform duration-300 ease-in-out will-change-transform lg:translate-x-0 lg:transition-none"
    :class="isMobileOpen ? 'translate-x-0' : '-translate-x-full'"
  >
    <div class="noise-overlay opacity-[0.025]"></div>

    <!-- Logo -->
    <div class="relative z-10 px-6 py-5 border-b border-[#1e293b]">
      <div class="flex items-center justify-between">
        <a href="/admin" class="flex items-center gap-3">
          <svg width="32" height="20" viewBox="100 105 280 180" fill="none" aria-hidden="true" class="shrink-0">
            <path fill="#449FD9" d="M239.5,234.1c0,10.2,0,20,0,30.5c-8.7,0-17.1,0.2-25.5-0.2c-1.3-0.1-2.8-2.4-3.6-3.9c-15.6-28.5-31.2-57.1-46.8-85.6c-8.5-15.7-17.1-31.3-26.2-47.9c6.7,0,12.8-0.1,18.9,0.1c1,0,2.1,1.7,2.7,2.9c11.6,21.3,23.1,42.6,34.6,63.9c9.5,17.5,19.1,35,28.6,52.5c0.3,0.6,0.8,1.2,2,3.1c0-7.2,0-13,0-19C227.9,233.2,233.5,234.4,239.5,234.1z"/>
            <path fill="#FFFFFF" d="M251.2,143.4c0,5.2,0,9.2,0,13.6c-4-3.4-10.6-4.7-14.7-4.4c0-8.3,0-16.6,0-25.9c6.2,0,12.3,0.3,18.4-0.1c4.6-0.3,7.3,1,9.9,5.2c22.2,34.3,44.7,68.3,67.2,102.4c0.8,1.3,1.8,2.4,3.5,4.6c0-38,0-74.7,0-111.8c6.5,0,12.5,0,18.8,0c0,45.8,0,91.4,0,137.6c-7.7,0-15.2,0.2-22.6-0.2c-1.3-0.1-2.8-1.9-3.7-3.2c-25.1-38.6-50.2-77.3-75.2-116C252.6,144.9,252.4,144.7,251.2,143.4z"/>
            <path fill="#61C3DB" d="M265,194c-0.1,16.1-13.4,29.3-29.4,29.1c-16-0.2-28.7-13.2-28.6-29.3c0.1-16.3,13.1-29.4,29.1-29.4C252.2,164.4,265.1,177.6,265,194z"/>
          </svg>
          <span class="font-headline text-lg text-[#dae2fd] tracking-tight">VUNO<span class="text-[#00A8FF]">-ECOMERCE</span></span>
        </a>
        <button type="button" class="lg:hidden cursor-pointer text-[#94a3b8] hover:text-[#dae2fd] p-1" aria-label="Cerrar menú" @click="closeMobile">
          <VunoIcon icon="close" :size="22" />
        </button>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="relative z-10 flex-1 overflow-y-auto px-4 py-6 space-y-1 sidebar-scroll">
      <template v-for="item in filteredNavItems" :key="item.href">
        <!-- Item with children -->
        <div v-if="item.children" :class="item.class" class="relative">
          <div class="flex items-center">
            <a
              :href="item.href"
              class="group flex-1 flex items-center gap-4 px-3 py-3 font-label-caps text-label-caps rounded-sm transition-all duration-200"
              :class="isActive(item.href)
                ? 'sidebar-link-active'
                : 'text-[#94a3b8] hover:bg-white/[0.06] hover:text-[#dae2fd]'"
              @click="closeMobile"
            >
              <span class="transition-transform duration-200 group-hover:scale-110">
                <VunoIcon :icon="item.icon" :size="20" class="shrink-0" />
              </span>
              <span class="flex-1">{{ item.label }}</span>
            </a>
            <button
              type="button"
              class="flex items-center justify-center w-9 h-9 mr-1 rounded-sm text-[#94a3b8] hover:text-[#dae2fd] hover:bg-white/[0.06] transition-all duration-200 cursor-pointer"
              @click.stop="toggleSubmenu(item.href)"
              :aria-label="`Toggle ${item.label} submenu`"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="shrink-0 transition-transform duration-250"
                :class="expandedParents[item.href] ? 'rotate-180' : ''"
              >
                <polyline points="6 9 12 15 18 9"/>
              </svg>
            </button>
          </div>
          <transition name="submenu-slide">
            <div v-if="expandedParents[item.href]" class="ml-4 space-y-0.5 overflow-hidden">
              <a
                v-for="child in item.children"
                :key="child.href"
                :href="child.href"
                class="group flex items-center gap-4 px-3 py-2 font-label-caps text-label-caps rounded-sm transition-all duration-200"
                :class="isActive(child.href)
                  ? 'sidebar-link-active'
                  : 'text-[#94a3b8] hover:bg-white/[0.06] hover:text-[#dae2fd]'"
                @click="closeMobile"
              >
                <span class="transition-transform duration-200 group-hover:scale-110">
                  <VunoIcon :icon="child.icon || ''" :size="18" class="shrink-0" />
                </span>
                {{ child.label }}
              </a>
            </div>
          </transition>
        </div>

        <!-- Plain item -->
        <a
          v-else
          :href="item.href"
          class="group flex items-center gap-4 px-3 py-3 font-label-caps text-label-caps rounded-sm transition-all duration-200"
          :class="[
            item.class,
            isActive(item.href)
              ? 'sidebar-link-active'
              : 'text-[#94a3b8] hover:bg-white/[0.06] hover:text-[#dae2fd]'
          ]"
          @click="closeMobile"
        >
          <span class="transition-transform duration-200 group-hover:scale-110">
            <VunoIcon :icon="item.icon" :size="20" class="shrink-0" />
          </span>
          {{ item.label }}
        </a>
      </template>
    </nav>

    <!-- Bottom links -->
    <div class="relative z-10 px-4 py-4 border-t border-[#1e293b] space-y-1">
      <a
        href="/"
        target="_blank"
        class="flex items-center gap-4 px-3 py-3 font-label-caps text-label-caps text-[#94a3b8] hover:bg-white/[0.06] hover:text-[#dae2fd] rounded-sm transition-all duration-200 group"
      >
        <span class="transition-transform duration-200 group-hover:scale-110">
          <VunoIcon icon="open_in_new" :size="20" class="shrink-0" />
        </span>
        Sitio Público
      </a>
      <button
        type="button"
        class="w-full flex items-center gap-4 px-3 py-3 font-label-caps text-label-caps text-[#94a3b8] hover:bg-white/[0.06] hover:text-[#DC2626] rounded-sm transition-all duration-200 cursor-pointer group"
        @click="handleLogout"
      >
        <span class="transition-transform duration-200 group-hover:scale-110">
          <VunoIcon icon="logout" :size="20" class="shrink-0" />
        </span>
        Cerrar Sesión
      </button>
    </div>
  </aside>
</template>
