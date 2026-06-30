<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useVariantsMatrix, type MatrixColor } from './useVariantsMatrix'
import VunoIcon from './VunoIcon.vue'

interface Props {
  colors: MatrixColor[]
  sizes: string[]
  initialStock?: Record<string, number> | null
  lowStockThreshold?: number
  readonly?: boolean
  sizePrefix?: string
}

const props = withDefaults(defineProps<Props>(), {
  lowStockThreshold: 5,
  readonly: false,
  sizePrefix: 'EU',
})

const matrix = useVariantsMatrix()

const fillPromptColor = ref('')
const fillPromptVisible = ref(false)
const fillValue = ref(5)
const activeMobileColor = ref(0)

onMounted(() => {
  matrix.init(props.colors, props.sizes, props.initialStock, props.lowStockThreshold, props.sizePrefix)
})

watch(() => props.colors, (val) => {
  for (const c of val) {
    const existing = matrix.colors.value.find(x => x.name === c.name)
    if (existing) {
      existing.hex = c.hex
    } else {
      matrix.addColor(c.name, c.hex)
    }
  }
  for (const c of matrix.colors.value) {
    if (!val.some(x => x.name === c.name)) {
      matrix.removeColor(c.name)
    }
  }
}, { deep: true })

watch(() => props.sizes, (val) => {
  for (const s of val) {
    if (!matrix.sizes.value.includes(s)) {
      matrix.addSize(s)
    }
  }
  for (const s of matrix.sizes.value) {
    if (!val.includes(s)) {
      matrix.removeSize(s)
    }
  }
}, { deep: true })

watch(() => props.lowStockThreshold, (val) => {
  matrix.threshold.value = val
})

watch(() => props.sizePrefix, (val) => {
  matrix.sizePrefix.value = val || 'EU'
})

function showFillPrompt(color: string) {
  fillPromptColor.value = color
  fillValue.value = 5
  fillPromptVisible.value = true
}

function hideFillPrompt() {
  fillPromptVisible.value = false
  fillPromptColor.value = ''
}

function applyFill() {
  if (!fillPromptColor.value) return
  const n = Math.max(0, Math.min(9999, parseInt(String(fillValue.value), 10) || 0))
  for (const s of matrix.sizes.value) {
    matrix.setStock(fillPromptColor.value, s, n)
  }
  hideFillPrompt()
}

function toggleColor(color: string) {
  const total = matrix.getColorTotal(color)
  if (total > 0) {
    for (const s of matrix.sizes.value) {
      matrix.setStock(color, s, 0)
    }
  } else {
    showFillPrompt(color)
  }
}

function clearColor(color: string) {
  for (const s of matrix.sizes.value) {
    matrix.setStock(color, s, 0)
  }
}

function handleInput(color: string, size: string, event: Event) {
  const val = parseInt((event.target as HTMLInputElement).value, 10) || 0
  matrix.setStock(color, size, val)
}

function handleKeydown(event: KeyboardEvent, color: string, size: string) {
  const input = event.target as HTMLInputElement
  const colorIdx = matrix.colors.value.findIndex(c => c.name === color)
  const sizeIdx = matrix.sizes.value.indexOf(size)

  if (event.key === 'ArrowUp') {
    event.preventDefault()
    matrix.setStock(color, size, matrix.getStock(color, size) + 1)
  } else if (event.key === 'ArrowDown') {
    event.preventDefault()
    matrix.setStock(color, size, Math.max(0, matrix.getStock(color, size) - 1))
  } else if (event.key === 'ArrowRight') {
    event.preventDefault()
    if (colorIdx < matrix.colors.value.length - 1) {
      const next = matrix.colors.value[colorIdx + 1].name
      focusCell(next, size)
    }
  } else if (event.key === 'ArrowLeft') {
    event.preventDefault()
    if (colorIdx > 0) {
      const prev = matrix.colors.value[colorIdx - 1].name
      focusCell(prev, size)
    }
  }
}

function focusCell(color: string, size: string) {
  const cell = document.querySelector<HTMLInputElement>(
    `input[data-vm-input="${color.replace(/[^a-zA-Z0-9_-]/g, '\\$&')}"][data-size="${size}"]`
  )
  cell?.focus()
}

function computeCellClass(sc: number, t: number) {
  if (sc <= 0) return 'vm-cell-empty'
  if (sc <= t) return 'vm-cell-low'
  return 'vm-cell-ok'
}

function computeColorCardClass(color: string) {
  const state = matrix.getColorState(color)
  if (state === 'empty') return 'vm-color-empty'
  if (state === 'low') return 'vm-color-low'
  return 'vm-color-ok'
}

function computeColorDotClass(color: string) {
  const state = matrix.getColorState(color)
  if (state === 'ok') return 'vm-dot-ok'
  if (state === 'low') return 'vm-dot-low'
  return 'vm-dot-empty'
}

defineExpose({
  toPayload: matrix.toPayload,
  reset: matrix.reset,
  getStock: matrix.getStock,
  setStock: matrix.setStock,
})
</script>

<template>
  <div class="variants-matrix w-full">
    <!-- HEADER: Title + threshold + total -->
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 lg:gap-4 mb-5">
      <div>
        <h3 class="font-headline text-headline-md flex items-center gap-2 text-[#dae2fd]">
          <VunoIcon icon="inventory" :size="24" />
          Matriz de Inventario
        </h3>
        <p class="font-body text-body-sm text-[#94a3b8] mt-1">
          Define el stock por color y talle.
        </p>
      </div>
      <div class="flex items-center gap-3 lg:gap-5 shrink-0">
        <label class="flex items-center gap-2 cursor-pointer whitespace-nowrap">
          <span class="font-label-caps text-label-caps text-[#94a3b8]">Alerta bajo stock</span>
          <div class="relative">
            <input type="number" min="0" max="99" step="1"
              :value="matrix.threshold.value"
              @input="matrix.threshold.value = Math.max(0, Math.min(99, parseInt(($event.target as HTMLInputElement).value, 10) || 0))"
              class="w-20 h-9 pl-2 pr-10 text-center font-price-display text-base bg-[#1e293b] border border-[#1e293b] rounded-sm text-[#dae2fd] focus:border-[#42b883] focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
              :disabled="readonly" />
            <span class="absolute right-2 top-1/2 -translate-y-1/2 font-label-caps text-label-caps text-[#94a3b8] text-[10px] pointer-events-none">und</span>
          </div>
        </label>
        <div class="text-right">
          <div class="font-label-caps text-label-caps text-[#94a3b8] whitespace-nowrap">Total</div>
          <div class="flex items-baseline gap-1.5 justify-end">
            <span class="font-price-display text-price-display text-xl lg:text-2xl text-[#dae2fd] leading-none">{{ matrix.getGrandTotal() }}</span>
            <span class="font-label-caps text-label-caps text-[#94a3b8] text-[10px]">und</span>
          </div>
          <div class="font-body text-body-sm text-[#94a3b8] mt-0.5 whitespace-nowrap">{{ matrix.getActiveCount() }} variantes activas</div>
        </div>
      </div>
    </div>

    <!-- Simplified legend -->
    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mb-5 pb-5 border-b border-[#1e293b]/40">
      <span class="flex items-center gap-1.5">
        <span class="w-2.5 h-2.5 rounded-full inline-block bg-transparent border border-[#1e293b]"></span>
        <span class="text-xs text-[#94a3b8]">Sin stock</span>
      </span>
      <span class="flex items-center gap-1.5">
        <span class="w-2.5 h-2.5 rounded-full inline-block bg-[#B8956A]"></span>
        <span class="text-xs text-[#94a3b8]">Bajo (≤ {{ matrix.threshold.value }})</span>
      </span>
      <span class="flex items-center gap-1.5">
        <span class="w-2.5 h-2.5 rounded-full inline-block bg-[#42b883]"></span>
        <span class="text-xs text-[#94a3b8]">Disponible</span>
      </span>
    </div>

    <!-- ===== TABLET/DESKTOP TABLE (lg+) ===== -->
    <div class="hidden lg:block overflow-x-auto pb-2">
      <table class="vm-table w-full border-separate border-spacing-[1px]">
        <thead>
          <tr>
            <th class="sticky left-0 z-10 w-14 lg:w-20 p-0 bg-[#0a1022]"></th>
            <th v-for="c in matrix.colors.value" :key="c.name" class="p-0 align-top whitespace-nowrap">
              <div :class="['flex flex-col items-center gap-1 p-2 lg:p-3 bg-[#162240] border border-[#1e293b] rounded-sm min-w-[90px] lg:min-w-[120px] transition-colors duration-200 group', computeColorCardClass(c.name)]">
                <button type="button" :disabled="readonly"
                  class="relative w-8 h-8 lg:w-9 lg:h-9 inline-flex items-center justify-center border border-[#1e293b] rounded-full cursor-pointer p-1 shrink-0 transition-all duration-200 hover:enabled:border-[#42b883] hover:enabled:bg-[#42b883] disabled:opacity-70 disabled:cursor-default"
                  @click="toggleColor(c.name)" :aria-label="'Alternar color ' + c.name">
                  <span class="block w-full h-full rounded-full shadow-[inset_0_0_0_1px_rgba(0,0,0,0.06)]" :style="{ backgroundColor: c.hex }"></span>
                  <span :class="['absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border-2 border-[#0a1022]', computeColorDotClass(c.name)]"></span>
                </button>
                <div class="text-center w-full">
                  <div class="font-label-caps text-label-caps text-[#dae2fd] truncate text-[11px] lg:text-xs">{{ c.name }}</div>
                  <div class="flex items-center justify-center gap-1">
                    <span class="font-price-display text-price-display text-sm lg:text-base text-[#dae2fd] leading-none">{{ matrix.getColorTotal(c.name) }}</span>
                    <span class="font-label-caps text-label-caps text-[#94a3b8] text-[9px]">und</span>
                  </div>
                </div>
                <div class="flex gap-1 mt-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                  <button type="button" :disabled="readonly"
                    class="w-6 h-6 inline-flex items-center justify-center bg-transparent border border-[#1e293b] rounded-sm text-[#94a3b8] cursor-pointer transition-all duration-200 hover:enabled:border-[#42b883] hover:enabled:bg-[#42b883] hover:enabled:text-white disabled:opacity-40 disabled:cursor-not-allowed"
                    title="Aplicar N a todas las talles" @click="showFillPrompt(c.name)">
                    <VunoIcon icon="grid" :size="12" />
                  </button>
                  <button type="button" :disabled="readonly"
                    class="w-6 h-6 inline-flex items-center justify-center bg-transparent border border-[#1e293b] rounded-sm text-[#94a3b8] cursor-pointer transition-all duration-200 hover:enabled:border-[#42b883] hover:enabled:bg-[#42b883] hover:enabled:text-white disabled:opacity-40 disabled:cursor-not-allowed"
                    title="Limpiar color" @click="clearColor(c.name)">
                    <VunoIcon icon="close" :size="12" />
                  </button>
                </div>
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="sz in matrix.sizes.value" :key="sz" class="vm-row">
            <th class="sticky left-0 z-10 p-0 align-middle bg-[#0a1022]">
              <div class="flex items-center gap-1.5 py-1.5 pl-1 lg:pl-2 pr-2 border-r-2 border-[#1e293b]">
                <div class="text-right leading-none">
                  <div class="font-label-caps text-label-caps text-[#94a3b8] text-[9px] leading-tight">{{ matrix.sizePrefix.value }}</div>
                  <div class="font-price-display text-price-display text-sm lg:text-base text-[#dae2fd] leading-none">{{ sz }}</div>
                </div>
                <div class="text-left text-[10px] text-[#94a3b8] leading-tight">
                  <div class="font-semibold">{{ matrix.getSizeTotal(sz) }}</div>
                  <div class="text-[8px]">und</div>
                </div>
              </div>
            </th>
            <td v-for="c in matrix.colors.value" :key="c.name + '_' + sz" class="p-0">
              <div :class="['inline-flex items-stretch w-full h-[34px] lg:h-[36px] bg-[#111d2e] border border-[#1e293b] rounded-sm transition-all duration-150 overflow-hidden focus-within:border-[#42b883] focus-within:shadow-[0_0_0_2px_rgba(66,184,131,0.15)]', computeCellClass(matrix.getStock(c.name, sz), matrix.threshold.value)]"
                role="group" :aria-label="'Stock ' + c.name + ' talle ' + sz">
                <button type="button" :disabled="readonly"
                  class="w-7 lg:w-8 shrink-0 inline-flex items-center justify-center bg-transparent border-0 text-[#94a3b8] cursor-pointer transition-all duration-150 hover:enabled:bg-[#42b883] hover:enabled:text-white disabled:opacity-40 disabled:cursor-not-allowed"
                  @click="matrix.setStock(c.name, sz, Math.max(0, matrix.getStock(c.name, sz) - 1))" aria-label="Disminuir">
                  <VunoIcon icon="remove" :size="14" />
                </button>
                <input type="number" min="0" step="1"
                  :value="matrix.getStock(c.name, sz)"
                  @input="handleInput(c.name, sz, $event)"
                  @keydown="handleKeydown($event, c.name, sz)"
                  @blur="($event.target as HTMLInputElement).value = String(matrix.getStock(c.name, sz))"
                  :readonly="readonly"
                  class="vm-step-input flex-1 min-w-[32px] lg:min-w-[40px] text-center font-price-display text-xs lg:text-sm bg-transparent border-0 text-[#dae2fd] p-0 outline-none focus:bg-[#42b883]/10"
                  :data-vm-input="c.name" :data-size="sz"
                  :aria-label="'Cantidad ' + c.name + ' ' + sz" />
                <button type="button" :disabled="readonly"
                  class="w-7 lg:w-8 shrink-0 inline-flex items-center justify-center bg-transparent border-0 text-[#94a3b8] cursor-pointer transition-all duration-150 hover:enabled:bg-[#42b883] hover:enabled:text-white disabled:opacity-40 disabled:cursor-not-allowed"
                  @click="matrix.setStock(c.name, sz, matrix.getStock(c.name, sz) + 1)" aria-label="Aumentar">
                  <VunoIcon icon="add" :size="14" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <th class="sticky left-0 z-10 bg-[#0a1022] text-right align-top pt-2 pr-2">
              <span class="font-label-caps text-label-caps text-[#94a3b8] text-[10px] tracking-widest">TOTAL</span>
            </th>
            <td v-for="c in matrix.colors.value" :key="c.name" class="pt-2 text-center border-t border-[#1e293b]">
              <span class="font-price-display text-price-display text-sm lg:text-base text-[#dae2fd]">{{ matrix.getColorTotal(c.name) }}</span>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- ===== MOBILE/TABLET: Single-color vertical layout (<lg) ===== -->
    <div class="lg:hidden">
      <div v-if="matrix.colors.value.length === 0" class="text-center py-8 text-[#94a3b8] text-sm">
        Agregá colores arriba para comenzar.
      </div>
      <template v-else>
        <!-- Color selector swatches -->
        <div class="flex gap-2 overflow-x-auto pb-2 -mx-1 px-1 mb-4">
          <button v-for="(c, i) in matrix.colors.value" :key="c.name"
            type="button" :disabled="readonly"
            :class="['flex flex-col items-center gap-1 p-2 rounded-sm border transition-all duration-200 min-w-[72px]',
              i === activeMobileColor
                ? 'border-[#42b883] bg-[#42b883]/10'
                : 'border-[#1e293b] bg-[#162240] opacity-60']"
            @click="activeMobileColor = i">
            <span class="relative w-8 h-8 inline-flex items-center justify-center border border-[#1e293b] rounded-full p-1 shrink-0">
              <span class="block w-full h-full rounded-full" :style="{ backgroundColor: c.hex }"></span>
              <span :class="['absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border-2 border-[#0a1022]', computeColorDotClass(c.name)]"></span>
            </span>
            <span class="font-label-caps text-label-caps text-[#dae2fd] text-[10px] truncate max-w-[64px]">{{ c.name }}</span>
            <span class="font-price-display text-price-display text-xs text-[#94a3b8]">{{ matrix.getColorTotal(c.name) }}</span>
          </button>
        </div>

        <!-- Active color header -->
        <div v-if="matrix.colors.value[activeMobileColor]" class="mb-4 px-1">
          <div class="flex items-center gap-2 mb-3">
            <span class="w-5 h-5 rounded-full border border-[#1e293b] shrink-0" :style="{ backgroundColor: matrix.colors.value[activeMobileColor].hex }"></span>
            <span class="font-label-caps text-label-caps text-[#dae2fd] text-base">{{ matrix.colors.value[activeMobileColor].name }}</span>
            <span class="font-price-display text-price-display text-lg text-[#dae2fd]">{{ matrix.getColorTotal(matrix.colors.value[activeMobileColor].name) }}</span>
            <span class="text-[10px] text-[#94a3b8]">und</span>
          </div>
          <div class="flex gap-2">
            <button type="button" :disabled="readonly"
              class="touch-target h-10 flex-1 inline-flex items-center justify-center gap-1.5 bg-transparent border border-[#1e293b] rounded-sm text-xs text-[#94a3b8] cursor-pointer hover:enabled:border-[#42b883] hover:enabled:bg-[#42b883] hover:enabled:text-white disabled:opacity-40 disabled:cursor-not-allowed transition-all"
              @click="showFillPrompt(matrix.colors.value[activeMobileColor].name)">
              <VunoIcon icon="grid" :size="16" /> RELLENAR
            </button>
            <button type="button" :disabled="readonly"
              class="touch-target h-10 flex-1 inline-flex items-center justify-center gap-1.5 bg-transparent border border-[#1e293b] rounded-sm text-xs text-[#94a3b8] cursor-pointer hover:enabled:border-[#DC2626] hover:enabled:bg-[#DC2626] hover:enabled:text-white disabled:opacity-40 disabled:cursor-not-allowed transition-all"
              @click="clearColor(matrix.colors.value[activeMobileColor].name)">
              <VunoIcon icon="close" :size="16" /> LIMPIAR
            </button>
          </div>
        </div>

        <!-- Size rows for active color -->
        <div v-for="sz in matrix.sizes.value" :key="sz" class="mb-2 last:mb-0">
          <div :class="['flex items-stretch h-[48px] rounded-sm overflow-hidden border transition-all duration-150 focus-within:border-[#42b883] focus-within:shadow-[0_0_0_2px_rgba(66,184,131,0.15)]', computeCellClass(matrix.getStock(matrix.colors.value[activeMobileColor]?.name || '', sz), matrix.threshold.value)]"
            role="group">
            <div class="w-16 shrink-0 flex flex-col items-center justify-center bg-[#162240] border-r border-[#1e293b]">
              <span class="font-label-caps text-label-caps text-[#94a3b8] text-[9px] leading-tight">{{ matrix.sizePrefix.value }}</span>
              <span class="font-price-display text-price-display text-sm text-[#dae2fd] leading-none">{{ sz }}</span>
            </div>
            <button type="button" :disabled="readonly"
              class="w-12 shrink-0 inline-flex items-center justify-center bg-transparent border-0 text-[#94a3b8] cursor-pointer transition-all duration-150 hover:enabled:bg-[#42b883] hover:enabled:text-white disabled:opacity-40 disabled:cursor-not-allowed touch-target"
              @click="matrix.setStock(matrix.colors.value[activeMobileColor]?.name || '', sz, Math.max(0, matrix.getStock(matrix.colors.value[activeMobileColor]?.name || '', sz) - 1))" aria-label="Disminuir">
              <VunoIcon icon="remove" :size="22" />
            </button>
            <input type="number" min="0" step="1"
              :value="matrix.getStock(matrix.colors.value[activeMobileColor]?.name || '', sz)"
              @input="handleInput(matrix.colors.value[activeMobileColor]?.name || '', sz, $event)"
              @keydown="handleKeydown($event, matrix.colors.value[activeMobileColor]?.name || '', sz)"
              @blur="($event.target as HTMLInputElement).value = String(matrix.getStock(matrix.colors.value[activeMobileColor]?.name || '', sz))"
              :readonly="readonly"
              class="vm-step-input flex-1 min-w-[60px] text-center font-price-display text-base font-semibold bg-transparent border-0 text-[#dae2fd] p-0 outline-none focus:bg-[#42b883]/10"
              :data-vm-input="matrix.colors.value[activeMobileColor]?.name || ''"
              :data-size="sz"
              :aria-label="'Cantidad ' + (matrix.colors.value[activeMobileColor]?.name || '') + ' ' + sz" />
            <button type="button" :disabled="readonly"
              class="w-12 shrink-0 inline-flex items-center justify-center bg-transparent border-0 text-[#94a3b8] cursor-pointer transition-all duration-150 hover:enabled:bg-[#42b883] hover:enabled:text-white disabled:opacity-40 disabled:cursor-not-allowed touch-target"
              @click="matrix.setStock(matrix.colors.value[activeMobileColor]?.name || '', sz, matrix.getStock(matrix.colors.value[activeMobileColor]?.name || '', sz) + 1)" aria-label="Aumentar">
              <VunoIcon icon="add" :size="22" />
            </button>
          </div>
        </div>
      </template>
    </div>

    <!-- FILL PROMPT -->
    <div v-if="fillPromptVisible"
      class="flex flex-wrap items-center gap-2 mt-3 p-3 bg-[#42b883]/10 border border-[#42b883]/20 rounded-sm animate-[vm-slide-down_200ms_ease-out]">
      <span class="font-label-caps text-label-caps text-[#dae2fd] text-sm">Aplicar a <strong>{{ fillPromptColor }}</strong>:</span>
      <input type="number" min="0" step="1" v-model.number="fillValue"
        class="w-16 h-8 px-2 text-center font-price-display text-base bg-[#1e293b] border border-[#1e293b] rounded-sm text-[#dae2fd] focus:border-[#42b883] focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
      <span class="font-label-caps text-label-caps text-[#94a3b8] text-sm">und a cada talle</span>
      <button type="button" class="font-label-caps text-label-caps text-white bg-[#42b883] border border-[#42b883] px-3 h-8 hover:bg-[#42b883]/90 transition-all" @click="applyFill">APLICAR</button>
      <button type="button" class="font-label-caps text-label-caps text-[#94a3b8] hover:text-[#dae2fd] transition-colors" @click="hideFillPrompt">CANCELAR</button>
    </div>
  </div>
</template>

<style scoped>
@reference "tailwindcss";
.vm-table { border-spacing: 2px; }
.vm-step-input { appearance: textfield; }
.vm-step-input::-webkit-outer-spin-button,
.vm-step-input::-webkit-inner-spin-button { appearance: none; }
.vm-row:hover td > div { border-color: rgba(218, 226, 253, 0.15); }
.vm-cell-empty { @apply bg-[#111d2e] opacity-40; }
.vm-cell-low { @apply bg-[#B8956A]/10 border-[#B8956A]/40; }
.vm-cell-ok { @apply bg-[#42b883]/8; }
.vm-color-empty { @apply opacity-50; }
.vm-color-low { @apply border-[#B8956A]/50 shadow-[inset_3px_0_0_#B8956A]; }
.vm-color-ok { @apply shadow-[inset_3px_0_0_#42b883]; }
.vm-dot-empty { @apply bg-transparent border-[#1e293b]; }
.vm-dot-low { @apply bg-[#B8956A]; }
.vm-dot-ok { @apply bg-[#42b883]; }
@keyframes vm-slide-down {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
