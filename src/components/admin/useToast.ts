export function useToast() {
  return {
    success: (msg: string) => (window as any).VunoToast?.success(msg),
    error: (msg: string) => (window as any).VunoToast?.error(msg),
    warning: (msg: string) => (window as any).VunoToast?.warning(msg),
    info: (msg: string) => (window as any).VunoToast?.info(msg),
  }
}
