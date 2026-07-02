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
  },
})
