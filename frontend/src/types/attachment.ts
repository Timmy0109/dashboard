/**
 * Attachment types — aligned with backend TaskAttachment model.
 * Download URLs are signed and short-lived (5 minutes).
 */

import type { UserSummary } from './user'

export interface Attachment {
  id: number
  task_id?: number
  original_name: string
  mime_type: string
  size: number
  size_human: string
  is_previewable: boolean
  download_url: string
  uploader_id: number
  uploader: UserSummary | null
  created_at: string
}

export interface ProjectAttachment extends Attachment {
  task_id: number
  task_name: string
}

/** Cursor pagination shape returned by /api/projects/{id}/attachments. */
export interface PaginatedAttachments {
  data: ProjectAttachment[]
  next_cursor: string | null
}
