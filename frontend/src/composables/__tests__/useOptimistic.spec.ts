import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useOptimistic } from '@/composables/useOptimistic'
import { useToastStore } from '@/stores/toast'

describe('useOptimistic', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('keeps optimistic state when request succeeds', async () => {
    let state = 0
    const opt = useOptimistic<number>({
      apply: () => { state = 1; return 1 },
      rollback: () => { state = 0 },
      request: async () => 1,
    })
    const result = await opt.mutate()
    expect(result).toBe(1)
    expect(state).toBe(1)
    expect(opt.pending.value).toBe(false)
    expect(opt.error.value).toBeNull()
  })

  it('calls rollback and pushes error toast when request rejects', async () => {
    let state = 0
    const rollback = vi.fn(() => { state = 0 })
    const toast = useToastStore()
    const errSpy = vi.spyOn(toast, 'error')

    const opt = useOptimistic<number>({
      apply: () => { state = 1; return 1 },
      rollback,
      request: async () => { throw new Error('boom') },
    })

    await expect(opt.mutate()).rejects.toThrow('boom')
    expect(rollback).toHaveBeenCalledOnce()
    expect(state).toBe(0)
    expect(opt.error.value).toBeInstanceOf(Error)
    expect(errSpy).toHaveBeenCalled()
  })

  it('flips pending true during request, false after', async () => {
    let resolveFn: (v: number) => void = () => {}
    const promise = new Promise<number>(r => { resolveFn = r })
    const opt = useOptimistic<number>({
      apply: () => 1,
      rollback: () => {},
      request: () => promise,
    })
    const p = opt.mutate()
    // microtask tick so the async function enters its try
    await Promise.resolve()
    expect(opt.pending.value).toBe(true)
    resolveFn(1)
    await p
    expect(opt.pending.value).toBe(false)
  })
})
