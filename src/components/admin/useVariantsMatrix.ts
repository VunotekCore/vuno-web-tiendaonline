import { ref } from 'vue'

export interface MatrixColor {
  name: string
  hex: string
}

export function useVariantsMatrix() {
  const colors = ref<MatrixColor[]>([]);
  const sizes = ref<string[]>([]);
  const threshold = ref(5);
  const sizePrefix = ref('EU');
  const stock = ref<Record<string, number>>({});

  function key(color: string, size: string) {
    return color + '_' + size;
  }

  function clamp(n: number): number {
    return Math.max(0, Math.min(9999, Math.round(parseInt(String(n), 10) || 0)));
  }

  function init(
    initColors: MatrixColor[],
    initSizes: string[],
    initialStock?: Record<string, number> | null,
    initThreshold?: number,
    initPrefix?: string,
  ) {
    colors.value = initColors;
    sizes.value = [...initSizes].sort((a, b) => parseInt(a, 10) - parseInt(b, 10));
    threshold.value = initThreshold ?? 5;
    sizePrefix.value = initPrefix || 'EU';
    const newStock: Record<string, number> = {};
    for (const c of initColors) {
      for (const s of initSizes) {
        newStock[key(c.name, s)] = 0;
      }
    }
    if (initialStock) {
      for (const [k, v] of Object.entries(initialStock)) {
        if (k in newStock) newStock[k] = clamp(v);
      }
    }
    stock.value = newStock;
  }

  function setStock(color: string, size: string, value: number) {
    stock.value[key(color, size)] = clamp(value);
  }

  function getStock(color: string, size: string): number {
    return stock.value[key(color, size)] || 0;
  }

  function getColorTotal(color: string): number {
    let total = 0;
    for (const s of sizes.value) total += getStock(color, s);
    return total;
  }

  function getSizeTotal(size: string): number {
    let total = 0;
    for (const c of colors.value) total += getStock(c.name, size);
    return total;
  }

  function getGrandTotal(): number {
    let total = 0;
    for (const c of colors.value) {
      for (const s of sizes.value) total += getStock(c.name, s);
    }
    return total;
  }

  function getActiveCount(): number {
    let count = 0;
    for (const c of colors.value) {
      for (const s of sizes.value) {
        if (getStock(c.name, s) > 0) count++;
      }
    }
    return count;
  }

  function getColorState(color: string): 'empty' | 'low' | 'ok' {
    const total = getColorTotal(color);
    if (total === 0) return 'empty';
    if (total <= threshold.value * Math.max(1, sizes.value.length) / 2) return 'low';
    return 'ok';
  }

  function classify(stockVal: number, t: number): 'empty' | 'low' | 'ok' {
    if (stockVal <= 0) return 'empty';
    if (stockVal <= t) return 'low';
    return 'ok';
  }

  function addColor(name: string, hex: string) {
    if (colors.value.some(c => c.name === name)) return;
    colors.value.push({ name, hex });
    for (const s of sizes.value) {
      stock.value[key(name, s)] = 0;
    }
  }

  function removeColor(name: string) {
    const idx = colors.value.findIndex(c => c.name === name);
    if (idx === -1) return;
    colors.value.splice(idx, 1);
    for (const s of sizes.value) {
      delete stock.value[key(name, s)];
    }
  }

  function addSize(value: string) {
    if (sizes.value.includes(value)) return;
    sizes.value.push(value);
    sizes.value.sort((a, b) => parseInt(a, 10) - parseInt(b, 10));
    for (const c of colors.value) {
      stock.value[key(c.name, value)] = 0;
    }
  }

  function removeSize(value: string) {
    const idx = sizes.value.indexOf(value);
    if (idx === -1) return;
    sizes.value.splice(idx, 1);
    for (const c of colors.value) {
      delete stock.value[key(c.name, value)];
    }
  }

  function toPayload(): { stocks: Record<string, number>; threshold: number } {
    const result: Record<string, number> = {};
    for (const c of colors.value) {
      for (const s of sizes.value) {
        result[key(c.name, s)] = getStock(c.name, s);
      }
    }
    return { stocks: result, threshold: threshold.value };
  }

  function reset() {
    for (const c of colors.value) {
      for (const s of sizes.value) {
        stock.value[key(c.name, s)] = 0;
      }
    }
  }

  return {
    colors, sizes, threshold, sizePrefix, stock,
    init, setStock, getStock,
    getColorTotal, getSizeTotal, getGrandTotal, getActiveCount,
    getColorState, classify,
    addColor, removeColor, addSize, removeSize,
    toPayload, reset,
  }
}
