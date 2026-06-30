import { useAuthStore } from '../../stores/auth'

export function useApi() {
  const auth = useAuthStore()

  async function get<T = unknown>(url: string): Promise<T> {
    return auth.apiFetch<T>(url)
  }

  async function post<T = unknown>(url: string, body: unknown): Promise<T> {
    const isFormData = body instanceof FormData
    return auth.apiFetch<T>(url, {
      method: 'POST',
      ...(isFormData ? { body } : { body: JSON.stringify(body) }),
    })
  }

  async function put<T = unknown>(url: string, body: unknown): Promise<T> {
    const isFormData = body instanceof FormData
    return auth.apiFetch<T>(url, {
      method: 'PUT',
      ...(isFormData ? { body } : { body: JSON.stringify(body) }),
    })
  }

  async function del<T = unknown>(url: string, body?: unknown): Promise<T> {
    const isFormData = body instanceof FormData
    return auth.apiFetch<T>(url, {
      method: 'DELETE',
      ...(body ? (isFormData ? { body } : { body: JSON.stringify(body) }) : {}),
    })
  }

  return { get, post, put, del }
}
