<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
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
const sizes = ref<string[]>(['36', '37', '38', '39', '40', '41'])
const sizePrefix = ref('EU')

const colorNameInput = ref('')
const colorHexInput = ref('#1A1A1A')
const sizeInputVal = ref('')

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
const MIN_IMG_W = 800
const MIN_IMG_H = 1000
const MIN_RATIO = 0.76
const MAX_RATIO = 0.84

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

    ;(p.variants || []).forEach((v: any) => {
      if (!colorMap[v.color_name]) {
        colorMap[v.color_name] = v.color_hex || '#000000'
        loadedColors.push({ name: v.color_name, hex: v.color_hex || '#000000' })
      }
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
}

function removeColor(name: string) {
  const idx = colors.value.findIndex(c => c.name === name)
  if (idx === -1) return
  for (const img of uploadedImages.value) {
    if (img.colorName === name) img.colorName = null
  }
  colors.value.splice(idx, 1)
}

function addSize() {
  const val = sizeInputVal.value.trim()
  if (!val) {
    toast.warning('Valor requerido', 'Ingresá un número de talle.')
    return
  }
  if (sizes.value.includes(val)) return
  sizes.value.push(val)
  sizes.value.sort((a, b) => parseInt(a, 10) - parseInt(b, 10))
  sizeInputVal.value = ''
}

function removeSize(val: string) {
  const idx = sizes.value.indexOf(val)
  if (idx === -1) return
  sizes.value.splice(idx, 1)
}

function setSizePreset(values: string[], prefix: string) {
  sizes.value = [...values].sort((a, b) => parseInt(a, 10) - parseInt(b, 10))
  if (prefix) sizePrefix.value = prefix
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
    <div v-show="activeTab === 'info'" id="tab-panel-info" role="tabpanel" class="max-w-2xl admin-card p-4 md:p-6">
      <h2 class="text-lg font-semibold text-[#dae2fd] mb-6 flex items-center gap-2">
        <VunoIcon icon="description" :size="24" />
        Información del Producto
      </h2>
      <div class="space-y-6">
        <div>
          <label for="fieldName" class="block text-xs font-semibold tracking-widest text-[#94a3b8] mb-2 uppercase">NOMBRE DEL PRODUCTO *</label>
          <div class="relative">
            <input id="fieldName" v-model="nameField" type="text" required maxlength="255"
              class="w-full bg-transparent border-b border-[#1e293b] pb-2 pr-16 text-[#dae2fd] focus:border-[#42b883] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#42b883] focus-visible:ring-offset-1 text-lg transition-colors" />
            <span class="absolute right-0 bottom-2 text-xs text-[#94a3b8] pointer-events-none">{{ nameField.length }}/255</span>
          </div>
          <div v-if="computedSlug" class="text-sm text-[#94a3b8] mt-1">
            <span class="text-[#42b883]">/producto/</span><span>{{ computedSlug }}</span>
          </div>
        </div>
        <div>
          <label for="fieldDescription" class="block text-xs font-semibold tracking-widest text-[#94a3b8] mb-2 uppercase">DESCRIPCIÓN</label>
          <div class="relative">
            <textarea id="fieldDescription" v-model="descriptionField"
              class="w-full bg-transparent border border-[#1e293b] p-3 pb-8 text-[#dae2fd] focus:border-[#42b883] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#42b883] focus-visible:ring-offset-1 resize-none h-32"></textarea>
            <span class="absolute right-2 bottom-2 text-xs text-[#94a3b8] pointer-events-none">{{ descriptionField.length }} caracteres</span>
          </div>
        </div>
        <div>
          <label for="fieldDetails" class="block text-xs font-semibold tracking-widest text-[#94a3b8] mb-2 uppercase">DETALLES (uno por línea)</label>
          <textarea id="fieldDetails" v-model="detailsField"
            class="w-full bg-transparent border border-[#1e293b] p-3 text-[#dae2fd] focus:border-[#42b883] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#42b883] focus-visible:ring-offset-1 resize-none h-24"
            placeholder="Ej:&#10;Piel genuina italiana&#10;Suela antideslizante&#10;Tacón de acero inoxidable"></textarea>
        </div>
      </div>
    </div>

    <!-- Tab: Pricing -->
    <div v-show="activeTab === 'pricing'" id="tab-panel-pricing" role="tabpanel" class="max-w-2xl admin-card p-4 md:p-6">
      <h2 class="text-lg font-semibold text-[#dae2fd] mb-6 flex items-center gap-2">
        <VunoIcon icon="sell" :size="24" />
        Precio y Categoría
      </h2>
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

    <!-- Tab: Variants -->
    <div v-show="activeTab === 'variants'" id="tab-panel-variants" role="tabpanel" class="admin-card p-6">
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
            <input v-model="colorHexInput" type="color" class="w-9 h-9 p-0.5 bg-[#1e293b] border border-[#1e293b] cursor-pointer rounded-sm" />
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
          <button type="button" class="size-preset h-8 px-3 bg-[#1e293b] text-[#94a3b8] text-xs font-semibold tracking-widest rounded-sm hover:bg-[#1e293b]/80 hover:text-[#dae2fd] transition-all border border-[#1e293b]" @click="setSizePreset(['35','36','37','38','39','40','41'], 'EU')">Mujer 35-41</button>
          <button type="button" class="size-preset h-8 px-3 bg-[#1e293b] text-[#94a3b8] text-xs font-semibold tracking-widest rounded-sm hover:bg-[#1e293b]/80 hover:text-[#dae2fd] transition-all border border-[#1e293b]" @click="setSizePreset(['39','40','41','42','43','44','45','46'], 'EU')">Hombre 39-46</button>
          <button type="button" class="size-preset h-8 px-3 bg-[#1e293b] text-[#94a3b8] text-xs font-semibold tracking-widest rounded-sm hover:bg-[#1e293b]/80 hover:text-[#dae2fd] transition-all border border-[#1e293b]" @click="setSizePreset(['28','29','30','31','32','33','34'], 'EU')">Niño 28-34</button>
          <button type="button" class="size-preset h-8 px-3 bg-[#1e293b] text-[#94a3b8] text-xs font-semibold tracking-widest rounded-sm hover:bg-[#1e293b]/80 hover:text-[#dae2fd] transition-all border border-[#1e293b]" @click="setSizePreset(['5','6','7','8','9','10','11'], 'US')">US Women 5-11</button>
          <button type="button" class="size-preset h-8 px-3 bg-[#1e293b] text-[#94a3b8] text-xs font-semibold tracking-widest rounded-sm hover:bg-[#1e293b]/80 hover:text-[#dae2fd] transition-all border border-[#1e293b]" @click="setSizePreset(['33','34','35','36','37','38','39'], 'BR')">BR 33-39</button>
        </div>
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
            <label class="block text-[10px] font-semibold tracking-widest text-[#94a3b8] mb-1 uppercase">PREFIJO</label>
            <input v-model="sizePrefix" type="text" maxlength="10" class="w-14 h-9 px-2 bg-[#1e293b] border border-[#1e293b] text-[#dae2fd] text-sm text-center focus:border-[#42b883] focus:outline-none rounded-sm" />
          </div>
          <div>
            <label class="block text-[10px] font-semibold tracking-widest text-[#94a3b8] mb-1 uppercase">AGREGAR TALLE</label>
            <input v-model="sizeInputVal" type="text" placeholder="Ej: 37"
              class="w-20 h-9 px-3 bg-[#1e293b] border border-[#1e293b] text-[#dae2fd] text-sm focus:border-[#42b883] focus:outline-none rounded-sm"
              @keydown.enter.prevent="addSize" />
          </div>
          <button type="button" class="h-9 px-4 bg-[#42b883] text-white text-xs font-semibold tracking-widest rounded-sm hover:bg-[#42b883]/90 transition-all disabled:opacity-40" @click="addSize">AGREGAR</button>
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

    <!-- Tab: Images -->
    <div v-show="activeTab === 'images'" id="tab-panel-images" role="tabpanel" class="max-w-2xl admin-card p-4 md:p-6">
      <h2 class="text-lg font-semibold text-[#dae2fd] mb-6 flex items-center gap-2">
        <VunoIcon icon="photo_library" :size="24" />
        Imágenes del Producto
      </h2>
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
            <select v-model="img.colorName" class="w-full text-xs bg-transparent border border-[#1e293b] rounded-sm px-1 py-0.5 text-[#dae2fd] focus:border-[#42b883] focus:outline-none">
              <option :value="null">Todas</option>
              <option v-for="c in colors" :key="c.name" :value="c.name">{{ c.name }}</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab: SEO -->
    <div v-show="activeTab === 'seo'" id="tab-panel-seo" role="tabpanel" class="max-w-2xl admin-card p-4 md:p-6">
      <h2 class="text-lg font-semibold text-[#dae2fd] mb-6 flex items-center gap-2">
        <VunoIcon icon="travel_explore" :size="24" />
        SEO & Open Graph
      </h2>
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
