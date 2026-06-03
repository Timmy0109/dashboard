/**
 * Fee types — aligned with backend TaskFee / ProjectAdminFee models.
 */

/**
 * 3-stage fee workflow:
 *   pending   — 待會計審核
 *   reviewed  — 會計已審，待老闆核發
 *   disbursed — 老闆已核發（最終）
 *   rejected  — 任一階段退件
 */
export type FeeStatus = 'pending' | 'reviewed' | 'disbursed' | 'rejected'

interface UserRef { id: number; name: string }

export interface TaskFeeAttachment {
  id: number
  task_fee_id: number | null
  original_name: string
  mime_type: string
  size_human: string
  is_previewable: boolean
  download_url: string
  uploader: UserRef | null
  created_at: string
}

export interface TaskFee {
  id: number
  task_id: number
  project_id: number
  submitted_by: number
  amount: string  // decimal cast — Laravel returns string
  note: string | null
  status: FeeStatus

  reviewed_by: number | null
  reviewed_at: string | null
  reject_reason: string | null

  disbursed_by: number | null
  disbursed_at: string | null

  unapproved_by: number | null
  unapproved_at: string | null
  unapprove_reason: string | null

  submitter?: UserRef
  reviewer?: UserRef | null
  disburser?: UserRef | null
  unapprover?: UserRef | null
  attachments?: TaskFeeAttachment[]
  task?: { id: number; name: string; project_id: number; project?: { id: number; name: string } }
  receipt_requested_at: string | null
  receipt_requested_by: number | null
  receipt_request_message: string | null
  receipt_requester?: UserRef | null

  created_at: string
  updated_at: string
}

export interface ProjectAdminFeeAttachment {
  id: number
  project_admin_fee_id: number
  original_name: string
  mime_type: string
  size_human: string
  is_previewable: boolean
  download_url: string
  uploader: UserRef | null
  created_at: string
}

export interface ProjectAdminFee {
  id: number
  project_id: number
  created_by: number
  amount: string
  note: string | null
  incurred_on: string | null

  creator?: UserRef
  attachments?: ProjectAdminFeeAttachment[]

  created_at: string
  updated_at: string
}

/**
 * Member only sees own contributions (scope: 'self');
 * manager/admin see project-wide totals + budget (scope: 'all').
 */
export type FeeSummary =
  | {
      scope: 'self'
      own_approved: number
      own_pending: number
      own_rejected: number
      own_total: number
    }
  | {
      scope: 'all'
      total: number
      task_fees_approved: number
      task_fees_pending: number
      admin_fees: number
      total_budget: number
      remaining: number
      over_budget: boolean
    }

export interface ProjectBudgetLog {
  id: number
  project_id: number
  actor_id: number
  from_amount: string
  to_amount: string
  actor?: UserRef
  created_at: string
}
