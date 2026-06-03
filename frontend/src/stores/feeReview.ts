import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'
import api from '@/lib/axios'
import type { TaskFee } from '@/types/fee'

export interface FeeReviewKpi {
  pending_count: number
  pending_amount: number
  receipt_requested_count: number
  reviewed_count: number
  reviewed_amount: number
  disbursed_this_month: number
  disbursed_this_month_amount: number
  rejected_count: number
  total_count: number
}

export type ReviewStatus =
  | 'pending'
  | 'reviewed'
  | 'disbursed'
  | 'rejected'
  | 'receipt_requested'
  | 'all'

export const useFeeReviewStore = defineStore('feeReview', () => {
  const kpi = ref<FeeReviewKpi | null>(null)
  const items = ref<TaskFee[]>([])
  const status = ref<ReviewStatus>('pending')
  const search = ref('')
  const loading = reactive({ list: false })

  async function fetch() {
    loading.list = true
    try {
      const res = await api.get<{ kpi: FeeReviewKpi; items: TaskFee[] }>(
        '/manager/fee-reviews',
        { params: { status: status.value, q: search.value || undefined } },
      )
      kpi.value = res.data.kpi
      items.value = res.data.items
    } finally {
      loading.list = false
    }
  }

  function setStatus(s: ReviewStatus) {
    status.value = s
    return fetch()
  }

  function setSearch(q: string) {
    search.value = q
    return fetch()
  }

  return { kpi, items, status, search, loading, fetch, setStatus, setSearch }
})
