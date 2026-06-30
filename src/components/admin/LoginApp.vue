<script setup lang="ts">
import { ref, onMounted } from 'vue'
import VunoIcon from './VunoIcon.vue'

const step = ref<'password' | 'totp'>('password')
const email = ref('')
const password = ref('')
const totpCode = ref('')
const errorMsg = ref('')
const loading = ref(false)
const totpLoading = ref(false)
const showPassword = ref(false)

onMounted(async () => {
  try {
    const res = await fetch('/api/admin/verify.php')
    const data = await res.json()
    if (data.totpPending) {
      email.value = data.email || ''
      step.value = 'totp'
    }
  } catch {}
})

async function handlePasswordLogin() {
  errorMsg.value = ''
  loading.value = true
  try {
    const res = await fetch('/api/admin/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: email.value, password: password.value }),
    })
    const data = await res.json()
    if (!res.ok) throw new Error(data.error || 'Login failed')

    if (data.totpRequired) {
      step.value = 'totp'
      totpCode.value = ''
    } else {
      window.location.href = '/admin/productos'
    }
  } catch (err: any) {
    errorMsg.value = err.message || 'Invalid credentials'
  } finally {
    loading.value = false
  }
}

async function handleTotpVerify() {
  errorMsg.value = ''
  totpLoading.value = true
  try {
    const res = await fetch('/api/admin/2fa/verify.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code: totpCode.value.trim() }),
    })
    const data = await res.json()
    if (!res.ok) throw new Error(data.error || 'Verification failed')
    window.location.href = '/admin/productos'
  } catch (err: any) {
    errorMsg.value = err.message || 'Invalid code'
    totpCode.value = ''
  } finally {
    totpLoading.value = false
  }
}

async function backToLogin() {
  await fetch('/api/admin/logout.php')
  step.value = 'password'
  password.value = ''
}
</script>

<template>
  <div class="min-h-screen flex w-full bg-[#0a1022]">
    <!-- Left decorative panel (desktop) -->
    <div class="hidden lg:flex w-[480px] xl:w-1/2 bg-[#0b1326] relative overflow-hidden flex-col p-12 shrink-0">
      <!-- Background ornament -->
      <div class="absolute inset-0 opacity-[0.03]">
        <svg class="h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <linearGradient id="loginGrad" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#42b883" />
              <stop offset="50%" stop-color="#0b1326" />
              <stop offset="100%" stop-color="#0a1022" />
            </linearGradient>
          </defs>
          <path d="M0 0 L100 0 L100 100 L0 100 Z" fill="url(#loginGrad)" />
          <path d="M0 60 Q 25 40 50 60 T 100 60 L100 100 L0 100 Z" fill="#0b1326" />
          <path d="M0 75 Q 30 55 60 75 T 100 70 L100 100 L0 100 Z" fill="#0a1022" />
        </svg>
      </div>

      <!-- Decorative grid pattern -->
      <div class="absolute inset-0 opacity-[0.015]">
        <svg class="h-full w-full" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
          <pattern id="loginGrid" x="0" y="0" width="40" height="40" patternUnits="userSpaceOnUse">
            <rect width="40" height="40" fill="none" stroke="#dae2fd" stroke-width="0.3" />
          </pattern>
          <rect width="100%" height="100%" fill="url(#loginGrid)" />
        </svg>
      </div>

      <!-- Centering wrapper: logo + content, vertically and horizontally centered -->
      <div class="flex-1 flex flex-col items-center justify-center min-h-0 px-4">
        <!-- Logo - tight viewBox (~80% content density), CSS-sized -->
        <div class="flex justify-center w-full max-w-[380px]">
          <svg viewBox="35 115 420 250" fill="none" aria-label="VUNOTEK" class="w-full h-auto">
            <path fill="#449FD9" d="M239.5,234.1c0,10.2,0,20,0,30.5c-8.7,0-17.1,0.2-25.5-0.2c-1.3-0.1-2.8-2.4-3.6-3.9c-15.6-28.5-31.2-57.1-46.8-85.6c-8.5-15.7-17.1-31.3-26.2-47.9c6.7,0,12.8-0.1,18.9,0.1c1,0,2.1,1.7,2.7,2.9c11.6,21.3,23.1,42.6,34.6,63.9c9.5,17.5,19.1,35,28.6,52.5c0.3,0.6,0.8,1.2,2,3.1c0-7.2,0-13,0-19C227.9,233.2,233.5,234.4,239.5,234.1z"/>
            <path fill="#FFFFFF" d="M251.2,143.4c0,5.2,0,9.2,0,13.6c-4-3.4-10.6-4.7-14.7-4.4c0-8.3,0-16.6,0-25.9c6.2,0,12.3,0.3,18.4-0.1c4.6-0.3,7.3,1,9.9,5.2c22.2,34.3,44.7,68.3,67.2,102.4c0.8,1.3,1.8,2.4,3.5,4.6c0-38,0-74.7,0-111.8c6.5,0,12.5,0,18.8,0c0,45.8,0,91.4,0,137.6c-7.7,0-15.2,0.2-22.6-0.2c-1.3-0.1-2.8-1.9-3.7-3.2c-25.1-38.6-50.2-77.3-75.2-116C252.6,144.9,252.4,144.7,251.2,143.4z"/>
            <path fill="#61C3DB" d="M265,194c-0.1,16.1-13.4,29.3-29.4,29.1c-16-0.2-28.7-13.2-28.6-29.3c0.1-16.3,13.1-29.4,29.1-29.4C252.2,164.4,265.1,177.6,265,194z"/>
            <g fill="#FFFFFF">
              <path d="M72.3,350.9l-17.1-64.2h11.4l14.5,57.2h1.3L97,286.7h11.4l-17.2,64.2H72.3z"/>
              <path d="M140.8,352.2c-5.1,0-9.6-0.9-13.3-2.8c-3.7-1.9-6.5-4.6-8.5-8.1c-2-3.5-3-7.8-3-12.7v-41.9h11v42.2c0,4.3,1.2,7.6,3.6,9.9c2.4,2.3,5.8,3.5,10.2,3.5c4.5,0,7.9-1.2,10.2-3.5c2.4-2.3,3.5-5.6,3.5-9.9v-42.2h11v41.9c0,4.9-1,9.1-2.9,12.7c-2,3.5-4.8,6.3-8.5,8.1C150.4,351.2,146,352.2,140.8,352.2z"/>
              <path d="M178,350.9v-64.2h21.1l15.1,56.8h1.5v-56.8h10.9v64.2h-21l-15.1-56.8h-1.6v56.8H178z"/>
              <path d="M263.8,352.2c-8,0-14.3-2.2-19-6.7c-4.7-4.4-7.1-10.8-7.1-19.1v-15.2c0-8.3,2.4-14.7,7.1-19.1c4.7-4.4,11.1-6.6,19-6.6c8,0,14.4,2.2,19.1,6.6c4.7,4.4,7.1,10.8,7.1,19.1v15.2c0,8.3-2.4,14.7-7.1,19.1C278.2,350,271.9,352.2,263.8,352.2z M263.8,342.3c4.8,0,8.5-1.4,11.1-4.2c2.7-2.8,4-6.6,4-11.3v-16c0-4.8-1.3-8.5-4-11.3c-2.7-2.8-6.4-4.2-11.1-4.2c-4.7,0-8.4,1.4-11.1,4.2c-2.7,2.8-4,6.6-4,11.3v16c0,4.8,1.3,8.5,4,11.3C255.5,340.9,259.1,342.3,263.8,342.3z"/>
            </g>
            <g fill="#449FD9">
              <path d="M313.8,350.9v-53.2h-18.7v-11h49.5v11h-18.7v53.2H313.8z"/>
              <path d="M352.9,350.9v-64.2h41.3v11H365V313h26.6v11H365v15.9h29.7v11H352.9z"/>
              <path d="M403.7,350.9v-64.2h12.1v25.5h1.7l20.8-25.5h15.5L427,318.3l27.7,32.6h-16l-21.3-26.1h-1.7v26.1H403.7z"/>
            </g>
          </svg>
        </div>

        <!-- Content: title + features (matching logo width) -->
        <div class="flex flex-col items-center text-center w-full max-w-[380px] mt-6">
          <div class="space-y-3 w-full">
            <h2 class="text-2xl font-headline text-[#dae2fd] tracking-tight">Control total de tu negocio</h2>
            <p class="text-sm text-[#94a3b8] leading-relaxed">
              Gestiona productos, pedidos, clientes, inventario y operaciones desde un panel unificado.
            </p>
          </div>
          <div class="w-12 h-px bg-[#42b883]/20 mx-auto my-6"></div>
          <ul class="space-y-3 w-full">
            <li class="flex items-start gap-3">
              <div class="w-5 h-5 rounded-sm bg-[#42b883]/10 flex items-center justify-center shrink-0 mt-0.5">
                <VunoIcon icon="check" :size="12" class="text-[#42b883]" />
              </div>
              <span class="text-xs text-[#94a3b8] leading-relaxed">Catálogo de productos con variantes por color y talle</span>
            </li>
            <li class="flex items-start gap-3">
              <div class="w-5 h-5 rounded-sm bg-[#42b883]/10 flex items-center justify-center shrink-0 mt-0.5">
                <VunoIcon icon="check" :size="12" class="text-[#42b883]" />
              </div>
              <span class="text-xs text-[#94a3b8] leading-relaxed">Gestión de pedidos, pagos y notificaciones</span>
            </li>
            <li class="flex items-start gap-3">
              <div class="w-5 h-5 rounded-sm bg-[#42b883]/10 flex items-center justify-center shrink-0 mt-0.5">
                <VunoIcon icon="check" :size="12" class="text-[#42b883]" />
              </div>
              <span class="text-xs text-[#94a3b8] leading-relaxed">Panel POS, reseñas, blog y campañas de email</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Bottom -->
      <div class="relative z-10 w-full flex items-center justify-between">
        <div class="flex items-center gap-2">
          <VunoIcon icon="shield" :size="14" class="text-[#42b883]/60" />
          <span class="text-[11px] text-[#4a5568]">Conexión segura · SSL</span>
        </div>
        <p class="text-[11px] text-[#4a5568]">&copy; {{ new Date().getFullYear() }} VUNOTEK</p>
      </div>
    </div>

    <!-- Right panel: form -->
    <div class="flex-1 flex items-center justify-center p-6 md:p-12">
      <div class="w-full max-w-sm">
        <!-- Mobile brand -->
        <div class="lg:hidden text-center mb-10">
          <div class="inline-flex items-center justify-center gap-2 mb-2">
              <svg width="28" height="18" viewBox="100 105 280 180" fill="none" aria-hidden="true">
                <path fill="#449FD9" d="M239.5,234.1c0,10.2,0,20,0,30.5c-8.7,0-17.1,0.2-25.5-0.2c-1.3-0.1-2.8-2.4-3.6-3.9c-15.6-28.5-31.2-57.1-46.8-85.6c-8.5-15.7-17.1-31.3-26.2-47.9c6.7,0,12.8-0.1,18.9,0.1c1,0,2.1,1.7,2.7,2.9c11.6,21.3,23.1,42.6,34.6,63.9c9.5,17.5,19.1,35,28.6,52.5c0.3,0.6,0.8,1.2,2,3.1c0-7.2,0-13,0-19C227.9,233.2,233.5,234.4,239.5,234.1z"/>
                <path fill="#FFFFFF" d="M251.2,143.4c0,5.2,0,9.2,0,13.6c-4-3.4-10.6-4.7-14.7-4.4c0-8.3,0-16.6,0-25.9c6.2,0,12.3,0.3,18.4-0.1c4.6-0.3,7.3,1,9.9,5.2c22.2,34.3,44.7,68.3,67.2,102.4c0.8,1.3,1.8,2.4,3.5,4.6c0-38,0-74.7,0-111.8c6.5,0,12.5,0,18.8,0c0,45.8,0,91.4,0,137.6c-7.7,0-15.2,0.2-22.6-0.2c-1.3-0.1-2.8-1.9-3.7-3.2c-25.1-38.6-50.2-77.3-75.2-116C252.6,144.9,252.4,144.7,251.2,143.4z"/>
                <path fill="#61C3DB" d="M265,194c-0.1,16.1-13.4,29.3-29.4,29.1c-16-0.2-28.7-13.2-28.6-29.3c0.1-16.3,13.1-29.4,29.1-29.4C252.2,164.4,265.1,177.6,265,194z"/>
              </svg>
              <span class="font-headline text-lg text-[#dae2fd] tracking-tight">VUNO<span class="text-[#00A8FF]">-ECOMERCE</span></span>
          </div>
          <p class="font-label-mono text-[#42b883]">management suite</p>
        </div>

        <!-- Form card -->
        <div
          v-if="step === 'password'"
          class="glass-card overflow-hidden rounded-xl"
        >
          <!-- Header -->
          <div class="px-7 pt-7 pb-2 text-center">
            <svg width="44" height="28" viewBox="100 105 280 180" fill="none" aria-hidden="true" class="mx-auto mb-5">
              <path fill="#449FD9" d="M239.5,234.1c0,10.2,0,20,0,30.5c-8.7,0-17.1,0.2-25.5-0.2c-1.3-0.1-2.8-2.4-3.6-3.9c-15.6-28.5-31.2-57.1-46.8-85.6c-8.5-15.7-17.1-31.3-26.2-47.9c6.7,0,12.8-0.1,18.9,0.1c1,0,2.1,1.7,2.7,2.9c11.6,21.3,23.1,42.6,34.6,63.9c9.5,17.5,19.1,35,28.6,52.5c0.3,0.6,0.8,1.2,2,3.1c0-7.2,0-13,0-19C227.9,233.2,233.5,234.4,239.5,234.1z"/>
              <path fill="#FFFFFF" d="M251.2,143.4c0,5.2,0,9.2,0,13.6c-4-3.4-10.6-4.7-14.7-4.4c0-8.3,0-16.6,0-25.9c6.2,0,12.3,0.3,18.4-0.1c4.6-0.3,7.3,1,9.9,5.2c22.2,34.3,44.7,68.3,67.2,102.4c0.8,1.3,1.8,2.4,3.5,4.6c0-38,0-74.7,0-111.8c6.5,0,12.5,0,18.8,0c0,45.8,0,91.4,0,137.6c-7.7,0-15.2,0.2-22.6-0.2c-1.3-0.1-2.8-1.9-3.7-3.2c-25.1-38.6-50.2-77.3-75.2-116C252.6,144.9,252.4,144.7,251.2,143.4z"/>
              <path fill="#61C3DB" d="M265,194c-0.1,16.1-13.4,29.3-29.4,29.1c-16-0.2-28.7-13.2-28.6-29.3c0.1-16.3,13.1-29.4,29.1-29.4C252.2,164.4,265.1,177.6,265,194z"/>
            </svg>
            <h2 class="text-xl font-headline text-[#dae2fd] tracking-tight">Bienvenido a VUNO-ECOMERCE</h2>
            <p class="mt-1.5 text-sm text-[#94a3b8] max-w-[260px] mx-auto leading-relaxed">
               Accede con tu email y contraseña para gestionar tu negocio.
            </p>
          </div>

          <!-- Error banner -->
          <div class="px-7">
            <div
              v-if="errorMsg"
              class="p-3 rounded-sm bg-[#DC2626]/10 border border-[#DC2626]/20 flex items-center gap-3 text-[#DC2626] text-sm"
              role="alert"
            >
              <VunoIcon icon="warning" :size="18" class="shrink-0" />
              <span>{{ errorMsg }}</span>
            </div>
          </div>

          <!-- Form body -->
          <form class="px-7 pt-5 pb-7 space-y-5" @submit.prevent="handlePasswordLogin">
            <div class="space-y-1.5">
              <label for="login-email" class="font-label-mono text-[#94a3b8]">Correo electrónico</label>
              <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <VunoIcon icon="mail" :size="18" class="text-[#4a5568] group-focus-within:text-[#42b883] transition-colors" />
                </div>
                <input
                  id="login-email"
                  v-model="email"
                  type="email"
                  required
                  autocomplete="email"
                  placeholder="admin@vunotek.com"
                  class="w-full bg-[#1e293b]/60 border border-[#dae2fd]/10 pl-10 pr-4 py-3 text-sm text-[#dae2fd] placeholder-[#4a5568] focus:border-[#42b883] focus:outline-none focus:ring-1 focus:ring-[#42b883]/30 transition-all rounded-sm"
                  :aria-invalid="errorMsg ? 'true' : undefined"
                />
              </div>
            </div>

            <div class="space-y-1.5">
              <label for="login-password" class="font-label-mono text-[#94a3b8]">Contraseña</label>
              <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <VunoIcon icon="lock" :size="18" class="text-[#4a5568] group-focus-within:text-[#42b883] transition-colors" />
                </div>
                <input
                  id="login-password"
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  required
                  autocomplete="current-password"
                  placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;"
                  class="w-full bg-[#1e293b]/60 border border-[#dae2fd]/10 pl-10 pr-10 py-3 text-sm text-[#dae2fd] placeholder-[#4a5568] focus:border-[#42b883] focus:outline-none focus:ring-1 focus:ring-[#42b883]/30 transition-all rounded-sm"
                  :aria-invalid="errorMsg ? 'true' : undefined"
                />
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="absolute inset-y-0 right-0 pr-3 flex items-center text-[#4a5568] hover:text-[#94a3b8] transition-colors"
                  tabindex="-1"
                >
                  <VunoIcon :icon="showPassword ? 'visibility' : 'edit_off'" :size="18" />
                </button>
              </div>
            </div>

            <button
              type="submit"
              class="w-full admin-btn admin-btn-primary text-sm font-semibold tracking-wider uppercase px-6 h-12 disabled:opacity-50 disabled:cursor-not-allowed justify-center gap-2"
              :disabled="loading"
            >
              <VunoIcon v-if="loading" icon="progress_activity" :size="18" class="animate-spin" />
              <template v-else>
                Iniciar sesión
                <VunoIcon icon="arrow_forward" :size="16" />
              </template>
            </button>
          </form>
        </div>

        <!-- TOTP form -->
        <form v-else class="glass-card overflow-hidden rounded-xl px-7 py-8 space-y-6" @submit.prevent="handleTotpVerify">
          <div class="text-center space-y-3">
            <div class="w-14 h-14 rounded-sm bg-[#42b883]/10 border border-[#42b883]/20 flex items-center justify-center mx-auto">
              <VunoIcon icon="shield" :size="28" class="text-[#42b883]" />
            </div>
            <h2 class="text-xl font-headline text-[#dae2fd] tracking-tight">Verificación en dos pasos</h2>
            <p class="text-sm text-[#94a3b8] leading-relaxed max-w-[280px] mx-auto">
              Ingresa el código de seis dígitos generado por tu aplicación autenticadora o uno de tus códigos de respaldo.
            </p>
          </div>

          <div class="space-y-1.5">
            <label for="totp-code" class="font-label-mono text-[#94a3b8] text-center block">Código de verificación</label>
            <input
              id="totp-code"
              v-model="totpCode"
              type="text"
              inputmode="numeric"
              autocomplete="one-time-code"
              required
              placeholder="000000"
              maxlength="8"
              class="w-full bg-[#1e293b]/60 border border-[#dae2fd]/10 px-4 py-3 text-[#dae2fd] focus:border-[#42b883] focus:outline-none focus:ring-1 focus:ring-[#42b883]/30 transition-all rounded-sm text-center text-2xl tracking-[0.25em]"
              :aria-invalid="errorMsg ? 'true' : undefined"
            />
            <div v-if="errorMsg" class="mt-2 p-3 rounded-sm bg-[#DC2626]/10 border border-[#DC2626]/20 flex items-center gap-3 text-[#DC2626] text-sm" role="alert">
              <VunoIcon icon="warning" :size="18" class="shrink-0" />
              <span>{{ errorMsg }}</span>
            </div>
          </div>

          <button
            type="submit"
              class="w-full admin-btn admin-btn-primary text-sm font-semibold tracking-wider uppercase px-6 h-12 disabled:opacity-50 disabled:cursor-not-allowed justify-center gap-2"
              :disabled="totpLoading"
            >
            <VunoIcon v-if="totpLoading" icon="progress_activity" :size="18" class="animate-spin" />
            <template v-else>
              Verificar identidad
              <VunoIcon icon="arrow_forward" :size="16" />
            </template>
          </button>

          <button
            type="button"
            class="text-sm text-[#42b883] hover:text-[#dae2fd] transition-colors underline underline-offset-2 text-center block mx-auto"
            @click="backToLogin"
          >
            Volver al inicio de sesión
          </button>
        </form>

        <!-- Mobile/tablet footer (visible when left panel is hidden) -->
        <div class="lg:hidden flex items-center justify-between mt-8">
          <div class="flex items-center gap-2">
            <VunoIcon icon="shield" :size="14" class="text-[#42b883]/60" />
            <span class="text-[11px] text-[#4a5568]">Conexión segura · SSL</span>
          </div>
          <p class="text-[11px] text-[#4a5568]">&copy; {{ new Date().getFullYear() }} VUNOTEK</p>
        </div>
      </div>
    </div>
  </div>
</template>
