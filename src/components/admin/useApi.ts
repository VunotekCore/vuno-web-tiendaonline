import api from '../../lib/api'

export function useApi() {
  async function get<T = unknown>(url: string): Promise<T> {
    const res = await api.get<T>(url)
    return res.data
  }

  async function post<T = unknown>(url: string, body: unknown): Promise<T> {
    const res = await api.post<T>(url, body)
    return res.data
  }

  async function put<T = unknown>(url: string, body: unknown): Promise<T> {
    const res = await api.put<T>(url, body)
    return res.data
  }

  async function del<T = unknown>(url: string, body?: unknown): Promise<T> {
    const res = body
      ? await api.delete<T>(url, { data: body })
      : await api.delete<T>(url)
    return res.data
  }

  return { get, post, put, del }
}
