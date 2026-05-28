/**
 * Comment types — aligned with backend TaskComment model.
 * Comments support nesting via parent_id (1 level deep — reply only).
 */

import type { UserSummary } from './user'

export interface Comment {
  id: number
  task_id: number
  parent_id: number | null
  user_id: number
  body: string
  user: UserSummary
  created_at: string
  /** Populated on the parent comment when /comments index returns nested. */
  replies?: Comment[]
  /** Optional flag set by frontend to track optimistic state. */
  _optimistic?: boolean
}
