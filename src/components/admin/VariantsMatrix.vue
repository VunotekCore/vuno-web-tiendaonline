<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useVariantsMatrix, type MatrixColor } from './useVariantsMatrix'

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

onMounted(() => {
  matrix.init(props.colors, props.sizes, props.initialStock, props.lowStockThreshold, props.sizePrefix)
})

watch(() => props.colors, (val) => {
  for (const c of val) {
    if (!matrix.colors.value.some(x => x.name === c.name)) {
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

function inputStyle(sc: number, t: number) {
  if (sc <= 0) return 'vm-input-empty'
  if (sc <= t) return 'vm-input-low'
  return 'vm-input-ok'
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
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 lg:gap-4 mb-5">
      <div>
        <h3 class="font-headline text-headline-md flex items-center gap-2 text-[#dae2fd]">
          <span class="material-symbols-outlined text-xl">inventory</span>
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
            <input
              type="number" min="0" max="99" step="1"
              :value="matrix.threshold.value"
              @input="matrix.threshold.value = Math.max(0, Math.min(99, parseInt(($event.target as HTMLInputElement).value, 10) || 0))"
              class="w-20 h-9 pl-2 pr-10 text-center font-price-display text-base bg-[#1e293b] border border-[#1e293b] rounded-sm text-[#dae2fd] focus:border-[#42b883] focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
              :disabled="readonly"
            />
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

    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mb-5 pb-5 border-b border-[#1e293b]/40">
      <span class="font-label-caps text-label-caps text-[#94a3b8]">Estados</span>
      <span class="flex items-center gap-1.5">
        <span class="w-2.5 h-2.5 rounded-full inline-block bg-transparent border border-[#1e293b]"></span>
        <span class="font-body text-body-sm text-[#94a3b8]">Sin stock</span>
      </span>
      <span class="flex items-center gap-1.5">
        <span class="w-2.5 h-2.5 rounded-full inline-block bg-[#42b883]"></span>
        <span class="font-body text-body-sm text-[#94a3b8]">Bajo (≤ {{ matrix.threshold.value }})</span>
      </span>
      <span class="flex items-center gap-1.5">
        <span class="w-2.5 h-2.5 rounded-full inline-block bg-[#dae2fd]"></span>
        <span class="font-body text-body-sm text-[#94a3b8]">Disponible</span>
      </span>
    </div>

    <!-- Desktop matrix -->
    <div class="hidden md:block overflow-x-auto pb-2">
      <table class="vm-table w-full border-separate border-spacing-1">
        <thead>
          <tr>
            <th class="w-16 p-0"></th>
            <th v-for="c in matrix.colors.value" :key="c.name" class="p-0 align-top whitespace-nowrap">
              <div :class="['flex flex-col items-center gap-1.5 p-3 bg-[#162240] border border-[#1e293b] rounded-sm min-w-[130px] transition-colors duration-200', computeColorCardClass(c.name)]">
                <button
                  type="button"
                  :disabled="readonly"
                  class="relative w-9 h-9 inline-flex items-center justify-center border border-[#1e293b] rounded-full cursor-pointer p-1 shrink-0 transition-all duration-200 hover:enabled:border-[#42b883] hover:enabled:bg-[#42b883] disabled:opacity-70 disabled:cursor-default group"
                  @click="toggleColor(c.name)"
                  :aria-label="'Alternar color ' + c.name"
                >
                  <span class="block w-full h-full rounded-full shadow-[inset_0_0_0_1px_rgba(0,0,0,0.06)]" :style="{ backgroundColor: c.hex }"></span>
                  <span :class="['absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border-2 border-[#0a1022]', computeColorDotClass(c.name)]"></span>
                </button>
                <div class="text-center w-full">
                  <div class="font-label-caps text-label-caps text-[#dae2fd] truncate">{{ c.name }}</div>
                  <div class="flex items-center gap-1">
                    <span class="font-price-display text-price-display text-base text-[#dae2fd] leading-none">{{ matrix.getColorTotal(c.name) }}</span>
                    <span class="font-label-caps text-label-caps text-[#94a3b8] text-[9px]">UND</span>
                  </div>
                </div>
                <div class="flex gap-1 mt-0.5">
                  <button
                    type="button" :disabled="readonly"
                    class="w-7 h-7 inline-flex items-center justify-center bg-transparent border border-[#1e293b] rounded-sm text-[#94a3b8] cursor-pointer transition-all duration-200 hover:enabled:border-[#42b883] hover:enabled:bg-[#42b883] hover:enabled:text-white disabled:opacity-40 disabled:cursor-not-allowed"
                    title="Aplicar N a todas las talles"
                    @click="showFillPrompt(c.name)"
                  >
                    <span class="material-symbols-outlined text-sm">deblur</span>
                  </button>
                  <button
                    type="button" :disabled="readonly"
                    class="w-7 h-7 inline-flex items-center justify-center bg-transparent border border-[#1e293b] rounded-sm text-[#94a3b8] cursor-pointer transition-all duration-200 hover:enabled:border-[#42b883] hover:enabled:bg-[#42b883] hover:enabled:text-white disabled:opacity-40 disabled:cursor-not-allowed"
                    title="Limpiar color"
                    @click="clearColor(c.name)"
                  >
                    <span class="material-symbols-outlined text-sm">backspace</span>
                  </button>
                </div>
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="sz in matrix.sizes.value" :key="sz">
            <th class="p-0 align-middle">
              <div class="text-right py-1.5 pr-2 border-r-2 border-[#1e293b]">
                <div class="font-label-caps text-label-caps text-[#94a3b8] text-[9px]">{{ matrix.sizePrefix.value }}</div>
                <div class="font-price-display text-price-display text-base text-[#dae2fd] leading-none">{{ sz }}</div>
                <div class="flex items-center gap-1 mt-1 justify-end">
                  <span class="font-body text-body-sm text-[#94a3b8] leading-none">{{ matrix.getSizeTotal(sz) }}</span>
                  <span class="font-label-caps text-label-caps text-[#94a3b8] text-[9px]">und</span>
                </div>
              </div>
            </th>
            <td v-for="c in matrix.colors.value" :key="c.name + '_' + sz" class="p-0">
              <div
                :class="['inline-flex items-stretch w-full h-[36px] bg-[#111d2e] border border-[#1e293b] rounded-sm transition-all duration-150 overflow-hidden focus-within:border-[#42b883] focus-within:shadow-[0_0_0_2px_rgba(66,184,131,0.15)]', computeCellClass(matrix.getStock(c.name, sz), matrix.threshold.value)]"
                role="group"
                :aria-label="'Stock ' + c.name + ' talle ' + sz"
              >
                <button
                  type="button" :disabled="readonly"
                  class="w-8 shrink-0 inline-flex items-center justify-center bg-transparent border-0 text-[#94a3b8] cursor-pointer transition-all duration-150 hover:enabled:bg-[#42b883] hover:enabled:text-white disabled:opacity-40 disabled:cursor-not-allowed"
                  @click="matrix.setStock(c.name, sz, Math.max(0, matrix.getStock(c.name, sz) - 1))"
                  aria-label="Disminuir"
                >
                  <span class="material-symbols-outlined text-base">remove</span>
                </button>
                <input
                  type="number" min="0" step="1"
                  :value="matrix.getStock(c.name, sz)"
                  @input="handleInput(c.name, sz, $event)"
                  @keydown="handleKeydown($event, c.name, sz)"
                  @blur="($event.target as HTMLInputElement).value = String(matrix.getStock(c.name, sz))"
                  :readonly="readonly"
                  class="vm-step-input flex-1 min-w-[40px] text-center font-price-display text-sm bg-transparent border-0 text-[#dae2fd] p-0 outline-none focus:bg-[#42b883]/10"
                  :data-vm-input="c.name"
                  :data-size="sz"
                  :aria-label="'Cantidad ' + c.name + ' ' + sz"
                />
                <button
                  type="button" :disabled="readonly"
                  class="w-8 shrink-0 inline-flex items-center justify-center bg-transparent border-0 text-[#94a3b8] cursor-pointer transition-all duration-150 hover:enabled:bg-[#42b883] hover:enabled:text-white disabled:opacity-40 disabled:cursor-not-allowed"
                  @click="matrix.setStock(c.name, sz, matrix.getStock(c.name, sz) + 1)"
                  aria-label="Aumentar"
                >
                  <span class="material-symbols-outlined text-base">add</span>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <th class="text-right align-top pt-3 pr-2">
              <span class="font-label-caps text-label-caps text-[#94a3b8] text-[10px] tracking-widest">TOTAL</span>
            </th>
            <td v-for="c in matrix.colors.value" :key="c.name" class="pt-3 text-center border-t border-[#1e293b]">
              <span class="font-price-display text-price-display text-base text-[#dae2fd]">{{ matrix.getColorTotal(c.name) }}</span>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Mobile cards -->
    <div class="md:hidden space-y-4">
      <div
        v-for="c in matrix.colors.value" :key="c.name"
        :class="['p-4 bg-[#162240] border border-[#1e293b] rounded-sm transition-all duration-200', computeColorCardClass(c.name)]"
      >
        <div class="flex items-center gap-3 mb-4">
          <button
            type="button" :disabled="readonly"
            class="relative w-9 h-9 inline-flex items-center justify-center border border-[#1e293b] rounded-full cursor-pointer p-1 shrink-0 transition-all duration-200 hover:enabled:border-[#42b883] hover:enabled:bg-[#42b883] disabled:opacity-70 disabled:cursor-default group"
            @click="toggleColor(c.name)"
            :aria-label="'Alternar color ' + c.name"
          >
            <span class="block w-full h-full rounded-full shadow-[inset_0_0_0_1px_rgba(0,0,0,0.06)]" :style="{ backgroundColor: c.hex }"></span>
            <span :class="['absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border-2 border-[#0a1022]', computeColorDotClass(c.name)]"></span>
          </button>
          <div class="flex-1">
            <div class="font-headline text-headline-sm text-[#dae2fd]">{{ c.name }}</div>
            <div class="flex items-center gap-1.5 mt-0.5">
              <span class="font-price-display text-price-display text-base text-[#dae2fd] leading-none">{{ matrix.getColorTotal(c.name) }}</span>
              <span class="font-label-caps text-label-caps text-[#94a3b8] text-[10px]">UND TOTAL</span>
            </div>
          </div>
          <div class="flex gap-1">
            <button
              type="button" :disabled="readonly"
              class="w-7 h-7 inline-flex items-center justify-center bg-transparent border border-[#1e293b] rounded-sm text-[#94a3b8] cursor-pointer transition-all duration-200 hover:enabled:border-[#42b883] hover:enabled:bg-[#42b883] hover:enabled:text-white disabled:opacity-40 disabled:cursor-not-allowed"
              title="Aplicar N"
              @click="showFillPrompt(c.name)"
            >
              <span class="material-symbols-outlined text-base">deblur</span>
            </button>
            <button
              type="button" :disabled="readonly"
              class="w-7 h-7 inline-flex items-center justify-center bg-transparent border border-[#1e293b] rounded-sm text-[#94a3b8] cursor-pointer transition-all duration-200 hover:enabled:border-[#42b883] hover:enabled:bg-[#42b883] hover:enabled:text-white disabled:opacity-40 disabled:cursor-not-allowed"
              title="Limpiar"
              @click="clearColor(c.name)"
            >
              <span class="material-symbols-outlined text-base">backspace</span>
            </button>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-1.5">
          <div
            v-for="sz in matrix.sizes.value" :key="c.name + '_' + sz"
            :class="['inline-flex items-stretch w-full h-auto min-h-[52px] bg-[#111d2e] flex-row items-center p-1.5 gap-1 border border-[#1e293b] rounded-sm transition-all duration-150 overflow-hidden focus-within:border-[#42b883] focus-within:shadow-[0_0_0_2px_rgba(66,184,131,0.15)]', computeCellClass(matrix.getStock(c.name, sz), matrix.threshold.value)]"
          >
            <button
              type="button" :disabled="readonly"
              class="w-9 shrink-0 inline-flex items-center justify-center bg-transparent border-0 text-[#94a3b8] cursor-pointer transition-all duration-150 hover:enabled:bg-[#42b883] hover:enabled:text-white disabled:opacity-40 disabled:cursor-not-allowed"
              @click="matrix.setStock(c.name, sz, Math.max(0, matrix.getStock(c.name, sz) - 1))"
              aria-label="Disminuir"
            >
              <span class="material-symbols-outlined text-base">remove</span>
            </button>
            <div class="flex flex-col items-center flex-1">
              <span class="font-label-caps text-label-caps text-[#94a3b8] text-[9px]">{{ matrix.sizePrefix.value }} {{ sz }}</span>
              <input
                type="number" min="0" step="1"
                :value="matrix.getStock(c.name, sz)"
                @input="handleInput(c.name, sz, $event)"
                @keydown="handleKeydown($event, c.name, sz)"
                @blur="($event.target as HTMLInputElement).value = String(matrix.getStock(c.name, sz))"
                :readonly="readonly"
                class="vm-step-input flex-1 min-w-[40px] text-center font-price-display text-base font-semibold bg-transparent border-0 text-[#dae2fd] p-0 outline-none focus:bg-[#42b883]/10"
                :data-vm-input="c.name"
                :data-size="sz"
                :aria-label="'Cantidad ' + c.name + ' ' + sz"
              />
            </div>
            <button
              type="button" :disabled="readonly"
              class="w-9 shrink-0 inline-flex items-center justify-center bg-transparent border-0 text-[#94a3b8] cursor-pointer transition-all duration-150 hover:enabled:bg-[#42b883] hover:enabled:text-white disabled:opacity-40 disabled:cursor-not-allowed"
              @click="matrix.setStock(c.name, sz, matrix.getStock(c.name, sz) + 1)"
              aria-label="Aumentar"
            >
              <span class="material-symbols-outlined text-base">add</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="fillPromptVisible"
      class="flex items-center gap-2 mt-3 p-3 bg-[#42b883]/10 border border-[#42b883]/20 rounded-sm animate-[vm-slide-down_200ms_ease-out]"
    >
      <span class="font-label-caps text-label-caps text-[#dae2fd] text-sm">Aplicar a <strong>{{ fillPromptColor }}</strong>:</span>
      <input
        type="number" min="0" step="1"
        v-model.number="fillValue"
        class="w-16 h-8 px-2 text-center font-price-display text-base bg-[#1e293b] border border-[#1e293b] rounded-sm text-[#dae2fd] focus:border-[#42b883] focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
      />
      <span class="font-label-caps text-label-caps text-[#94a3b8] text-sm">und a cada talle</span>
      <button type="button" class="ml-auto font-label-caps text-label-caps text-white bg-[#42b883] border border-[#42b883] px-3 h-8 hover:bg-[#42b883]/90 transition-all" @click="applyFill">APLICAR</button>
      <button type="button" class="font-label-caps text-label-caps text-[#94a3b8] hover:text-[#dae2fd] transition-colors" @click="hideFillPrompt">CANCELAR</button>
    </div>
  </div>
</template>

<style scoped>
@reference "tailwindcss";
.vm-table { border-spacing: 4px; }
.vm-step-input { appearance: textfield; }
.vm-step-input::-webkit-outer-spin-button,
.vm-step-input::-webkit-inner-spin-button { appearance: none; }
.vm-cell-empty { @apply border-dashed bg-transparent; }
.vm-cell-low { @apply border-[#42b883] shadow-[inset_0_-2px_0_#42b883]; }
.vm-cell-ok { @apply border-[#dae2fd] shadow-[inset_0_-2px_0_#dae2fd]; }
.vm-color-empty { @apply opacity-55; }
.vm-color-low { @apply border-[#42b883] shadow-[inset_3px_0_0_#42b883]; }
.vm-color-ok { @apply shadow-[inset_3px_0_0_#dae2fd]; }
.vm-dot-empty { @apply bg-transparent border-[#1e293b]; }
.vm-dot-low { @apply bg-[#42b883]; }
.vm-dot-ok { @apply bg-[#dae2fd]; }
@keyframes vm-slide-down {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
