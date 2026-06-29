import { defineStore } from 'pinia'

const isBrowser = typeof window !== 'undefined'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    csrfToken: isBrowser ? (window as any).__csrfToken || '' : '',
    user: null as { id: number; name: string; role: string } | null,
  }),
  actions: {
    setCsrfToken(token: string) {
      this.csrfToken = token
    },
    setUser(user: { id: number; name: string; role: string }) {
      this.user = user
    },
    async apiFetch<T = unknown>(url: string, opts: RequestInit = {}): Promise<T> {
      const headers = new Headers(opts.headers || {})
      if (!headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json')
      }
      if (this.csrfToken && opts.method && opts.method !== 'GET' && opts.method !== 'HEAD') {
        headers.set('X-CSRF-Token', this.csrfToken)
      }
      const res = await fetch(url, {
        ...opts,
        headers,
        credentials: 'include',
      })
      if (!res.ok) {
        const err = await res.json().catch(() => ({ error: res.statusText }))
        throw new Error(err.error || `HTTP ${res.status}`)
      }
      return res.json()
    },
  },
})
