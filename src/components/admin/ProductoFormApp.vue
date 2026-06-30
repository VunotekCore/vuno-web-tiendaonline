<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'
import VariantsMatrix from './VariantsMatrix.vue'
import VunoIcon from './VunoIcon.vue'

const api = useApi()
const toast = useToast()
const variantsRef = ref<InstanceType<typeof VariantsMatrix> | null>(null)

const params = new URLSearchParams(window.location.search)
const productId = params.get('id')
const isEdit = !!productId

const isViewer = computed(() => (window as any).adminRole === 'viewer')

const activeTab = ref('info')
const tabs = [
  { id: 'info', label: 'Información', icon: 'description' },
  { id: 'pricing', label: 'Precio & Categoría', icon: 'sell' },
  { id: 'variants', label: 'Variantes', icon: 'palette' },
  { id: 'images', label: 'Imágenes', icon: 'photo_library' },
  { id: 'seo', label: 'SEO', icon: 'travel_explore' },
]

const nameField = ref('')
const descriptionField = ref('')
const detailsField = ref('')
const priceField = ref<number | null>(null)
const categoryField = ref('')
const isFeaturedField = ref(false)
const metaTitleField = ref('')
const metaDescriptionField = ref('')
const ogImageUrlField = ref('')

const colors = ref<{ name: string; hex: string }[]>([
  { name: 'Noir', hex: '#1A1A1A' },
  { name: 'White', hex: '#FFFFFF' },
  { name: 'Nude', hex: '#E6DED5' },
  { name: 'Arcilla', hex: '#C18C7E' },
])
const sizes = ref<string[]>(['5', '6', '7', '8', '9', '10', '11'])
const sizePrefix = ref('US')

const colorNameInput = ref('')
const colorHexInput = ref('#C18C7E')
const colorSuggestions = ['#C18C7E', '#E6DED5', '#1A1A1A', '#FFFFFF', '#DC2626', '#2563EB', '#D97706', '#7C3AED']

const sizeType = ref('US Women')
const sizeFrom = ref(5)
const sizeTo = ref(11)

const sizePresets: Record<string, { prefix: string; from: number; to: number }> = {
  'Mujer': { prefix: 'EU', from: 35, to: 41 },
  'Hombre': { prefix: 'EU', from: 39, to: 46 },
  'Niño': { prefix: 'EU', from: 28, to: 34 },
  'Niña': { prefix: 'EU', from: 28, to: 34 },
  'Unisex': { prefix: 'EU', from: 35, to: 41 },
  'US Women': { prefix: 'US', from: 5, to: 11 },
  'US Men': { prefix: 'US', from: 6, to: 13 },
  'UK': { prefix: 'UK', from: 2, to: 8 },
  'BR': { prefix: 'BR', from: 33, to: 39 },
}

watch(sizeType, (type) => {
  const preset = sizePresets[type]
  if (preset) {
    sizeFrom.value = preset.from
    sizeTo.value = preset.to
    sizePrefix.value = preset.prefix
  }
})

const categories = ref<{ name: string }[]>([])
const loadingCategories = ref(false)

interface UploadedImage {
  _local?: boolean
  objectUrl?: string
  file?: File
  name: string
  url?: string
  fileId?: string
  imageId?: string | number | null
  colorName?: string | null
}

const MAX_IMAGES = 20
const MIN_IMG_W = 200
const MIN_IMG_H = 200
const MIN_RATIO = 0.4
const MAX_RATIO = 2.5

const uploadedImages = ref<UploadedImage[]>([])
const fileInput = ref<HTMLInputElement | null>(null)
const deletingIdx = ref(-1)

const loading = ref(isEdit)
const submitting = ref(false)
const uploadProgress = ref({ visible: false, current: 0, total: 0 })

onMounted(async () => {
  await loadCategories()
  if (isEdit) await loadProduct()
  loading.value = false
})

function formatPrice(val: number | null | undefined, symbol = '$'): string {
  if (val == null || isNaN(val)) val = 0
  return symbol + Number(val).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

async function loadCategories() {
  loadingCategories.value = true
  try {
    const data = await api.get<{ items: { name: string }[] }>('/api/categorias/list.php')
    categories.value = data.items || []
  } catch {
    categories.value = []
  } finally {
    loadingCategories.value = false
  }
}

async function loadProduct() {
  try {
    const p = await api.get<any>(`/api/productos/get.php?id=${encodeURIComponent(productId!)}`)

    nameField.value = p.name || ''
    descriptionField.value = p.description || ''
    detailsField.value = (p.details || []).join('\n')
    priceField.value = p.price || null
    categoryField.value = p.category || ''
    isFeaturedField.value = !!p.isFeatured

    metaTitleField.value = p.metaTitle || ''
    metaDescriptionField.value = p.metaDescription || ''
    ogImageUrlField.value = p.ogImageUrl || ''

    const loadedColors: { name: string; hex: string }[] = []
    const colorMap: Record<string, string> = {}
    const loadedSizes: string[] = []

    // Try API colors first (they have proper hex), fall back to extracting from variants
    if (p.colors && p.colors.length) {
      for (const c of p.colors) {
        loadedColors.push({ name: c.name, hex: c.hex || '#C18C7E' })
        colorMap[c.name] = c.hex || '#C18C7E'
      }
    } else {
      ;(p.variants || []).forEach((v: any) => {
        if (!colorMap[v.color_name]) {
          colorMap[v.color_name] = v.color_hex || '#C18C7E'
          loadedColors.push({ name: v.color_name, hex: v.color_hex || '#C18C7E' })
        }
      })
    }
    ;(p.variants || []).forEach((v: any) => {
      if (!loadedSizes.includes(v.size_value)) {
        loadedSizes.push(v.size_value)
      }
    })
    loadedSizes.sort((a: string, b: string) => parseInt(a, 10) - parseInt(b, 10))

    if (loadedColors.length) {
      colors.value = loadedColors
    }
    if (loadedSizes.length) {
      sizes.value = loadedSizes
    }

    const initialStocks: Record<string, number> = {}
    ;(p.variants || []).forEach((v: any) => {
      initialStocks[v.color_name + '_' + v.size_value] = v.stock
    })

    if (typeof p.lowStockThreshold === 'number') {
    }

    const imageList = p.imageDetails || p.images || []
    imageList.forEach((img: any) => {
      const url = typeof img === 'string' ? img : img.url
      uploadedImages.value.push({
        url,
        name: url?.split('/').pop() || 'image',
        fileId: typeof img === 'string' ? '' : (img.fileId || ''),
        imageId: typeof img === 'string' ? null : (img.id || null),
        colorName: typeof img === 'string' ? null : (img.colorName || null),
      })
    })

    nextTickSetStock(initialStocks)
  } catch (err: any) {
    toast.error(err.message || 'Error loading product')
    loading.value = false
  }
}

function nextTickSetStock(initialStocks: Record<string, number>) {
  setTimeout(() => {
    if (variantsRef.value) {
      for (const [k, v] of Object.entries(initialStocks)) {
        const parts = k.split('_')
        if (parts.length >= 2) {
          const color = parts.slice(0, -1).join('_')
          const size = parts[parts.length - 1]
          variantsRef.value.setStock(color, size, v)
        }
      }
    }
  }, 50)
}

function switchTab(id: string) {
  activeTab.value = id
}

function slugify(text: string): string {
  return text.toLowerCase()
    .replace(/[^\w\s-]/g, '')
    .replace(/[\s_]+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '')
    .substring(0, 80)
}

const slugPreview = computed(() => {
  const slug = slugify(nameField.value)
  return slug || null
})

function addColor() {
  const name = colorNameInput.value.trim()
  const hex = colorHexInput.value
  if (!name) {
    toast.warning('Nombre requerido', 'Ingresá un nombre para el color.')
    return
  }
  if (colors.value.some(c => c.name === name)) {
    toast.warning('Color duplicado', 'El color ya existe.')
    return
  }
  colors.value.push({ name, hex })
  for (const img of uploadedImages.value) {
    if (img.colorName === name) img.colorName = null
  }
  colorNameInput.value = ''
  const usedHexes = new Set(colors.value.map(c => c.hex))
  colorHexInput.value = colorSuggestions.find(h => !usedHexes.has(h)) || colorSuggestions[0]
}

function removeColor(name: string) {
  const idx = colors.value.findIndex(c => c.name === name)
  if (idx === -1) return
  for (const img of uploadedImages.value) {
    if (img.colorName === name) img.colorName = null
  }
  colors.value.splice(idx, 1)
}

function addSizeRange() {
  const from = sizeFrom.value
  const to = sizeTo.value
  if (from > to) {
    toast.warning('Rango inválido', 'El valor "Desde" debe ser menor o igual a "Hasta".')
    return
  }
  const added: string[] = []
  for (let i = from; i <= to; i++) {
    const str = String(i)
    if (!sizes.value.includes(str)) {
      sizes.value.push(str)
      added.push(str)
    }
  }
  if (added.length === 0) {
    toast.info('Sin cambios', 'Los talles del rango ya están agregados.')
    return
  }
  sizes.value.sort((a, b) => parseInt(a, 10) - parseInt(b, 10))
}

function removeSize(val: string) {
  const idx = sizes.value.indexOf(val)
  if (idx === -1) return
  sizes.value.splice(idx, 1)
}

function escHtml(s: string): string {
  return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

function validateImageDimensions(file: File): Promise<void> {
  return new Promise((resolve, reject) => {
    const img = new Image()
    const url = URL.createObjectURL(file)
    img.onload = () => {
      URL.revokeObjectURL(url)
      if (img.width < MIN_IMG_W || img.height < MIN_IMG_H) {
        reject(new Error(`${file.name} demasiado pequeña: ${img.width}×${img.height}. Mínimo: ${MIN_IMG_W}×${MIN_IMG_H}px`))
      } else {
        const ratio = img.width / img.height
        if (ratio < MIN_RATIO || ratio > MAX_RATIO) {
          reject(new Error(`${file.name} ratio ${ratio.toFixed(2)} incorrecto. Debe ser 4:5 (0.80) ±5%`))
        } else {
          resolve()
        }
      }
    }
    img.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error(`No se pudo leer ${file.name}`))
    }
    img.src = url
  })
}

function addFileToPreview(file: File) {
  if (uploadedImages.value.length >= MAX_IMAGES) {
    toast.warning('Límite de imágenes', `Máximo ${MAX_IMAGES} imágenes permitidas por producto.`)
    return
  }
  uploadedImages.value.push({ _local: true, objectUrl: URL.createObjectURL(file), file, name: file.name, colorName: null })
}

async function handleSelectedFiles(fileList: FileList | File[]) {
  const files = Array.from(fileList).filter(f => f.type.startsWith('image/'))
  for (const f of files) {
    if (uploadedImages.value.length >= MAX_IMAGES) {
      toast.warning('Límite de imágenes', `Máximo ${MAX_IMAGES} imágenes permitidas por producto.`)
      break
    }
    try {
      await validateImageDimensions(f)
      addFileToPreview(f)
    } catch (err: any) {
      toast.error('Error en imagen', err.message)
    }
  }
}

async function uploadFile(file: File): Promise<any> {
  const formData = new FormData()
  formData.append('file', file)
  formData.append('folder', 'products')
  const res = await fetch('/api/imagekit/upload.php', { method: 'POST', body: formData })
  const data = await res.json()
  if (!res.ok) {
    const isImageKit = res.status >= 500 || (data.error || '').toLowerCase().includes('imagekit')
    throw new Error(isImageKit
      ? 'El servicio de ImageKit no respondió correctamente. Intenta de nuevo en unos segundos.'
      : (data.error || 'Error desconocido'))
  }
  return data
}

async function deleteImageFromKit(img: UploadedImage): Promise<boolean> {
  if (!img.fileId) {
    toast.info('Imagen eliminada', 'No se pudo identificar el archivo en ImageKit.')
    return true
  }
  const payload: Record<string, any> = { fileId: img.fileId }
  if (img.imageId) payload.imageId = img.imageId
  try {
    const url = img.imageId ? '/api/imagekit/delete-image.php' : '/api/imagekit/delete.php'
    await api.post(url, payload)
    return true
  } catch {
    toast.error('Error de red', 'No se pudo conectar con ImageKit')
    return false
  }
}

async function removeImage(idx: number) {
  const img = uploadedImages.value[idx]
  if (!img) return

  if (img._local) {
    if (img.objectUrl) URL.revokeObjectURL(img.objectUrl)
    uploadedImages.value.splice(idx, 1)
    return
  }

  deletingIdx.value = idx
  const ok = await deleteImageFromKit(img)
  deletingIdx.value = -1
  if (ok) {
    toast.success('Imagen eliminada', img.name)
    uploadedImages.value.splice(idx, 1)
  }
}

function onFileInputChange(event: Event) {
  const input = event.target as HTMLInputElement
  if (input.files) {
    handleSelectedFiles(input.files)
    input.value = ''
  }
}

function onDrop(event: DragEvent) {
  event.preventDefault()
  if (event.dataTransfer?.files) {
    handleSelectedFiles(event.dataTransfer.files)
  }
}

const computedSlug = computed(() => {
  const s = slugify(nameField.value)
  return s || null
})

async function handleSubmit() {
  if (isViewer.value) return

  const imagesToUpload = uploadedImages.value.filter(i => i._local)

  if (imagesToUpload.length > 0) {
    uploadProgress.value = { visible: true, current: 0, total: imagesToUpload.length }
    const uploadedIds: string[] = []
    let uploadOk = true

    for (let u = 0; u < imagesToUpload.length; u++) {
      uploadProgress.value.current = u + 1
      const localImg = imagesToUpload[u]
      const idx = uploadedImages.value.indexOf(localImg)
      try {
        const data = await uploadFile(localImg.file!)
        if (localImg.objectUrl) URL.revokeObjectURL(localImg.objectUrl)
        uploadedImages.value[idx] = {
          url: data.url,
          name: data.name || localImg.name,
          fileId: data.fileId,
          colorName: localImg.colorName || null,
        }
        uploadedIds.push(data.fileId)
      } catch (err: any) {
        uploadOk = false
        for (const fid of uploadedIds) {
          try { await fetch('/api/imagekit/delete.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ fileId: fid }) }) } catch { }
        }
        uploadProgress.value = { visible: false, current: 0, total: 0 }
        toast.error('Error al subir imágenes', err.message || 'Intenta de nuevo.')
        return
      }
    }

    uploadProgress.value = { visible: false, current: 0, total: 0 }
  }

  const detailsArr = detailsField.value.split('\n').map(l => l.trim()).filter(Boolean)

  const matrixPayload = variantsRef.value?.toPayload() || { stocks: {}, threshold: 5 }

  const payload = {
    ...(isEdit ? { id: productId } : {}),
    name: nameField.value,
    description: descriptionField.value,
    details: detailsArr.length ? detailsArr : null,
    price: parseFloat(String(priceField.value)),
    category: categoryField.value,
    colors: colors.value,
    sizes: sizes.value.map(s => ({ label: s, value: s })),
    stocks: matrixPayload.stocks,
    lowStockThreshold: matrixPayload.threshold,
    images: uploadedImages.value.map(i => ({
      url: i.url || '',
      fileId: i.fileId || null,
      colorName: i.colorName || null,
    })),
    isFeatured: isFeaturedField.value,
    metaTitle: metaTitleField.value,
    metaDescription: metaDescriptionField.value,
    ogImageUrl: ogImageUrlField.value,
  }

  submitting.value = true
  try {
    const endpoint = isEdit ? '/api/productos/update.php' : '/api/productos/create.php'
    const res = await api.post<{ success?: boolean; error?: string }>(endpoint, payload)
    if (res.error) throw new Error(res.error)
    toast.success(isEdit ? 'Producto actualizado' : 'Producto creado', 'Guardado correctamente.')
    setTimeout(() => { window.location.href = '/admin/productos' }, 800)
  } catch (err: any) {
    for (const img of uploadedImages.value) {
      if (img.fileId && !img._local) {
        try { await fetch('/api/imagekit/delete.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ fileId: img.fileId }) }) } catch { }
      }
    }
    toast.error('Error al guardar', err.message || 'No se pudo guardar el producto.')
  } finally {
    submitting.value = false
  }
}

const totalImages = computed(() => uploadedImages.value.length)
const remainingImages = computed(() => MAX_IMAGES - totalImages.value)
</script>

<template>
  <div v-if="loading" class="admin-card p-6">
    <div class="skeleton skeleton-title w-40 mb-4"></div>
    <div class="space-y-3"><div v-for="i in 6" :key="i" class="skeleton skeleton-text w-3/4"></div></div>
  </div>

  <div v-else-if="isViewer" class="font-body text-body-md text-[#DC2626] bg-[#DC2626]/10 p-3">
    No tienes permisos para {{ isEdit ? 'editar' : 'crear' }} productos.
  </div>

  <form v-else @submit.prevent="handleSubmit" class="admin-enter">
    <div class="border-b border-[#1e293b] mb-8 overflow-x-auto">
      <div class="flex gap-1 min-w-max" role="tablist" :aria-label="'Secciones del producto'">
        <button
          v-for="t in tabs" :key="t.id"
          type="button"
          :class="[
            'px-4 py-3 font-label-caps text-label-caps border-b-2 transition-all whitespace-nowrap flex items-center gap-1.5',
            activeTab === t.id
              ? 'text-[#42b883] border-[#42b883]'
              : 'text-[#94a3b8] border-transparent hover:text-[#dae2fd] hover:border-[#42b883]/30'
          ]"
          role="tab"
          :aria-selected="activeTab === t.id"
          :aria-controls="'tab-panel-' + t.id"
          @click="switchTab(t.id)"
          @keydown.left.prevent="switchTab(tabs[(tabs.indexOf(t) - 1 + tabs.length) % tabs.length].id)"
          @keydown.right.prevent="switchTab(tabs[(tabs.indexOf(t) + 1) % tabs.length].id)"
        >
          <VunoIcon :icon="t.icon" :size="20" />
          {{ t.label }}
        </button>
      </div>
    </div>

    <!-- Tab: Info -->
    <div v-show="activeTab === 'info'" id="tab-panel-info" role="tabpanel" class="max-w-4xl glass-card overflow-hidden rounded-xl">
      <div class="px-4 md:px-6 pt-5 pb-4 border-b border-[#dae2fd]/5">
        <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
          <VunoIcon icon="description" :size="24" />
          Información del Producto
        </h2>
      </div>
      <div class="px-4 md:px-6 py-4">
        <div class="space-y-6 lg:space-y-0 lg:grid lg:grid-cols-3 lg:gap-6">
          <div class="lg:col-span-2 space-y-6">
            <div>
              <label for="fieldName" class="block text-xs font-semibold tracking-widest text-[#94a3b8] mb-2 uppercase">NOMBRE DEL PRODUCTO *</label>
              <div class="relative">
                <input id="fieldName" v-model="nameField" type="text" required maxlength="255"
                  class="w-full bg-[#1e293b]/50 border border-[#dae2fd]/10 rounded-sm px-3 py-2.5 pr-16 text-[#dae2fd] placeholder-[#94a3b8]/50 focus:border-[#42b883] focus:ring-1 focus:ring-[#42b883]/30 focus:outline-none text-lg transition-colors" />
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[#94a3b8] pointer-events-none">{{ nameField.length }}/255</span>
              </div>
              <div v-if="computedSlug" class="text-sm text-[#94a3b8] mt-1.5">
                <span class="text-[#42b883]">/producto/</span><span>{{ computedSlug }}</span>
              </div>
            </div>
            <div>
              <label for="fieldDescription" class="block text-xs font-semibold tracking-widest text-[#94a3b8] mb-2 uppercase">DESCRIPCIÓN</label>
              <div class="relative">
                <textarea id="fieldDescription" v-model="descriptionField"
                  class="w-full bg-[#1e293b]/50 border border-[#dae2fd]/10 rounded-sm px-3 py-2.5 pb-8 text-[#dae2fd] placeholder-[#94a3b8]/50 focus:border-[#42b883] focus:ring-1 focus:ring-[#42b883]/30 focus:outline-none resize-none h-32"></textarea>
                <span class="absolute right-3 bottom-2 text-xs text-[#94a3b8] pointer-events-none">{{ descriptionField.length }} caracteres</span>
              </div>
            </div>
          </div>
          <div class="flex flex-col">
            <label for="fieldDetails" class="block text-xs font-semibold tracking-widest text-[#94a3b8] mb-2 uppercase">DETALLES (uno por línea)</label>
            <textarea id="fieldDetails" v-model="detailsField"
              class="w-full bg-[#1e293b]/50 border border-[#dae2fd]/10 rounded-sm px-3 py-2.5 text-[#dae2fd] placeholder-[#94a3b8]/50 focus:border-[#42b883] focus:ring-1 focus:ring-[#42b883]/30 focus:outline-none resize-none min-h-[128px] lg:flex-1 lg:min-h-0"
              placeholder="Ej:&#10;Piel genuina italiana&#10;Suela antideslizante&#10;Tacón de acero inoxidable"></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab: Pricing -->
    <div v-show="activeTab === 'pricing'" id="tab-panel-pricing" role="tabpanel" class="max-w-2xl glass-card overflow-hidden rounded-xl">
      <div class="px-4 md:px-6 pt-5 pb-4 border-b border-[#dae2fd]/5">
        <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
          <VunoIcon icon="sell" :size="24" />
          Precio y Categoría
        </h2>
      </div>
      <div class="px-4 md:px-6 py-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label for="fieldPrice" class="block text-xs font-semibold tracking-widest text-[#94a3b8] mb-2 uppercase">PRECIO (USD)</label>
          <input id="fieldPrice" v-model.number="priceField" type="number" step="0.01" required
            class="w-full bg-transparent border-b border-[#1e293b] pb-2 text-[#dae2fd] focus:border-[#42b883] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#42b883] focus-visible:ring-offset-1" />
        </div>
        <div>
          <label for="fieldCategory" class="block text-xs font-semibold tracking-widest text-[#94a3b8] mb-2 uppercase">CATEGORÍA</label>
          <select id="fieldCategory" v-model="categoryField"
            class="w-full bg-transparent border-b border-[#1e293b] pb-2 text-[#dae2fd] focus:border-[#42b883] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#42b883] focus-visible:ring-offset-1">
            <option value="" disabled>{{ loadingCategories ? 'Cargando...' : 'Seleccionar categoría' }}</option>
            <option v-for="c in categories" :key="c.name" :value="c.name">{{ c.name }}</option>
          </select>
        </div>
      </div>
      <div class="mt-6">
        <label class="flex items-center gap-3 cursor-pointer group">
          <input type="checkbox" v-model="isFeaturedField" class="w-4 h-4 accent-[#42b883]" />
          <span class="text-sm text-[#94a3b8] group-hover:text-[#dae2fd] transition-colors">Destacado — mostrar en la portada (hero slideshow)</span>
        </label>
      </div>
      </div>
    </div>

    <!-- Tab: Variants -->
    <div v-show="activeTab === 'variants'" id="tab-panel-variants" role="tabpanel" class="glass-card overflow-hidden rounded-xl">
      <div class="px-4 md:px-6 pt-5 pb-4 border-b border-[#dae2fd]/5">
        <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
          <VunoIcon icon="palette" :size="24" />
          Variantes
        </h2>
      </div>
      <div class="px-4 md:px-6 py-4">
      <details class="mb-6 group">
        <summary class="cursor-pointer text-xs font-semibold tracking-widest text-[#94a3b8] hover:text-[#dae2fd] transition-colors flex items-center gap-1.5">
          <VunoIcon icon="help" :size="20" class="group-open:rotate-90 transition-transform" />
          ¿Cómo funciona la matriz de inventario?
        </summary>
        <div class="mt-3 pl-6 space-y-1 text-sm text-[#94a3b8]">
          <p>• Cada celda representa el <strong class="text-[#dae2fd]">stock de un color × talle</strong></p>
          <p>• Usá los botones <strong class="text-[#dae2fd]">+ / −</strong> o escribí el número directamente en cada celda</p>
          <p>• <strong class="text-[#dae2fd]">Flechas del teclado</strong> navegan entre celdas; ↑ suma 1, ↓ resta 1</p>
          <p>• Click en el <strong class="text-[#dae2fd]">círculo del color</strong>: si tiene stock lo limpia; si está vacío abre un prompt para llenar</p>
          <p>• Botón <strong class="text-[#dae2fd]">◕</strong> llena todos los talles de un color con un valor fijo</p>
          <p>• Agregá o eliminá colores y talles abajo según las variantes reales del producto</p>
        </div>
      </details>

      <!-- Color Manager -->
      <div class="mb-8">
        <h4 class="font-headline text-headline-sm mb-3 text-[#dae2fd] flex items-center gap-2">
          <VunoIcon icon="palette" :size="20" />
          Colores disponibles
        </h4>
        <div class="flex flex-wrap gap-2 mb-3">
          <span v-for="c in colors" :key="c.name"
            class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-[#1e293b] border border-[#1e293b] rounded-sm text-xs">
            <span class="w-3 h-3 rounded-full inline-block" :style="{ backgroundColor: c.hex, border: '1px solid rgba(255,255,255,0.1)' }"></span>
            {{ c.name }}
            <button type="button" class="text-[#94a3b8] hover:text-[#DC2626] transition-colors" @click="removeColor(c.name)">
              <VunoIcon icon="close" :size="14" />
            </button>
          </span>
        </div>
        <div class="flex flex-wrap items-end gap-3">
          <div>
            <label class="block text-[10px] font-semibold tracking-widest text-[#94a3b8] mb-1 uppercase">NOMBRE</label>
            <input v-model="colorNameInput" type="text" placeholder="Ej: Noir" maxlength="50"
              class="w-full sm:w-32 h-9 px-3 bg-[#1e293b] border border-[#1e293b] text-[#dae2fd] text-sm focus:border-[#42b883] focus:outline-none rounded-sm"
              @keydown.enter.prevent="addColor" />
          </div>
          <div>
            <label class="block text-[10px] font-semibold tracking-widest text-[#94a3b8] mb-1 uppercase">COLOR</label>
            <div class="flex items-center gap-1.5">
              <input v-model="colorHexInput" type="color"
                class="w-9 h-9 p-0.5 bg-[#1e293b] border border-[#1e293b] cursor-pointer rounded-sm" />
              <span class="text-[10px] text-[#94a3b8] font-mono w-16 truncate">{{ colorHexInput }}</span>
              <span v-for="h in colorSuggestions" :key="h"
                class="w-4 h-4 rounded-full border border-[#1e293b] cursor-pointer shrink-0 hover:scale-110 transition-transform"
                :style="{ backgroundColor: h }"
                @click="colorHexInput = h" />
            </div>
          </div>
          <button type="button" class="h-9 px-4 bg-[#42b883] text-white text-xs font-semibold tracking-widest rounded-sm hover:bg-[#42b883]/90 transition-all disabled:opacity-40" @click="addColor">AGREGAR</button>
        </div>
      </div>

      <!-- Size Manager -->
      <div class="mb-8">
        <h4 class="font-headline text-headline-sm mb-3 text-[#dae2fd] flex items-center gap-2">
          <VunoIcon icon="straighten" :size="20" />
          Talles disponibles
        </h4>
        <div class="flex flex-wrap gap-2 mb-3">
          <span v-for="s in sizes" :key="s"
            class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-[#1e293b] border border-[#1e293b] rounded-sm text-xs">
            {{ sizePrefix }} {{ s }}
            <button type="button" class="text-[#94a3b8] hover:text-[#DC2626] transition-colors" @click="removeSize(s)">
              <VunoIcon icon="close" :size="14" />
            </button>
          </span>
        </div>
        <div class="flex flex-wrap items-end gap-3">
          <div>
            <label class="block text-[10px] font-semibold tracking-widest text-[#94a3b8] mb-1 uppercase">TIPO</label>
            <input v-model="sizeType" type="text" placeholder="Ej: US Women, UK, Mujer..."
              class="h-9 px-3 bg-[#1e293b] border border-[#1e293b] text-[#dae2fd] text-sm placeholder:text-[#4a5568] focus:border-[#42b883] focus:outline-none rounded-sm w-36" />
          </div>
          <div>
            <label class="block text-[10px] font-semibold tracking-widest text-[#94a3b8] mb-1 uppercase">DESDE</label>
            <input v-model.number="sizeFrom" type="number" min="1" max="99" class="w-16 h-9 px-2 bg-[#1e293b] border border-[#1e293b] text-[#dae2fd] text-sm text-center focus:border-[#42b883] focus:outline-none rounded-sm" />
          </div>
          <div>
            <label class="block text-[10px] font-semibold tracking-widest text-[#94a3b8] mb-1 uppercase">HASTA</label>
            <input v-model.number="sizeTo" type="number" min="1" max="99" class="w-16 h-9 px-2 bg-[#1e293b] border border-[#1e293b] text-[#dae2fd] text-sm text-center focus:border-[#42b883] focus:outline-none rounded-sm" />
          </div>
          <button type="button" class="h-9 px-4 bg-[#42b883] text-white text-xs font-semibold tracking-widest rounded-sm hover:bg-[#42b883]/90 transition-all" @click="addSizeRange">AGREGAR RANGO</button>
        </div>
        <div class="flex flex-wrap items-center gap-3 mt-3">
          <label class="text-[10px] font-semibold tracking-widest text-[#94a3b8] uppercase">PREFIJO</label>
          <input v-model="sizePrefix" type="text" maxlength="10" class="w-14 h-7 px-2 bg-[#1e293b] border border-[#1e293b] text-[#dae2fd] text-xs text-center focus:border-[#42b883] focus:outline-none rounded-sm" />
        </div>
      </div>

      <VariantsMatrix
        ref="variantsRef"
        :colors="colors"
        :sizes="sizes"
        :low-stock-threshold="5"
        :size-prefix="sizePrefix"
      />
      </div>
    </div>

    <!-- Tab: Images -->
    <div v-show="activeTab === 'images'" id="tab-panel-images" role="tabpanel" class="max-w-2xl glass-card overflow-hidden rounded-xl">
      <div class="px-4 md:px-6 pt-5 pb-4 border-b border-[#dae2fd]/5">
        <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
          <VunoIcon icon="photo_library" :size="24" />
          Imágenes del Producto
        </h2>
      </div>
      <div class="px-4 md:px-6 py-4">
      <div
        class="border-2 border-dashed border-[#1e293b] rounded-sm p-8 text-center cursor-pointer hover:border-[#42b883] transition-colors bg-[#111d2e] mb-4"
        tabindex="0" role="button" aria-label="Seleccionar imágenes para subir"
        @click="fileInput?.click()"
        @keydown.enter.prevent="fileInput?.click()"
        @keydown.space.prevent="fileInput?.click()"
        @dragover.prevent="($event.target as HTMLElement).classList.add('border-[#42b883]', 'bg-[#42b883]/5')"
        @dragleave.prevent="($event.target as HTMLElement).classList.remove('border-[#42b883]', 'bg-[#42b883]/5')"
        @drop.prevent="onDrop($event); ($event.target as HTMLElement).classList.remove('border-[#42b883]', 'bg-[#42b883]/5')"
      >
        <VunoIcon icon="cloud_upload" :size="36" class="text-[#94a3b8] mb-2 block mx-auto" />
        <p class="text-sm text-[#94a3b8] mb-1">Arrastra imágenes aquí o haz clic para seleccionar</p>
        <p class="text-sm text-[#94a3b8] text-xs">JPG, PNG, WebP · Máximo 5MB cada una</p>
        <input type="file" ref="fileInput" accept="image/jpeg,image/png,image/webp" multiple class="hidden" @change="onFileInputChange" />
      </div>
      <div class="flex items-center justify-between mb-4">
        <div class="text-sm text-[#94a3b8]">{{ totalImages }}/{{ MAX_IMAGES }} imágenes</div>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <template v-if="totalImages === 0">
          <div class="empty-state col-span-full">
            <VunoIcon icon="photo_library" :size="36" class="empty-state-icon" />
            <p class="empty-state-title">Sin imágenes</p>
            <p class="empty-state-desc">Subí imágenes del producto usando el área de carga.</p>
          </div>
        </template>
        <div v-for="(img, i) in uploadedImages" :key="i" class="relative group border border-[#1e293b] rounded-sm overflow-hidden bg-[#111d2e]">
          <div class="relative">
            <img :src="img._local ? img.objectUrl : img.url" :alt="img.name" class="w-full h-32 object-cover" />
            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/60 transition-colors flex items-center justify-center pointer-events-none">
              <button type="button"
                class="bg-[#DC2626] text-white rounded-full w-10 h-10 flex items-center justify-center transition-all pointer-events-auto"
                :disabled="deletingIdx === i"
                @click="removeImage(i)">
                <VunoIcon v-if="deletingIdx === i" icon="progress_activity" :size="14" class="animate-spin" />
                <VunoIcon v-else icon="close" :size="14" />
              </button>
            </div>
          </div>
          <div class="px-2 py-1.5">
            <p class="text-xs truncate text-[#94a3b8] mb-1">{{ img.name }}</p>
            <select v-model="img.colorName" class="w-full text-xs bg-[#162240] border border-[#1e293b] rounded-sm px-1 py-0.5 text-[#dae2fd] focus:border-[#42b883] focus:outline-none">
              <option :value="null" style="background:#162240;color:#dae2fd">Todas</option>
              <option v-for="c in colors" :key="c.name" :value="c.name" style="background:#162240;color:#dae2fd">{{ c.name }}</option>
            </select>
          </div>
        </div>
      </div>
      </div>
    </div>

    <!-- Tab: SEO -->
    <div v-show="activeTab === 'seo'" id="tab-panel-seo" role="tabpanel" class="max-w-2xl glass-card overflow-hidden rounded-xl">
      <div class="px-4 md:px-6 pt-5 pb-4 border-b border-[#dae2fd]/5">
        <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
          <VunoIcon icon="travel_explore" :size="24" />
          SEO & Open Graph
        </h2>
      </div>
      <div class="px-4 md:px-6 py-4">
      <div class="space-y-6">
        <div>
          <label for="fieldMetaTitle" class="block text-xs font-semibold tracking-widest text-[#94a3b8] mb-2 uppercase">META TITLE</label>
          <input id="fieldMetaTitle" v-model="metaTitleField" type="text" maxlength="70"
            class="w-full bg-transparent border-b border-[#1e293b] pb-2 text-[#dae2fd] focus:border-[#42b883] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#42b883] focus-visible:ring-offset-1" />
          <p class="text-xs text-[#94a3b8] mt-1">Si se deja vacío, se usa: Nombre del producto | Vunotek. Máximo 60 caracteres.</p>
        </div>
        <div>
          <label for="fieldMetaDescription" class="block text-xs font-semibold tracking-widest text-[#94a3b8] mb-2 uppercase">META DESCRIPTION</label>
          <textarea id="fieldMetaDescription" v-model="metaDescriptionField" maxlength="160"
            class="w-full bg-transparent border border-[#1e293b] p-3 text-[#dae2fd] focus:border-[#42b883] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#42b883] focus-visible:ring-offset-1 resize-none h-24"></textarea>
          <p class="text-xs text-[#94a3b8] mt-1">Si se deja vacío, se usa la descripción del producto. Máximo 160 caracteres.</p>
        </div>
        <div>
          <label for="fieldOgImageUrl" class="block text-xs font-semibold tracking-widest text-[#94a3b8] mb-2 uppercase">OG IMAGE URL</label>
          <input id="fieldOgImageUrl" v-model="ogImageUrlField" type="url" maxlength="500"
            class="w-full bg-transparent border-b border-[#1e293b] pb-2 text-[#dae2fd] focus:border-[#42b883] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#42b883] focus-visible:ring-offset-1"
            placeholder="https://ik.imagekit.io/..." />
          <p class="text-xs text-[#94a3b8] mt-1">URL de la imagen para compartir en redes. Si se deja vacío, se usa la imagen principal del producto.</p>
        </div>
      </div>
      </div>
    </div>

    <!-- Submit -->
    <div class="mt-8 pt-6 border-t border-[#1e293b] flex flex-col-reverse sm:flex-row items-stretch sm:items-center gap-4">
      <a href="/admin/productos" class="font-label-caps text-label-caps text-center sm:text-left text-[#94a3b8] hover:text-[#dae2fd] transition-colors py-3 sm:py-0">CANCELAR</a>
      <button type="submit" :disabled="submitting"
        class="bg-[#42b883] text-white font-label-caps text-label-caps h-12 px-8 inline-flex items-center justify-center gap-1.5 rounded-md hover:bg-[#42b883]/90 hover:shadow-sm transition-all duration-200 disabled:opacity-40 disabled:cursor-not-allowed w-full sm:w-auto">
        <VunoIcon :icon="submitting ? 'progress_activity' : 'save'" :size="20" :class="{ 'animate-spin': submitting }" />
        {{ submitting ? 'GUARDANDO...' : (isEdit ? 'GUARDAR CAMBIOS' : 'GUARDAR PRODUCTO') }}
      </button>
    </div>
  </form>

  <!-- Upload Progress Modal -->
  <div v-if="uploadProgress.visible" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center">
    <div class="bg-[#111d2e] max-w-sm w-full mx-4 border-t-2 border-[#42b883] shadow-lg">
      <div class="p-8 text-center">
        <div class="relative w-10 h-10 mx-auto mb-6">
          <div class="absolute inset-0 rounded-full border-2 border-[#1e293b]"></div>
          <div class="absolute inset-0 rounded-full border-2 border-transparent border-t-[#42b883] animate-spin"></div>
        </div>
        <p class="text-lg font-semibold text-[#dae2fd] mb-2">Subiendo imágenes</p>
        <p class="text-sm text-[#94a3b8] mb-6">{{ uploadProgress.current }} de {{ uploadProgress.total }}</p>
        <div class="h-0.5 bg-[#1e293b] rounded-full overflow-hidden">
          <div class="h-full bg-gradient-to-r from-[#42b883] to-[#42b883] rounded-full transition-all duration-500" :style="{ width: uploadProgress.total > 0 ? Math.round((uploadProgress.current / uploadProgress.total) * 100) + '%' : '0%' }"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-6">
    <a href="/admin/productos" class="inline-flex items-center gap-1.5 text-sm text-[#94a3b8] hover:text-[#dae2fd] transition-colors">
      <VunoIcon icon="arrow_back" :size="20" />
      Volver a Productos
    </a>
  </div>
</template>
