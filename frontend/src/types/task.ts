/**
 * Task types — aligned with backend Task model.
 * NOTE: dates are 'date:Y-m-d' formatted strings (Y-m-d), not ISO 8601.
 */

import type { UserSummary } from './user'
import type { ProjectStatus, ProjectPriority } from './project'

export interface Task {
  id: number
  project_id: number
  name: string
  note?: string | null
  start_date: string | null
  end_date: string | null
  progress: number
  is_completed: boolean
  completed_at: string | null
  assignee_id: number | null
  status_id: number
  priority_id: number
  assignee?: UserSummary | null
  status?: ProjectStatus | null
  priority?: ProjectPriority | null
  is_overdue?: boolean
  // counts for TaskMetaBadges (computed, may be eager-loaded)
  comments_count?: number
  attachments_count?: number
}

/** Lightweight task shape used in dashboards / lists. */
export interface TodoTask {
  id: number
  name: string
  project_id: number
  project_name: string
  end_date: string | null
  progress: number
  is_overdue: boolean
  status: { name: string; color: string; icon?: string } | null
  priority: { name: string; color: string } | null
}
