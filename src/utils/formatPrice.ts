export function formatPrice(value: number): string {
  if (value == null || isNaN(value)) value = 0
  return new Intl.NumberFormat('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value)
}
