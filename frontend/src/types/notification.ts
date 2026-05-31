/**
 * Notification types — aligned with backend Notification model.
 * 4 type variants (per CEO plan); payload shape differs per type.
 */

export type NotificationType =
  | 'task_assigned'
  | 'task_mentioned'
  | 'task_status_changed'
  | 'task_replied'
  | 'fee_submitted'
  | 'fee_approved'
  | 'fee_rejected'
  | 'fee_unapproved'
  | 'fee_receipt_requested'

interface NotificationPayloadByType {
  task_assigned: {
    task_id: number
    task_name: string
    project_id: number
    project_name: string
    actor_name: string
  }
  task_mentioned: {
    comment_id: number
    task_id: number
    task_name: string
    project_id: number
    actor_name: string
    snippet: string
  }
  task_status_changed: {
    task_id: number
    task_name: string
    project_id: number
    from: string
    to: string
    actor_name: string
  }
  task_replied: {
    comment_id: number
    parent_comment_id: number
    task_id: number
    project_id: number
    actor_name: string
    snippet: string
  }
  fee_submitted: {
    task_fee_id: number
    task_id: number
    project_id: number
    task_name: string
    amount: number
    submitter: string
    is_resubmit?: boolean
  }
  fee_approved: {
    task_fee_id: number
    task_id: number
    project_id: number
    amount: number
    reviewer: string
  }
  fee_rejected: {
    task_fee_id: number
    task_id: number
    project_id: number
    amount: number
    reviewer: string
    reject_reason: string
  }
  fee_unapproved: {
    task_fee_id: number
    task_id: number
    project_id: number
    amount: number
    reviewer: string
    unapprove_reason: string
  }
  fee_receipt_requested: {
    task_fee_id: number
    task_id: number
    project_id: number
    amount: number
    reviewer: string
    message: string | null
  }
}

export interface Notification<T extends NotificationType = NotificationType> {
  id: number
  user_id: number
  type: T
  payload: NotificationPayloadByType[T]
  read_at: string | null
  created_at: string
}

export interface PaginatedNotifications {
  data: Notification[]
  next_cursor: string | null
  unread_count: number
}

/** Activity feed item (read-only, from /api/activity). */
export interface ActivityFeedItem {
  id: number
  event: string
  task_id: number
  task_name: string
  project_id: number
  project_name: string
  actor: { id: number; name: string; avatar_url: string | null } | null
  payload: Record<string, unknown> | null
  created_at: string
}

export interface PaginatedActivity {
  data: ActivityFeedItem[]
  next_cursor: string | null
}
