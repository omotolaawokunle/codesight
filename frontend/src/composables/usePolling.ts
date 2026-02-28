import { onUnmounted, ref } from 'vue'

/**
 * usePolling runs a given async function on a fixed interval.
 *
 * By default the interval is 5 seconds, matching the guide specification.
 * The interval is automatically cleared when the component is unmounted so
 * there is no risk of memory leaks or stale requests after navigation.
 *
 * @example
 * const { start, stop } = usePolling(() => store.fetchStatus(repoId))
 * onMounted(start)
 * // stop() is called automatically on unmount; call it manually to stop early.
 */
export function usePolling(fn: () => Promise<unknown>, intervalMs = 5000) {
  const timerId = ref<ReturnType<typeof setInterval> | null>(null)
  const isPolling = ref(false)

  function start() {
    if (isPolling.value) return   // Prevent double-start
    isPolling.value = true
    timerId.value = setInterval(async () => {
      try {
        await fn()
      } catch {
        // Errors in the polled function should not stop the interval;
        // the store or caller should handle them.
      }
    }, intervalMs)
  }

  function stop() {
    if (timerId.value !== null) {
      clearInterval(timerId.value)
      timerId.value = null
    }
    isPolling.value = false
  }

  // Automatically clean up when the parent component is destroyed.
  onUnmounted(stop)

  return { start, stop, isPolling }
}
