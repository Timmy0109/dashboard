import { ref, type Ref } from 'vue'
import { useToastStore } from '@/stores/toast'

export interface UseOptimisticOptions<T> {
  /** Run synchronously to compute / apply optimistic state. */
  apply: () => T
  /** Called on error to revert state. */
  rollback: () => void
  /** The actual API call. */
  request: () => Promise<T>
  /** Optional error toast message override. */
  errorMessage?: string
}

export interface UseOptimisticReturn<T> {
  mutate: () => Promise<T>
  pending: Ref<boolean>
  error: Ref<unknown>
}

/**
 * Generic optimistic mutation helper.
 *
 * - applies optimistic state synchronously
 * - awaits the request; on reject calls rollback() + pushes an error toast
 * - exposes pending + error refs
 */
export function useOptimistic<T>(opts: UseOptimisticOptions<T>): UseOptimisticReturn<T> {
  const pending = ref(false)
  const error = ref<unknown>(null)

  async function mutate(): Promise<T> {
    error.value = null
    // apply optimistic state synchronously
    opts.apply()
    pending.value = true
    try {
      const result = await opts.request()
      return result
    } catch (e) {
      // rollback on failure
      try {
        opts.rollback()
      } catch (rollbackError) {
        console.warn('[useOptimistic] rollback threw', rollbackError)
      }
      error.value = e
      const toast = useToastStore()
      const msg =
        opts.errorMessage ??
        (e instanceof Error ? e.message : '操作失敗，已還原變更')
      toast.error(msg)
      throw e
    } finally {
      pending.value = false
    }
  }

  return { mutate, pending, error }
}

export default useOptimistic
