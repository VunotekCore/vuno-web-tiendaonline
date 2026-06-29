<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'
import VunoIcon from './VunoIcon.vue'

interface Template {
  id: number
  code: string
  name: string
  subject: string
  body_html?: string
  is_active: boolean
}

interface SubscriberListData {
  total: number
}

interface SendResponse {
  success: boolean
  error?: string
  sent?: number
  failed?: number
  total?: number
  done?: boolean
  next_offset?: number
  errors?: Array<{ email: string; error: string }>
}

const BATCH_SIZE = 20
const api = useApi()
const toast = useToast()

const templates = ref<Template[]>([])
const selectedTemplate = ref<Template | null>(null)
const subjectOverride = ref('')
const subjectUserEdited = ref(false)
const totalSubscribers = ref(0)
const isSending = ref(false)

const previewHtml = ref('')
const showPreview = ref(false)

const confirmModal = ref(false)
const confirmTemplateName = ref('')
const confirmCount = ref(0)

const progressModal = ref(false)
const progressTitle = ref('Enviando campaña...')
const progressText = ref('0 / 0 enviados')
const progressPct = ref(0)
const progressErrors = ref<string[]>([])

const resultModal = ref(false)
const resultIcon = ref('check_circle')
const resultIconClass = ref('text-[#42b883]')
const resultTitle = ref('')
const resultText = ref('')

onMounted(() => {
  loadTemplates()
  loadSubscriberCount()
})

async function loadTemplates() {
  try {
    const data = await api.get<{ items: Template[] }>('/api/email-templates/list.php?limit=50')
    templates.value = data.items || []
  } catch {
    templates.value = []
  }
}

async function loadSubscriberCount() {
  try {
    const data = await api.get<SubscriberListData>('/api/suscriptores/list.php?limit=1&page=1&is_active=1')
    totalSubscribers.value = data.total || 0
  } catch {
    totalSubscribers.value = 0
  }
}

function onTemplateChange(event: Event) {
  const id = parseInt((event.target as HTMLSelectElement).value)
  selectedTemplate.value = templates.value.find(t => t.id === id) || null
  if (selectedTemplate.value) {
    loadPreview(selectedTemplate.value.code)
    confirmTemplateName.value = selectedTemplate.value.name
  } else {
    showPreview.value = false
    previewHtml.value = ''
  }
}

async function loadPreview(code: string) {
  if (!code) {
    showPreview.value = false
    return
  }
  try {
    const data = await api.post<{ success: boolean; body_html?: string; error?: string }>('/api/email-templates/preview.php', { code })
    if (!data.success) {
      previewHtml.value = `<p style="padding:20px;color:#c00">Preview failed: ${data.error || 'Unknown'}</p>`
    } else {
      previewHtml.value = data.body_html || ''
    }
    showPreview.value = true
  } catch (err: any) {
    previewHtml.value = `<p style="padding:20px;color:#c00">Error: ${err.message}</p>`
    showPreview.value = true
  }
}

async function sendTest() {
  if (!selectedTemplate.value) return
  try {
    const verifyRes = await api.get<{ email?: string }>('/api/admin/verify.php') as any
    const adminEmail = verifyRes.email
    if (!adminEmail) {
      toast.error('No se pudo obtener el email del admin. Re-inicia sesión.')
      return
    }
    const data = await api.post<{ success: boolean; error?: string }>('/api/newsletter/send-campaign.php', {
      template_id: selectedTemplate.value.id,
      subject_override: subjectOverride.value,
      test_email: adminEmail,
    })
    if (data.success) {
      toast.success('Prueba enviada a ' + adminEmail)
    } else {
      toast.error(data.error || 'Error al enviar prueba')
    }
  } catch (err: any) {
    toast.error('Error de red: ' + err.message)
  }
}

function openConfirm() {
  confirmModal.value = true
  confirmCount.value = totalSubscribers.value
}

async function confirmSend() {
  confirmModal.value = false
  if (!selectedTemplate.value || isSending.value) return
  isSending.value = true

  progressTitle.value = 'Enviando campaña...'
  progressText.value = '0 / 0 enviados'
  progressPct.value = 0
  progressErrors.value = []
  progressModal.value = true

  let offset = 0
  let totalSent = 0
  let totalFailed = 0

  try {
    while (true) {
      const data = await api.post<SendResponse>('/api/newsletter/send-campaign.php', {
        template_id: selectedTemplate.value.id,
        subject_override: subjectOverride.value,
        offset,
        limit: BATCH_SIZE,
      })
      totalSent += data.sent || 0
      totalFailed += data.failed || 0

      if (data.errors && data.errors.length > 0) {
        progressErrors.value = [...progressErrors.value, ...data.errors.map(e => `${e.email}: ${e.error}`)]
      }

      progressText.value = `${totalSent} / ${data.total} enviados`
      progressPct.value = (data.total || 0) > 0 ? Math.round((totalSent / (data.total || 1)) * 100) : 0

      if (data.done) break
      offset = data.next_offset || 0
      await new Promise(r => setTimeout(r, 100))
    }

    showResult(true, totalSent, totalFailed, totalSubscribers.value)
  } catch (err: any) {
    progressTitle.value = 'Error'
    progressText.value = err.message
    progressPct.value = 0
    showResult(false, totalSent, totalFailed, totalSubscribers.value)
  } finally {
    isSending.value = false
  }
}

function showResult(success: boolean, sent: number, failed: number, total: number) {
  progressModal.value = false
  resultModal.value = true

  if (success && failed === 0) {
    resultIcon.value = 'check_circle'
    resultIconClass.value = 'text-[#42b883]'
    resultTitle.value = 'Campaña enviada'
    resultText.value = `Se enviaron ${sent} emails correctamente a ${total} suscriptores.`
  } else if (success && failed > 0) {
    resultIcon.value = 'warning'
    resultIconClass.value = 'text-[#B8956A]'
    resultTitle.value = 'Campaña enviada con errores'
    resultText.value = `Se enviaron ${sent} de ${total} emails. ${failed} fallaron (revisa el log del servidor).`
  } else {
    resultIcon.value = 'error'
    resultIconClass.value = 'text-[#DC2626]'
    resultTitle.value = 'Error al enviar'
    resultText.value = 'No se pudo completar el envío.'
  }
}

function resetForm() {
  selectedTemplate.value = null
  subjectOverride.value = ''
  subjectUserEdited.value = false
  showPreview.value = false
  previewHtml.value = ''
  confirmModal.value = false
  progressModal.value = false
  resultModal.value = false
}
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
    <!-- Composer -->
    <div class="lg:col-span-2 glass-card overflow-hidden rounded-xl">
      <div class="px-6 pt-5 pb-4 border-b border-[#dae2fd]/5">
        <h2 class="text-lg font-semibold text-[#dae2fd]">Nueva Campaña</h2>
      </div>
      <div class="px-6 py-4 space-y-5">

      <!-- Template selector -->
      <div class="mb-5">
        <label class="block text-xs font-semibold tracking-widest text-[#94a3b8] mb-2 uppercase">PLANTILLA</label>
        <select class="admin-input" @change="onTemplateChange">
          <option value="">Seleccionar plantilla...</option>
          <option v-for="t in templates" :key="t.id" :value="t.id" :disabled="!t.is_active">
            {{ t.name }}{{ t.is_active ? '' : ' (inactiva)' }}
          </option>
          <option v-if="templates.length === 0" value="" disabled>— No hay plantillas —</option>
        </select>
      </div>

      <!-- Subject -->
      <div class="mb-5">
        <label class="block text-xs font-semibold tracking-widest text-[#94a3b8] mb-2 uppercase">
          ASUNTO <span class="font-normal normal-case text-[#94a3b8]/50">(opcional — reemplaza el asunto de la plantilla)</span>
        </label>
        <input v-model="subjectOverride" type="text" class="admin-input"
               placeholder="Dejar vacío para usar el asunto de la plantilla..."
               @input="subjectUserEdited = subjectOverride.length > 0" />
      </div>

      <!-- Preview -->
      <div v-if="showPreview" class="mb-5">
        <label class="block text-xs font-semibold tracking-widest text-[#94a3b8] mb-2 uppercase">VISTA PREVIA</label>
        <div class="border border-[#dae2fd]/10 rounded-sm overflow-hidden bg-white">
          <iframe class="w-full border-0" style="min-height:320px" sandbox="allow-same-origin" :srcdoc="previewHtml"></iframe>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pt-5 border-t border-[#dae2fd]/5">
        <div>
          <p class="text-sm text-[#94a3b8]">
            <VunoIcon icon="people" :size="20" class="align-middle mr-1" />
            {{ totalSubscribers }} suscriptores activos
          </p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
          <button class="admin-btn admin-btn-secondary w-full sm:w-auto justify-center" :disabled="!selectedTemplate" @click="sendTest">
            <VunoIcon icon="bug_report" :size="20" />
            ENVIAR PRUEBA
          </button>
          <button class="admin-btn admin-btn-primary w-full sm:w-auto justify-center" :disabled="!selectedTemplate || totalSubscribers === 0 || isSending" @click="openConfirm">
            <VunoIcon :icon="isSending ? 'progress_activity' : 'campaign'" :size="20" :class="{ 'animate-spin': isSending }" />
            {{ isSending ? 'ENVIANDO...' : 'ENVIAR CAMPAÑA' }}
          </button>
        </div>
      </div>
    </div>
    </div>

    <!-- Sidebar -->
    <div class="glass-card overflow-hidden rounded-xl">
      <div class="px-6 pt-5 pb-4 border-b border-[#dae2fd]/5">
        <h3 class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase">VARIABLES DISPONIBLES</h3>
      </div>
      <div class="px-6 py-4 space-y-3 text-sm">
        <div>
          <code class="bg-[#1e293b] px-2 py-0.5 rounded text-xs font-mono text-[#dae2fd]">&#123;&#123;subscriber_name&#125;&#125;</code>
          <p class="text-[#94a3b8] text-xs mt-1">Nombre del suscriptor</p>
        </div>
        <div>
          <code class="bg-[#1e293b] px-2 py-0.5 rounded text-xs font-mono text-[#dae2fd]">&#123;&#123;unsubscribe_url&#125;&#125;</code>
          <p class="text-[#94a3b8] text-xs mt-1">Link de baja personalizado</p>
        </div>
        <div>
          <code class="bg-[#1e293b] px-2 py-0.5 rounded text-xs font-mono text-[#dae2fd]">&#123;&#123;title&#125;&#125;</code>
          <p class="text-[#94a3b8] text-xs mt-1">Título del contenido</p>
        </div>
        <div>
          <code class="bg-[#1e293b] px-2 py-0.5 rounded text-xs font-mono text-[#dae2fd]">&#123;&#123;message&#125;&#125;</code>
          <p class="text-[#94a3b8] text-xs mt-1">Mensaje principal</p>
        </div>
        <div>
          <code class="bg-[#1e293b] px-2 py-0.5 rounded text-xs font-mono text-[#dae2fd]">&#123;&#123;content_block&#125;&#125;</code>
          <p class="text-[#94a3b8] text-xs mt-1">Bloque HTML personalizado</p>
        </div>
      </div>
      <div class="px-6 pb-5 pt-4 border-t border-[#dae2fd]/5">
        <p class="text-xs text-[#94a3b8]/60">
          Las variables <code class="font-mono">&#123;&#123;store_name&#125;&#125;</code>, <code class="font-mono">&#123;&#123;store_logo_block&#125;&#125;</code> y <code class="font-mono">&#123;&#123;store_slogan&#125;&#125;</code> se inyectan automáticamente.
        </p>
      </div>
    </div>
  </div>

  <!-- Confirm Modal -->
  <Teleport to="body">
    <div v-if="confirmModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-md p-4" @click.self="confirmModal = false">
      <div class="admin-card-lg w-full max-w-md mx-4 p-6">
        <div class="flex items-center gap-3 mb-4">
          <VunoIcon icon="campaign" :size="32" class="text-[#B8956A]" />
          <div>
            <h3 class="text-lg font-semibold text-[#dae2fd]">Enviar Campaña</h3>
            <p class="text-sm text-[#94a3b8]">Esto enviará un email a <strong class="text-[#dae2fd]">{{ confirmCount }}</strong> suscriptores activos.</p>
          </div>
        </div>
        <p class="text-sm text-[#94a3b8] mb-6">
          ¿Estás seguro de enviar esta campaña utilizando la plantilla <strong class="text-[#dae2fd]">{{ confirmTemplateName }}</strong>?
        </p>
        <div class="flex flex-col-reverse sm:flex-row justify-end gap-3">
          <button class="admin-btn admin-btn-secondary w-full sm:w-auto justify-center" @click="confirmModal = false">CANCELAR</button>
          <button class="admin-btn admin-btn-primary w-full sm:w-auto justify-center" @click="confirmSend">
            <VunoIcon icon="send" :size="20" />
            ENVIAR
          </button>
        </div>
      </div>
    </div>

    <!-- Progress Modal -->
    <div v-if="progressModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-md p-4">
      <div class="admin-card-lg w-full max-w-md mx-4 p-6">
        <div class="flex items-center gap-4 mb-4">
          <VunoIcon icon="progress_activity" :size="28" class="text-[#42b883] animate-spin" />
          <div class="flex-1 min-w-0">
            <p class="font-semibold text-[#dae2fd]">{{ progressTitle }}</p>
            <p class="text-sm text-[#94a3b8] mt-1">{{ progressText }}</p>
          </div>
        </div>
        <div class="w-full bg-[#1e293b] h-2 rounded-sm overflow-hidden">
          <div class="bg-[#42b883] h-2 rounded-sm transition-all duration-300" :style="{ width: progressPct + '%' }"></div>
        </div>
        <div v-if="progressErrors.length > 0" class="mt-4">
          <p class="text-sm text-[#DC2626] flex items-center gap-1">
            <VunoIcon icon="warning" :size="14" />
            {{ progressErrors.length }} errores — revisa el log del servidor
          </p>
        </div>
      </div>
    </div>

    <!-- Result Modal -->
    <div v-if="resultModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-md p-4" @click.self="resultModal = false">
      <div class="admin-card-lg w-full max-w-md mx-4 p-6">
        <div class="flex items-start gap-3 mb-4">
          <VunoIcon :icon="resultIcon" :size="32" :class="resultIconClass" />
          <div class="flex-1 min-w-0">
            <h3 class="text-lg font-semibold text-[#dae2fd]">{{ resultTitle }}</h3>
            <p class="text-sm text-[#94a3b8] mt-1">{{ resultText }}</p>
          </div>
        </div>
        <div class="flex flex-col-reverse sm:flex-row justify-end gap-3">
          <button class="admin-btn admin-btn-secondary w-full sm:w-auto justify-center" @click="resetForm">
            <VunoIcon icon="add" :size="20" />
            NUEVA CAMPAÑA
          </button>
          <button class="admin-btn admin-btn-primary w-full sm:w-auto justify-center" @click="resultModal = false">CERRAR</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
