import axios from 'axios'

const isBrowser = typeof window !== 'undefined'

const api = axios.create({
  withCredentials: true,
})

api.interceptors.request.use((config) => {
  if (isBrowser) {
    const token = (window as any).__csrfToken
    if (token && config.method && !['get', 'head', 'options'].includes(config.method)) {
      config.headers.set('X-CSRF-Token', token)
    }
  }
  return config
})

api.interceptors.response.use(
  (res) => res,
  (error) => {
    if (error.response?.status === 401 && isBrowser && !window.location.pathname.startsWith('/admin/login')) {
      window.location.href = '/admin/login'
    }
    const msg = error.response?.data?.error || error.message || 'Request failed'
    return Promise.reject(new Error(msg))
  },
)

export default api
