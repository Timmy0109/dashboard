<template>
  <v-dialog :model-value="true" :max-width="task ? 720 : 520" scrollable persistent @update:model-value="$emit('close')">
    <v-card rounded="xl">
      <v-card-title class="pa-5 pb-3 d-flex align-center justify-space-between bg-primary rounded-t-xl">
        <span class="text-body-1 font-weight-semibold text-white">{{ task ? '編輯任務' : '新增任務' }}</span>
        <v-btn icon="mdi-close" variant="text" size="small" color="white" @click="$emit('close')" />
      </v-card-title>

      <v-card-text class="pa-5 pt-2">
        <v-row :no-gutters="!task">
          <!-- Left: form -->
          <v-col :cols="task ? 6 : 12" :class="task ? 'pr-4' : ''">
            <v-form @submit.prevent="handleSubmit" class="pt-2">
              <v-text-field v-model="form.name" label="任務名稱" required autofocus class="mb-3" />

              <v-row dense class="mb-1">
                <v-col cols="6">
                  <v-text-field v-model="form.start_date" label="開始日期" type="date" required />
                </v-col>
                <v-col cols="6">
                  <v-text-field v-model="form.end_date" label="結束日期" type="date" required />
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

              <v-alert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-3 text-body-2">
                {{ errorMsg }}
              </v-alert>

              <div class="d-flex">
                <v-btn variant="outlined" color="grey" class="grow mr-3" @click="$emit('close')">取消</v-btn>
                <v-btn type="submit" color="primary" class="grow" :loading="saving">儲存</v-btn>
              </div>
            </v-form>
          </v-col>

          <!-- Right: tabs (edit mode only) -->
          <v-col v-if="task" cols="6" class="pl-4" style="border-left: 1px solid rgba(0,0,0,0.08);">
            <v-tabs v-model="activeTab" density="compact" class="mb-3">
              <v-tab value="comments" class="text-caption">留言</v-tab>
              <v-tab value="activities" class="text-caption">歷程</v-tab>
              <v-tab value="attachments" class="text-caption">附件</v-tab>
            </v-tabs>

            <!-- Comments -->
            <div v-if="activeTab === 'comments'" style="height: 340px; display: flex; flex-direction: column;">
              <div class="grow overflow-y-auto" style="max-height: 265px;">
                <div v-if="commentsLoading" class="d-flex justify-center pa-4">
                  <v-progress-circular indeterminate size="20" />
                </div>
                <div v-else-if="!comments.length" class="text-caption text-grey text-center pa-4">尚無留言</div>
                <div v-else v-for="c in comments" :key="c.id" class="d-flex mb-3">
                  <v-avatar size="28" color="primary" class="mr-2 shrink-0 mt-1">
                    <v-img v-if="c.user.avatar_url" :src="c.user.avatar_url" />
                    <span v-else class="text-caption text-white font-weight-bold">{{ c.user.name.charAt(0) }}</span>
                  </v-avatar>
                  <div class="grow">
                    <div class="d-flex align-center justify-space-between">
                      <span class="text-caption font-weight-bold">{{ c.user.name }}</span>
                      <div class="d-flex align-center">
                        <span class="text-caption text-grey mr-1">{{ formatTime(c.created_at) }}</span>
                        <v-menu v-if="canDeleteComment(c)" offset-y>
                          <template #activator="{ props: mProps }">
                            <v-btn v-bind="mProps" icon="mdi-dots-vertical" size="x-small" variant="text" density="compact" />
                          </template>
                          <v-list density="compact">
                            <v-list-item prepend-icon="mdi-delete-outline" title="刪除" @click="deleteComment(c.id)" />
                          </v-list>
                        </v-menu>
                      </div>
                    </div>
                    <div class="text-body-2 mt-0" style="white-space: pre-wrap; word-break: break-word;">{{ c.body }}</div>
                  </div>
                </div>
              </div>

              <div class="pt-2 mt-auto" style="border-top: 1px solid rgba(0,0,0,0.08);">
                <v-textarea
                  v-model="commentDraft"
                  placeholder="輸入留言..."
                  rows="2"
                  auto-grow
                  max-rows="4"
                  hide-details
                  variant="outlined"
                  density="compact"
                  class="mb-2"
                  @keydown.ctrl.enter.prevent="submitComment"
                />
                <v-btn
                  size="small"
                  color="primary"
                  variant="flat"
                  :disabled="!commentDraft.trim()"
                  :loading="commentSaving"
                  @click="submitComment"
                >送出</v-btn>
              </div>
            </div>

            <!-- Activities -->
            <div v-else-if="activeTab === 'activities'" style="height: 340px; overflow-y: auto;">
              <div v-if="activitiesLoading" class="d-flex justify-center pa-4">
                <v-progress-circular indeterminate size="20" />
              </div>
              <div v-else-if="!activities.length" class="text-caption text-grey text-center pa-4">尚無歷程記錄</div>
              <v-timeline v-else density="compact" side="end" truncate-line="start" class="pl-0">
                <v-timeline-item
                  v-for="a in activities"
                  :key="a.id"
                  :dot-color="activityColor(a.event)"
                  size="x-small"
                >
                  <template #icon>
                    <v-icon size="12" :color="activityColor(a.event)">{{ activityIcon(a.event) }}</v-icon>
                  </template>
                  <div class="d-flex flex-column">
                    <span class="text-caption" v-html="activityLabel(a)" />
                    <span class="text-caption text-grey">{{ a.actor?.name }} · {{ formatTime(a.created_at) }}</span>
                  </div>
                </v-timeline-item>
              </v-timeline>
            </div>

            <!-- Attachments -->
            <div v-else-if="activeTab === 'attachments'" style="height: 340px; display: flex; flex-direction: column;">
              <div class="grow overflow-y-auto" style="max-height: 280px;">
                <div v-if="attachmentsLoading" class="d-flex justify-center pa-4">
                  <v-progress-circular indeterminate size="20" />
                </div>
                <div v-else-if="!attachments.length" class="text-caption text-grey text-center pa-4">尚無附件</div>
                <v-list v-else density="compact">
                  <v-list-item
                    v-for="att in attachments"
                    :key="att.id"
                    :href="att.download_url"
                    :target="att.is_previewable ? '_blank' : undefined"
                    :download="att.is_previewable ? undefined : att.original_name"
                    class="px-0"
                  >
                    <template #prepend>
                      <v-icon size="20" class="mr-2">{{ fileIcon(att.mime_type) }}</v-icon>
                    </template>
                    <v-list-item-title class="text-caption" style="word-break: break-all;">{{ att.original_name }}</v-list-item-title>
                    <v-list-item-subtitle class="text-caption">{{ att.size_human }}</v-list-item-subtitle>
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

              <div class="pt-2 mt-auto" style="border-top: 1px solid rgba(0,0,0,0.08);">
                <v-file-input
                  v-model="uploadFile"
                  label="上傳附件（最大 50MB）"
                  prepend-icon=""
                  prepend-inner-icon="mdi-paperclip"
                  variant="outlined"
                  density="compact"
                  hide-details
                  class="mb-2"
                />
                <v-btn
                  size="small"
                  color="primary"
                  variant="flat"
                  :disabled="!uploadFile.length"
                  :loading="uploadSaving"
                  @click="uploadAttachment"
                >上傳</v-btn>
                <span v-if="uploadError" class="text-caption text-error ml-2">{{ uploadError }}</span>
              </div>
            </div>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { reactive, ref, watch, onMounted, computed } from 'vue'
import { useProjectStore, type Task } from '@/stores/project'
import { useLookupStore } from '@/stores/lookup'
import { useAuthStore } from '@/stores/auth'
import axios from 'axios'

const props = defineProps<{ task: Task | null; projectId: number; canEdit?: boolean }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const projectStore = useProjectStore()
const lookup = useLookupStore()
const auth = useAuthStore()

const saving = ref(false)
const errorMsg = ref('')
const activeTab = ref('comments')

// ── Comments ──────────────────────────────────────────────────────────────
interface Comment { id: number; user: { id: number; name: string; avatar_url?: string }; body: string; created_at: string }
const comments = ref<Comment[]>([])
const commentsLoading = ref(false)
const commentDraft = ref('')
const commentSaving = ref(false)

async function loadComments() {
  if (!props.task) return
  commentsLoading.value = true
  const res = await axios.get(`/api/projects/${props.projectId}/tasks/${props.task.id}/comments`)
  comments.value = res.data
  commentsLoading.value = false
}

async function submitComment() {
  if (!commentDraft.value.trim() || !props.task) return
  commentSaving.value = true
  await axios.post(`/api/projects/${props.projectId}/tasks/${props.task.id}/comments`, { body: commentDraft.value.trim() })
  commentDraft.value = ''
  await loadComments()
  commentSaving.value = false
}

async function deleteComment(id: number) {
  if (!props.task) return
  await axios.delete(`/api/projects/${props.projectId}/tasks/${props.task.id}/comments/${id}`)
  await loadComments()
}

function canDeleteComment(c: Comment) {
  return auth.isAdmin || c.user.id === auth.user?.id
}

// ── Activities ────────────────────────────────────────────────────────────
interface Activity { id: number; event: string; payload: Record<string, any> | null; created_at: string; actor?: { id: number; name: string } | null }
const activities = ref<Activity[]>([])
const activitiesLoading = ref(false)

async function loadActivities() {
  if (!props.task) return
  activitiesLoading.value = true
  const res = await axios.get(`/api/projects/${props.projectId}/tasks/${props.task.id}/activities`)
  activities.value = res.data
  activitiesLoading.value = false
}

function activityIcon(event: string) {
  const map: Record<string, string> = {
    created: 'mdi-plus-circle-outline',
    assignee_changed: 'mdi-account-arrow-right-outline',
    status_changed: 'mdi-refresh',
    progress_updated: 'mdi-chart-line',
    completed: 'mdi-check-circle-outline',
    reopened: 'mdi-restore',
    commented: 'mdi-comment-outline',
  }
  return map[event] ?? 'mdi-circle-small'
}

function activityColor(event: string) {
  const map: Record<string, string> = {
    created: 'success',
    assignee_changed: 'blue',
    status_changed: 'orange',
    progress_updated: 'purple',
    completed: 'green',
    reopened: 'grey',
    commented: 'teal',
  }
  return map[event] ?? 'grey'
}

function activityLabel(a: Activity): string {
  const p = a.payload ?? {}
  switch (a.event) {
    case 'created': return '建立了任務'
    case 'assignee_changed': return `負責人 <b>${p.from ?? '未指派'}</b> → <b>${p.to ?? '未指派'}</b>`
    case 'status_changed': return `狀態 <b>${p.from}</b> → <b>${p.to}</b>`
    case 'progress_updated': return `進度 <b>${p.from}%</b> → <b>${p.to}%</b>`
    case 'completed': return '標記為<b>已完成</b>'
    case 'reopened': return '重新開啟任務'
    case 'commented': return '新增了留言'
    default: return a.event
  }
}

// ── Attachments ───────────────────────────────────────────────────────────
interface Attachment { id: number; original_name: string; mime_type: string; size_human: string; download_url: string; is_previewable: boolean; uploader_id: number }
const attachments = ref<Attachment[]>([])
const attachmentsLoading = ref(false)
const uploadFile = ref<File[]>([])
const uploadSaving = ref(false)
const uploadError = ref('')

async function loadAttachments() {
  if (!props.task) return
  attachmentsLoading.value = true
  const res = await axios.get(`/api/projects/${props.projectId}/tasks/${props.task.id}/attachments`)
  attachments.value = res.data
  attachmentsLoading.value = false
}

async function uploadAttachment() {
  const file = uploadFile.value[0]
  if (!file || !props.task) return
  uploadError.value = ''
  uploadSaving.value = true
  const fd = new FormData()
  fd.append('file', file)
  try {
    await axios.post(`/api/projects/${props.projectId}/tasks/${props.task.id}/attachments`, fd)
    uploadFile.value = []
    await loadAttachments()
  } catch (err: any) {
    uploadError.value = err?.response?.data?.message ?? '上傳失敗'
  } finally {
    uploadSaving.value = false
  }
}

async function deleteAttachment(id: number) {
  if (!props.task) return
  await axios.delete(`/api/projects/${props.projectId}/tasks/${props.task.id}/attachments/${id}`)
  await loadAttachments()
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
  return 'mdi-file-outline'
}

// ── Shared util ───────────────────────────────────────────────────────────
function formatTime(iso: string) {
  return new Intl.DateTimeFormat('zh-TW', { month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }).format(new Date(iso))
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
    loadComments()
    loadActivities()
    loadAttachments()
  } else {
    form.start_date = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Taipei' }).format(new Date())
    const end = new Date()
    end.setDate(end.getDate() + 7)
    form.end_date = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Taipei' }).format(end)
    if (lookup.statuses.length)  form.status_id   = lookup.statuses[0]!.id
    if (lookup.priorities.length) {
      const mid = lookup.priorities.find(p => p.sort_order === 2)
      form.priority_id = mid?.id ?? lookup.priorities[0]!.id
    }
  }
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
  } catch (err: any) {
    errorMsg.value = err?.response?.data?.message ?? '儲存失敗，請重試'
  } finally {
    saving.value = false
  }
}
</script>
