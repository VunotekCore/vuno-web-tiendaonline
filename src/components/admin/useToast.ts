const isBrowser = typeof window !== 'undefined'

export function useToast() {
  return {
    success: (title: string, message?: string) => isBrowser ? (window as any).VunoToast?.success(title, message) : undefined,
    error: (title: string, message?: string) => isBrowser ? (window as any).VunoToast?.error(title, message) : undefined,
    warning: (title: string, message?: string) => isBrowser ? (window as any).VunoToast?.warning(title, message) : undefined,
    info: (title: string, message?: string) => isBrowser ? (window as any).VunoToast?.info(title, message) : undefined,
  }
}
