<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'

const api = useApi()
const toast = useToast()

const props = defineProps<{ templateId?: string }>()

const isEdit = !!props.templateId
const CODE_MAX = 100
const NAME_MAX = 200
const SUBJECT_MAX = 255

const loading = ref(isEdit)
const saving = ref(false)

const code = ref('')
const name = ref('')
const subject = ref('')
const bodyHtml = ref('')
const isActive = ref(true)
const isVisualMode = ref(true)
const previewModalVisible = ref(false)
const previewSubject = ref('')
const previewIframeReady = ref(false)
let codeManual = false

const bodyVisualRef = ref<HTMLElement | null>(null)
const bodyHtmlRef = ref<HTMLTextAreaElement | null>(null)
const previewContent = ref('')

const variables1 = ['customer_name', 'order_id', 'order_items_html', 'order_subtotal', 'order_total', 'order_shipping', 'currency_symbol', 'preheader', 'payment_method', 'customer_email']
const globals1 = ['store_name', 'store_slogan', 'store_logo_block', 'store_email']
const newsletterVars = ['subscriber_name', 'discount_block', 'social_links_block', 'unsubscribe_url']

onMounted(async () => {
  if (isEdit && props.templateId) {
    try {
      const t = await api.get<any>(`/api/email-templates/get.php?id=${props.templateId}`)
      code.value = t.code || ''
      name.value = t.name || ''
      subject.value = t.subject || ''
      bodyHtml.value = t.body_html || ''
      isActive.value = t.is_active ? true : false
      codeManual = !!t.code
    } catch (e: any) {
      toast.error(e.message || 'Error loading template')
    } finally {
      loading.value = false
    }
  } else {
    loading.value = false
  }
})

function slugify(text: string): string {
  return text.toLowerCase().trim()
    .replace(/[^\w\s]/g, '')
    .replace(/\s+/g, '_')
    .replace(/_+/g, '_')
    .replace(/^_|_$/g, '')
}

function onNameInput() {
  if (!codeManual) {
    code.value = slugify(name.value)
  }
}

function onCodeInput() {
  codeManual = !!code.value
}

function getBodyHtml(): string {
  if (isVisualMode.value) {
    return bodyVisualRef.value?.innerHTML || ''
  }
  return bodyHtmlRef.value?.value || ''
}

function setMode(visual: boolean) {
  isVisualMode.value = visual
  if (visual && bodyHtmlRef.value) {
    bodyVisualRef.value!.innerHTML = bodyHtmlRef.value.value
  } else if (!visual && bodyVisualRef.value) {
    bodyHtmlRef.value!.value = bodyVisualRef.value.innerHTML
  }
  updateBodyCounter()
}

function updateBodyCounter() {
  const el = isVisualMode.value ? bodyVisualRef.value : bodyHtmlRef.value
  const len = el ? (el.textContent || '').length : 0
  const counter = document.getElementById('bodyCounter')
  if (counter) counter.textContent = len + ' caracteres'
}

function execCmd(cmd: string, value?: string) {
  if (!isVisualMode.value) return
  if (cmd === 'link') {
    const url = prompt('URL del enlace:', 'https://')
    if (url) document.execCommand('createLink', false, url)
  } else {
    document.execCommand(cmd, false, value || null)
  }
  bodyVisualRef.value?.focus()
  syncVisualToHtml()
}

function syncVisualToHtml() {
  updateBodyCounter()
}

function insertVariable(varName: string) {
  const varText = `{{${varName}}}`
  if (isVisualMode.value) {
    bodyVisualRef.value?.focus()
    document.execCommand('insertText', false, varText)
    syncVisualToHtml()
  } else if (bodyHtmlRef.value) {
    const ta = bodyHtmlRef.value
    const start = ta.selectionStart
    const end = ta.selectionEnd
    ta.value = ta.value.substring(0, start) + varText + ta.value.substring(end)
    ta.selectionStart = ta.selectionEnd = start + varText.length
    ta.focus()
    ta.dispatchEvent(new Event('input'))
    updateBodyCounter()
  }
}

function handlePaste(e: ClipboardEvent) {
  e.preventDefault()
  const text = (e.clipboardData || (window as any).clipboardData).getData('text/plain')
  document.execCommand('insertText', false, text)
  syncVisualToHtml()
}

async function openPreview() {
  const html = getBodyHtml().trim()
  if (!html) {
    toast.error('Escribe contenido para previsualizar.')
    return
  }
  previewModalVisible.value = true
  previewSubject.value = '—'

  try {
    const data = await api.post<{ success: boolean; subject: string; body_html: string }>('/api/email-templates/preview.php', {
      code: code.value.trim() || 'preview',
      subject: subject.value.trim(),
      body_html: html,
    })
    if (!data.success) throw new Error('Preview failed')
    previewSubject.value = data.subject
    previewContent.value = data.body_html
  } catch (e: any) {
    previewSubject.value = 'Error: ' + e.message
  }
}

function closePreview() {
  previewModalVisible.value = false
}

async function handleSubmit() {
  const body = getBodyHtml().trim()
  if (!code.value.trim() || !name.value.trim() || !subject.value.trim() || !body) {
    toast.error('Completa todos los campos obligatorios.')
    return
  }

  saving.value = true
  try {
    const payload: Record<string, any> = {
      code: code.value.trim(),
      name: name.value.trim(),
      subject: subject.value.trim(),
      body_html: body,
      is_active: isActive.value,
    }

    if (isEdit) {
      payload.id = parseInt(props.templateId!)
      await api.post('/api/email-templates/update.php', payload)
      toast.success('Plantilla actualizada')
    } else {
      await api.post('/api/email-templates/create.php', payload)
      toast.success('Plantilla creada')
    }
    window.location.href = '/admin/email-templates'
  } catch (e: any) {
    toast.error(e.message || 'Error al guardar')
  } finally {
    saving.value = false
  }
}

async function restoreOriginal() {
  if (!confirm('¿Restaurar esta plantilla a su versión original del archivo? Se perderán los cambios personalizados.')) return
  if (!code.value.trim()) return
  try {
    await api.post('/api/email-templates/restore.php', { code: code.value.trim() })
    const t = await api.get<any>(`/api/email-templates/get.php?id=${props.templateId}`)
    code.value = t.code || ''
    name.value = t.name || ''
    subject.value = t.subject || ''
    bodyHtml.value = t.body_html || ''
    if (bodyVisualRef.value) bodyVisualRef.value.innerHTML = t.body_html || ''
    if (bodyHtmlRef.value) bodyHtmlRef.value.value = t.body_html || ''
    isActive.value = t.is_active ? true : false
    toast.success('Plantilla restaurada a versión original')
  } catch (e: any) {
    toast.error(e.message || 'Error al restaurar')
  }
}

function countClass(len: number, max: number) {
  if (len >= max) return 'text-[#DC2626]'
  if (len >= max * 0.8) return 'text-[#B8956A]'
  return 'text-[#94a3b8]'
}
</script>

<template>
  <div v-if="loading" class="font-body text-body-md text-[#94a3b8] p-4">Cargando...</div>

  <form v-else class="max-w-3xl" @submit.prevent="handleSubmit">
    <div class="admin-card space-y-6">
      <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
        <span class="material-symbols-outlined text-xl">mail</span>
        {{ isEdit ? 'Editar Plantilla' : 'Nueva Plantilla' }}
      </h2>

      <div>
        <label class="block text-sm font-medium text-[#94a3b8] mb-2">CÓDIGO *</label>
        <div class="relative">
          <input v-model="code" type="text" required :maxlength="CODE_MAX" class="admin-input pr-16 font-mono text-sm" placeholder="order_confirmation" @input="onCodeInput" />
          <span class="absolute right-2 bottom-2 text-xs pointer-events-none" :class="countClass(code.length, CODE_MAX)">{{ code.length }}/{{ CODE_MAX }}</span>
        </div>
        <p class="text-xs text-[#64748b] mt-1">Solo minúsculas, números y guión bajo. Se genera desde el nombre.</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-[#94a3b8] mb-2">NOMBRE *</label>
        <div class="relative">
          <input v-model="name" type="text" required :maxlength="NAME_MAX" class="admin-input pr-16" @input="onNameInput" />
          <span class="absolute right-2 bottom-2 text-xs pointer-events-none" :class="countClass(name.length, NAME_MAX)">{{ name.length }}/{{ NAME_MAX }}</span>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-[#94a3b8] mb-2">ASUNTO *</label>
        <div class="relative">
          <input v-model="subject" type="text" required :maxlength="SUBJECT_MAX" class="admin-input pr-16" placeholder="Order Confirmation #{{order_id}} — Ram;Lop" />
          <span class="absolute right-2 bottom-2 text-xs pointer-events-none" :class="countClass(subject.length, SUBJECT_MAX)">{{ subject.length }}/{{ SUBJECT_MAX }}</span>
        </div>
        <p class="text-xs text-[#64748b] mt-1">Usa <code class="text-[#B8956A]">&#123;&#123;variable&#125;&#125;</code> para datos dinámicos</p>
      </div>

      <!-- Visual editor -->
      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="block text-sm font-medium text-[#94a3b8]">CUERPO DE LA PLANTILLA *</label>
          <div class="flex items-center gap-2">
            <button v-if="isEdit" type="button" class="text-xs font-medium text-[#B8956A] border border-[#B8956A]/30 rounded-sm px-3 h-8 inline-flex items-center gap-1 hover:bg-[#B8956A]/5 transition-all" @click="restoreOriginal">
              <span class="material-symbols-outlined text-sm">restore</span>
              ORIGINAL
            </button>
            <button type="button" class="text-xs font-medium text-[#dae2fd] border border-[#1e293b] rounded-sm px-3 h-8 inline-flex items-center gap-1 hover:bg-white/5 transition-all" @click="openPreview">
              <span class="material-symbols-outlined text-sm">visibility</span>
              VISTA PREVIA
            </button>
            <div class="flex items-center gap-0.5 border border-[#1e293b] rounded-sm overflow-hidden text-xs font-medium">
              <button type="button" class="px-3 h-8 transition-colors" :class="isVisualMode ? 'bg-[#42b883] text-white' : 'text-[#94a3b8] hover:text-[#dae2fd]'" @click="setMode(true)">Visual</button>
              <button type="button" class="px-3 h-8 transition-colors" :class="!isVisualMode ? 'bg-[#42b883] text-white' : 'text-[#94a3b8] hover:text-[#dae2fd]'" @click="setMode(false)">HTML</button>
            </div>
          </div>
        </div>

        <!-- Toolbar -->
        <div v-show="isVisualMode" class="flex flex-wrap items-center gap-0.5 p-1.5 border border-[#1e293b] bg-[#162240] rounded-t-sm select-none">
          <button type="button" class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-sm hover:bg-white/10 text-[#dae2fd] font-bold text-sm" title="Negrita" @click="execCmd('bold')">B</button>
          <button type="button" class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-sm hover:bg-white/10 text-[#dae2fd] italic text-sm" title="Itálica" @click="execCmd('italic')">I</button>
          <button type="button" class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-sm hover:bg-white/10 text-[#dae2fd] underline text-sm" title="Subrayado" @click="execCmd('underline')">U</button>
          <span class="w-px h-5 bg-[#1e293b] mx-0.5"></span>
          <button type="button" class="px-2 min-h-[44px] flex items-center justify-center rounded-sm hover:bg-white/10 text-[#dae2fd] text-xs font-semibold" title="Título grande" @click="execCmd('formatBlock', 'h2')">H2</button>
          <button type="button" class="px-2 min-h-[44px] flex items-center justify-center rounded-sm hover:bg-white/10 text-[#dae2fd] text-xs font-semibold" title="Título mediano" @click="execCmd('formatBlock', 'h3')">H3</button>
          <button type="button" class="px-2 min-h-[44px] flex items-center justify-center rounded-sm hover:bg-white/10 text-[#dae2fd] text-xs" title="Párrafo" @click="execCmd('formatBlock', 'p')">¶</button>
          <span class="w-px h-5 bg-[#1e293b] mx-0.5"></span>
          <button type="button" class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-sm hover:bg-white/10 text-[#dae2fd]" title="Lista viñetas" @click="execCmd('insertUnorderedList')"><span class="material-symbols-outlined text-base">format_list_bulleted</span></button>
          <button type="button" class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-sm hover:bg-white/10 text-[#dae2fd]" title="Lista numerada" @click="execCmd('insertOrderedList')"><span class="material-symbols-outlined text-base">format_list_numbered</span></button>
          <span class="w-px h-5 bg-[#1e293b] mx-0.5"></span>
          <button type="button" class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-sm hover:bg-white/10 text-[#dae2fd]" title="Insertar enlace" @click="execCmd('link')"><span class="material-symbols-outlined text-base">link</span></button>
          <button type="button" class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-sm hover:bg-white/10 text-[#94a3b8]" title="Quitar enlace" @click="execCmd('unlink')"><span class="material-symbols-outlined text-base">link_off</span></button>
          <span class="w-px h-5 bg-[#1e293b] mx-0.5"></span>
          <button type="button" class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-sm hover:bg-white/10 text-[#94a3b8]" title="Limpiar formato" @click="execCmd('removeFormat')"><span class="material-symbols-outlined text-base">format_clear</span></button>
        </div>

        <!-- Visual contenteditable -->
        <div
          ref="bodyVisualRef"
          contenteditable="true"
          class="min-h-[320px] p-4 border-x border-b border-[#1e293b] bg-[#0a1022] text-[#dae2fd] leading-relaxed focus:outline-none focus:border-[#42b883] rounded-b-sm"
          style="[&_h2]:text-xl [&_h3]:text-lg [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5"
          data-placeholder="Escribe aquí el contenido de la plantilla..."
          @input="syncVisualToHtml"
          @paste="handlePaste"
        ></div>

        <!-- HTML textarea -->
        <textarea
          v-show="!isVisualMode"
          ref="bodyHtmlRef"
          rows="20"
          class="w-full border-x border-b border-[#1e293b] p-3 text-[#dae2fd] focus:border-[#42b883] focus:outline-none bg-[#0a1022] font-mono text-sm leading-relaxed resize-y rounded-b-sm"
          @input="updateBodyCounter"
        ></textarea>

        <!-- Status bar -->
        <div class="flex items-center justify-between px-3 py-1.5 bg-[#162240] border border-t-0 border-[#1e293b] rounded-b-sm text-xs text-[#94a3b8]">
          <span class="font-medium text-[10px] uppercase tracking-wider">{{ isVisualMode ? 'MODO VISUAL' : 'MODO HTML' }}</span>
          <span id="bodyCounter">{{ (bodyVisualRef?.textContent?.length || 0) }} caracteres</span>
        </div>

        <!-- Variable buttons -->
        <div class="flex flex-wrap gap-1.5 mt-3">
          <span class="text-xs text-[#94a3b8] flex items-center">Insertar variable:</span>
          <button v-for="v in variables1" :key="v" type="button" class="text-xs font-mono text-[#B8956A] hover:text-white hover:bg-[#B8956A] bg-[#B8956A]/10 px-2 py-1 rounded-sm transition-all" @click="insertVariable(v)">{{ v }}</button>
        </div>
        <div class="flex flex-wrap gap-1.5 mt-2">
          <span class="text-xs text-[#94a3b8] flex items-center">Globales:</span>
          <button v-for="v in globals1" :key="v" type="button" class="text-xs font-mono text-[#B8956A] hover:text-white hover:bg-[#B8956A] bg-[#B8956A]/10 px-2 py-1 rounded-sm transition-all" @click="insertVariable(v)">{{ v }}</button>
        </div>
        <div class="flex flex-wrap gap-1.5 mt-2">
          <span class="text-xs text-[#94a3b8] flex items-center">Newsletter:</span>
          <button v-for="v in newsletterVars" :key="v" type="button" class="text-xs font-mono text-[#B8956A] hover:text-white hover:bg-[#B8956A] bg-[#B8956A]/10 px-2 py-1 rounded-sm transition-all" @click="insertVariable(v)">{{ v }}</button>
        </div>
      </div>

      <label class="admin-toggle-label cursor-pointer py-2">
        <label class="admin-toggle">
          <input v-model="isActive" type="checkbox" />
          <div></div>
        </label>
        <span class="text-xs font-semibold tracking-widest uppercase" :class="isActive ? 'text-[#42b883]' : 'text-[#94a3b8]'">{{ isActive ? 'ACTIVA' : 'INACTIVA' }}</span>
      </label>
    </div>

    <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 mt-8">
      <a href="/admin/email-templates" class="admin-btn admin-btn-secondary w-full sm:w-auto justify-center">CANCELAR</a>
      <button type="submit" class="admin-btn admin-btn-primary w-full sm:w-auto justify-center" :disabled="saving">
        <span class="material-symbols-outlined text-base">{{ saving ? 'progress_activity' : 'save' }}</span>
        {{ saving ? 'GUARDANDO...' : 'GUARDAR' }}
      </button>
    </div>
  </form>

  <!-- Preview Modal -->
  <Teleport to="body">
    <div v-if="previewModalVisible" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-md p-4" @click.self="closePreview">
      <div class="admin-card-lg w-full max-w-4xl max-h-[90vh] flex flex-col mx-4 animate-modal-in">
        <div class="flex items-center justify-between p-4 border-b border-[#1e293b] shrink-0">
          <div class="flex items-center gap-3 min-w-0 flex-1">
            <h3 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
              <span class="material-symbols-outlined text-xl">visibility</span>
              Vista Previa
            </h3>
            <span class="text-sm text-[#94a3b8] truncate">{{ previewSubject }}</span>
          </div>
          <button type="button" class="w-10 h-10 flex items-center justify-center text-[#94a3b8] hover:text-[#dae2fd] transition-colors rounded-sm hover:bg-white/5 shrink-0 ml-4" @click="closePreview">
            <span class="material-symbols-outlined text-lg">close</span>
          </button>
        </div>
        <div class="flex-1 overflow-auto p-4 bg-white">
          <iframe class="w-full h-full border-0" sandbox="allow-same-origin" title="Preview" :srcdoc="previewContent"></iframe>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.animate-modal-in {
  animation: modalIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) both;
}
@keyframes modalIn {
  from { opacity: 0; transform: scale(0.95) translateY(8px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}
[contenteditable]:empty::before {
  content: attr(data-placeholder);
  color: #64748b;
  pointer-events: none;
}
</style>
