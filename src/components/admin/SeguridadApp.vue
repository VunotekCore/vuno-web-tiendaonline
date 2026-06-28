<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'

const api = useApi()
const toast = useToast()

interface StatusResponse {
  enabled: boolean
}

interface SetupResponse {
  secret: string
  qrUri: string
  email: string
}

interface EnableResponse {
  backupCodes: string[]
}

const loading = ref(true)
const enabled = ref(false)
const step = ref<'idle' | 'password' | 'qr' | 'backup'>('idle')

const setupPassword = ref('')
const setupPasswordMsg = ref('')
const setupPasswordError = ref(false)

const qrCode = ref('')
const secret = ref('')
const qrImage = ref('')
const qrEmail = ref('')
const qrMsg = ref('')
const qrError = ref(false)

const backupCodes = ref<string[]>([])
const verifCode = ref('')

const disablePassword = ref('')
const disableCode = ref('')
const disableMsg = ref('')
const disableError = ref(false)
const disableModal = ref(false)

const verifying = ref(false)

onMounted(loadStatus)

async function loadStatus() {
  loading.value = true
  try {
    await api.get('/api/admin/verify.php') as any
    const status = await api.get<StatusResponse>('/api/admin/2fa/status.php')
    enabled.value = status.enabled
  } catch {
    window.location.href = '/admin/login'
    return
  } finally {
    loading.value = false
  }
}

function startSetup() {
  step.value = 'password'
  setupPassword.value = ''
  setupPasswordMsg.value = ''
}

async function confirmPassword() {
  if (!setupPassword.value.trim()) {
    setupPasswordMsg.value = 'Ingresa tu contraseña'
    setupPasswordError.value = true
    return
  }
  verifying.value = true
  setupPasswordMsg.value = ''
  try {
    const data = await api.post<SetupResponse>('/api/admin/2fa/setup.php', { password: setupPassword.value })
    secret.value = data.secret || data.qrUri
    qrImage.value = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=10&data=${encodeURIComponent(data.qrUri)}`
    qrEmail.value = data.email
    step.value = 'qr'
    verifCode.value = ''
    qrMsg.value = ''
  } catch (err: any) {
    setupPasswordMsg.value = err.message
    setupPasswordError.value = true
  } finally {
    verifying.value = false
  }
}

async function verifyAndEnable() {
  const code = verifCode.value.trim()
  if (!code) {
    qrMsg.value = 'Ingresa el código de verificación'
    qrError.value = true
    return
  }
  verifying.value = true
  qrMsg.value = ''
  try {
    const data = await api.post<EnableResponse>('/api/admin/2fa/enable.php', { code })
    backupCodes.value = data.backupCodes || []
    step.value = 'backup'
  } catch (err: any) {
    qrMsg.value = err.message
    qrError.value = true
  } finally {
    verifying.value = false
  }
}

function finishSetup() {
  backupCodes.value = []
  step.value = 'idle'
  enabled.value = true
  window.location.reload()
}

function openDisable() {
  disablePassword.value = ''
  disableCode.value = ''
  disableMsg.value = ''
  disableModal.value = true
}

function closeDisable() {
  disableModal.value = false
}

async function confirmDisable() {
  if (!disablePassword.value || !disableCode.value.trim()) {
    disableMsg.value = 'Completa todos los campos'
    disableError.value = true
    return
  }
  verifying.value = true
  disableMsg.value = ''
  try {
    await api.post('/api/admin/2fa/disable.php', { password: disablePassword.value, code: disableCode.value.trim() })
    disableModal.value = false
    enabled.value = false
    window.location.reload()
  } catch (err: any) {
    disableMsg.value = err.message
    disableError.value = true
  } finally {
    verifying.value = false
  }
}
</script>

<template>
  <div v-if="loading" class="admin-card p-6">
    <div class="skeleton skeleton-title w-40 mb-4"></div>
    <div class="space-y-3"><div v-for="i in 3" :key="i" class="skeleton skeleton-text w-3/4"></div></div>
  </div>

  <div v-else class="max-w-xl space-y-8 admin-enter">
    <!-- Status card -->
    <div class="admin-card p-6">
      <h2 class="text-lg font-semibold text-[#dae2fd] mb-4 flex items-center gap-2">
        <span class="material-symbols-outlined text-xl">security</span>
        Autenticación de Dos Factores (2FA)
      </h2>
      <div class="flex items-center gap-3 mb-6">
        <span class="material-symbols-outlined text-2xl" :class="enabled ? 'text-[#42b883]' : 'text-[#94a3b8]'">{{ enabled ? 'lock' : 'lock_open' }}</span>
        <div>
          <p class="font-medium text-[#dae2fd]">{{ enabled ? '2FA está activo' : '2FA no está configurado' }}</p>
          <p class="text-sm text-[#94a3b8]">{{ enabled ? 'Se requiere código de autenticación al iniciar sesión.' : 'Protege tu cuenta con autenticación de dos factores.' }}</p>
        </div>
      </div>
      <button v-if="!enabled" class="admin-btn admin-btn-primary" @click="startSetup">
        <span class="material-symbols-outlined text-lg">add</span>
        CONFIGURAR 2FA
      </button>
      <button v-else class="admin-btn admin-btn-danger" @click="openDisable">
        <span class="material-symbols-outlined text-lg">lock_open</span>
        DESACTIVAR 2FA
      </button>
    </div>

    <!-- Setup Step 1: Password -->
    <div v-if="step === 'password'" class="admin-card p-6">
      <h3 class="text-lg font-semibold text-[#dae2fd] mb-4">Confirmar Contraseña</h3>
      <p class="text-sm text-[#94a3b8] mb-4">Re-ingresa tu contraseña para configurar 2FA.</p>
      <input v-model="setupPassword" type="password" class="admin-input mb-4" placeholder="••••••••" @keydown.enter.prevent="confirmPassword" />
      <p v-if="setupPasswordMsg" class="text-sm mb-4" :class="setupPasswordError ? 'text-[#DC2626]' : 'text-[#42b883]'">{{ setupPasswordMsg }}</p>
      <button class="admin-btn admin-btn-primary" :disabled="verifying" @click="confirmPassword">
        <span class="material-symbols-outlined text-lg" :class="{ 'animate-spin': verifying }">{{ verifying ? 'progress_activity' : 'arrow_forward' }}</span>
        {{ verifying ? 'VERIFICANDO...' : 'CONTINUAR' }}
      </button>
    </div>

    <!-- Setup Step 2: QR -->
    <div v-if="step === 'qr'" class="admin-card p-6">
      <h3 class="text-lg font-semibold text-[#dae2fd] mb-4">Escanea el Código QR</h3>
      <p class="text-sm text-[#94a3b8] mb-4">Escanea este código QR con Google Authenticator, Authy o cualquier app compatible.</p>
      <div class="flex justify-center mb-4">
        <div class="bg-[#111d2e] p-4 border border-[#1e293b] rounded-sm">
          <img :src="qrImage" alt="QR Code" class="w-48 h-48" />
        </div>
      </div>
      <p class="text-sm text-[#94a3b8] mb-1">O ingresa este código manualmente:</p>
      <p class="font-mono text-sm bg-[#1e293b] p-3 rounded-sm text-center tracking-wider mb-4 select-all cursor-pointer" @click="copyText(secret)">{{ secret }}</p>
      <p class="text-sm text-[#94a3b8] mb-4">Cuenta: <span class="text-[#dae2fd] font-medium">{{ qrEmail }}</span></p>
      <div class="border-t border-[#1e293b] pt-4">
        <label class="block text-xs font-semibold tracking-widest text-[#94a3b8] mb-2 uppercase">CÓDIGO DE VERIFICACIÓN</label>
        <input v-model="verifCode" type="text" inputmode="numeric" class="admin-input text-center text-2xl tracking-[0.25em] mb-4" placeholder="000000" maxlength="8" @keydown.enter.prevent="verifyAndEnable" />
        <p v-if="qrMsg" class="text-sm mb-4" :class="qrError ? 'text-[#DC2626]' : 'text-[#42b883]'">{{ qrMsg }}</p>
        <button class="admin-btn admin-btn-primary" :disabled="verifying" @click="verifyAndEnable">
          <span class="material-symbols-outlined text-lg" :class="{ 'animate-spin': verifying }">{{ verifying ? 'progress_activity' : 'verified' }}</span>
          {{ verifying ? 'VERIFICANDO...' : 'VERIFICAR Y ACTIVAR' }}
        </button>
      </div>
    </div>

    <!-- Setup Step 3: Backup codes -->
    <div v-if="step === 'backup'" class="admin-card p-6">
      <h3 class="text-lg font-semibold text-[#dae2fd] mb-2 flex items-center gap-2">
        <span class="material-symbols-outlined text-xl text-[#B8956A]">warning</span>
        Códigos de Respaldo
      </h3>
      <p class="text-sm text-[#94a3b8] mb-4">Guarda estos códigos en un lugar seguro. Son de un solo uso y te permitirán acceder si pierdes tu dispositivo.</p>
      <div class="bg-[#1e293b] p-4 rounded-sm mb-4 font-mono text-sm space-y-1">
        <div v-for="(c, i) in backupCodes" :key="i" class="flex items-center gap-3 py-1">
          <span class="text-[#94a3b8] w-6 text-right">{{ i + 1 }}.</span>
          <code class="text-[#dae2fd] font-bold tracking-wider">{{ c }}</code>
        </div>
      </div>
      <p class="text-sm text-[#DC2626] mb-4">Estos códigos no se mostrarán nuevamente.</p>
      <button class="admin-btn admin-btn-primary" @click="finishSetup">
        <span class="material-symbols-outlined text-lg">check</span>
        FINALIZAR
      </button>
    </div>
  </div>

  <Teleport to="body">
    <div v-if="disableModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-md" @click.self="closeDisable">
      <div class="admin-card-lg w-full max-w-md mx-4 p-6">
        <h3 class="text-lg font-semibold text-[#dae2fd] mb-4">Desactivar 2FA</h3>
        <p class="text-sm text-[#94a3b8] mb-4">Ingresa tu contraseña y un código de verificación para desactivar 2FA.</p>
        <input v-model="disablePassword" type="password" class="admin-input mb-4" placeholder="Contraseña" />
        <input v-model="disableCode" type="text" inputmode="numeric" class="admin-input text-center text-lg tracking-[0.25em] mb-4" placeholder="000000" maxlength="8" />
        <p v-if="disableMsg" class="text-sm mb-4" :class="disableError ? 'text-[#DC2626]' : 'text-[#42b883]'">{{ disableMsg }}</p>
        <div class="flex flex-col-reverse sm:flex-row justify-end gap-3">
          <button class="admin-btn admin-btn-secondary" @click="closeDisable">Cancelar</button>
          <button class="admin-btn admin-btn-danger" :disabled="verifying" @click="confirmDisable">
            <span class="material-symbols-outlined text-lg" :class="{ 'animate-spin': verifying }">{{ verifying ? 'progress_activity' : 'lock_open' }}</span>
            {{ verifying ? 'DESACTIVANDO...' : 'DESACTIVAR' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script lang="ts">
function copyText(text: string) {
  navigator.clipboard.writeText(text).catch(() => {})
}
</script>
