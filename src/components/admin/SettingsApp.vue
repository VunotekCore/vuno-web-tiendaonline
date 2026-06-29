<script setup lang="ts">
import { ref, reactive, onMounted, computed, watch } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'
import VunoIcon from './VunoIcon.vue'

const api = useApi()
const toast = useToast()

interface TabItem {
  id: string
  label: string
  icon: string
  children?: TabItem[]
}

const tabs: TabItem[] = [
  {
    id: 'group-tienda', label: 'Tienda', icon: 'store',
    children: [
      { id: 'store-info', label: 'Info Tienda', icon: 'store' },
      { id: 'store-currency', label: 'Moneda', icon: 'payments' },
      { id: 'store-tax', label: 'Impuestos', icon: 'receipt_long' },
      { id: 'store-receipt', label: 'Recibo', icon: 'receipt' },
      { id: 'store-sizeguide', label: 'Talles', icon: 'straighten' },
    ],
  },
  {
    id: 'group-pagos', label: 'Pagos', icon: 'credit_card',
    children: [
      { id: 'stripe', label: 'Stripe', icon: 'credit_card' },
      { id: 'transfer', label: 'Transferencia', icon: 'account_balance' },
    ],
  },
  {
    id: 'group-servicios', label: 'Servicios', icon: 'settings_applications',
    children: [
      { id: 'imagekit', label: 'ImageKit', icon: 'photo_library' },
      { id: 'smtp', label: 'Email / SMTP', icon: 'mail' },
    ],
  },
  { id: 'shipping', label: 'Envío', icon: 'local_shipping' },
  { id: 'whatsapp', label: 'WhatsApp', icon: 'chat' },
  {
    id: 'group-landing', label: 'Landing', icon: 'dashboard_customize',
    children: [
      { id: 'hero', label: 'Hero', icon: 'movie' },
      { id: 'new_arrivals', label: 'New Arrivals', icon: 'new_releases' },
      { id: 'categories', label: 'Categorías', icon: 'category' },
      { id: 'brand_values', label: 'Brand Values', icon: 'handshake' },
      { id: 'closing_cta', label: 'Closing CTA', icon: 'call_to_action' },
      { id: 'social', label: 'Social', icon: 'alternate_email' },
      { id: 'testimonials', label: 'Testimonios', icon: 'star' },
      { id: 'blog', label: 'Blog', icon: 'article' },
    ],
  },
  { id: 'policies', label: 'Políticas', icon: 'policy' },
  { id: 'seo', label: 'SEO', icon: 'travel_explore' },
]

const activeTab = ref('group-tienda')
const activeChildTab = ref('store-info')
const loading = ref(true)

const effectiveTab = computed(() => {
  if (activeTab.value === 'group-tienda' || activeTab.value === 'group-pagos' || activeTab.value === 'group-servicios' || activeTab.value === 'group-landing') {
    return activeChildTab.value
  }
  return activeTab.value
})

const flatTabsList = computed(() => {
  const list: { id: string; label: string }[] = []
  for (const t of tabs) {
    list.push({ id: t.id, label: t.label })
  }
  return list
})

watch(activeTab, (id) => {
  const group = tabs.find(t => t.id === id)
  if (group?.children?.length) {
    activeChildTab.value = group.children[0].id
  }
})

// Store settings
interface StoreSettings {
  name?: string; slogan?: string; email?: string; description?: string
  logo?: string; newsletter_discount_code?: string
}
interface ReceiptSettings {
  businessName?: string; taxId?: string; address?: string; city?: string
  state?: string; zip?: string; phone?: string
}
interface ImageKitSettings { publicKey?: string; privateKey?: string; urlEndpoint?: string }
interface StripeSettings {
  enabled?: boolean; publishableKey?: string; secretKey?: string; webhookSecret?: string
}
interface SMTP {
  host?: string; port?: string; user?: string; pass?: string
  fromEmail?: string; fromName?: string; adminEmail?: string
}
interface ShippingSettings {
  enabled?: boolean; base_rate?: number; free_above?: number; estimated_days?: string
}
interface WhatsappSettings { enabled?: boolean; number?: string; message?: string }
interface TaxSettings { rate?: number }
interface PoliciesSettings {
  shipping_es?: string; shipping_en?: string; returns_es?: string; returns_en?: string
  privacy_es?: string; privacy_en?: string
}
interface SeoSettings {
  global_title?: string; global_description?: string; og_default_image?: string
  twitter_site?: string; facebook_page_id?: string; google_site_verification?: string
  bing_site_verification?: string; ga_id?: string; robots_default?: string; theme_color?: string
}
interface SizeGuideSettings {
  title_es?: string; title_en?: string; footer_es?: string; footer_en?: string
}
interface TransferSettings { enabled?: boolean; banks?: BankEntry[] }
interface LandingSettings { [section: string]: any }

interface BankEntry {
  bankName?: string; accountHolder?: string; accountNumber?: string
  accountType?: string; routingNumber?: string; instructions?: string
}

interface Currency {
  code: string; name: string; symbol: string; exchange_rate: number
  decimal_places: number; is_active: boolean
}

interface SizeGuideRow { us?: string; eu?: string; uk?: string; cm?: string }

const store = reactive<StoreSettings>({})
const receipt = reactive<ReceiptSettings>({})
const imagekit = reactive<ImageKitSettings>({})
const stripe = reactive<StripeSettings>({ enabled: false })
const smtp = reactive<SMTP>({})
const shipping = reactive<ShippingSettings>({ enabled: false })
const whatsapp = reactive<WhatsappSettings>({ enabled: false })
const tax = reactive<TaxSettings>({ rate: 0 })
const policies = reactive<PoliciesSettings>({})
const seo = reactive<SeoSettings>({})
const sizeGuide = reactive<SizeGuideSettings>({})
const transfer = reactive<TransferSettings>({ enabled: true, banks: [] })
const landing = reactive<LandingSettings>({})
const currencies = ref<Currency[]>([])
const storeCurrency = ref('')
const sizeGuideRows = ref<SizeGuideRow[]>([])

const SENSITIVE_FIELDS = ['pass', 'privateKey', 'secretKey', 'webhookSecret']

// Logo upload
const logoUrl = ref('')
const logoUploading = ref(false)
const logoProgress = ref(-1)
const logoError = ref('')

// Landing
// Landing (section key derived from activeChildTab)
const categories = ref<Array<{ slug: string; name: string }>>([])
const coupons = ref<Array<{ code: string; discount_type: string; discount_value: number; description?: string }>>([])

// Currency modal
const showCurrencyModal = ref(false)
const newCurrency = reactive({ code: '', name: '', symbol: '', rate: 1.0, decimals: 2 })
const currencyModalLoading = ref(false)
const currencyModalError = ref('')

const dirtySections = reactive(new Set<string>())
const editMode = reactive(new Set<string>())

function isEditing(section: string) { return editMode.has(section) }
function enableEdit(section: string) { editMode.add(section) }
function disableEdit(section?: string) {
  if (section) editMode.delete(section)
  else editMode.clear()
}

function markDirty(section: string) {
  dirtySections.add(section)
}

function clearDirty(section?: string) {
  if (section) dirtySections.delete(section)
  else dirtySections.clear()
}

const landingSections: Record<string, { label: string; fields: string[]; extras: string[] }> = {
  hero: { label: 'Hero', fields: ['label', 'title', 'subtitle', 'cta'], extras: ['enabled', 'cta_link', 'cta_category_slug'] },
  new_arrivals: { label: 'New Arrivals', fields: ['label', 'title', 'subtitle', 'cta'], extras: ['enabled', 'cta_link', 'cta_category_slug'] },
  categories: { label: 'Categories', fields: ['label', 'title'], extras: ['enabled', 'cta_link', 'cta_category_slug'] },
  brand_values: { label: 'Brand Values', fields: ['label', 'title', 'paragraph', 'cta'], extras: ['enabled', 'image_url', 'cta_link', 'cta_category_slug'] },
  closing_cta: { label: 'Closing CTA', fields: ['label', 'title', 'subtitle', 'cta'], extras: ['enabled', 'cta_link', 'cta_category_slug'] },
  social: { label: 'Social Media', fields: ['title'], extras: ['enabled', 'platforms', 'images'] },
  testimonials: { label: 'Testimonials', fields: ['title', 'subtitle'], extras: ['enabled', 'testimonial_items'] },
  blog: { label: 'Blog Journal', fields: ['label', 'title', 'desc', 'view_all'], extras: ['enabled'] },
}

function lsVal(section: string, field: string): string { return landing[section]?.[field] ?? '' }
function lsBool(section: string, field: string): boolean { return landing[section]?.[field] ?? false }
function setLs(section: string, field: string, val: any) {
  if (!landing[section]) landing[section] = {}
  landing[section][field] = val
  markDirty('landing')
}

onMounted(async () => {
  try {
    await loadSettings()
    loading.value = false
  } catch (err: any) {
    loading.value = false
    toast.error('Error loading settings: ' + err.message)
  }
})

async function loadSettings() {
  const data = await api.get<any>('/api/configuracion/get.php')
  Object.assign(store, data.store || {})
  Object.assign(receipt, data.receipt || {})
  Object.assign(imagekit, data.imagekit || {})
  Object.assign(stripe, { enabled: false, publishableKey: '', secretKey: '', webhookSecret: '', ...(data.stripe || {}) })
  Object.assign(smtp, data.smtp || {})
  Object.assign(shipping, { enabled: false, base_rate: 0, free_above: 0, estimated_days: '', ...(data.shipping || {}) })
  Object.assign(whatsapp, { enabled: false, number: '', message: '', ...(data.whatsapp || {}) })
  Object.assign(tax, data.tax || {})
  Object.assign(policies, data.policies || {})
  Object.assign(seo, data.seo || {})
  Object.assign(sizeGuide, data.size_guide || {})
  transfer.enabled = data.transfer?.enabled ?? true
  transfer.banks = data.transfer?.banks || [{ bankName: '', accountHolder: '', accountNumber: '', accountType: '', routingNumber: '', instructions: '' }]
  Object.assign(landing, data.landing || {})
  logoUrl.value = data.store?.logo || ''
  coupons.value = data.active_coupons || []
  const catItems = data.categories?.items || data.categories || []
  categories.value = Array.isArray(catItems) ? catItems : []
  currencies.value = data.currencies || []
  storeCurrency.value = data.storeCurrency || ''
  sizeGuideRows.value = data.size_guide_rows?.length ? data.size_guide_rows : [{ us: '', eu: '', uk: '', cm: '' }]

  for (const key of Object.keys(data.stripe || {})) {
    if (SENSITIVE_FIELDS.includes(key) && (stripe as any)[key]) (stripe as any)[key] = '••••••••'
  }
  for (const key of Object.keys(data.imagekit || {})) {
    if (SENSITIVE_FIELDS.includes(key) && (imagekit as any)[key]) (imagekit as any)[key] = '••••••••'
  }
  for (const key of Object.keys(data.smtp || {})) {
    if (SENSITIVE_FIELDS.includes(key) && (smtp as any)[key]) (smtp as any)[key] = '••••••••'
  }
}

function isSensitive(section: string, field: string): boolean {
  return SENSITIVE_FIELDS.includes(field)
}

async function handleLogoUpload(file: File) {
  if (!file) return
  logoError.value = ''
  const allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml']
  if (!allowed.includes(file.type)) { logoError.value = 'Formato no válido. Usá PNG, JPG, WEBP o SVG.'; return }
  if (file.size > 1024 * 1024) { logoError.value = `El archivo pesa ${(file.size / (1024 * 1024)).toFixed(1)} MB. Máx 1 MB.`; return }

  if (file.type !== 'image/svg+xml') {
    const img = await new Promise<HTMLImageElement>((resolve, reject) => {
      const i = new Image()
      i.onload = () => resolve(i)
      i.onerror = reject
      i.src = URL.createObjectURL(file)
    })
    if (img.naturalWidth > 800 || img.naturalHeight > 400) {
      logoError.value = `Dimensiones: ${img.naturalWidth}×${img.naturalHeight} px. Máx 800×400 px.`
      URL.revokeObjectURL(img.src); return
    }
    if (img.naturalWidth < 100 || img.naturalHeight < 50) {
      logoError.value = `Dimensiones: ${img.naturalWidth}×${img.naturalHeight} px. Mín 100×50 px.`
      URL.revokeObjectURL(img.src); return
    }
    URL.revokeObjectURL(img.src)
  }

  logoUploading.value = true
  logoProgress.value = 10
  try {
    const fd = new FormData()
    fd.append('file', file)
    fd.append('folder', 'logos')
    const data = await api.post<any>('/api/imagekit/upload.php', fd as any)
    logoProgress.value = 100
    await new Promise(r => setTimeout(r, 300))
    logoUrl.value = data.url
    store.logo = data.url
    markDirty('store')
  } catch (err: any) {
    logoError.value = err.message
  } finally {
    logoUploading.value = false
    logoProgress.value = -1
  }
}

function removeLogo() {
  logoUrl.value = ''
  store.logo = ''
  markDirty('store')
}

function addBank() {
  transfer.banks.push({ bankName: '', accountHolder: '', accountNumber: '', accountType: '', routingNumber: '', instructions: '' })
  markDirty('transfer')
}

function removeBank(index: number) {
  transfer.banks.splice(index, 1)
  markDirty('transfer')
}

function addTestimonial(section: string) {
  if (!landing[section]?.items) landing[section] = { ...landing[section], items: [] }
  landing[section].items.push({ name: '', rating: 5, text: '' })
  markDirty('landing')
}

function removeTestimonial(section: string, index: number) {
  landing[section]?.items?.splice(index, 1)
  markDirty('landing')
}

async function addCurrency() {
  currencyModalError.value = ''
  if (newCurrency.code.length !== 3) { currencyModalError.value = 'El código debe tener 3 letras'; return }
  if (!newCurrency.name) { currencyModalError.value = 'El nombre es requerido'; return }
  if (!newCurrency.symbol) { currencyModalError.value = 'El símbolo es requerido'; return }
  if (!newCurrency.rate || newCurrency.rate <= 0) { currencyModalError.value = 'La tasa debe ser mayor a 0'; return }

  currencyModalLoading.value = true
  try {
    await api.post('/api/monedas/create.php', {
      code: newCurrency.code, name: newCurrency.name, symbol: newCurrency.symbol,
      exchange_rate: newCurrency.rate, decimal_places: newCurrency.decimals,
    })
    showCurrencyModal.value = false
    toast.success(`Moneda ${newCurrency.code} agregada`)
    await loadCurrencies()
    newCurrency.code = ''; newCurrency.name = ''; newCurrency.symbol = ''; newCurrency.rate = 1.0; newCurrency.decimals = 2
  } catch (err: any) {
    currencyModalError.value = err.message
  } finally {
    currencyModalLoading.value = false
  }
}

async function deleteCurrency(code: string) {
  try {
    await api.post('/api/monedas/delete.php', { code })
    toast.success(`Moneda ${code} desactivada`)
    await loadCurrencies()
  } catch (err: any) { toast.error(err.message) }
}

let rateSaveTimer: any = null
async function saveCurrencyRates() {
  clearTimeout(rateSaveTimer)
  rateSaveTimer = setTimeout(async () => {
    const rates = currencies.value
      .filter(c => c.code && c.exchange_rate > 0)
      .map(c => ({ code: c.code, exchange_rate: c.exchange_rate, is_active: c.is_active, sort_order: 0 }))
    try {
      await api.post('/api/monedas/update-rate.php', { rates })
    } catch { /* silent */ }
  }, 800)
}

async function changeStoreCurrency(code: string) {
  if (!code) return
  try {
    await api.post('/api/monedas/update-rate.php', { code, exchange_rate: 1.0, set_as_store: true })
    storeCurrency.value = code
    await loadCurrencies()
    toast.success(`Moneda cambiada a ${code}`)
  } catch (err: any) { toast.error(err.message) }
}

function addSizeGuideRow() {
  sizeGuideRows.value.push({ us: '', eu: '', uk: '', cm: '' })
  markDirty('size_guide')
}

function removeSizeGuideRow(index: number) {
  sizeGuideRows.value.splice(index, 1)
  markDirty('size_guide')
}

async function handleLandingImageUpload(section: string, file: File) {
  if (!file) return
  const allowed = ['image/png', 'image/jpeg', 'image/webp']
  if (!allowed.includes(file.type)) { toast.error('Formato no válido'); return }
  if (file.size > 5 * 1024 * 1024) { toast.error('Máx 5 MB'); return }
  try {
    const fd = new FormData()
    fd.append('file', file)
    fd.append('folder', 'brand-values')
    const data = await api.post<any>('/api/imagekit/upload.php', fd as any)
    setLs(section, 'image_url', data.url)
  } catch (err: any) { toast.error(err.message) }
}

function removeLandingImage(section: string) {
  setLs(section, 'image_url', '')
}

async function save() {
  if (dirtySections.size === 0) {
    toast.info('Sin cambios que guardar')
    return
  }

  const settings: Record<string, any> = {}
  const sectionDefs: Record<string, { text: string[]; bool: string[] }> = {
    store: { text: ['name', 'slogan', 'email', 'description', 'logo', 'newsletter_discount_code'], bool: [] },
    receipt: { text: ['businessName', 'taxId', 'address', 'city', 'state', 'zip', 'phone'], bool: [] },
    imagekit: { text: ['publicKey', 'privateKey', 'urlEndpoint'], bool: [] },
    stripe: { text: ['publishableKey', 'secretKey', 'webhookSecret'], bool: ['enabled'] },
    smtp: { text: ['host', 'port', 'user', 'pass', 'fromEmail', 'fromName', 'adminEmail'], bool: [] },
    shipping: { text: ['base_rate', 'free_above', 'estimated_days'], bool: ['enabled'] },
    whatsapp: { text: ['number', 'message'], bool: ['enabled'] },
    tax: { text: ['rate'], bool: [] },
    policies: { text: ['shipping_es', 'shipping_en', 'returns_es', 'returns_en', 'privacy_es', 'privacy_en'], bool: [] },
    seo: { text: ['global_title', 'global_description', 'og_default_image', 'twitter_site', 'facebook_page_id', 'google_site_verification', 'bing_site_verification', 'ga_id', 'robots_default', 'theme_color'], bool: [] },
    size_guide: { text: ['title_es', 'title_en', 'footer_es', 'footer_en'], bool: [] },
  }

  for (const section of dirtySections) {
    if (section === 'transfer') {
      settings.transfer = { enabled: transfer.enabled, banks: transfer.banks.map(b => ({ ...b })) }
      continue
    }
    if (section === 'landing') {
      settings.landing = JSON.parse(JSON.stringify(landing))
      continue
    }
    if (section === 'size_guide') {
      settings.size_guide = {
        title_es: sizeGuide.title_es ?? '',
        title_en: sizeGuide.title_en ?? '',
        footer_es: sizeGuide.footer_es ?? '',
        footer_en: sizeGuide.footer_en ?? '',
        rows: sizeGuideRows.value.filter(r => r.us || r.eu || r.uk || r.cm),
      }
      continue
    }

    const def = sectionDefs[section]
    if (!def) continue

    const data: Record<string, any> = {}
    const srcMap: Record<string, any> = {
      store, receipt, imagekit, stripe, smtp, shipping, whatsapp, tax, policies, seo, size_guide: sizeGuide,
    }
    const src = srcMap[section]
    if (!src) continue

    for (const field of def.text) {
      const val = src[field]
      if (isSensitive(section, field) && (!val || val === '••••••••')) continue
      data[field] = val ?? ''
    }
    for (const field of def.bool) {
      data[field] = src[field] ?? false
    }
    settings[section] = data
  }

  try {
    const res = await api.post<{ success: boolean; error?: string }>('/api/configuracion/update.php', settings)
    if (!res.success) throw new Error(res.error || 'Error saving')

    clearDirty()
    disableEdit()
    toast.success('Configuración guardada correctamente')
  } catch (err: any) {
    toast.error(err.message)
  }
}

function collectLandingSection(section: string) {
  const s = landingSections[section]
  const data: Record<string, any> = {}
  for (const f of s.fields) {
    data[`${f}_es`] = landing[section]?.[`${f}_es`] ?? ''
    data[`${f}_en`] = landing[section]?.[`${f}_en`] ?? ''
  }
  for (const ext of s.extras) {
    if (ext === 'testimonial_items') {
      data.items = landing[section]?.items || []
    } else if (ext === 'platforms') {
      data.platforms = landing[section]?.platforms || {}
    } else if (ext === 'images') {
      data.images = landing[section]?.images || []
    } else if (ext === 'enabled') {
      data.enabled = landing[section]?.enabled ?? true
    } else if (ext === 'cta_category_slug') {
      data.cta_category_slug = landing[section]?.cta_category_slug ?? ''
    } else if (ext === 'cta_link') {
      data.cta_link = landing[section]?.cta_link ?? ''
    } else if (ext === 'image_url') {
      data.image_url = landing[section]?.image_url ?? ''
    }
  }
  return data
}

const landingFieldLabels: Record<string, string> = {
  label_es: 'ETIQUETA (ES)', label_en: 'LABEL (EN)',
  title_es: 'TÍTULO (ES)', title_en: 'TITLE (EN)',
  subtitle_es: 'SUBTÍTULO (ES)', subtitle_en: 'SUBTITLE (EN)',
  paragraph_es: 'PÁRRAFO (ES)', paragraph_en: 'PARAGRAPH (EN)',
  cta_es: 'CTA (ES)', cta_en: 'CTA (EN)',
  desc_es: 'DESCRIPCIÓN (ES)', desc_en: 'DESCRIPTION (EN)',
  view_all_es: 'VER TODOS (ES)', view_all_en: 'VIEW ALL (EN)',
}
</script>

<template>
  <div v-if="loading" class="p-4 text-[#94a3b8]">Loading settings...</div>

  <div v-else class="admin-enter">
    <!-- Mobile tab select -->
    <select v-model="activeTab" class="md:hidden w-full admin-select mb-8 text-center">
      <option v-for="t in flatTabsList" :key="t.id" :value="t.id">{{ t.label }}</option>
    </select>

    <!-- Desktop/tablet tab bar -->
    <div class="hidden md:block border-b border-[#1e293b] mb-8">
      <div class="grid grid-cols-3 md:grid-cols-5 xl:grid-cols-10 gap-2" role="tablist">
        <button v-for="t in tabs" :key="t.id" role="tab"
                :aria-selected="activeTab === t.id"
                :class="activeTab === t.id
                  ? 'bg-[#42b883] text-white'
                  : 'text-[#94a3b8] hover:bg-white/5 hover:text-[#dae2fd]'"
                class="px-3 py-3 text-xs font-semibold tracking-widest rounded-sm transition-all flex items-center justify-center gap-1.5"
                @click="activeTab = t.id; if (t.children?.length) activeChildTab = t.children[0].id">
          <VunoIcon :icon="t.icon" :size="16" class="shrink-0" />
          <span class="truncate leading-tight text-center">{{ t.label }}</span>
        </button>
      </div>
    </div>

    <!-- Pagos group sub-tabs -->
    <div v-if="activeTab === 'group-pagos'" class="mb-6 -mt-4">
      <div class="grid grid-cols-3 gap-2 border-b border-[#1e293b] pb-3">
        <button v-for="child in (tabs.find(t => t.id === 'group-pagos')?.children || [])" :key="child.id"
                :class="activeChildTab === child.id
                  ? 'bg-[#42b883] text-white'
                  : 'text-[#94a3b8] hover:bg-white/10 hover:text-[#dae2fd] border border-[#1e293b]'"
                class="xs:px-2 sm:px-3 xs:text-[11px] py-2 text-xs font-semibold tracking-widest rounded-sm transition-all flex items-center justify-center gap-1.5"
                @click="activeChildTab = child.id">
          <VunoIcon :icon="child.icon" :size="16" />
          {{ child.label }}
        </button>
      </div>
    </div>

    <!-- Servicios group sub-tabs -->
    <div v-if="activeTab === 'group-servicios'" class="mb-6 -mt-4">
      <div class="grid grid-cols-3 gap-2 border-b border-[#1e293b] pb-3">
        <button v-for="child in (tabs.find(t => t.id === 'group-servicios')?.children || [])" :key="child.id"
                :class="activeChildTab === child.id
                  ? 'bg-[#42b883] text-white'
                  : 'text-[#94a3b8] hover:bg-white/10 hover:text-[#dae2fd] border border-[#1e293b]'"
                class="xs:px-2 sm:px-3 xs:text-[11px] py-2 text-xs font-semibold tracking-widest rounded-sm transition-all flex items-center justify-center gap-1.5"
                @click="activeChildTab = child.id">
          <VunoIcon :icon="child.icon" :size="16" />
          {{ child.label }}
        </button>
      </div>
    </div>

    <!-- Landing group sub-tabs -->
    <div v-if="activeTab === 'group-landing'" class="mb-6 -mt-4">
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 border-b border-[#1e293b] pb-3">
        <button v-for="child in (tabs.find(t => t.id === 'group-landing')?.children || [])" :key="child.id"
                :class="activeChildTab === child.id
                  ? 'bg-[#42b883] text-white shadow-sm shadow-[#42b883]/20'
                  : 'text-[#94a3b8] hover:bg-white/10 hover:text-[#dae2fd] border border-[#1e293b]'"
                class="xs:px-2 sm:px-3 xs:text-[11px] py-2 text-xs font-semibold tracking-widest rounded-sm transition-all flex items-center justify-center gap-1.5"
                @click="activeChildTab = child.id">
          <VunoIcon :icon="child.icon" :size="16" />
          {{ child.label }}
        </button>
      </div>
    </div>

    <!-- Tienda group sub-tabs -->
    <div v-if="activeTab === 'group-tienda'" class="mb-6 -mt-4">
      <div class="grid grid-cols-3 sm:grid-cols-5 gap-2 border-b border-[#1e293b] pb-3">
        <button v-for="child in (tabs.find(t => t.id === 'group-tienda')?.children || [])" :key="child.id"
                :class="activeChildTab === child.id
                  ? 'bg-[#42b883] text-white shadow-sm shadow-[#42b883]/20'
                  : 'text-[#94a3b8] hover:bg-white/10 hover:text-[#dae2fd] border border-[#1e293b]'"
                class="xs:px-2 sm:px-3 xs:text-[11px] py-2 text-xs font-semibold tracking-widest rounded-sm transition-all flex items-center justify-center gap-1.5"
                @click="activeChildTab = child.id">
          <VunoIcon :icon="child.icon" :size="16" />
          {{ child.label }}
        </button>
      </div>
    </div>

    <!-- ================================================================== -->
    <!-- TAB: Información de la Tienda                                       -->
    <!-- ================================================================== -->
    <div v-if="effectiveTab === 'store-info'" :key="'store-info'" class="admin-card p-4 md:p-6 admin-enter">
      <div class="flex items-start gap-4 mb-6">
        <div class="w-10 h-10 rounded-sm bg-[#42b883]/10 flex items-center justify-center shrink-0">
          <VunoIcon icon="store" class="text-[#42b883]" />
        </div>
        <div class="flex-1">
          <h2 class="text-lg font-semibold text-[#dae2fd]">Información de la Tienda</h2>
          <p class="text-sm text-[#94a3b8] mt-1">Datos básicos que identifican tu marca en la tienda pública.</p>
        </div>
        <button v-if="!isEditing('store')" class="admin-btn admin-btn-edit h-11 px-4 shrink-0" @click="enableEdit('store')">
          <VunoIcon icon="edit" :size="16" />
          EDITAR
        </button>
        <span v-else class="badge badge-paid shrink-0 h-fit mt-2">EDITANDO</span>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="md:col-span-2">
          <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">NOMBRE DE LA TIENDA</label>
          <input v-model="store.name" class="admin-input" maxlength="200" placeholder="Vunotek" @input="markDirty('store')" :disabled="!isEditing('store')" />
        </div>
        <div class="md:col-span-2">
          <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">LEMA / SLOGAN</label>
          <input v-model="store.slogan" class="admin-input" maxlength="255" placeholder="Architectural Minimalism in Footwear" @input="markDirty('store')" :disabled="!isEditing('store')" />
        </div>
        <div class="md:col-span-2">
          <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">EMAIL DE CONTACTO</label>
          <input v-model="store.email" type="email" class="admin-input" maxlength="255" placeholder="hola@vunotek.com" @input="markDirty('store')" :disabled="!isEditing('store')" />
        </div>
        <div class="md:col-span-2">
          <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">DESCRIPCIÓN</label>
          <textarea v-model="store.description" class="admin-textarea h-24" maxlength="1000" placeholder="Descripción breve de tu tienda..." @input="markDirty('store')" :disabled="!isEditing('store')"></textarea>
          <p class="text-xs text-[#94a3b8]/60 mt-1 text-right">{{ (store.description || '').length }}/1000</p>
        </div>
      </div>

      <div class="h-px bg-gradient-to-r from-transparent via-[#1e293b] to-transparent my-8"></div>

      <!-- Logo upload -->
      <div>
        <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-3">LOGOTIPO</label>
        <div v-if="!logoUrl" class="border-2 border-dashed border-[#1e293b] hover:border-[#42b883] rounded-sm p-8 text-center cursor-pointer transition-all duration-300 group"
             :class="{ 'opacity-50 pointer-events-none': !isEditing('store') }"
             @click="isEditing('store') && $refs.logoInput?.click()"
             @dragover.prevent="isEditing('store') && ($event.target as HTMLElement).classList.add('!border-[#42b883]', '!bg-[#42b883]/5')"
             @dragleave.prevent="isEditing('store') && ($event.target as HTMLElement).classList.remove('!border-[#42b883]', '!bg-[#42b883]/5')"
             @drop.prevent="isEditing('store') && handleLogoUpload(($event.dataTransfer?.files[0])!)">
          <input ref="logoInput" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="hidden" @change="($event.target as HTMLInputElement).files?.[0] && handleLogoUpload(($event.target as HTMLInputElement).files![0])" />
          <div class="w-16 h-16 rounded-full bg-[#1e293b] flex items-center justify-center mx-auto mb-4 group-hover:bg-[#42b883]/10 transition-colors">
            <VunoIcon icon="image" :size="32" class="text-[#94a3b8] group-hover:text-[#42b883] transition-colors" />
          </div>
          <p class="text-sm text-[#94a3b8] mb-1">Arrastrá el logotipo o <span class="text-[#42b883] underline font-medium">seleccioná un archivo</span></p>
          <p class="text-xs text-[#94a3b8]/60">PNG, JPG, WEBP o SVG — Máx 1 MB — 800×400 px máx</p>
        </div>
        <div v-else class="border border-[#1e293b] rounded-sm p-4 flex flex-col sm:flex-row items-center gap-6" :class="{ 'opacity-50': !isEditing('store') }">
          <div class="w-48 h-20 flex items-center justify-center bg-[#1e293b] rounded-sm overflow-hidden">
            <img :src="logoUrl" class="max-w-full max-h-full object-contain" alt="Logotipo" />
          </div>
          <div class="flex items-center gap-3">
            <button class="text-xs font-semibold tracking-widest text-[#42b883] hover:text-[#dae2fd] transition-colors inline-flex items-center gap-1.5 px-3 py-2 border border-[#1e293b] rounded-sm hover:border-[#42b883]/30" :disabled="!isEditing('store')" @click="$refs.logoInput?.click()">
              <VunoIcon icon="refresh" :size="20" /> REEMPLAZAR
            </button>
            <button class="text-xs font-semibold tracking-widest text-[#DC2626] hover:text-[#ff4444] transition-colors inline-flex items-center gap-1.5 px-3 py-2 border border-[#1e293b] rounded-sm hover:border-[#DC2626]/30" :disabled="!isEditing('store')" @click="removeLogo">
              <VunoIcon icon="delete" :size="20" /> ELIMINAR
            </button>
          </div>
        </div>
        <div v-if="logoUploading" class="mt-3">
          <div class="w-full bg-[#1e293b] rounded-full h-1.5 overflow-hidden">
            <div class="bg-[#42b883] h-1.5 rounded-full transition-all duration-500 ease-out" :style="{ width: Math.max(0, Math.min(100, logoProgress)) + '%' }"></div>
          </div>
          <p class="text-xs text-[#94a3b8] mt-1.5 flex items-center gap-1.5">
            <VunoIcon icon="progress_activity" :size="14" class="animate-spin" />
            Subiendo...
          </p>
        </div>
        <p v-if="logoError" class="text-xs text-[#DC2626] mt-2 flex items-center gap-1">
          <VunoIcon icon="error" :size="14" />
          {{ logoError }}
        </p>
      </div>

      <div class="h-px bg-gradient-to-r from-transparent via-[#1e293b] to-transparent my-8"></div>

      <!-- Newsletter coupon -->
      <div>
        <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2 flex items-center gap-2">
          <VunoIcon icon="confirmation_number" :size="20" />
          CUPÓN DE BIENVENIDA NEWSLETTER
        </label>
        <p class="text-sm text-[#94a3b8]/70 mb-3">Cupón que se asigna automáticamente al suscribirse al newsletter.</p>
        <select v-model="store.newsletter_discount_code" class="admin-select max-w-md" @change="markDirty('store')" :disabled="!isEditing('store')">
          <option value="">— Sin descuento —</option>
          <option v-for="c in coupons" :key="c.code" :value="c.code">
            {{ c.code }} ({{ c.discount_type === 'percentage' ? c.discount_value + '%' : '$' + c.discount_value.toFixed(2) }}){{ c.description ? ' — ' + c.description : '' }}
          </option>
        </select>
      </div>
    </div>

    <!-- ================================================================== -->
    <!-- TAB: Moneda — Divisas                                               -->
    <!-- ================================================================== -->
    <div v-if="effectiveTab === 'store-currency'" :key="'store-currency'" class="admin-card p-4 md:p-6 admin-enter">
      <div class="flex items-start gap-4 mb-6">
        <div class="w-10 h-10 rounded-sm bg-[#42b883]/10 flex items-center justify-center shrink-0">
          <VunoIcon icon="payments" class="text-[#42b883]" />
        </div>
        <div class="flex-1">
          <h2 class="text-lg font-semibold text-[#dae2fd]">Moneda — Divisas</h2>
          <p class="text-sm text-[#94a3b8] mt-1">Seleccioná la moneda principal y configurá las tasas de cambio.</p>
        </div>
        <button v-if="!isEditing('moneda')" class="admin-btn admin-btn-edit h-11 px-4 shrink-0" @click="enableEdit('moneda')">
          <VunoIcon icon="edit" :size="16" />
          EDITAR
        </button>
        <span v-else class="badge badge-paid shrink-0 h-fit mt-2">EDITANDO</span>
      </div>

      <div class="bg-[#1e293b]/40 border border-[#1e293b] rounded-sm p-4 mb-6 flex items-start gap-3">
        <VunoIcon icon="info" :size="20" class="text-[#42b883] shrink-0 mt-px" />
        <p class="text-xs text-[#94a3b8] leading-relaxed">
          Los precios se almacenan en USD y se convierten a la moneda seleccionada usando la tasa de cambio.
          La moneda base de referencia es USD con tasa fija 1.0.
        </p>
      </div>

      <div class="mb-8">
        <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">MONEDA PRINCIPAL DE LA TIENDA</label>
        <div class="relative w-full md:w-auto md:inline-block">
          <select v-model="storeCurrency" class="admin-select w-full md:w-auto md:min-w-[280px]" @change="changeStoreCurrency(storeCurrency)" :disabled="!isEditing('moneda')">
            <option value="">— Seleccionar moneda —</option>
            <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.code }} — {{ c.name }} ({{ c.symbol }})</option>
          </select>
        </div>
      </div>

      <div>
        <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-3">TASAS DE CAMBIO (vs USD)</label>

        <!-- Mobile: cards -->
        <div class="md:hidden space-y-5">
          <div v-for="c in currencies" :key="c.code"
               class="glass-card overflow-hidden rounded-xl"
               :class="c.code === storeCurrency ? 'ring-1 ring-[#42b883]/30' : ''">

            <!-- Header -->
            <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-[#dae2fd]/5">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-mono text-sm font-semibold text-[#dae2fd]">{{ c.code }}</span>
                <span v-if="c.code === storeCurrency" class="badge badge-paid">ACTIVA</span>
                <span v-if="c.code === 'USD'" class="text-[10px] text-[#94a3b8] bg-[#1e293b]/60 px-2 py-0.5 rounded-sm font-semibold">BASE</span>
              </div>
              <VunoIcon v-if="c.code === storeCurrency" icon="check_circle" :size="24" class="text-[#42b883]" />
            </div>

            <!-- Body -->
            <div class="px-6 py-4 space-y-4">
              <div>
                <p class="text-sm text-[#94a3b8]">{{ c.name }}</p>
                <p class="font-mono text-[#dae2fd] text-2xl mt-1">{{ c.symbol }}</p>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="text-[10px] font-semibold tracking-widest text-[#94a3b8] uppercase block mb-1.5">TASA (1 USD =)</label>
                   <input type="number" step="0.000001" min="0.000001" v-model="c.exchange_rate"
                          class="w-full bg-[#1e293b]/60 border border-[#dae2fd]/10 rounded-sm px-3 py-2 text-[#dae2fd] focus:border-[#42b883] focus:outline-none text-sm font-mono transition-colors"
                          @input="saveCurrencyRates; markDirty('moneda')"
                          :disabled="c.code === 'USD' || !isEditing('moneda')" />
                </div>
                <div>
                  <label class="text-[10px] font-semibold tracking-widest text-[#94a3b8] uppercase block mb-1.5">DECIMALES</label>
                  <p class="text-sm text-[#94a3b8] pt-2">{{ c.decimal_places }}</p>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between px-6 pb-5 pt-4 border-t border-[#dae2fd]/5">
              <label class="admin-toggle-label">
                <label class="admin-toggle">
                   <input type="checkbox" v-model="c.is_active"
                          class="accent-[#42b883]"
                          :disabled="c.code === storeCurrency || !isEditing('moneda')"
                          @change="saveCurrencyRates; markDirty('moneda')" />
                  <div></div>
                </label>
                <span class="text-[11px] font-semibold tracking-widest uppercase whitespace-nowrap" :class="c.is_active ? 'text-[#42b883]' : 'text-[#94a3b8]'">{{ c.is_active ? 'ACTIVA' : 'INACTIVA' }}</span>
              </label>
              <button v-if="c.code !== 'USD'"
                      class="w-11 h-11 flex items-center justify-center shrink-0 text-[#94a3b8] hover:text-[#DC2626] hover:bg-[#DC2626]/10 rounded-sm transition-all touch-target"
                      :disabled="!isEditing('moneda')"
                      @click="deleteCurrency(c.code)" title="Desactivar moneda">
                <VunoIcon icon="delete" :size="24" />
              </button>
            </div>
          </div>
        </div>

        <!-- Desktop/tablet: table -->
        <div class="hidden md:block overflow-x-auto rounded-sm border border-[#1e293b]">
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="bg-[#0f1929] border-b border-[#1e293b]">
                <th class="text-xs font-semibold tracking-widest text-[#94a3b8] py-3 px-4">Código</th>
                <th class="text-xs font-semibold tracking-widest text-[#94a3b8] py-3 px-4">Nombre</th>
                <th class="text-xs font-semibold tracking-widest text-[#94a3b8] py-3 px-4">Símbolo</th>
                <th class="text-xs font-semibold tracking-widest text-[#94a3b8] py-3 px-4">Tasa (1 USD =)</th>
                <th class="text-xs font-semibold tracking-widest text-[#94a3b8] py-3 px-4 text-center">Decimales</th>
                <th class="text-xs font-semibold tracking-widest text-[#94a3b8] py-3 px-4 text-center">Activa</th>
                <th class="text-xs font-semibold tracking-widest text-[#94a3b8] py-3 px-4 text-center">Acción</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in currencies" :key="c.code"
                  class="border-b border-[#1e293b]/50 hover:bg-white/[0.015] transition-colors"
                  :class="{ 'bg-[#42b883]/[0.03]': c.code === storeCurrency }">
                <td class="py-3 px-4">
                  <div class="flex items-center gap-2">
                    <span class="font-mono text-sm font-semibold text-[#dae2fd]">{{ c.code }}</span>
                    <span v-if="c.code === storeCurrency" class="badge badge-paid text-[10px]">ACTIVA</span>
                    <span v-if="c.code === 'USD'" class="text-[10px] text-[#94a3b8] bg-[#1e293b] px-1.5 py-0.5 rounded-sm font-semibold">BASE</span>
                  </div>
                </td>
                <td class="py-3 px-4 text-[#dae2fd]">{{ c.name }}</td>
                <td class="py-3 px-4 font-mono text-[#dae2fd] text-lg">{{ c.symbol }}</td>
                <td class="py-3 px-4">
                   <input type="number" step="0.000001" min="0.000001" v-model="c.exchange_rate"
                          class="w-28 bg-transparent border-b-2 border-[#1e293b] pb-1 text-[#dae2fd] focus:border-[#42b883] focus:outline-none text-sm font-mono transition-colors"
                          @input="saveCurrencyRates; markDirty('moneda')"
                          :disabled="c.code === 'USD' || !isEditing('moneda')" />
                </td>
                <td class="py-3 px-4 text-center text-[#94a3b8]">{{ c.decimal_places }}</td>
                <td class="py-3 px-4 text-center">
                  <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" v-model="c.is_active"
                           class="w-4 h-4 accent-[#42b883] cursor-pointer"
                           :disabled="c.code === storeCurrency || !isEditing('moneda')"
                           @change="saveCurrencyRates; markDirty('moneda')" />
                  </label>
                </td>
                <td class="py-3 px-4 text-center">
                  <button v-if="c.code !== 'USD'"
                          class="w-8 h-8 flex items-center justify-center text-[#94a3b8] hover:text-[#DC2626] hover:bg-[#DC2626]/10 rounded-sm transition-all"
                          :disabled="!isEditing('moneda')"
                          @click="deleteCurrency(c.code)" title="Desactivar moneda">
                    <VunoIcon icon="delete" :size="20" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <button class="mt-4 text-xs font-semibold tracking-widest text-[#94a3b8] hover:text-[#42b883] inline-flex items-center gap-1.5 transition-all px-3 py-2 border border-[#1e293b] rounded-sm hover:border-[#42b883]/30" :disabled="!isEditing('moneda')" @click="showCurrencyModal = true">
          <VunoIcon icon="add_circle" :size="20" /> AGREGAR MONEDA
        </button>
      </div>
    </div>

    <!-- ================================================================== -->
    <!-- TAB: Impuestos — IVA                                                -->
    <!-- ================================================================== -->
    <div v-if="effectiveTab === 'store-tax'" :key="'store-tax'" class="admin-card p-4 md:p-6 admin-enter">
      <div class="flex items-start gap-4 mb-6">
        <div class="w-10 h-10 rounded-sm bg-[#42b883]/10 flex items-center justify-center shrink-0">
          <VunoIcon icon="receipt_long" class="text-[#42b883]" />
        </div>
        <div class="flex-1">
          <h2 class="text-lg font-semibold text-[#dae2fd]">Impuestos — IVA / Tax Rate</h2>
          <p class="text-sm text-[#94a3b8] mt-1">Configuración del impuesto aplicado a los pedidos.</p>
        </div>
        <button v-if="!isEditing('tax')" class="admin-btn admin-btn-edit h-11 px-4 shrink-0" @click="enableEdit('tax')">
          <VunoIcon icon="edit" :size="16" />
          EDITAR
        </button>
        <span v-else class="badge badge-paid shrink-0 h-fit mt-2">EDITANDO</span>
      </div>

      <div class="bg-[#1e293b]/40 border border-[#1e293b] rounded-sm p-4 mb-8 flex items-start gap-3">
        <VunoIcon icon="info" :size="20" class="text-[#42b883] shrink-0 mt-px" />
        <div class="text-xs text-[#94a3b8] leading-relaxed">
          <p>El impuesto se calcula sobre el <strong class="text-[#dae2fd]">subtotal después de descuentos</strong> y se suma al total del pedido.</p>
          <p v-if="tax.rate && tax.rate > 0" class="mt-2">
            Ejemplo: Subtotal $100.00 + IVA ({{ tax.rate }}%) = <strong class="text-[#42b883]">${{ (100 * (1 + tax.rate / 100)).toFixed(2) }}</strong>
          </p>
          <p v-else class="mt-2 text-[#ffa500]/70">⚠ Impuesto desactivado — no se aplicará recargo en los pedidos.</p>
        </div>
      </div>

      <div class="max-w-xs">
        <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">TASA DE IMPUESTO (%)</label>
        <div class="flex items-center gap-3">
          <div class="relative flex-1">
            <input v-model.number="tax.rate" type="number" step="0.01" min="0" max="100" class="admin-input pr-10" placeholder="15" @input="markDirty('tax')" :disabled="!isEditing('tax')" />
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[#94a3b8] font-semibold text-sm pointer-events-none">%</span>
          </div>
          <span v-if="tax.rate && tax.rate > 0" class="text-xs text-[#94a3b8] bg-[#1e293b] px-3 py-2 rounded-sm whitespace-nowrap">
            IVA: {{ tax.rate }}%
          </span>
          <span v-else class="text-xs text-[#94a3b8]/50 bg-[#1e293b] px-3 py-2 rounded-sm whitespace-nowrap">
            Sin impuesto
          </span>
        </div>
      </div>
    </div>

    <!-- ================================================================== -->
    <!-- TAB: Recibo — Datos Fiscales                                       -->
    <!-- ================================================================== -->
    <div v-if="effectiveTab === 'store-receipt'" :key="'store-receipt'" class="admin-card p-4 md:p-6 admin-enter">
      <div class="flex items-start gap-4 mb-6">
        <div class="w-10 h-10 rounded-sm bg-[#42b883]/10 flex items-center justify-center shrink-0">
          <VunoIcon icon="receipt" class="text-[#42b883]" />
        </div>
        <div class="flex-1">
          <h2 class="text-lg font-semibold text-[#dae2fd]">Recibo — Datos de Facturación</h2>
          <p class="text-sm text-[#94a3b8] mt-1">Datos fiscales que aparecen en los recibos de compra.</p>
        </div>
        <button v-if="!isEditing('receipt')" class="admin-btn admin-btn-edit h-11 px-4 shrink-0" @click="enableEdit('receipt')">
          <VunoIcon icon="edit" :size="16" />
          EDITAR
        </button>
        <span v-else class="badge badge-paid shrink-0 h-fit mt-2">EDITANDO</span>
      </div>

      <div class="bg-[#1e293b]/40 border border-[#1e293b] rounded-sm p-4 mb-6 flex items-start gap-3">
        <VunoIcon icon="info" :size="20" class="text-[#42b883] shrink-0 mt-px" />
        <p class="text-xs text-[#94a3b8] leading-relaxed">
          Estos datos se muestran en los comprobantes de pago y facturas. Completalos según la información fiscal de tu negocio.
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="md:col-span-2">
          <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">RAZÓN SOCIAL</label>
          <input v-model="receipt.businessName" class="admin-input" maxlength="255" @input="markDirty('receipt')" :disabled="!isEditing('receipt')" />
        </div>
        <div class="md:col-span-2">
          <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">RUC / NIT / TAX ID</label>
          <input v-model="receipt.taxId" class="admin-input" maxlength="50" @input="markDirty('receipt')" :disabled="!isEditing('receipt')" />
        </div>
        <div class="md:col-span-2">
          <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">DIRECCIÓN</label>
          <input v-model="receipt.address" class="admin-input" maxlength="500" @input="markDirty('receipt')" :disabled="!isEditing('receipt')" />
        </div>
        <div>
          <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">CIUDAD</label>
          <input v-model="receipt.city" class="admin-input" maxlength="100" @input="markDirty('receipt')" :disabled="!isEditing('receipt')" />
        </div>
        <div>
          <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">ESTADO / PROVINCIA</label>
          <input v-model="receipt.state" class="admin-input" maxlength="100" @input="markDirty('receipt')" :disabled="!isEditing('receipt')" />
        </div>
        <div>
          <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">CÓDIGO POSTAL</label>
          <input v-model="receipt.zip" class="admin-input" maxlength="20" @input="markDirty('receipt')" :disabled="!isEditing('receipt')" />
        </div>
        <div>
          <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">TELÉFONO</label>
          <input v-model="receipt.phone" class="admin-input" maxlength="50" @input="markDirty('receipt')" :disabled="!isEditing('receipt')" />
        </div>
      </div>
    </div>

    <!-- ImageKit Tab -->
    <div v-if="effectiveTab === 'imagekit'" class="admin-card p-4 md:p-6">
      <div class="flex items-start justify-between mb-4">
        <div>
          <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
            <VunoIcon icon="photo_library" :size="24" />
            ImageKit — Gestión de Imágenes
          </h2>
          <p class="text-sm text-[#94a3b8] mt-1">Configuración del servicio de gestión de imágenes.</p>
        </div>
        <button v-if="!isEditing('imagekit')" class="admin-btn admin-btn-edit h-11 px-4 shrink-0" @click="enableEdit('imagekit')">
          <VunoIcon icon="edit" :size="16" />
          EDITAR
        </button>
        <span v-else class="badge badge-paid shrink-0 h-fit mt-2">EDITANDO</span>
      </div>
      <div class="mb-6 p-3 bg-[#1e293b] border-l-2 border-[#42b883] rounded-sm">
        <p class="text-xs text-[#94a3b8]">ImageKit para subir y optimizar imágenes. Creá cuenta gratis en <a href="https://imagekit.io" class="text-[#42b883] underline" target="_blank">imagekit.io</a>.</p>
      </div>
      <div class="space-y-6">
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">PUBLIC KEY</label>
          <input v-model="imagekit.publicKey" class="admin-input font-mono text-sm" maxlength="255" @input="markDirty('imagekit')" :disabled="!isEditing('imagekit')" /></div>
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">PRIVATE KEY</label>
          <input v-model="imagekit.privateKey" type="password" autocomplete="new-password" class="admin-input font-mono text-sm" maxlength="255" @input="markDirty('imagekit')" :disabled="!isEditing('imagekit')" /></div>
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">URL ENDPOINT</label>
          <input v-model="imagekit.urlEndpoint" class="admin-input font-mono text-sm" maxlength="500" placeholder="https://ik.imagekit.io/your_id" @input="markDirty('imagekit')" :disabled="!isEditing('imagekit')" /></div>
      </div>
    </div>

    <!-- Stripe Tab -->
    <div v-if="effectiveTab === 'stripe'" class="admin-card p-4 md:p-6">
      <div class="flex items-start justify-between mb-2">
        <div>
          <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
            <VunoIcon icon="credit_card" :size="24" />
            Stripe — Pasarela de Pago
          </h2>
          <p class="text-sm text-[#94a3b8] mt-1">Configuración de la pasarela de pagos con tarjeta.</p>
        </div>
        <button v-if="!isEditing('stripe')" class="admin-btn admin-btn-edit h-11 px-4 shrink-0" @click="enableEdit('stripe')">
          <VunoIcon icon="edit" :size="16" />
          EDITAR
        </button>
        <span v-else class="badge badge-paid shrink-0 h-fit mt-2">EDITANDO</span>
      </div>
      <div class="mb-6 p-3 bg-[#1e293b] border-l-2 border-[#42b883] rounded-sm">
        <p class="text-xs text-[#94a3b8]">Stripe permite pagos con tarjeta. Activá el toggle para habilitarlo.</p>
      </div>
      <div class="flex items-center justify-between pb-6 mb-6 border-b border-[#1e293b]">
        <div>
          <p class="font-medium text-[#dae2fd]">Habilitar Stripe</p>
          <p class="text-xs text-[#94a3b8]">Permite pagos con tarjeta vía Stripe</p>
        </div>
        <div class="flex items-center gap-3">
          <label class="admin-toggle"><input v-model="stripe.enabled" type="checkbox" @change="markDirty('stripe')" :disabled="!isEditing('stripe')" /><div></div></label>
          <span class="text-[11px] font-semibold tracking-widest uppercase whitespace-nowrap" :class="stripe.enabled ? 'text-[#42b883]' : 'text-[#94a3b8]'">{{ stripe.enabled ? 'ACTIVO' : 'INACTIVO' }}</span>
        </div>
      </div>
      <div class="space-y-6">
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">PUBLISHABLE KEY</label>
          <input v-model="stripe.publishableKey" class="admin-input font-mono text-sm" maxlength="255" placeholder="pk_live_..." @input="markDirty('stripe')" :disabled="!isEditing('stripe')" /></div>
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">SECRET KEY</label>
          <input v-model="stripe.secretKey" type="password" class="admin-input font-mono text-sm" maxlength="255" placeholder="sk_live_..." @input="markDirty('stripe')" :disabled="!isEditing('stripe')" /></div>
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">WEBHOOK SECRET</label>
          <input v-model="stripe.webhookSecret" type="password" class="admin-input font-mono text-sm" maxlength="255" placeholder="whsec_..." @input="markDirty('stripe')" :disabled="!isEditing('stripe')" /></div>
      </div>
    </div>

    <!-- Transfer Tab -->
    <div v-if="effectiveTab === 'transfer'" class="admin-card p-4 md:p-6">
      <div class="flex items-start justify-between mb-4">
        <div>
          <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
            <VunoIcon icon="account_balance" :size="24" />
            Transferencia Bancaria
          </h2>
          <p class="text-sm text-[#94a3b8] mt-1">Método offline: el cliente recibe datos bancarios al finalizar la compra.</p>
        </div>
        <button v-if="!isEditing('transfer')" class="admin-btn admin-btn-edit h-11 px-4 shrink-0" @click="enableEdit('transfer')">
          <VunoIcon icon="edit" :size="16" />
          EDITAR
        </button>
        <span v-else class="badge badge-paid shrink-0 h-fit mt-2">EDITANDO</span>
      </div>
      <div class="mb-6 p-3 bg-[#1e293b] border-l-2 border-[#42b883] rounded-sm">
        <p class="text-xs text-[#94a3b8]">Método offline: el cliente recibe datos bancarios al finalizar la compra.</p>
      </div>
      <div class="flex items-center justify-between pb-6 mb-6 border-b border-[#1e293b]">
        <div>
          <p class="font-medium text-[#dae2fd]">Habilitar Transferencia</p>
          <p class="text-xs text-[#94a3b8]">Permite pagos mediante transferencia bancaria</p>
        </div>
        <div class="flex items-center gap-3">
          <label class="admin-toggle"><input v-model="transfer.enabled" type="checkbox" @change="markDirty('transfer')" :disabled="!isEditing('transfer')" /><div></div></label>
          <span class="text-[11px] font-semibold tracking-widest uppercase whitespace-nowrap" :class="transfer.enabled ? 'text-[#42b883]' : 'text-[#94a3b8]'">{{ transfer.enabled ? 'ACTIVO' : 'INACTIVO' }}</span>
        </div>
      </div>
      <div class="space-y-6">
        <div v-for="(bank, i) in transfer.banks" :key="i" class="border border-[#1e293b] rounded-sm p-4 relative">
          <button class="absolute top-2 right-2 w-10 h-10 flex items-center justify-center text-[#94a3b8] hover:text-[#DC2626] transition-colors" :disabled="!isEditing('transfer')" @click="removeBank(i)"><VunoIcon icon="close" :size="20" /></button>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-1">NOMBRE DEL BANCO</label>
              <input v-model="bank.bankName" class="admin-input-sm" maxlength="200" @input="markDirty('transfer')" :disabled="!isEditing('transfer')" /></div>
            <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-1">TITULAR</label>
              <input v-model="bank.accountHolder" class="admin-input-sm" maxlength="200" @input="markDirty('transfer')" :disabled="!isEditing('transfer')" /></div>
            <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-1">NÚMERO DE CUENTA</label>
              <input v-model="bank.accountNumber" class="admin-input-sm font-mono" maxlength="50" @input="markDirty('transfer')" :disabled="!isEditing('transfer')" /></div>
            <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-1">TIPO DE CUENTA</label>
              <select v-model="bank.accountType" class="admin-select-sm" @change="markDirty('transfer')" :disabled="!isEditing('transfer')">
                <option value="">Seleccionar...</option><option value="Corriente">Corriente</option>
                <option value="Ahorro">Ahorro</option><option value="Vista">Vista</option>
              </select></div>
            <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-1">RUT / CÓDIGO</label>
              <input v-model="bank.routingNumber" class="admin-input-sm font-mono" maxlength="50" @input="markDirty('transfer')" :disabled="!isEditing('transfer')" /></div>
            <div class="md:col-span-2"><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-1">INSTRUCCIONES</label>
              <textarea v-model="bank.instructions" class="admin-textarea-sm h-16" maxlength="500" @input="markDirty('transfer')" :disabled="!isEditing('transfer')"></textarea></div>
          </div>
        </div>
      </div>
      <button class="mt-4 text-xs font-semibold tracking-widest text-[#94a3b8] hover:text-[#dae2fd] inline-flex items-center gap-1.5 transition-colors" :disabled="!isEditing('transfer')" @click="addBank">
        <VunoIcon icon="add_circle" :size="20" /> AGREGAR BANCO
      </button>
    </div>

    <!-- SMTP Tab -->
    <div v-if="effectiveTab === 'smtp'" class="admin-card p-4 md:p-6">
      <div class="flex items-start justify-between mb-4">
        <div>
          <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
            <VunoIcon icon="mail" :size="24" />
            Email / SMTP — Notificaciones
          </h2>
          <p class="text-sm text-[#94a3b8] mt-1">Configuración SMTP para emails transaccionales.</p>
        </div>
        <button v-if="!isEditing('smtp')" class="admin-btn admin-btn-edit h-11 px-4 shrink-0" @click="enableEdit('smtp')">
          <VunoIcon icon="edit" :size="16" />
          EDITAR
        </button>
        <span v-else class="badge badge-paid shrink-0 h-fit mt-2">EDITANDO</span>
      </div>
      <div class="mb-6 p-3 bg-[#1e293b] border-l-2 border-[#42b883] rounded-sm">
        <p class="text-xs text-[#94a3b8]">Configuración SMTP para emails transaccionales.</p>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="md:col-span-2"><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">SMTP HOST</label>
          <input v-model="smtp.host" class="admin-input font-mono text-sm" maxlength="255" placeholder="smtp.gmail.com" @input="markDirty('smtp')" :disabled="!isEditing('smtp')" /></div>
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">SMTP PORT</label>
          <input v-model="smtp.port" class="admin-input font-mono text-sm" maxlength="10" placeholder="587" @input="markDirty('smtp')" :disabled="!isEditing('smtp')" /></div>
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">SMTP USER</label>
          <input v-model="smtp.user" class="admin-input font-mono text-sm" maxlength="255" @input="markDirty('smtp')" :disabled="!isEditing('smtp')" /></div>
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">SMTP PASSWORD</label>
          <input v-model="smtp.pass" type="password" class="admin-input font-mono text-sm" maxlength="255" @input="markDirty('smtp')" :disabled="!isEditing('smtp')" /></div>
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">FROM EMAIL</label>
          <input v-model="smtp.fromEmail" type="email" class="admin-input" maxlength="255" placeholder="tienda@vuno.com" @input="markDirty('smtp')" :disabled="!isEditing('smtp')" /></div>
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">FROM NAME</label>
          <input v-model="smtp.fromName" class="admin-input" maxlength="100" placeholder="Vunotek" @input="markDirty('smtp')" :disabled="!isEditing('smtp')" /></div>
        <div class="md:col-span-2"><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">EMAIL DE NOTIFICACIONES</label>
          <input v-model="smtp.adminEmail" type="email" class="admin-input" maxlength="255" placeholder="admin@vunotek.com" @input="markDirty('smtp')" :disabled="!isEditing('smtp')" />
          <p class="text-xs text-[#94a3b8]/60 mt-1">Destino de notificaciones de nuevos pedidos y formulario de contacto.</p></div>
      </div>
    </div>



    <!-- Shipping Tab -->
    <div v-if="effectiveTab === 'shipping'" class="admin-card p-4 md:p-6">
      <div class="flex items-start justify-between mb-4">
        <div>
          <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
            <VunoIcon icon="local_shipping" :size="24" />
            Envío — Tarifa Plana
          </h2>
          <p class="text-sm text-[#94a3b8] mt-1">Configurá el costo de envío para los pedidos.</p>
        </div>
        <button v-if="!isEditing('shipping')" class="admin-btn admin-btn-edit h-11 px-4 shrink-0" @click="enableEdit('shipping')">
          <VunoIcon icon="edit" :size="16" />
          EDITAR
        </button>
        <span v-else class="badge badge-paid shrink-0 h-fit mt-2">EDITANDO</span>
      </div>
      <div class="mb-6 p-3 bg-[#1e293b] border-l-2 border-[#42b883] rounded-sm">
        <p class="text-xs text-[#94a3b8]">Configurá el costo de envío. Desactivá para envío siempre gratuito.</p>
      </div>
      <div class="flex items-center justify-between pb-6 mb-6 border-b border-[#1e293b]">
        <div><p class="font-medium text-[#dae2fd]">Cobrar envío</p><p class="text-xs text-[#94a3b8]">Activar tarifa de envío en el checkout</p></div>
        <div class="flex items-center gap-3">
          <label class="admin-toggle"><input v-model="shipping.enabled" type="checkbox" @change="markDirty('shipping')" :disabled="!isEditing('shipping')" /><div></div></label>
          <span class="text-[11px] font-semibold tracking-widest uppercase whitespace-nowrap" :class="shipping.enabled ? 'text-[#42b883]' : 'text-[#94a3b8]'">{{ shipping.enabled ? 'ACTIVO' : 'INACTIVO' }}</span>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">TARIFA PLANA ($)</label>
          <input v-model.number="shipping.base_rate" type="number" step="0.01" min="0" class="admin-input" placeholder="15.00" @input="markDirty('shipping')" :disabled="!isEditing('shipping')" />
          <p class="text-xs text-[#94a3b8] mt-1">Monto fijo de envío para todos los pedidos.</p></div>
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">GRATIS DESDE ($)</label>
          <input v-model.number="shipping.free_above" type="number" step="0.01" min="0" class="admin-input" placeholder="200.00" @input="markDirty('shipping')" :disabled="!isEditing('shipping')" />
          <p class="text-xs text-[#94a3b8] mt-1">Si el subtotal supera este monto, el envío es gratis.</p></div>
        <div class="md:col-span-2"><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">TIEMPO ESTIMADO DE ENTREGA</label>
          <input v-model="shipping.estimated_days" class="admin-input" maxlength="100" placeholder="5-7 días hábiles" @input="markDirty('shipping')" :disabled="!isEditing('shipping')" /></div>
      </div>
    </div>

    <!-- WhatsApp Tab -->
    <div v-if="effectiveTab === 'whatsapp'" class="admin-card p-4 md:p-6">
      <div class="flex items-start justify-between mb-4">
        <div>
          <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
            <VunoIcon icon="chat" :size="24" />
            WhatsApp — Chat Flotante
          </h2>
          <p class="text-sm text-[#94a3b8] mt-1">Botón flotante en la esquina inferior derecha de la tienda.</p>
        </div>
        <button v-if="!isEditing('whatsapp')" class="admin-btn admin-btn-edit h-11 px-4 shrink-0" @click="enableEdit('whatsapp')">
          <VunoIcon icon="edit" :size="16" />
          EDITAR
        </button>
        <span v-else class="badge badge-paid shrink-0 h-fit mt-2">EDITANDO</span>
      </div>
      <div class="mb-6 p-3 bg-[#1e293b] border-l-2 border-[#42b883] rounded-sm">
        <p class="text-xs text-[#94a3b8]">Botón flotante en la esquina inferior derecha de la tienda.</p>
      </div>
      <div class="flex items-center justify-between pb-6 mb-6 border-b border-[#1e293b]">
        <div><p class="font-medium text-[#dae2fd]">Habilitar WhatsApp</p><p class="text-xs text-[#94a3b8]">Muestra el botón flotante</p></div>
        <div class="flex items-center gap-3">
          <label class="admin-toggle"><input v-model="whatsapp.enabled" type="checkbox" @change="markDirty('whatsapp')" :disabled="!isEditing('whatsapp')" /><div></div></label>
          <span class="text-[11px] font-semibold tracking-widest uppercase whitespace-nowrap" :class="whatsapp.enabled ? 'text-[#42b883]' : 'text-[#94a3b8]'">{{ whatsapp.enabled ? 'ACTIVO' : 'INACTIVO' }}</span>
        </div>
      </div>
      <div class="space-y-6">
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">NÚMERO DE WHATSAPP</label>
          <input v-model="whatsapp.number" class="admin-input font-mono text-sm" maxlength="20" placeholder="50588888888" @input="markDirty('whatsapp')" :disabled="!isEditing('whatsapp')" />
          <p class="text-xs text-[#94a3b8] mt-1">Formato internacional sin +. Ej: 50588888888</p></div>
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">MENSAJE PREDEFINIDO</label>
          <textarea v-model="whatsapp.message" class="admin-textarea h-20" maxlength="500" placeholder="Hola, quiero consultar sobre..." @input="markDirty('whatsapp')" :disabled="!isEditing('whatsapp')"></textarea></div>
      </div>
    </div>



    <!-- Policies Tab -->
    <div v-if="effectiveTab === 'policies'" class="admin-card p-4 md:p-6">
      <div class="flex items-start justify-between mb-2">
        <div>
          <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
            <VunoIcon icon="policy" :size="24" />
            Políticas
          </h2>
          <p class="text-sm text-[#94a3b8] mt-1">Textos legales que se muestran en los modales del footer.</p>
        </div>
        <button v-if="!isEditing('policies')" class="admin-btn admin-btn-edit h-11 px-4 shrink-0" @click="enableEdit('policies')">
          <VunoIcon icon="edit" :size="16" />
          EDITAR
        </button>
        <span v-else class="badge badge-paid shrink-0 h-fit mt-2">EDITANDO</span>
      </div>
      <div class="space-y-6">
        <div v-for="section in [{key:'shipping',label:'ENVÍOS / SHIPPING'},{key:'returns',label:'DEVOLUCIONES / RETURNS'},{key:'privacy',label:'PRIVACIDAD / PRIVACY'}]" :key="section.key">
          <h3 class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase mb-3 pb-1 border-b border-[#1e293b]">{{ section.label }}</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">ES</label>
              <textarea :value="(policies as any)[`${section.key}_es`]" @input="(policies as any)[`${section.key}_es`] = ($event.target as HTMLTextAreaElement).value; markDirty('policies')"
                        class="admin-textarea h-28" maxlength="2000" :disabled="!isEditing('policies')"></textarea></div>
            <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">EN</label>
              <textarea :value="(policies as any)[`${section.key}_en`]" @input="(policies as any)[`${section.key}_en`] = ($event.target as HTMLTextAreaElement).value; markDirty('policies')"
                        class="admin-textarea h-28" maxlength="2000" :disabled="!isEditing('policies')"></textarea></div>
          </div>
        </div>
      </div>
    </div>

    <!-- SEO Tab -->
    <div v-if="effectiveTab === 'seo'" class="admin-card p-4 md:p-6">
      <div class="flex items-start justify-between mb-2">
        <div>
          <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
            <VunoIcon icon="travel_explore" :size="24" />
            SEO — Optimización para Motores de Búsqueda
          </h2>
          <p class="text-sm text-[#94a3b8] mt-1">Configuración global de SEO. Fallback si una página no define los suyos.</p>
        </div>
        <button v-if="!isEditing('seo')" class="admin-btn admin-btn-edit h-11 px-4 shrink-0" @click="enableEdit('seo')">
          <VunoIcon icon="edit" :size="16" />
          EDITAR
        </button>
        <span v-else class="badge badge-paid shrink-0 h-fit mt-2">EDITANDO</span>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="md:col-span-2"><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">TÍTULO GLOBAL (fallback)</label>
          <input v-model="seo.global_title" class="admin-input" maxlength="200" placeholder="Vunotek | Calzado Artesanal" @input="markDirty('seo')" :disabled="!isEditing('seo')" /></div>
        <div class="md:col-span-2"><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">DESCRIPCIÓN GLOBAL (fallback)</label>
          <textarea v-model="seo.global_description" class="admin-textarea h-24" maxlength="500" placeholder="Calzado artesanal para damas con diseño minimalista." @input="markDirty('seo')" :disabled="!isEditing('seo')"></textarea></div>
        <div class="md:col-span-2"><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">IMAGEN OG POR DEFECTO</label>
          <input v-model="seo.og_default_image" class="admin-input font-mono text-sm" maxlength="500" placeholder="https://ik.imagekit.io/vunotek/og-default.jpg" @input="markDirty('seo')" :disabled="!isEditing('seo')" /></div>
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">TWITTER / X (@handle)</label>
          <input v-model="seo.twitter_site" class="admin-input" maxlength="100" placeholder="@vunotek" @input="markDirty('seo')" :disabled="!isEditing('seo')" /></div>
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">FACEBOOK PAGE ID</label>
          <input v-model="seo.facebook_page_id" class="admin-input" maxlength="100" @input="markDirty('seo')" :disabled="!isEditing('seo')" /></div>
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">GOOGLE SITE VERIFICATION</label>
          <input v-model="seo.google_site_verification" class="admin-input font-mono text-sm" maxlength="200" @input="markDirty('seo')" :disabled="!isEditing('seo')" /></div>
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">BING SITE VERIFICATION</label>
          <input v-model="seo.bing_site_verification" class="admin-input font-mono text-sm" maxlength="200" @input="markDirty('seo')" :disabled="!isEditing('seo')" /></div>
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">GOOGLE ANALYTICS ID</label>
          <input v-model="seo.ga_id" class="admin-input font-mono text-sm" maxlength="50" placeholder="G-XXXXXXXX" @input="markDirty('seo')" :disabled="!isEditing('seo')" /></div>
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">META ROBOTS GLOBAL</label>
          <select v-model="seo.robots_default" class="admin-select" @change="markDirty('seo')" :disabled="!isEditing('seo')">
            <option value="index,follow">index, follow</option>
            <option value="noindex,nofollow">noindex, nofollow</option>
          </select></div>
        <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">THEME COLOR</label>
          <input v-model="seo.theme_color" class="admin-input font-mono text-sm" maxlength="20" placeholder="#1A1A1A" @input="markDirty('seo')" :disabled="!isEditing('seo')" /></div>
      </div>
    </div>

    <!-- ================================================================== -->
    <!-- TAB: Landing — Página Principal                                    -->
    <!-- ================================================================== -->
    <div v-if="activeTab === 'group-landing'" :key="'group-landing'" class="admin-card p-4 md:p-6 admin-enter">
      <div class="flex items-start gap-4 mb-6">
        <div class="w-10 h-10 rounded-sm bg-[#42b883]/10 flex items-center justify-center shrink-0">
          <VunoIcon icon="dashboard_customize" class="text-[#42b883]" />
        </div>
        <div class="flex-1">
          <h2 class="text-lg font-semibold text-[#dae2fd]">Landing — Página Principal</h2>
          <p class="text-sm text-[#94a3b8] mt-1">Personalizá cada sección de la home. Campos vacíos usan valores por defecto.</p>
        </div>
        <button v-if="!isEditing('landing')" class="admin-btn admin-btn-edit h-11 px-4 shrink-0" @click="enableEdit('landing')">
          <VunoIcon icon="edit" :size="16" />
          EDITAR
        </button>
        <span v-else class="badge badge-paid shrink-0 h-fit mt-2">EDITANDO</span>
      </div>

      <div class="bg-[#1e293b]/40 border border-[#1e293b] rounded-sm p-4 mb-6 flex items-start gap-3">
        <VunoIcon icon="info" :size="20" class="text-[#42b883] shrink-0 mt-px" />
        <p class="text-xs text-[#94a3b8] leading-relaxed">
          Seleccioná una sección en los sub-tabs de arriba para editar su contenido. Cada sección tiene campos en español e inglés, más opciones adicionales como imágenes, enlaces y configurción de visibilidad.
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="md:col-span-2"><div class="border-b border-[#1e293b] pb-4 mb-4"><span class="text-xs font-semibold tracking-[0.15em] text-[#42b883]">ESPAÑOL</span></div></div>
        <div v-for="f in landingSections[activeChildTab]?.fields" :key="f+'_es'" class="md:col-span-2">
          <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">{{ landingFieldLabels[f+'_es'] }}</label>
          <textarea v-if="f.startsWith('paragraph') || f.startsWith('desc')"
                    :value="lsVal(activeChildTab, f+'_es')"
                    @input="setLs(activeChildTab, f+'_es', ($event.target as HTMLTextAreaElement).value)"
                    class="admin-textarea h-20" maxlength="500" :disabled="!isEditing('landing')"></textarea>
          <input v-else :value="lsVal(activeChildTab, f+'_es')"
                 @input="setLs(activeChildTab, f+'_es', ($event.target as HTMLInputElement).value)"
                 class="admin-input" :maxlength="f.includes('title') ? '200' : '100'" :disabled="!isEditing('landing')" />
        </div>
        <div class="md:col-span-2"><div class="border-b border-[#1e293b] pb-4 mb-4"><span class="text-xs font-semibold tracking-[0.15em] text-[#42b883]">ENGLISH</span></div></div>
        <div v-for="f in landingSections[activeChildTab]?.fields" :key="f+'_en'" class="md:col-span-2">
          <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">{{ landingFieldLabels[f+'_en'] }}</label>
          <textarea v-if="f.startsWith('paragraph') || f.startsWith('desc')"
                    :value="lsVal(activeChildTab, f+'_en')"
                    @input="setLs(activeChildTab, f+'_en', ($event.target as HTMLTextAreaElement).value)"
                    class="admin-textarea h-20" maxlength="500" :disabled="!isEditing('landing')"></textarea>
          <input v-else :value="lsVal(activeChildTab, f+'_en')"
                 @input="setLs(activeChildTab, f+'_en', ($event.target as HTMLInputElement).value)"
                 class="admin-input" :maxlength="f.includes('title') ? '200' : '100'" :disabled="!isEditing('landing')" />
        </div>
        <!-- Extra fields -->
        <div v-for="ext in landingSections[activeChildTab]?.extras" :key="ext" class="md:col-span-2">
          <template v-if="ext === 'enabled'">
            <label class="admin-toggle-label">
              <label class="admin-toggle"><input :checked="lsBool(activeChildTab, 'enabled')" type="checkbox" @change="setLs(activeChildTab, 'enabled', ($event.target as HTMLInputElement).checked)" :disabled="!isEditing('landing')" /><div></div></label>
              <span class="text-[11px] font-semibold tracking-widest uppercase whitespace-nowrap" :class="lsBool(activeChildTab, 'enabled') ? 'text-[#42b883]' : 'text-[#94a3b8]'">{{ lsBool(activeChildTab, 'enabled') ? 'ACTIVO' : 'INACTIVO' }}</span>
            </label>
          </template>
          <template v-else-if="ext === 'cta_category_slug'">
            <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">CTA LINK — CATEGORÍA</label>
            <select :value="lsVal(activeChildTab, 'cta_category_slug')"
                    @change="setLs(activeChildTab, 'cta_category_slug', ($event.target as HTMLSelectElement).value)"
                    class="admin-select" :disabled="!isEditing('landing')">
              <option value="">Catálogo general</option>
              <option v-for="c in categories" :key="c.slug" :value="c.slug">{{ c.name }}</option>
            </select>
            <p class="text-xs text-[#94a3b8] mt-1">Link: {{ lsVal(activeChildTab, 'cta_category_slug') ? '/es/catalogo?categoria=' + lsVal(activeChildTab, 'cta_category_slug') : '/es/catalogo' }}</p>
          </template>
          <template v-else-if="ext === 'testimonial_items'">
            <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">TESTIMONIOS</label>
            <div class="space-y-4">
              <div v-for="(item, i) in (landing[activeChildTab]?.items || [])" :key="i" class="border border-[#1e293b] rounded-sm p-4 relative">
                <button class="absolute top-2 right-2 w-10 h-10 flex items-center justify-center text-[#94a3b8] hover:text-[#DC2626] transition-colors" :disabled="!isEditing('landing')" @click="removeTestimonial(activeChildTab, i)"><VunoIcon icon="close" :size="20" /></button>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-1">NOMBRE</label>
                    <input :value="item.name" @input="item.name = ($event.target as HTMLInputElement).value; markDirty('landing')" class="admin-input-sm" maxlength="100" :disabled="!isEditing('landing')" /></div>
                  <div><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-1">PUNTUACIÓN (1-5)</label>
                    <input :value="item.rating" @input="item.rating = parseInt(($event.target as HTMLInputElement).value) || 5; markDirty('landing')" type="number" min="1" max="5" class="admin-input-sm" :disabled="!isEditing('landing')" /></div>
                  <div class="md:col-span-3"><label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-1">TEXTO</label>
                    <textarea :value="item.text" @input="item.text = ($event.target as HTMLTextAreaElement).value; markDirty('landing')" class="admin-textarea-sm h-16" maxlength="500" :disabled="!isEditing('landing')"></textarea></div>
                </div>
              </div>
            </div>
            <button class="mt-2 text-xs font-semibold tracking-widest text-[#94a3b8] hover:text-[#dae2fd] inline-flex items-center gap-1.5" :disabled="!isEditing('landing')" @click="addTestimonial(activeChildTab)"><VunoIcon icon="add_circle" :size="20" /> AGREGAR TESTIMONIO</button>
          </template>
          <template v-else-if="ext === 'platforms'">
            <div class="border-b border-[#1e293b] pb-4 mb-4"><span class="text-xs font-semibold tracking-[0.15em] text-[#42b883]">PLATAFORMAS</span></div>
            <div v-for="pName in ['facebook','instagram','tiktok','linkedin','youtube']" :key="pName" class="flex items-start gap-4 py-3 border-b border-[#1e293b]/40">
              <div class="flex items-center gap-3 pt-2 min-w-[140px]">
                <label class="admin-toggle"><input :checked="landing[activeChildTab]?.platforms?.[pName]?.enabled" type="checkbox"
                  @change="setLs(activeChildTab, 'platforms', { ...(landing[activeChildTab]?.platforms || {}), [pName]: { enabled: ($event.target as HTMLInputElement).checked, url: landing[activeChildTab]?.platforms?.[pName]?.url || '' } })" :disabled="!isEditing('landing')" /><div></div></label>
                <span class="text-sm capitalize text-[#dae2fd]">{{ pName }}</span>
                <span class="text-[10px] font-semibold tracking-widest uppercase" :class="landing[activeChildTab]?.platforms?.[pName]?.enabled ? 'text-[#42b883]' : 'text-[#94a3b8]'">{{ landing[activeChildTab]?.platforms?.[pName]?.enabled ? 'ON' : 'OFF' }}</span>
              </div>
              <div class="flex-1">
                <input :value="landing[activeChildTab]?.platforms?.[pName]?.url || ''"
                       @input="setLs(activeChildTab, 'platforms', { ...(landing[activeChildTab]?.platforms || {}), [pName]: { enabled: landing[activeChildTab]?.platforms?.[pName]?.enabled || false, url: ($event.target as HTMLInputElement).value } })"
                       class="admin-input text-sm" maxlength="500" :placeholder="'https://' + pName + '.com/...'" :disabled="!isEditing('landing')" />
              </div>
            </div>
          </template>
          <template v-else-if="ext === 'image_url'">
            <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">IMAGEN (URL)</label>
            <div v-if="!lsVal(activeChildTab, 'image_url')" class="border-2 border-dashed border-[#1e293b] hover:border-[#42b883] rounded-sm p-6 text-center cursor-pointer transition-colors"
                 :class="{ 'opacity-50 pointer-events-none': !isEditing('landing') }"
                 @click="isEditing('landing') && ($refs as any)['landingImg' + activeChildTab]?.click()"
                 @dragover.prevent="isEditing('landing') && ($event.target as HTMLElement).classList.add('border-[#42b883]')"
                 @dragleave.prevent="isEditing('landing') && ($event.target as HTMLElement).classList.remove('border-[#42b883]')"
                 @drop.prevent="isEditing('landing') && handleLandingImageUpload(activeChildTab, ($event.dataTransfer?.files[0])!)">
              <input :ref="'landingImg' + activeChildTab" type="file" accept="image/png,image/jpeg,image/webp" class="hidden"
                     :disabled="!isEditing('landing')"
                     @change="($event.target as HTMLInputElement).files?.[0] && handleLandingImageUpload(activeChildTab, ($event.target as HTMLInputElement).files![0])" />
              <VunoIcon icon="upload" :size="28" class="text-[#94a3b8] block mb-1" />
              <p class="text-sm text-[#94a3b8]">Arrastrá la imagen o <span class="text-[#42b883] underline">seleccioná un archivo</span></p>
            </div>
            <div v-else class="border border-[#1e293b] rounded-sm p-3 flex items-center gap-4">
              <div class="w-24 h-24 flex items-center justify-center bg-[#1e293b] rounded-sm overflow-hidden">
                <img :src="lsVal(activeChildTab, 'image_url')" class="max-w-full max-h-full object-contain" alt="Imagen" />
              </div>
              <div class="flex flex-col gap-1.5">
                <button class="text-xs font-semibold tracking-widest text-[#42b883] hover:text-[#dae2fd] transition-colors inline-flex items-center gap-1" :disabled="!isEditing('landing')"
                        @click="($refs as any)['landingImg' + activeChildTab]?.click()"><VunoIcon icon="refresh" :size="20" /> REEMPLAZAR</button>
                <button class="text-xs font-semibold tracking-widest text-[#DC2626] hover:text-red-700 transition-colors inline-flex items-center gap-1" :disabled="!isEditing('landing')" @click="removeLandingImage(activeChildTab)"><VunoIcon icon="delete" :size="20" /> ELIMINAR</button>
              </div>
            </div>
            <p class="text-xs text-[#94a3b8] mt-1">URL pública de la imagen. Relación 4:5 recomendada.</p>
          </template>
          <template v-else>
            <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">{{ ext === 'image_url' ? 'IMAGEN (URL)' : ext === 'cta_link' ? 'CTA LINK (URL)' : ext }}</label>
            <input :value="lsVal(activeChildTab, ext)" @input="setLs(activeChildTab, ext, ($event.target as HTMLInputElement).value)"
                   class="admin-input" maxlength="500" :disabled="!isEditing('landing')" />
          </template>
        </div>
      </div>
    </div>

    <!-- ================================================================== -->
    <!-- TAB: Guía de Talles                                                 -->
    <!-- ================================================================== -->
    <div v-if="effectiveTab === 'store-sizeguide'" :key="'store-sizeguide'" class="admin-card p-4 md:p-6 admin-enter">
      <div class="flex items-start gap-4 mb-6">
        <div class="w-10 h-10 rounded-sm bg-[#42b883]/10 flex items-center justify-center shrink-0">
          <VunoIcon icon="straighten" class="text-[#42b883]" />
        </div>
        <div class="flex-1">
          <h2 class="text-lg font-semibold text-[#dae2fd]">Guía de Talles</h2>
          <p class="text-sm text-[#94a3b8] mt-1">Tabla de conversión que se muestra en la página de cada producto.</p>
        </div>
        <button v-if="!isEditing('size_guide')" class="admin-btn admin-btn-edit h-11 px-4 shrink-0" @click="enableEdit('size_guide')">
          <VunoIcon icon="edit" :size="16" />
          EDITAR
        </button>
        <span v-else class="badge badge-paid shrink-0 h-fit mt-2">EDITANDO</span>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-full md:max-w-2xl mb-8">
        <div>
          <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">TÍTULO (ES)</label>
          <input v-model="sizeGuide.title_es" class="admin-input" maxlength="100" placeholder="Guía de Talles" @input="markDirty('size_guide')" :disabled="!isEditing('size_guide')" />
        </div>
        <div>
          <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">TITLE (EN)</label>
          <input v-model="sizeGuide.title_en" class="admin-input" maxlength="100" placeholder="Size Guide" @input="markDirty('size_guide')" :disabled="!isEditing('size_guide')" />
        </div>
        <div class="md:col-span-2">
          <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">PIE / FOOTER (ES)</label>
          <input v-model="sizeGuide.footer_es" class="admin-input" maxlength="300" placeholder="Medí tu pie desde el talón..." @input="markDirty('size_guide')" :disabled="!isEditing('size_guide')" />
        </div>
        <div class="md:col-span-2">
          <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-2">PIE / FOOTER (EN)</label>
          <input v-model="sizeGuide.footer_en" class="admin-input" maxlength="300" placeholder="Measure your foot from heel..." @input="markDirty('size_guide')" :disabled="!isEditing('size_guide')" />
        </div>
      </div>

      <div class="h-px bg-gradient-to-r from-transparent via-[#1e293b] to-transparent my-8"></div>

      <div>
        <p class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase mb-3 flex items-center gap-2">
          <VunoIcon icon="table_rows" :size="20" />
          FILAS DE CONVERSIÓN
        </p>
        <p class="text-sm text-[#94a3b8]/70 mb-4">Agregá las equivalencias de talles US / EU / UK / CM.</p>
        <div class="overflow-x-auto rounded-sm border border-[#1e293b]">
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="bg-[#0f1929] border-b border-[#1e293b]">
                <th class="text-xs font-semibold tracking-widest text-[#94a3b8] py-3 px-4 w-24">US</th>
                <th class="text-xs font-semibold tracking-widest text-[#94a3b8] py-3 px-4 w-24">EU</th>
                <th class="text-xs font-semibold tracking-widest text-[#94a3b8] py-3 px-4 w-24">UK</th>
                <th class="text-xs font-semibold tracking-widest text-[#94a3b8] py-3 px-4 w-24">CM</th>
                <th class="text-xs font-semibold tracking-widest text-[#94a3b8] py-3 px-4 w-16"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in sizeGuideRows" :key="i" class="border-b border-[#1e293b]/50 hover:bg-white/[0.015] transition-colors">
                <td class="py-2 px-4"><input v-model="row.us" class="admin-input-sm w-full font-mono" maxlength="6" placeholder="7" @input="markDirty('size_guide')" :disabled="!isEditing('size_guide')" /></td>
                <td class="py-2 px-4"><input v-model="row.eu" class="admin-input-sm w-full font-mono" maxlength="6" placeholder="37" @input="markDirty('size_guide')" :disabled="!isEditing('size_guide')" /></td>
                <td class="py-2 px-4"><input v-model="row.uk" class="admin-input-sm w-full font-mono" maxlength="6" placeholder="4.5" @input="markDirty('size_guide')" :disabled="!isEditing('size_guide')" /></td>
                <td class="py-2 px-4"><input v-model="row.cm" class="admin-input-sm w-full font-mono" maxlength="6" placeholder="23.5" @input="markDirty('size_guide')" :disabled="!isEditing('size_guide')" /></td>
                <td class="py-2 px-4">
                  <button class="w-7 h-7 flex items-center justify-center text-[#94a3b8] hover:text-[#DC2626] hover:bg-[#DC2626]/10 rounded-sm transition-all" :disabled="!isEditing('size_guide')" @click="removeSizeGuideRow(i)" title="Eliminar fila">
                    <VunoIcon icon="remove_circle" :size="20" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <button class="mt-3 text-xs font-semibold tracking-widest text-[#94a3b8] hover:text-[#42b883] inline-flex items-center gap-1.5 transition-all px-3 py-2 border border-[#1e293b] rounded-sm hover:border-[#42b883]/30" :disabled="!isEditing('size_guide')" @click="addSizeGuideRow">
          <VunoIcon icon="add_circle" :size="20" /> AGREGAR FILA
        </button>
      </div>
    </div>

    <!-- Add Currency Modal -->
    <Teleport to="body">
      <div v-if="showCurrencyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-md p-4" @click.self="showCurrencyModal = false">
        <div class="admin-card-lg w-full max-w-md mx-4 p-6">
          <h3 class="text-lg font-semibold text-[#dae2fd] mb-4">Agregar Moneda</h3>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-1">CÓDIGO</label>
                <input v-model="newCurrency.code" class="admin-input-sm uppercase font-mono" maxlength="3" placeholder="EUR" />
              </div>
              <div>
                <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-1">SÍMBOLO</label>
                <input v-model="newCurrency.symbol" class="admin-input-sm" maxlength="10" placeholder="€" />
              </div>
            </div>
            <div>
              <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-1">NOMBRE</label>
              <input v-model="newCurrency.name" class="admin-input-sm" maxlength="100" placeholder="Euro" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-1">TASA DE CAMBIO</label>
                <input v-model.number="newCurrency.rate" type="number" step="0.000001" min="0.000001" class="admin-input-sm" placeholder="21.5" />
              </div>
              <div>
                <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-1">DECIMALES</label>
                <input v-model.number="newCurrency.decimals" type="number" min="0" max="6" class="admin-input-sm" />
              </div>
            </div>
            <p v-if="currencyModalError" class="text-sm text-[#DC2626]">{{ currencyModalError }}</p>
          </div>
          <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-[#1e293b]">
            <button class="admin-btn admin-btn-secondary" @click="showCurrencyModal = false">CANCELAR</button>
            <button class="admin-btn admin-btn-primary" :disabled="currencyModalLoading" @click="addCurrency">
              <VunoIcon :icon="currencyModalLoading ? 'progress_activity' : 'add'" :size="20" :class="{ 'animate-spin': currencyModalLoading }" />
              {{ currencyModalLoading ? 'GUARDANDO...' : 'AGREGAR' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Save button -->
    <div class="mt-8 pt-6 border-t border-[#1e293b]">
      <button class="admin-btn admin-btn-primary w-full md:w-auto h-12 px-8 justify-center gap-2" :disabled="editMode.size === 0" @click="save">
        <VunoIcon icon="save" :size="20" />
        GUARDAR CONFIGURACIÓN
        <span v-if="dirtySections.size" class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-white/20 text-[11px] font-bold">{{ dirtySections.size }}</span>
      </button>
      <p v-if="dirtySections.size" class="text-xs text-[#94a3b8] mt-2 md:text-center">
        {{ dirtySections.size }} seccion{{ dirtySections.size !== 1 ? 'es' : '' }} con cambios pendientes
      </p>
    </div>
  </div>
</template>

<style scoped>
@reference "tailwindcss";
.admin-toggle input[type="checkbox"] { @apply sr-only; }
.admin-toggle div {
  @apply w-12 h-6 rounded-full relative cursor-pointer transition-colors;
  background: #2a3038;
}
.admin-toggle input:checked + div {
  background: #42b883;
}
.admin-toggle div::after {
  content: '';
  @apply absolute top-[3px] left-[3px] bg-white border border-[#2a3038] rounded-full h-5 w-5 transition-all;
}
.admin-toggle input:checked + div::after { @apply translate-x-6 border-white; }
.admin-toggle-label { @apply flex items-center gap-3 cursor-pointer; }
</style>
