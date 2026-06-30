import { ref } from 'vue'
import { useApi } from './useApi'

export function useCrud<T extends { id: string | number }>(endpoint: string) {
  const items = ref<T[]>([])
  const loading = ref(false)
  const api = useApi()

  async function load(params: Record<string, string | number> = {}) {
    loading.value = true
    try {
      const qs = new URLSearchParams()
      Object.entries(params).forEach(([k, v]) => qs.set(k, String(v)))
      const data = await api.get<{ items?: T[] } | T[]>(`/api/${endpoint}/list.php?${qs}`)
      items.value = Array.isArray(data) ? data : (data as { items: T[] }).items || []
    } finally {
      loading.value = false
    }
  }

  async function create(body: Partial<T>): Promise<T | null> {
    const result = await api.post<T>(`/api/${endpoint}/create.php`, body)
    await load()
    return result
  }

  async function update(id: string | number, body: Partial<T>): Promise<T | null> {
    const result = await api.post<T>(`/api/${endpoint}/update.php`, { id, ...body })
    await load()
    return result
  }

  async function remove(id: string | number): Promise<boolean> {
    const result = await api.post<{ success: boolean }>(`/api/${endpoint}/delete.php`, { id })
    if (result.success) await load()
    return result.success
  }

  return { items, loading, load, create, update, remove }
}
