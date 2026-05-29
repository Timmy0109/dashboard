<template>
  <v-dialog
    :model-value="true"
    :max-width="task ? 880 : 560"
    scrollable
    persistent
    @update:model-value="$emit('close')"
  >
    <v-card rounded="xl" class="pms-task-modal">
      <!-- Header -->
      <v-card-title class="pa-5 pb-3 d-flex align-center justify-space-between bg-primary rounded-t-xl">
        <span class="text-body-1 font-weight-semibold text-white">
          {{ task ? '編輯任務' : '新增任務' }}
        </span>
        <v-btn icon="mdi-close" variant="text" size="small" color="white" @click="$emit('close')" />
      </v-card-title>

      <!-- Tabs (edit mode only) -->
      <Tabs
        v-if="task"
        v-model="activeTab"
        :items="tabItems"
        class="px-5 pt-2"
      />

      <v-card-text class="pa-5 pt-4">
        <!-- 詳情 -->
        <div v-if="!task || activeTab === 'detail'">
          <v-form @submit.prevent="handleSubmit">
            <v-text-field
              v-model="form.name"
              label="任務名稱"
              required
              autofocus
              class="mb-3"
            />

            <v-row dense class="mb-1">
              <v-col cols="6">
                <v-text-field
                  v-model="form.start_date"
                  label="開始日期"
                  type="date"
                  required
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model="form.end_date"
                  label="結束日期"
                  type="date"
                  required
                />
              </v-col>
            </v-row>

            <v-row dense class="mb-1">
              <v-col cols="6">
                <v-select
                  v-model="form.status_id"
                  label="狀態"
                  :items="lookup.statuses.map(s => ({ title: s.name, value: s.id }))"
                  required
                />
              </v-col>
              <v-col cols="6">
                <v-select
                  v-model="form.priority_id"
                  label="優先級"
                  :items="lookup.priorities.map(p => ({ title: p.name, value: p.id }))"
                  required
                />
              </v-col>
            </v-row>

            <v-select
              v-model="form.assignee_id"
              label="負責人"
              :items="[{ title: '未指派', value: null }, ...lookup.users.map(u => ({ title: u.name, value: u.id }))]"
              clearable
              class="mb-3"
            />

            <div class="mb-4">
              <div class="d-flex justify-space-between mb-1">
                <span class="text-caption text-grey">進度</span>
                <span class="text-caption font-weight-bold text-primary">{{ form.progress }}%</span>
              </div>
              <v-slider
                v-model="form.progress"
                min="0"
                max="100"
                step="5"
                color="primary"
                track-color="grey-lighten-2"
                thumb-label
                hide-details
              />
            </div>

            <v-textarea v-model="form.note" label="備註" rows="2" auto-grow class="mb-3" />

            <v-alert
              v-if="errorMsg"
              type="error"
              variant="tonal"
              density="compact"
              class="mb-3 text-body-2"
            >
              {{ errorMsg }}
            </v-alert>

            <div class="d-flex">
              <v-btn variant="outlined" color="grey" class="grow mr-3" @click="$emit('close')">
                取消
              </v-btn>
              <v-btn type="submit" color="primary" class="grow" :loading="saving">
                儲存
              </v-btn>
            </div>
          </v-form>
        </div>

        <!-- 留言 -->
        <div v-else-if="activeTab === 'comments'" class="pms-tab-pane">
          <!-- Composer -->
          <div class="pms-composer mb-4">
            <div class="d-flex align-start">
              <v-avatar size="32" color="primary" class="mr-2 mt-1">
                <v-img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" />
                <span v-else class="text-caption text-white font-weight-bold">
                  {{ (auth.user?.name ?? '?').charAt(0) }}
                </span>
              </v-avatar>
              <div class="grow position-relative">
                <v-textarea
                  ref="composerRef"
                  v-model="commentDraft"
                  placeholder="輸入留言... 使用 @ 提及成員"
                  rows="2"
                  auto-grow
                  max-rows="6"
                  hide-details
                  variant="outlined"
                  density="compact"
                  @keydown="onComposerKeydown"
                  @input="onComposerInput"
                />
                <!-- @mention dropdown -->
                <v-menu
                  v-model="mentionOpen"
                  :close-on-content-click="false"
                  :open-on-click="false"
                  location="bottom start"
                  :target="composerRef?.$el ?? undefined"
                >
                  <v-list density="compact" min-width="220" max-height="260">
                    <v-list-item
                      v-for="(m, idx) in mentionSuggestions"
                      :key="m.id"
                      :active="idx === mentionIndex"
                      @click="applyMention(m)"
                    >
                      <template #prepend>
                        <v-avatar size="24" color="primary">
                          <span class="text-caption text-white font-weight-bold">
                            {{ m.name.charAt(0) }}
                          </span>
                        </v-avatar>
                      </template>
                      <v-list-item-title class="text-body-2">{{ m.name }}</v-list-item-title>
                    </v-list-item>
                    <v-list-item
                      v-if="!mentionSuggestions.length"
                      class="text-caption text-grey"
                      title="沒有符合的成員"
                    />
                  </v-list>
                </v-menu>
                <div class="d-flex justify-end align-center mt-2">
                  <span class="text-caption text-grey mr-2">Ctrl + Enter 送出</span>
                  <v-btn
                    size="small"
                    color="primary"
                    variant="flat"
                    :disabled="!commentDraft.trim()"
                    :loading="commentSubmitting"
                    @click="submitComment"
                  >
                    送出
                  </v-btn>
                </div>
              </div>
            </div>
          </div>

          <!-- Thread -->
          <div v-if="commentStore.loading && !taskComments.length" class="d-flex justify-center pa-4">
            <v-progress-circular indeterminate size="24" />
          </div>
          <EmptyState
            v-else-if="!taskComments.length"
            icon="mdi-comment-text-outline"
            title="尚無留言"
            sub="成為第一個留言的人，與夥伴展開討論"
          />
          <div v-else class="pms-thread">
            <div
              v-for="c in taskComments"
              :key="c.id"
              class="pms-comment"
              :class="{ 'pms-comment--pending': c._optimistic }"
            >
              <CommentBlock
                :comment="c"
                :can-delete="canDeleteComment(c)"
                @reply="startReply(c.id)"
                @delete="deleteComment(c.id)"
              />

              <!-- Replies -->
              <div v-if="c.replies && c.replies.length" class="pms-reply-list">
                <div
                  v-for="r in c.replies"
                  :key="r.id"
                  class="pms-comment pms-comment--reply"
                  :class="{ 'pms-comment--pending': r._optimistic }"
                >
                  <CommentBlock
                    :comment="r"
                    :can-delete="canDeleteComment(r)"
                    :show-reply="false"
                    @delete="deleteComment(r.id)"
                  />
                </div>
              </div>

              <!-- Reply composer -->
              <div v-if="replyTo === c.id" class="pms-reply-composer">
                <v-textarea
                  v-model="replyDraft"
                  placeholder="輸入回覆..."
                  rows="2"
                  auto-grow
                  max-rows="4"
                  hide-details
                  variant="outlined"
                  density="compact"
                  autofocus
                />
                <div class="d-flex justify-end mt-2">
                  <v-btn
                    size="small"
                    variant="text"
                    class="mr-2"
                    @click="cancelReply"
                  >
                    取消
                  </v-btn>
                  <v-btn
                    size="small"
                    color="primary"
                    variant="flat"
                    :disabled="!replyDraft.trim()"
                    :loading="replySubmitting"
                    @click="submitReply"
                  >
                    回覆
                  </v-btn>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 歷史 -->
        <div v-else-if="activeTab === 'history'" class="pms-tab-pane">
          <div v-if="historyStore.loading && !taskHistory.length" class="d-flex justify-center pa-4">
            <v-progress-circular indeterminate size="24" />
          </div>
          <EmptyState
            v-else-if="!taskHistory.length"
            icon="mdi-history"
            title="尚無歷史記錄"
            sub="任務發生任何變更時，會在這裡留下紀錄"
          />
          <v-timeline
            v-else
            density="compact"
            side="end"
            truncate-line="start"
            class="pl-0"
          >
            <v-timeline-item
              v-for="ev in taskHistory"
              :key="ev.id"
              :dot-color="historyColor(ev.event)"
              size="x-small"
            >
              <template #icon>
                <v-icon size="12" color="white">{{ historyIcon(ev.event) }}</v-icon>
              </template>
              <div class="d-flex flex-column">
                <span class="text-body-2" v-html="historyLabel(ev)" />
                <span class="text-caption text-grey">
                  {{ ev.actor?.name ?? '系統' }} · {{ formatTime(ev.created_at) }}
                </span>
              </div>
            </v-timeline-item>
          </v-timeline>
        </div>

        <!-- 附件 -->
        <div v-else-if="activeTab === 'attachments'" class="pms-tab-pane">
          <!-- Upload zone -->
          <div
            class="pms-uploader mb-4"
            :class="{ 'pms-uploader--dragging': isDragging }"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
          >
            <v-icon size="32" class="mb-2 text-medium-emphasis">mdi-cloud-upload-outline</v-icon>
            <div class="text-body-2 mb-1">拖拉檔案到這裡或點擊上傳</div>
            <div class="text-caption text-grey mb-3">單檔最大 50MB</div>
            <v-file-input
              v-model="uploadFile"
              hide-details
              prepend-icon=""
              prepend-inner-icon="mdi-paperclip"
              variant="outlined"
              density="compact"
              label="選擇檔案"
              class="pms-uploader__input"
            />
            <div v-if="uploadProgress > 0 && uploadProgress < 100" class="mt-2">
              <v-progress-linear :model-value="uploadProgress" height="6" rounded color="primary" />
            </div>
            <div class="d-flex justify-end mt-3">
              <v-btn
                size="small"
                color="primary"
                variant="flat"
                :disabled="!uploadFile"
                :loading="uploadSaving"
                @click="uploadAttachment"
              >
                上傳
              </v-btn>
            </div>
            <span v-if="uploadError" class="text-caption text-error mt-1 d-block">
              {{ uploadError }}
            </span>
          </div>

          <!-- List -->
          <div v-if="attachmentStore.loading && !taskAttachments.length" class="d-flex justify-center pa-4">
            <v-progress-circular indeterminate size="24" />
          </div>
          <EmptyState
            v-else-if="!taskAttachments.length"
            icon="mdi-paperclip"
            title="尚無附件"
            sub="支援文件、圖片、影片等格式"
          />
          <v-list v-else density="compact" class="pa-0">
            <v-list-item
              v-for="att in taskAttachments"
              :key="att.id"
              :href="att.download_url"
              :target="att.is_previewable ? '_blank' : undefined"
              :download="att.is_previewable ? undefined : att.original_name"
              class="px-2 pms-attachment"
              rounded="lg"
            >
              <template #prepend>
                <v-icon size="22" :color="fileIconColor(att.mime_type)" class="mr-2">
                  {{ fileIcon(att.mime_type) }}
                </v-icon>
              </template>
              <v-list-item-title class="text-body-2 font-weight-medium" style="word-break: break-all;">
                {{ att.original_name }}
              </v-list-item-title>
              <v-list-item-subtitle class="text-caption">
                {{ att.size_human }}
                <template v-if="att.uploader"> · {{ att.uploader.name }}</template>
                · {{ formatTime(att.created_at) }}
              </v-list-item-subtitle>
              <template #append>
                <v-btn
                  v-if="canDeleteAttachment(att)"
                  icon="mdi-delete-outline"
                  size="x-small"
                  variant="text"
                  color="error"
                  @click.prevent="deleteAttachment(att.id)"
                />
              </template>
            </v-list-item>
          </v-list>
        </div>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useProjectStore, type Task } from '@/stores/project'
import { useLookupStore } from '@/stores/lookup'
import { useAuthStore } from '@/stores/auth'
import { useCommentStore } from '@/stores/comment'
import { useAttachmentStore } from '@/stores/attachment'
import { useHistoryStore } from '@/stores/history'
import { useTaskChannel } from '@/composables/useTaskChannel'
import { useToast } from '@/composables/useToast'
import Tabs from '@/components/ui/Tabs.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import CommentBlock from '@/components/task/CommentBlock.vue'
import type { Comment } from '@/types/comment'
import type { Attachment } from '@/types/attachment'
import type { HistoryEvent } from '@/types/history'

const props = defineProps<{ task: Task | null; projectId: number; canEdit?: boolean }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const projectStore = useProjectStore()
const lookup = useLookupStore()
const auth = useAuthStore()
const commentStore = useCommentStore()
const attachmentStore = useAttachmentStore()
const historyStore = useHistoryStore()
const toast = useToast()

const saving = ref(false)
const errorMsg = ref('')
type TabValue = 'detail' | 'comments' | 'history' | 'attachments'
const activeTab = ref<TabValue>('detail')

const taskIdRef = computed<number | null>(() => props.task?.id ?? null)

// ── Reactive collections from stores ───────────────────────────────────────
const taskComments = computed<Comment[]>(() =>
  props.task ? commentStore.byTask[props.task.id] ?? [] : [],
)
const taskAttachments = computed<Attachment[]>(() =>
  props.task ? attachmentStore.byTask[props.task.id] ?? [] : [],
)
const taskHistory = computed<HistoryEvent[]>(() =>
  props.task ? historyStore.byTask[props.task.id] ?? [] : [],
)

const tabItems = computed(() => [
  { value: 'detail', label: '詳情' },
  { value: 'comments', label: '留言', count: taskComments.value.reduce((s, c) => s + 1 + (c.replies?.length ?? 0), 0) },
  { value: 'history', label: '歷史', count: taskHistory.value.length },
  { value: 'attachments', label: '附件', count: taskAttachments.value.length },
])

// ── WebSocket realtime ────────────────────────────────────────────────────
useTaskChannel(taskIdRef, {
  onCommentCreated(payload) {
    if (!props.task) return
    const list = commentStore.byTask[props.task.id] ?? []
    if (list.some(c => c.id === payload.id)) return
    if (!commentStore.byTask[props.task.id]) commentStore.byTask[props.task.id] = []
    commentStore.byTask[props.task.id]!.push(payload)
  },
  onCommentReplied(payload) {
    if (!props.task) return
    const list = commentStore.byTask[props.task.id]
    if (!list || payload.parent_id == null) return
    const parent = list.find(c => c.id === payload.parent_id)
    if (!parent) return
    if (!parent.replies) parent.replies = []
    if (parent.replies.some(r => r.id === payload.id)) return
    parent.replies.push(payload)
  },
  onAttachmentUploaded(payload) {
    if (!props.task) return
    if (!attachmentStore.byTask[props.task.id]) attachmentStore.byTask[props.task.id] = []
    const list = attachmentStore.byTask[props.task.id]!
    if (list.some(a => a.id === payload.id)) return
    list.push(payload)
  },
  onHistoryEventRecorded(payload) {
    if (!props.task) return
    if (!historyStore.byTask[props.task.id]) historyStore.byTask[props.task.id] = []
    const list = historyStore.byTask[props.task.id]!
    if (list.some(e => e.id === payload.id)) return
    list.unshift(payload)
  },
  onReconnect() {
    if (!props.task) return
    void commentStore.fetchForTask(props.projectId, props.task.id)
    void attachmentStore.fetchForTask(props.projectId, props.task.id)
    void historyStore.fetchForTask(props.projectId, props.task.id)
  },
})

// ── @mention autocomplete ─────────────────────────────────────────────────
const composerRef = ref<{ $el: HTMLElement } | null>(null)
const commentDraft = ref('')
const commentSubmitting = ref(false)
const mentionOpen = ref(false)
const mentionQuery = ref('')
const mentionAnchorPos = ref(-1)
const mentionIndex = ref(0)

const mentionables = computed(() => lookup.users.map(u => ({ id: u.id, name: u.name })))
const mentionSuggestions = computed(() => {
  const q = mentionQuery.value.toLowerCase()
  return mentionables.value
    .filter(u => !q || u.name.toLowerCase().includes(q))
    .slice(0, 6)
})

function getComposerTextarea(): HTMLTextAreaElement | null {
  const root = composerRef.value?.$el
  if (!root) return null
  return root.querySelector('textarea') as HTMLTextAreaElement | null
}

function onComposerInput() {
  const ta = getComposerTextarea()
  if (!ta) return
  const caret = ta.selectionStart ?? commentDraft.value.length
  const before = commentDraft.value.slice(0, caret)
  const atIdx = before.lastIndexOf('@')
  if (atIdx === -1) {
    mentionOpen.value = false
    return
  }
  const fragment = before.slice(atIdx + 1)
  // bail if whitespace appears between @ and caret
  if (/\s/.test(fragment)) {
    mentionOpen.value = false
    return
  }
  mentionAnchorPos.value = atIdx
  mentionQuery.value = fragment
  mentionOpen.value = true
  mentionIndex.value = 0
}

function onComposerKeydown(e: KeyboardEvent) {
  if (mentionOpen.value && mentionSuggestions.value.length) {
    if (e.key === 'ArrowDown') {
      e.preventDefault()
      mentionIndex.value = (mentionIndex.value + 1) % mentionSuggestions.value.length
      return
    }
    if (e.key === 'ArrowUp') {
      e.preventDefault()
      mentionIndex.value =
        (mentionIndex.value - 1 + mentionSuggestions.value.length) % mentionSuggestions.value.length
      return
    }
    if (e.key === 'Enter' && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
      e.preventDefault()
      const m = mentionSuggestions.value[mentionIndex.value]
      if (m) applyMention(m)
      return
    }
    if (e.key === 'Escape') {
      mentionOpen.value = false
      return
    }
  }
  if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
    e.preventDefault()
    void submitComment()
  }
}

function applyMention(m: { id: number; name: string }) {
  if (mentionAnchorPos.value < 0) return
  const ta = getComposerTextarea()
  const caret = ta?.selectionStart ?? commentDraft.value.length
  const before = commentDraft.value.slice(0, mentionAnchorPos.value)
  const after = commentDraft.value.slice(caret)
  commentDraft.value = `${before}@${m.name} ${after}`
  mentionOpen.value = false
  mentionQuery.value = ''
  mentionAnchorPos.value = -1
  // restore caret after mention
  void nextTick(() => {
    const t = getComposerTextarea()
    if (t) {
      const pos = before.length + m.name.length + 2
      t.focus()
      t.setSelectionRange(pos, pos)
    }
  })
}

// ── Comment submit / reply / delete ───────────────────────────────────────
async function submitComment() {
  if (!commentDraft.value.trim() || !props.task) return
  commentSubmitting.value = true
  try {
    await commentStore.create(props.projectId, props.task.id, commentDraft.value.trim())
    commentDraft.value = ''
    toast.success('留言已送出')
  } catch {
    // toast already shown by store
  } finally {
    commentSubmitting.value = false
  }
}

const replyTo = ref<number | null>(null)
const replyDraft = ref('')
const replySubmitting = ref(false)

function startReply(parentId: number) {
  replyTo.value = parentId
  replyDraft.value = ''
}
function cancelReply() {
  replyTo.value = null
  replyDraft.value = ''
}
async function submitReply() {
  if (!replyTo.value || !replyDraft.value.trim() || !props.task) return
  replySubmitting.value = true
  try {
    await commentStore.reply(
      props.projectId,
      props.task.id,
      replyTo.value,
      replyDraft.value.trim(),
    )
    cancelReply()
    toast.success('回覆已送出')
  } catch {
    // toast already shown by store
  } finally {
    replySubmitting.value = false
  }
}

async function deleteComment(id: number) {
  if (!props.task) return
  try {
    await commentStore.delete(props.projectId, props.task.id, id)
    toast.success('留言已刪除')
  } catch {
    toast.error('刪除失敗')
  }
}

function canDeleteComment(c: Comment) {
  return auth.isAdmin || c.user.id === auth.user?.id
}

// ── History rendering ─────────────────────────────────────────────────────
function historyIcon(event: string) {
  const map: Record<string, string> = {
    created: 'mdi-plus-circle-outline',
    assignee_changed: 'mdi-account-arrow-right-outline',
    status_changed: 'mdi-refresh',
    progress_updated: 'mdi-chart-line',
    completed: 'mdi-check-circle-outline',
    reopened: 'mdi-restore',
    commented: 'mdi-comment-outline',
    attached: 'mdi-paperclip',
    detached: 'mdi-paperclip-off',
  }
  return map[event] ?? 'mdi-circle-small'
}
function historyColor(event: string) {
  const map: Record<string, string> = {
    created: 'success',
    assignee_changed: 'blue',
    status_changed: 'orange',
    progress_updated: 'purple',
    completed: 'green',
    reopened: 'grey',
    commented: 'teal',
    attached: 'blue-grey',
    detached: 'blue-grey',
  }
  return map[event] ?? 'grey'
}
function historyLabel(ev: HistoryEvent): string {
  if (ev.label) return ev.label
  const p = (ev.payload ?? {}) as Record<string, unknown>
  switch (ev.event) {
    case 'created': return '建立了任務'
    case 'assignee_changed': return `負責人 <b>${p.from ?? '未指派'}</b> → <b>${p.to ?? '未指派'}</b>`
    case 'status_changed': return `狀態 <b>${p.from}</b> → <b>${p.to}</b>`
    case 'progress_updated': return `進度 <b>${p.from}%</b> → <b>${p.to}%</b>`
    case 'completed': return '標記為<b>已完成</b>'
    case 'reopened': return '重新開啟任務'
    case 'commented': return '新增了留言'
    case 'attached': return `上傳了附件 <b>${p.filename ?? ''}</b>`
    case 'detached': return `刪除了附件 <b>${p.filename ?? ''}</b>`
    default: return ev.event
  }
}

// ── Attachments ───────────────────────────────────────────────────────────
const uploadFile = ref<File | null>(null)
const uploadSaving = ref(false)
const uploadError = ref('')
const uploadProgress = ref(0)
const isDragging = ref(false)

function onDrop(e: DragEvent) {
  isDragging.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file) uploadFile.value = file
}

async function uploadAttachment() {
  if (!uploadFile.value || !props.task) return
  uploadError.value = ''
  uploadSaving.value = true
  uploadProgress.value = 0
  try {
    await attachmentStore.upload(
      props.projectId,
      props.task.id,
      uploadFile.value,
      (pct) => { uploadProgress.value = pct },
    )
    uploadFile.value = null
    uploadProgress.value = 0
    toast.success('附件上傳成功')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    uploadError.value = e?.response?.data?.message ?? '上傳失敗'
    toast.error(uploadError.value)
  } finally {
    uploadSaving.value = false
  }
}

async function deleteAttachment(id: number) {
  if (!props.task) return
  try {
    await attachmentStore.delete(props.projectId, props.task.id, id)
    toast.success('附件已刪除')
  } catch {
    toast.error('刪除失敗')
  }
}

function canDeleteAttachment(att: Attachment) {
  return auth.isAdmin || att.uploader_id === auth.user?.id
}

function fileIcon(mime: string) {
  if (mime.startsWith('image/')) return 'mdi-image-outline'
  if (mime === 'application/pdf') return 'mdi-file-pdf-box'
  if (mime.includes('word')) return 'mdi-file-word-outline'
  if (mime.includes('excel') || mime.includes('spreadsheet')) return 'mdi-file-excel-outline'
  if (mime.startsWith('video/')) return 'mdi-video-outline'
  if (mime.startsWith('audio/')) return 'mdi-music-note'
  if (mime.includes('zip') || mime.includes('compressed')) return 'mdi-folder-zip-outline'
  return 'mdi-file-outline'
}

function fileIconColor(mime: string) {
  if (mime.startsWith('image/')) return 'teal'
  if (mime === 'application/pdf') return 'red'
  if (mime.includes('word')) return 'blue'
  if (mime.includes('excel') || mime.includes('spreadsheet')) return 'green'
  if (mime.startsWith('video/')) return 'purple'
  if (mime.startsWith('audio/')) return 'orange'
  return 'grey-darken-1'
}

// ── Shared util ───────────────────────────────────────────────────────────
function formatTime(iso: string) {
  return new Intl.DateTimeFormat('zh-TW', {
    month: 'numeric',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(new Date(iso))
}

// ── Form ──────────────────────────────────────────────────────────────────
const form = reactive({
  name: '',
  start_date: '',
  end_date: '',
  status_id: '' as number | '',
  priority_id: '' as number | '',
  assignee_id: null as number | null,
  progress: 0,
  note: '',
})

const STATUS_PROGRESS: Record<string, number> = {
  '準備中': 0, '待確認': 25, '進行中': 50, '追蹤中': 75, '已完成': 100,
}

watch(() => form.status_id, (id) => {
  if (!id) return
  const status = lookup.statuses.find(s => s.id === id)
  if (status && status.name in STATUS_PROGRESS) {
    form.progress = STATUS_PROGRESS[status.name]!
  }
})

onMounted(async () => {
  await lookup.fetch()
  if (props.task) {
    form.name        = props.task.name
    form.start_date  = props.task.start_date.slice(0, 10)
    form.end_date    = props.task.end_date.slice(0, 10)
    form.status_id   = props.task.status?.id ?? ''
    form.priority_id = props.task.priority?.id ?? ''
    form.assignee_id = props.task.assignee?.id ?? null
    form.progress    = props.task.progress
    form.note        = props.task.note ?? ''
    // Load tab data
    void commentStore.fetchForTask(props.projectId, props.task.id)
    void historyStore.fetchForTask(props.projectId, props.task.id)
    void attachmentStore.fetchForTask(props.projectId, props.task.id)
  } else {
    form.start_date = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Taipei' }).format(new Date())
    const end = new Date()
    end.setDate(end.getDate() + 7)
    form.end_date = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Taipei' }).format(end)
    if (lookup.statuses.length) form.status_id = lookup.statuses[0]!.id
    if (lookup.priorities.length) {
      const mid = lookup.priorities.find(p => p.sort_order === 2)
      form.priority_id = mid?.id ?? lookup.priorities[0]!.id
    }
  }
})

onBeforeUnmount(() => {
  // useTaskChannel auto-leaves on unmount via its own hook
})

async function handleSubmit() {
  saving.value = true
  errorMsg.value = ''
  try {
    const selectedStatus = lookup.statuses.find(s => s.id === form.status_id)
    const payload = {
      name: form.name,
      start_date: form.start_date,
      end_date: form.end_date,
      status_id: form.status_id,
      priority_id: form.priority_id,
      assignee_id: form.assignee_id,
      progress: form.progress,
      note: form.note || null,
      is_completed: selectedStatus?.name === '已完成',
    }
    if (props.task) {
      await projectStore.updateTask(props.projectId, props.task.id, payload)
    } else {
      await projectStore.createTask(props.projectId, payload)
    }
    emit('saved')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    errorMsg.value = e?.response?.data?.message ?? '儲存失敗，請重試'
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.pms-task-modal {
  overflow: hidden;
}

.pms-tab-pane {
  min-height: 320px;
}

/* Composer */
.pms-composer {
  background-color: rgba(var(--v-theme-on-surface), 0.02);
  border-radius: 12px;
  padding: 12px;
}

/* Thread */
.pms-thread {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.pms-comment {
  position: relative;
}

.pms-comment--pending {
  opacity: 0.6;
}

.pms-reply-list {
  margin-left: 40px;
  margin-top: 12px;
  border-left: 2px solid rgba(var(--v-theme-on-surface), 0.08);
  padding-left: 12px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.pms-reply-composer {
  margin-left: 40px;
  margin-top: 12px;
}

/* Uploader */
.pms-uploader {
  border: 2px dashed rgba(var(--v-theme-on-surface), 0.18);
  border-radius: 12px;
  padding: 20px;
  text-align: center;
  transition: border-color 0.15s, background-color 0.15s;
}

.pms-uploader--dragging {
  border-color: rgb(var(--v-theme-primary));
  background-color: rgba(var(--v-theme-primary), 0.04);
}

.pms-uploader__input {
  max-width: 360px;
  margin: 0 auto;
}

.pms-attachment {
  margin-bottom: 4px;
}
</style>
