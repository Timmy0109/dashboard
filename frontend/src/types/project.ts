/**
 * Project types — aligned with backend Project model + ProjectController responses.
 * Existing Pinia stores (useProjectStore) use compatible shapes.
 */

import type { UserSummary } from './user'

export interface ProjectStatus {
  id: number
  name: string
  color: string
  icon?: string | null
}

export interface ProjectPriority {
  id: number
  name: string
  color: string
}

export interface ProjectCategory {
  id: number
  name: string
  color: string
}

export interface ProjectListItem {
  id: number
  name: string
  code?: string | null
  progress_percent: number
  is_completed: boolean
  start_date: string | null
  end_date: string | null
  owner?: UserSummary | null
  status: ProjectStatus | null
  priority: ProjectPriority | null
  category?: ProjectCategory | null
  member_count?: number
}

export interface ProjectDetail extends ProjectListItem {
  note?: string | null
  members?: UserSummary[]
  task_count?: number
  attachment_count?: number
  // realtime flags surfaced by ProjectDetailView header
  is_overdue?: boolean
}
