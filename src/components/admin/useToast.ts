const isBrowser = typeof window !== 'undefined'

export function useToast() {
  return {
    success: (msg: string) => isBrowser ? (window as any).VunoToast?.success(msg) : undefined,
    error: (msg: string) => isBrowser ? (window as any).VunoToast?.error(msg) : undefined,
    warning: (msg: string) => isBrowser ? (window as any).VunoToast?.warning(msg) : undefined,
    info: (msg: string) => isBrowser ? (window as any).VunoToast?.info(msg) : undefined,
  }
}
