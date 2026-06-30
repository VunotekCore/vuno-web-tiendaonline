<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'
import VunoIcon from './VunoIcon.vue'

interface Variant {
  id: number
  color_name: string
  size_value: string
  stock: number
  price_override?: number
}

interface Product {
  id: number
  name: string
  price: number
  display_price?: number
  display_symbol?: string
  description?: string
  category?: string
  totalStock?: number
  lowStockThreshold?: number
  colors?: Array<{ name: string; hex?: string; image?: string }>
  variants?: Variant[]
  images?: string[]
  imagesByColor?: Record<string, string[]>
}

interface ConfigResponse {
  currency?: { symbol?: string; exchange_rate?: number; decimal_places?: number }
  tax?: { rate?: number }
}

interface CartItem {
  variant_id: number
  product_id: number
  product_name: string
  color_name: string
  size_label: string
  price: number
  quantity: number
  max_stock: number
}

interface CreatePosResponse {
  success: boolean
  id?: string
  total?: number
  items_count?: number
  error?: string
}

const api = useApi()
const toast = useToast()

const loading = ref(true)
const error = ref('')
const products = ref<Product[]>([])
const cart = ref<CartItem[]>([])
const paymentMethod = ref('pos_cash')
const currencySymbol = ref('$')
const exchangeRate = ref(1)
const decimalPlaces = ref(2)
const taxRate = ref(0)
const searchText = ref('')
const categoryFilter = ref('')

const selectedProduct = ref<Product | null>(null)
const selectedColor = ref('')
const selectedSize = ref('')
const addQty = ref(1)

const productListPanel = ref(true)
const productDetailPanel = ref(false)

// Mobile
const cartOverlay = ref(false)
const customerName = ref('Venta en Mostrador')
const customerEmail = ref('')

const categories = computed(() => {
  const set = new Set<string>()
  products.value.forEach(p => { if (p.category) set.add(p.category) })
  return Array.from(set).sort()
})

const filteredProducts = computed(() => {
  const filter = searchText.value.toLowerCase().trim()
  const cat = categoryFilter.value
  return products.value.filter(p => {
    if (cat && p.category !== cat) return false
    if (!filter) return true
    return (p.name || '').toLowerCase().includes(filter)
      || (p.colors || []).some(c => (c.name || '').toLowerCase().includes(filter))
      || (p.category || '').toLowerCase().includes(filter)
  })
})

const totalQty = computed(() => cart.value.reduce((s, i) => s + i.quantity, 0))
const subtotal = computed(() => cart.value.reduce((s, i) => s + i.price * i.quantity, 0) * exchangeRate.value)
const taxAmount = computed(() => subtotal.value * taxRate.value)
const grandTotal = computed(() => subtotal.value + taxAmount.value)
const hasItems = computed(() => cart.value.length > 0)

function formatPrice(val: number): string {
  const fixed = (parseFloat(String(val)) || 0).toFixed(decimalPlaces.value)
  const parts = fixed.split('.')
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',')
  return parts.join('.')
}

function esc(str?: string | number | null): string {
  const d = document.createElement('div')
  d.textContent = String(str ?? '')
  return d.innerHTML
}

onMounted(async () => {
  try {
    const [prodData, configData] = await Promise.all([
      api.get<{ items: Product[] }>('/api/productos/list.php?limit=200'),
      api.get<ConfigResponse>('/api/configuracion/public.php'),
    ])
    products.value = prodData.items || []

    if (configData?.currency?.symbol) currencySymbol.value = configData.currency.symbol
    if (configData?.currency?.exchange_rate) exchangeRate.value = parseFloat(String(configData.currency.exchange_rate)) || 1
    if (configData?.currency?.decimal_places) decimalPlaces.value = parseInt(String(configData.currency.decimal_places)) || 2
    const taxRateSetting = configData?.tax?.rate
    taxRate.value = taxRateSetting !== undefined && taxRateSetting !== '' ? parseFloat(String(taxRateSetting)) / 100 : 0

    loading.value = false
    nextTick(() => {
      const input = document.querySelector<HTMLInputElement>('#posSearch')
      if (input) setTimeout(() => input.focus(), 100)
    })
  } catch (err: any) {
    error.value = err.message
    loading.value = false
  }
})

function showProductDetail(productId: number | string) {
  const p = products.value.find(x => String(x.id) === String(productId))
  if (!p) return
  selectedProduct.value = p
  selectedColor.value = ''
  selectedSize.value = ''
  addQty.value = 1
  productListPanel.value = false
  productDetailPanel.value = true
}

function hideProductDetail() {
  selectedProduct.value = null
  selectedColor.value = ''
  selectedSize.value = ''
  productDetailPanel.value = false
  productListPanel.value = true
}

function selectColor(color: string) {
  selectedColor.value = selectedColor.value === color ? '' : color
  selectedSize.value = ''
  addQty.value = 1
}

function selectSize(size: string) {
  selectedSize.value = selectedSize.value === size ? '' : size
  addQty.value = 1
}

function changeAddQty(delta: number) {
  addQty.value = Math.max(1, addQty.value + delta)
}

const colorMap = computed(() => {
  if (!selectedProduct.value) return {}
  const map: Record<string, { stock: number; sizes: Record<string, number> }> = {}
  for (const v of (selectedProduct.value.variants || [])) {
    if (!map[v.color_name]) map[v.color_name] = { stock: 0, sizes: {} }
    const s = parseInt(String(v.stock)) || 0
    map[v.color_name].stock += s
    map[v.color_name].sizes[v.size_value] = (map[v.color_name].sizes[v.size_value] || 0) + s
  }
  return map
})

const selectedVariant = computed(() => {
  if (!selectedProduct.value || !selectedColor.value || !selectedSize.value) return null
  return (selectedProduct.value.variants || []).find(
    v => v.color_name === selectedColor.value && v.size_value === selectedSize.value
  ) || null
})

const displayPrice = computed(() => {
  if (!selectedProduct.value) return 0
  const p = selectedProduct.value
  if (selectedVariant.value && selectedVariant.value.price_override) {
    return parseFloat(String(selectedVariant.value.price_override)) * exchangeRate.value
  }
  const base = parseFloat(String(p.display_price ?? p.price)) || 0
  return base
})

function addToCart() {
  if (!selectedVariant.value || !selectedProduct.value) return
  const variantId = selectedVariant.value.id
  const existing = cart.value.find(c => c.variant_id === variantId)
  if (existing) {
    existing.quantity += addQty.value
    return
  }
  const p = selectedProduct.value
  const v = selectedVariant.value
  const price = v.price_override ? parseFloat(String(v.price_override)) : parseFloat(String(p.price))
  cart.value.push({
    variant_id: variantId,
    product_id: Number(p.id),
    product_name: p.name,
    color_name: v.color_name,
    size_label: v.size_value,
    price,
    quantity: addQty.value,
    max_stock: parseInt(String(v.stock)) || 0,
  })
  toast.success('Agregado', `${addQty.value}x ${p.name} — ${v.color_name}/${v.size_value}`)
}

function updateQty(idx: number, delta: number) {
  const newQty = cart.value[idx].quantity + delta
  if (newQty <= 0) {
    cart.value.splice(idx, 1)
  } else if (newQty <= cart.value[idx].max_stock) {
    cart.value[idx].quantity = newQty
  }
}

function removeItem(idx: number) {
  cart.value.splice(idx, 1)
}

function openCartOverlay() {
  cartOverlay.value = true
  document.body.classList.add('overflow-hidden')
}

function closeCartOverlay() {
  cartOverlay.value = false
  document.body.classList.remove('overflow-hidden')
}

async function processSale() {
  if (cart.value.length === 0) return
  try {
    const res = await api.post<CreatePosResponse>('/api/pedidos/create-pos.php', {
      cart_items: cart.value.map(i => ({ variant_id: i.variant_id, quantity: i.quantity })),
      payment_method: paymentMethod.value,
      customer_name: customerName.value.trim() || 'Venta en Mostrador',
      customer_email: customerEmail.value.trim() || undefined,
    })
    if (!res.success) throw new Error(res.error || 'Error al procesar venta')

    toast.success(
      'Venta procesada',
      `#${res.id} — ${currencySymbol.value}${formatPrice((res.total || 0) * exchangeRate.value)} — ${res.items_count} ítem(s)`
    )
    cart.value = []
    closeCartOverlay()
    hideProductDetail()
  } catch (err: any) {
    toast.error('Error', err.message)
  }
}

// Keyboard shortcuts
function onKeydown(e: KeyboardEvent) {
  if (e.key === '/' && e.target instanceof HTMLElement && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
    e.preventDefault()
    const input = document.querySelector<HTMLInputElement>('#posSearch')
    input?.focus()
  }
  if (e.key === 'Enter' && document.activeElement?.id === 'posSearch') {
    const filtered = filteredProducts.value
    if (filtered.length > 0) showProductDetail(filtered[0].id)
  }
  if (e.key === 'Escape') {
    if (cartOverlay.value) {
      closeCartOverlay()
    } else if (productDetailPanel.value) {
      hideProductDetail()
    } else {
      const input = document.querySelector<HTMLInputElement>('#posSearch')
      if (document.activeElement === input) input?.blur()
    }
  }
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
})
</script>

<template>
  <div v-if="loading" class="text-center py-8">
    <VunoIcon icon="progress_activity" :size="30" class="block mb-2 animate-spin text-[#dae2fd]" />
    <p class="text-[#94a3b8]">Cargando productos...</p>
  </div>

  <div v-else-if="error" class="bg-[#DC2626]/10 text-[#DC2626] p-3 rounded-sm">
    Error al cargar productos: {{ error }}
  </div>

  <div v-else class="admin-enter">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-1.5 sm:gap-2 mb-3 sm:mb-4">
      <a href="/admin/pos/dashboard" class="inline-flex items-center gap-1 text-xs sm:text-sm text-[#94a3b8] hover:text-[#dae2fd] transition-colors">
        <VunoIcon icon="dashboard" :size="20" />
        <span class="hidden sm:inline">Dashboard</span>
      </a>
      <span class="text-[#94a3b8]/30 text-xs sm:text-sm">/</span>
      <span class="text-xs sm:text-sm text-[#dae2fd] font-medium">Mostrador POS</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 pb-24 lg:pb-0">
      <!-- LEFT COLUMN (3/5) -->
      <div class="lg:col-span-3 space-y-4">
        <!-- Product list -->
        <div v-if="productListPanel">
          <div class="flex flex-col sm:flex-row items-stretch sm:items-end gap-3">
            <div class="relative flex-1">
              <VunoIcon icon="search" :size="20" class="absolute left-0 top-1/2 -translate-y-1/2 text-[#94a3b8]" />
              <input id="posSearch" v-model="searchText" type="text" placeholder="Buscar producto..."
                     class="w-full bg-transparent border-b border-[#1e293b] pb-3 pl-8 pr-8 text-[#dae2fd]
             focus:border-[#00A8FF] focus:outline-none placeholder:text-[#94a3b8]
                             text-base sm:text-lg" autocomplete="off" />
              <kbd class="absolute right-0 top-1/2 -translate-y-1/2 text-xs text-[#94a3b8]/40 border border-[#1e293b]/30
                          rounded-sm px-1.5 py-0.5 font-mono hidden sm:inline">/</kbd>
            </div>
            <div class="shrink-0">
              <label for="posCategory" class="sr-only">Categoría</label>
              <select id="posCategory" v-model="categoryFilter"
                      class="h-[42px] w-full sm:w-auto bg-[#1e293b] border border-[#1e293b] text-[#dae2fd] text-sm
                             rounded-sm px-3 cursor-pointer min-w-[140px]">
                <option value="">Todas las categorías</option>
                <option v-for="cat in categories" :key="cat" :value="cat">{{ esc(cat) }}</option>
              </select>
            </div>
          </div>

          <div id="productGrid"
               class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-3 max-h-[calc(100dvh-340px)] lg:max-h-[calc(100vh-280px)] overflow-y-auto pr-2 mt-4">
            <div v-if="filteredProducts.length === 0" class="col-span-full text-center py-12 text-[#94a3b8]">
              Sin resultados
            </div>
            <button v-for="p in filteredProducts" :key="p.id"
                    @click="showProductDetail(p.id)"
                     class="text-left glass-card overflow-hidden hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 group w-full"
                    :class="(p.totalStock || 0) <= 0 ? 'opacity-40' : ''"
                    :disabled="(p.totalStock || 0) <= 0">
              <div class="flex gap-2 sm:gap-3 p-2 sm:p-3">
                <img v-if="p.images?.[0]" :src="p.images[0]" alt="" class="w-12 sm:w-16 h-12 sm:h-16 object-cover rounded-sm shrink-0"
                     @error="($event.target as HTMLElement).style.display='none'" />
                <div v-else class="w-12 sm:w-16 h-12 sm:h-16 bg-[#1e293b] rounded-sm shrink-0 flex items-center justify-center">
                  <VunoIcon icon="inventory_2" :size="24" class="text-[#94a3b8]/30" />
                </div>
                <div class="flex-1 min-w-0">
                  <h4 class="text-sm sm:text-base font-semibold truncate text-[#dae2fd] group-hover:text-[#00A8FF] transition-colors">{{ esc(p.name) }}</h4>
                  <p class="text-sm font-semibold text-[#dae2fd] mt-0.5">{{ p.display_symbol || currencySymbol }}{{ formatPrice(parseFloat(String(p.display_price ?? p.price)) || 0) }}</p>
                  <p class="text-xs text-[#94a3b8] mt-1">
                    <span :class="(p.totalStock || 0) <= 0 ? 'text-[#DC2626]' : (p.totalStock || 0) <= (p.lowStockThreshold || 5) ? 'text-[#B8956A]' : ''">
                      {{ p.totalStock || 0 }} en stock
                    </span>
                    <span v-if="p.category" class="text-[#94a3b8]/50"> · {{ esc(p.category) }}</span>
                  </p>
                </div>
                <VunoIcon icon="chevron_right" :size="20" class="text-[#94a3b8]/30 self-center" />
              </div>
            </button>
          </div>
        </div>

        <!-- Product Detail -->
        <div v-if="productDetailPanel" class="sm:mb-0 max-sm:pb-20">
          <button @click="hideProductDetail" class="inline-flex items-center gap-1.5 text-sm text-[#94a3b8] hover:text-[#dae2fd] transition-colors mb-4">
            <VunoIcon icon="arrow_back" :size="20" />
            Volver a productos
          </button>

          <div v-if="selectedProduct" class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
              <img v-if="selectedProduct.images?.[0]"
                   :src="selectedProduct.images[0]" :alt="esc(selectedProduct.name)"
                   class="w-full h-56 object-cover rounded-sm"
                   @error="($event.target as HTMLElement).style.display='none'" />
              <div v-else class="w-full h-56 bg-[#1e293b] rounded-sm flex items-center justify-center">
                <VunoIcon icon="inventory_2" :size="36" class="text-[#94a3b8]/20" />
              </div>
              <div class="mt-3 space-y-1">
                <h3 class="text-lg font-semibold text-[#dae2fd]">{{ esc(selectedProduct.name) }}</h3>
                <p class="text-sm font-semibold text-[#dae2fd]">{{ currencySymbol }}{{ formatPrice(displayPrice) }}</p>
                <p class="text-xs text-[#94a3b8]">Stock total: {{ selectedProduct.totalStock || 0 }} unidad(es)</p>
                <p v-if="selectedProduct.description" class="text-sm text-[#94a3b8] mt-2 line-clamp-3">{{ esc(selectedProduct.description) }}</p>
              </div>
            </div>
            <div class="space-y-5">
              <!-- Colors -->
              <div class="space-y-2">
                <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase flex items-center gap-2">
                  COLOR
                  <span v-if="selectedColor" class="text-[10px] uppercase text-[#94a3b8]/50">· seleccionado: <strong class="text-[#dae2fd]">{{ esc(selectedColor) }}</strong></span>
                </label>
                <div class="flex flex-wrap gap-2">
                  <template v-if="(selectedProduct.colors || []).length > 0">
                    <button v-for="c in selectedProduct.colors" :key="c.name"
                            @click="selectColor(c.name)"
                            class="px-4 py-2.5 text-sm border rounded-sm transition-all duration-150 cursor-pointer"
                              :class="selectedColor === c.name
                                ? 'admin-btn-primary'
                                : 'bg-transparent text-[#dae2fd] border-[#dae2fd]/10 hover:border-[#00A8FF]'"
                            :disabled="(colorMap[c.name]?.stock || 0) <= 0"
                            :style="{ opacity: (colorMap[c.name]?.stock || 0) <= 0 ? 0.3 : 1 }">
                      <span class="font-medium">{{ esc(c.name) }}</span>
                      <span class="text-xs ml-1.5 px-1.5 py-0.5 rounded-sm font-medium"
                            :class="selectedColor === c.name ? 'bg-white/20 text-white' : 'bg-[#1e293b] text-[#94a3b8]'">
                        {{ colorMap[c.name]?.stock || 0 }}u
                      </span>
                    </button>
                  </template>
                  <span v-else class="text-sm text-[#94a3b8]">Sin colores disponibles</span>
                </div>
              </div>

              <!-- Sizes -->
              <div v-if="selectedColor" class="space-y-2">
                <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase">TALLE</label>
                <div class="flex flex-wrap gap-2">
                  <button v-for="(stock, size) in (colorMap[selectedColor]?.sizes || {})" :key="size"
                          @click="selectSize(size)"
                          class="px-4 py-2.5 text-sm border rounded-sm transition-all duration-150 cursor-pointer"
                              :class="selectedSize === size
                                 ? 'admin-btn-primary'
                                 : 'bg-transparent text-[#dae2fd] border-[#dae2fd]/10 hover:border-[#00A8FF]'"
                          :disabled="stock <= 0"
                          :style="{ opacity: stock <= 0 ? 0.3 : 1 }">
                    <span class="font-medium">{{ esc(size) }}</span>
                    <span class="text-xs ml-1.5 px-1.5 py-0.5 rounded-sm font-medium"
                          :class="selectedSize === size ? 'bg-white/20 text-white' : 'bg-[#1e293b] text-[#94a3b8]'">
                      {{ stock }}u
                    </span>
                  </button>
                </div>
              </div>

              <!-- Add to cart -->
              <div v-if="selectedVariant" class="pt-4 border-t border-[#dae2fd]/8 space-y-3">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase">STOCK DISPONIBLE</span>
                  <span class="text-sm font-semibold"
                        :class="(selectedVariant.stock || 0) <= (selectedProduct?.lowStockThreshold || 5) ? 'text-[#DC2626]' : 'text-[#dae2fd]'">
                    {{ selectedVariant.stock }} unidad(es)
                  </span>
                </div>
                <div class="flex items-center gap-3">
                  <span class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase shrink-0">CANTIDAD</span>
                  <div class="flex items-center gap-1">
                    <button @click="changeAddQty(-1)"
                            class="touch-target w-9 h-9 flex items-center justify-center border border-[#dae2fd]/10 rounded-sm text-sm hover:bg-white/5 transition-colors"
                            :class="addQty <= 1 ? 'opacity-30' : ''">−</button>
                    <span class="w-10 text-center font-semibold text-[#dae2fd] tabular-nums">{{ addQty }}</span>
                    <button @click="changeAddQty(1)"
                            class="touch-target w-9 h-9 flex items-center justify-center border border-[#dae2fd]/10 rounded-sm text-sm hover:bg-white/5 transition-colors"
                            :class="addQty >= (selectedVariant.stock || 0) ? 'opacity-30' : ''">+</button>
                  </div>
                </div>
                <button @click="addToCart"
                        class="w-full admin-btn admin-btn-primary min-h-[48px]"
                        :disabled="(selectedVariant.stock || 0) <= 0">
                  <VunoIcon icon="add_shopping_cart" :size="24" />
                  <span>Agregar al carrito — {{ currencySymbol }}{{ formatPrice(displayPrice) }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- DESKTOP CART (2/5) — glass-card -->
      <div class="hidden lg:block lg:col-span-2 glass-card overflow-hidden rounded-xl sticky top-0">
        <!-- HEADER -->
        <div class="flex items-center justify-between px-5 pt-5 pb-4 border-b border-[#dae2fd]/5">
          <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
            <VunoIcon icon="shopping_cart" :size="24" />
            Venta
          </h2>
          <span v-if="hasItems" class="text-xs font-semibold tracking-widest bg-[#42b883] text-white px-2 py-0.5 rounded-sm">{{ totalQty }}</span>
        </div>

        <!-- BODY -->
        <div class="px-5 py-4 space-y-5">
          <div v-if="!hasItems" class="text-center py-8 text-[#94a3b8]">
            <VunoIcon icon="add_shopping_cart" :size="36" class="block mb-2" />
            Carrito vacío<br />
            <span class="text-sm">Seleccioná un producto de la izquierda</span>
          </div>

          <div v-else class="space-y-3 divide-y divide-[#dae2fd]/8 max-h-[40vh] overflow-y-auto" id="cartItems">
            <div v-for="(item, idx) in cart" :key="item.variant_id"
                 class="flex items-start gap-3 py-3 first:pt-0">
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold truncate text-[#dae2fd]">{{ esc(item.product_name) }}</p>
                <p class="text-xs text-[#94a3b8]">{{ esc(item.color_name) }} / {{ esc(item.size_label) }}</p>
                <p class="text-sm font-semibold text-[#dae2fd]">{{ currencySymbol }}{{ formatPrice(item.price * item.quantity * exchangeRate) }}</p>
              </div>
              <div class="flex items-center gap-0.5 shrink-0">
                <button @click="updateQty(idx, -1)" class="touch-target w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center border border-[#dae2fd]/10 rounded-sm hover:bg-white/5 text-sm"
                        :class="item.quantity <= 1 ? 'opacity-30' : ''">−</button>
                <span class="w-8 text-center font-semibold text-[#dae2fd] tabular-nums">{{ item.quantity }}</span>
                <button @click="updateQty(idx, 1)" class="touch-target w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center border border-[#dae2fd]/10 rounded-sm hover:bg-white/5 text-sm">+</button>
                <button @click="removeItem(idx)" class="ml-1 text-[#DC2626]/60 hover:text-[#DC2626]" aria-label="Eliminar item">
                  <VunoIcon icon="delete" :size="20" />
                </button>
              </div>
            </div>
          </div>

          <div v-if="hasItems" class="space-y-2">
            <div class="flex justify-between text-sm">
              <span class="text-[#94a3b8]">Subtotal</span>
              <span class="font-semibold text-[#dae2fd]">{{ currencySymbol }}{{ formatPrice(subtotal) }}</span>
            </div>
            <div class="flex justify-between text-xs text-[#94a3b8]">
              <span>IVA</span>
              <span class="font-semibold">{{ currencySymbol }}{{ formatPrice(taxAmount) }}</span>
            </div>
            <div class="flex justify-between font-semibold text-lg border-t border-[#dae2fd]/8 pt-2">
              <span class="text-[#dae2fd]">Total</span>
              <span class="text-[#dae2fd]">{{ currencySymbol }}{{ formatPrice(grandTotal) }}</span>
            </div>
          </div>

          <div class="space-y-3">
            <div>
              <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-1">CLIENTE</label>
              <input v-model="customerName" type="text" placeholder="Nombre del cliente"
                     class="w-full bg-[#1e293b]/50 border border-[#dae2fd]/10 rounded-sm px-3 py-2.5 text-[#dae2fd]
                            focus:border-[#00A8FF] focus:outline-none placeholder:text-[#94a3b8]/50 text-sm" />
            </div>
            <div>
              <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-1">CORREO</label>
              <input v-model="customerEmail" type="email" placeholder="correo@ejemplo.com"
                     class="w-full bg-[#1e293b]/50 border border-[#dae2fd]/10 rounded-sm px-3 py-2.5 text-[#dae2fd]
                            focus:border-[#00A8FF] focus:outline-none placeholder:text-[#94a3b8]/50 text-sm" />
            </div>
          </div>

          <div class="space-y-2">
            <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block">MÉTODO DE PAGO</label>
            <div class="grid grid-cols-3 gap-2">
              <button v-for="pm in [{ code: 'pos_cash', label: 'Efectivo', icon: 'payments' }, { code: 'pos_card', label: 'Tarjeta', icon: 'credit_card' }, { code: 'pos_transfer', label: 'Transferencia', icon: 'account_balance' }]"
                      :key="pm.code"
                      @click="paymentMethod = pm.code"
                      class="min-h-[44px] px-4 rounded-sm flex items-center justify-center gap-2 text-xs font-semibold tracking-widest transition-all duration-150"
                      :class="paymentMethod === pm.code
                         ? 'admin-btn-primary'
                         : 'bg-transparent border border-[#dae2fd]/10 text-[#dae2fd] hover:border-[#00A8FF]'">
                <VunoIcon :icon="pm.icon" :size="20" />
                <span class="hidden lg:inline">{{ pm.label }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- FOOTER -->
        <div class="px-5 pb-5 pt-4 border-t border-[#dae2fd]/5">
          <button @click="processSale" :disabled="!hasItems"
                  class="w-full admin-btn admin-btn-primary min-h-[48px] disabled:opacity-40 disabled:cursor-not-allowed">
            <VunoIcon icon="point_of_sale" :size="24" />
            <span>Procesar Venta</span>
          </button>
        </div>
      </div>
    </div>

    <!-- MOBILE CART BAR -->
    <div class="lg:hidden fixed bottom-0 left-0 right-0 z-50 glass-card px-4 py-3 flex items-center gap-3"
         :class="hasItems ? 'translate-y-0' : 'translate-y-full'"
         style="transition: transform 0.3s ease-out">
      <div class="flex items-center gap-2 shrink-0">
        <VunoIcon icon="shopping_cart" :size="24" class="text-[#dae2fd]" />
        <span v-if="hasItems" class="text-xs font-semibold tracking-widest bg-[#42b883] text-white px-2 py-0.5 rounded-sm">{{ totalQty }}</span>
      </div>
      <div class="flex-1 min-w-0">
        <div class="flex justify-between items-center">
          <span class="text-xs text-[#94a3b8]">{{ totalQty }} ítem(s)</span>
          <span class="text-sm font-semibold text-[#dae2fd]">{{ currencySymbol }}{{ formatPrice(grandTotal) }}</span>
        </div>
      </div>
      <button @click="openCartOverlay" :disabled="!hasItems"
              class="admin-btn admin-btn-primary text-xs tracking-widest min-h-[40px] px-4 gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed">
        <VunoIcon icon="point_of_sale" :size="20" />
        <span class="hidden sm:inline">Ver carrito</span>
        <span class="inline sm:hidden">Ir</span>
      </button>
    </div>

    <!-- MOBILE CART OVERLAY -->
    <Teleport to="body">
      <div v-if="cartOverlay" class="lg:hidden fixed inset-0 z-[60] transition-opacity duration-300"
           :class="cartOverlay ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'">
        <div class="absolute inset-0 bg-black/40" @click="closeCartOverlay"></div>
        <div class="absolute bottom-0 left-0 right-0 glass-card rounded-t-xl max-h-[85dvh] overflow-y-auto px-5 py-6 shadow-2xl"
             style="transform: translateY(0); transition: transform 0.3s ease-out">
          <!-- Header -->
          <div class="flex items-center justify-between mb-5">
            <h2 class="text-lg font-semibold text-[#dae2fd] flex items-center gap-2">
              <VunoIcon icon="shopping_cart" :size="24" />
              Venta
            </h2>
            <button @click="closeCartOverlay" class="text-[#94a3b8] hover:text-[#dae2fd]"><VunoIcon icon="close" :size="28" /></button>
          </div>

          <!-- Cart items -->
          <div v-if="!hasItems" class="text-center py-8 text-[#94a3b8]">Carrito vacío</div>
          <div v-else class="space-y-3 divide-y divide-[#dae2fd]/8 max-h-[40vh] overflow-y-auto">
            <div v-for="(item, idx) in cart" :key="item.variant_id"
                 class="flex items-start gap-3 py-3 first:pt-0">
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold truncate text-[#dae2fd]">{{ esc(item.product_name) }}</p>
                <p class="text-xs text-[#94a3b8]">{{ esc(item.color_name) }} / {{ esc(item.size_label) }}</p>
                <p class="text-sm font-semibold text-[#dae2fd]">{{ currencySymbol }}{{ formatPrice(item.price * item.quantity * exchangeRate) }}</p>
              </div>
              <div class="flex items-center gap-0.5 shrink-0">
                <button @click="updateQty(idx, -1)" class="touch-target w-9 h-9 flex items-center justify-center border border-[#dae2fd]/10 rounded-sm hover:bg-white/5 text-sm"
                        :class="item.quantity <= 1 ? 'opacity-30' : ''">−</button>
                <span class="w-8 text-center font-semibold text-[#dae2fd] tabular-nums">{{ item.quantity }}</span>
                <button @click="updateQty(idx, 1)" class="touch-target w-9 h-9 flex items-center justify-center border border-[#dae2fd]/10 rounded-sm hover:bg-white/5 text-sm">+</button>
                <button @click="removeItem(idx)" class="ml-1 text-[#DC2626]/60 hover:text-[#DC2626]" aria-label="Eliminar item">
                  <VunoIcon icon="delete" :size="20" />
                </button>
              </div>
            </div>
          </div>

          <!-- Summary -->
          <div v-if="hasItems" class="mt-3 pt-3 border-t border-[#dae2fd]/8 space-y-2">
            <div class="flex justify-between text-sm">
              <span class="text-[#94a3b8]">Subtotal</span>
              <span class="font-semibold text-[#dae2fd]">{{ currencySymbol }}{{ formatPrice(subtotal) }}</span>
            </div>
            <div class="flex justify-between text-xs text-[#94a3b8]">
              <span>IVA</span>
              <span class="font-semibold">{{ currencySymbol }}{{ formatPrice(taxAmount) }}</span>
            </div>
            <div class="flex justify-between font-semibold text-lg border-t border-[#dae2fd]/8 pt-2">
              <span class="text-[#dae2fd]">Total</span>
              <span class="text-[#dae2fd]">{{ currencySymbol }}{{ formatPrice(grandTotal) }}</span>
            </div>
          </div>

          <!-- Customer -->
          <div class="mt-4 space-y-3">
            <div>
              <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-1">CLIENTE</label>
              <input v-model="customerName" type="text" placeholder="Nombre del cliente"
                     class="w-full bg-[#1e293b]/50 border border-[#dae2fd]/10 rounded-sm px-3 py-2.5 text-[#dae2fd]
                            focus:border-[#00A8FF] focus:outline-none placeholder:text-[#94a3b8]/50 text-sm" />
            </div>
            <div>
              <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block mb-1">CORREO</label>
              <input v-model="customerEmail" type="email" placeholder="correo@ejemplo.com"
                     class="w-full bg-[#1e293b]/50 border border-[#dae2fd]/10 rounded-sm px-3 py-2.5 text-[#dae2fd]
                            focus:border-[#00A8FF] focus:outline-none placeholder:text-[#94a3b8]/50 text-sm" />
            </div>
          </div>

          <!-- Payment method -->
          <div class="mt-3 space-y-2">
            <label class="text-xs font-semibold tracking-widest text-[#94a3b8] uppercase block">MÉTODO DE PAGO</label>
            <div class="grid max-sm:grid-cols-1 grid-cols-3 gap-2">
              <button v-for="pm in [{ code: 'pos_cash', label: 'Efectivo', icon: 'payments' }, { code: 'pos_card', label: 'Tarjeta', icon: 'credit_card' }, { code: 'pos_transfer', label: 'Transferencia', icon: 'account_balance' }]"
                      :key="pm.code"
                      @click="paymentMethod = pm.code"
                      class="min-h-[44px] px-4 rounded-sm flex items-center justify-center gap-2 text-xs font-semibold tracking-widest transition-all duration-150"
                      :class="paymentMethod === pm.code
                         ? 'admin-btn-primary'
                         : 'bg-transparent border border-[#dae2fd]/10 text-[#dae2fd] hover:border-[#00A8FF]'">
                <VunoIcon :icon="pm.icon" :size="20" />
                <span class="hidden sm:inline">{{ pm.label }}</span>
              </button>
            </div>
          </div>

          <!-- Process button -->
          <button @click="processSale" :disabled="!hasItems"
                  class="mt-5 w-full admin-btn admin-btn-primary min-h-[48px] disabled:opacity-40 disabled:cursor-not-allowed">
            <VunoIcon icon="point_of_sale" :size="20" />
            <span>Procesar Venta</span>
          </button>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
#productGrid::-webkit-scrollbar,
#cartItems::-webkit-scrollbar { width: 4px; }
#productGrid::-webkit-scrollbar-track,
#cartItems::-webkit-scrollbar-track { background: transparent; }
#productGrid::-webkit-scrollbar-thumb,
#cartItems::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 2px; }
@media (max-width: 1023px) {
  #productGrid { max-height: calc(100dvh - 340px); }
}
</style>
