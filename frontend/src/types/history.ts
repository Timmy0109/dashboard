/**
 * Task history types — aligned with backend TaskActivity model.
 * Read-only on the frontend; activities are appended by observers.
 */

import type { UserSummary } from './user'

export type HistoryEventType =
  | 'created'
  | 'assignee_changed'
  | 'status_changed'
  | 'progress_updated'
  | 'completed'
  | 'reopened'
  | 'commented'
  | 'attached'
  | 'detached'

export interface HistoryEvent {
  id: number
  task_id: number
  event: HistoryEventType
  label: string
  description: string | null
  payload: Record<string, unknown> | null
  actor: UserSummary | null
  created_at: string
}
