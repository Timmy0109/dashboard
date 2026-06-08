import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'
import api from '@/lib/axios'
import type {
  TaskFee,
  ProjectAdminFee,
  FeeSummary,
} from '@/types/fee'

export const useFeeStore = defineStore('fee', () => {
  // task fees keyed by task_id
  const byTask = ref<Record<number, TaskFee[]>>({})
  // task fees aggregated by project (for ProjectFeesPanel detail list)
  const taskFeesByProject = ref<Record<number, TaskFee[]>>({})
  // admin fees keyed by project_id
  const adminByProject = ref<Record<number, ProjectAdminFee[]>>({})
  // fee summary keyed by project_id
  const summaryByProject = ref<Record<number, FeeSummary>>({})

  const loading = reactive({ task: false, admin: false, summary: false, projectTask: false })

  // ── Project-level task fees (panel detail list) ───────────────
  async function fetchProjectTaskFees(projectId: number) {
    loading.projectTask = true
    try {
      const res = await api.get<TaskFee[]>(`/projects/${projectId}/task-fees`)
      taskFeesByProject.value[projectId] = res.data
    } finally {
      loading.projectTask = false
    }
  }

  // ── Task fees ─────────────────────────────────────────────────
  async function fetchTaskFees(projectId: number, taskId: number) {
    loading.task = true
    try {
      const res = await api.get<TaskFee[]>(
        `/projects/${projectId}/tasks/${taskId}/fees`,
      )
      byTask.value[taskId] = res.data
    } finally {
      loading.task = false
    }
  }

  async function createTaskFee(
    projectId: number,
    taskId: number,
    payload: { amount: number; note?: string },
  ): Promise<TaskFee> {
    const res = await api.post<TaskFee>(
      `/projects/${projectId}/tasks/${taskId}/fees`,
      payload,
    )
    if (!byTask.value[taskId]) byTask.value[taskId] = []
    byTask.value[taskId].unshift(res.data)
    invalidateSummary(projectId)
    return res.data
  }

  async function updateTaskFee(
    fee: TaskFee,
    payload: { amount?: number; note?: string | null },
  ): Promise<TaskFee> {
    const res = await api.patch<TaskFee>(`/task-fees/${fee.id}`, payload)
    replaceLocal(fee.task_id, res.data)
    invalidateSummary(fee.project_id)
    return res.data
  }

  async function deleteTaskFee(fee: TaskFee): Promise<void> {
    await api.delete(`/task-fees/${fee.id}`)
    const list = byTask.value[fee.task_id]
    if (list) {
      byTask.value[fee.task_id] = list.filter(f => f.id !== fee.id)
    }
    invalidateSummary(fee.project_id)
  }

  /** 一階審核：pending → reviewed（accountant / boss / admin） */
  async function reviewTaskFee(fee: TaskFee): Promise<TaskFee> {
    const res = await api.post<TaskFee>(`/task-fees/${fee.id}/review`)
    replaceLocal(fee.task_id, res.data)
    invalidateSummary(fee.project_id)
    return res.data
  }

  /** 二階核發：reviewed → disbursed（boss / admin） */
  async function disburseTaskFee(fee: TaskFee): Promise<TaskFee> {
    const res = await api.post<TaskFee>(`/task-fees/${fee.id}/disburse`)
    replaceLocal(fee.task_id, res.data)
    invalidateSummary(fee.project_id)
    return res.data
  }

  async function rejectTaskFee(fee: TaskFee, reason: string): Promise<TaskFee> {
    const res = await api.post<TaskFee>(`/task-fees/${fee.id}/reject`, {
      reject_reason: reason,
    })
    replaceLocal(fee.task_id, res.data)
    invalidateSummary(fee.project_id)
    return res.data
  }

  /** reviewed → pending（會計 / 老闆 / admin） */
  async function unreviewTaskFee(fee: TaskFee, reason: string): Promise<TaskFee> {
    const res = await api.post<TaskFee>(`/task-fees/${fee.id}/unreview`, {
      unapprove_reason: reason,
    })
    replaceLocal(fee.task_id, res.data)
    invalidateSummary(fee.project_id)
    return res.data
  }

  /** disbursed → reviewed（boss / admin，撤回核發） */
  async function undisburseTaskFee(fee: TaskFee, reason: string): Promise<TaskFee> {
    const res = await api.post<TaskFee>(`/task-fees/${fee.id}/undisburse`, {
      unapprove_reason: reason,
    })
    replaceLocal(fee.task_id, res.data)
    invalidateSummary(fee.project_id)
    return res.data
  }

  async function resubmitTaskFee(fee: TaskFee): Promise<TaskFee> {
    const res = await api.post<TaskFee>(`/task-fees/${fee.id}/resubmit`)
    replaceLocal(fee.task_id, res.data)
    invalidateSummary(fee.project_id)
    return res.data
  }

  async function requestReceipt(fee: TaskFee, message?: string): Promise<void> {
    await api.post(`/task-fees/${fee.id}/request-receipt`, message ? { message } : {})
  }

  // ── Project admin fees ───────────────────────────────────────
  async function fetchAdminFees(projectId: number) {
    loading.admin = true
    try {
      const res = await api.get<ProjectAdminFee[]>(
        `/projects/${projectId}/admin-fees`,
      )
      adminByProject.value[projectId] = res.data
    } finally {
      loading.admin = false
    }
  }

  async function createAdminFee(
    projectId: number,
    payload: { amount: number; note?: string; incurred_on?: string },
  ): Promise<ProjectAdminFee> {
    const res = await api.post<ProjectAdminFee>(
      `/projects/${projectId}/admin-fees`,
      payload,
    )
    if (!adminByProject.value[projectId]) adminByProject.value[projectId] = []
    adminByProject.value[projectId].unshift(res.data)
    invalidateSummary(projectId)
    return res.data
  }

  async function updateAdminFee(
    fee: ProjectAdminFee,
    payload: { amount?: number; note?: string | null; incurred_on?: string | null },
  ): Promise<ProjectAdminFee> {
    const res = await api.patch<ProjectAdminFee>(`/project-admin-fees/${fee.id}`, payload)
    const list = adminByProject.value[fee.project_id]
    if (list) {
      const i = list.findIndex(f => f.id === fee.id)
      if (i >= 0) list[i] = res.data
    }
    invalidateSummary(fee.project_id)
    return res.data
  }

  async function deleteAdminFee(fee: ProjectAdminFee): Promise<void> {
    await api.delete(`/project-admin-fees/${fee.id}`)
    const list = adminByProject.value[fee.project_id]
    if (list) {
      adminByProject.value[fee.project_id] = list.filter(f => f.id !== fee.id)
    }
    invalidateSummary(fee.project_id)
  }

  /** 會計（無會計時老闆兼審）核准 / 退件 PM 建立的行政費 */
  async function reviewAdminFee(
    fee: ProjectAdminFee,
    decision: 'approve' | 'reject',
    reviewNote?: string,
  ): Promise<ProjectAdminFee> {
    const res = await api.post<ProjectAdminFee>(`/project-admin-fees/${fee.id}/review`, {
      decision,
      ...(reviewNote ? { review_note: reviewNote } : {}),
    })
    const list = adminByProject.value[fee.project_id]
    if (list) {
      const i = list.findIndex(f => f.id === fee.id)
      if (i >= 0) list[i] = res.data
    }
    invalidateSummary(fee.project_id)
    return res.data
  }

  // ── Summary ─────────────────────────────────────────────────
  async function fetchSummary(projectId: number): Promise<FeeSummary> {
    loading.summary = true
    try {
      const res = await api.get<FeeSummary>(`/projects/${projectId}/fee-summary`)
      summaryByProject.value[projectId] = res.data
      return res.data
    } finally {
      loading.summary = false
    }
  }

  function invalidateSummary(projectId: number) {
    // Lazy-refresh: drop cache; consumers re-fetch on next read
    delete summaryByProject.value[projectId]
    delete taskFeesByProject.value[projectId]
  }

  // ── Internals ───────────────────────────────────────────────
  function replaceLocal(taskId: number, fresh: TaskFee) {
    const list = byTask.value[taskId]
    if (!list) return
    const i = list.findIndex(f => f.id === fresh.id)
    if (i >= 0) list[i] = fresh
  }

  return {
    byTask,
    taskFeesByProject,
    adminByProject,
    summaryByProject,
    loading,

    fetchProjectTaskFees,
    fetchTaskFees,
    createTaskFee,
    updateTaskFee,
    deleteTaskFee,
    reviewTaskFee,
    disburseTaskFee,
    rejectTaskFee,
    unreviewTaskFee,
    undisburseTaskFee,
    resubmitTaskFee,
    requestReceipt,

    fetchAdminFees,
    createAdminFee,
    updateAdminFee,
    deleteAdminFee,
    reviewAdminFee,

    fetchSummary,
    invalidateSummary,
  }
})
