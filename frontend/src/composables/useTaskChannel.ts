import { watch, onBeforeUnmount, type Ref } from 'vue'
import { getEcho } from '@/lib/echo'
import type { Comment } from '@/types/comment'
import type { Attachment } from '@/types/attachment'
import type { HistoryEvent } from '@/types/history'

export interface TaskChannelHandlers {
  onCommentCreated?: (comment: Comment) => void
  onCommentReplied?: (reply: Comment) => void
  onAttachmentUploaded?: (attachment: Attachment) => void
  onHistoryEventRecorded?: (event: HistoryEvent) => void
  /** Fired after Echo reconnects so the caller can refetch active task data. */
  onReconnect?: () => void
}

/**
 * Subscribe to the `task.{taskId}` private channel for the currently-open
 * TaskModal. Re-subscribes when `taskIdRef` changes; cleans up on unmount.
 *
 * Hooks `connected` on the underlying connector so reconnect after a drop
 * can trigger a refetch via `handlers.onReconnect`.
 */
export function useTaskChannel(
  taskIdRef: Ref<number | null>,
  handlers: TaskChannelHandlers,
): void {
  let currentTaskId: number | null = null
  let reconnectBound = false
  let lastConnectedAt = 0

  function leave() {
    if (currentTaskId == null) return
    const echo = getEcho()
    if (echo) {
      try {
        echo.leave(`task.${currentTaskId}`)
      } catch (e) {
        console.warn('[useTaskChannel] leave failed', e)
      }
    }
    currentTaskId = null
  }

  function join(taskId: number) {
    const echo = getEcho()
    if (!echo) return

    const ch = echo.private(`task.${taskId}`)
    ch.listen('.CommentCreated', (payload: Comment) => {
      handlers.onCommentCreated?.(payload)
    })
    ch.listen('.CommentReplied', (payload: Comment) => {
      handlers.onCommentReplied?.(payload)
    })
    ch.listen('.AttachmentUploaded', (payload: Attachment) => {
      handlers.onAttachmentUploaded?.(payload)
    })
    ch.listen('.HistoryEventRecorded', (payload: HistoryEvent) => {
      handlers.onHistoryEventRecorded?.(payload)
    })

    currentTaskId = taskId

    if (!reconnectBound) {
      // Bind once per composable instance.
      try {
        const connector = (echo as unknown as { connector?: { pusher?: { connection?: { bind: (e: string, cb: () => void) => void } } } }).connector
        const conn = connector?.pusher?.connection
        if (conn?.bind) {
          conn.bind('connected', () => {
            const now = Date.now()
            // Only treat as reconnect if we've been connected before within this session.
            if (lastConnectedAt > 0) {
              handlers.onReconnect?.()
            }
            lastConnectedAt = now
          })
          reconnectBound = true
        }
      } catch (e) {
        console.warn('[useTaskChannel] could not bind reconnect', e)
      }
    }
  }

  watch(
    taskIdRef,
    (newId) => {
      leave()
      if (newId != null) join(newId)
    },
    { immediate: true },
  )

  onBeforeUnmount(() => {
    leave()
  })
}

export default useTaskChannel
