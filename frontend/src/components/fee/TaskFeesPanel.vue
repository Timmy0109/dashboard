<template>
  <div class="pms-fees-panel">
    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-4">
      <div>
        <div class="text-caption text-grey">已核准總額</div>
        <div class="text-h6 font-weight-bold text-primary">{{ formatNT(approvedTotal) }}</div>
      </div>
      <v-btn
        color="primary"
        variant="flat"
        size="small"
        prepend-icon="mdi-plus"
        @click="openCreate"
      >
        新增費用
      </v-btn>
    </div>

    <!-- Loading -->
    <div v-if="feeStore.loading.task && !fees.length" class="d-flex justify-center pa-4">
      <v-progress-circular indeterminate size="24" />
    </div>

    <!-- Empty -->
    <EmptyState
      v-else-if="!fees.length"
      icon="mdi-cash-multiple"
      title="尚無費用"
      sub="點擊右上角「新增費用」開始記錄"
    />

    <!-- List -->
    <v-list v-else density="comfortable" class="pa-0">
      <template v-for="fee in fees" :key="fee.id">
        <v-list-item
          class="pms-fee-row mb-2"
          rounded="lg"
          @click="toggleExpand(fee.id)"
        >
          <template #prepend>
            <v-avatar size="32" color="primary" class="mr-2">
              <v-img
                v-if="fee.submitter && (fee.submitter as { avatar_url?: string }).avatar_url"
                :src="(fee.submitter as { avatar_url?: string }).avatar_url"
              />
              <span v-else class="text-caption text-white font-weight-bold">
                {{ (fee.submitter?.name ?? '?').charAt(0) }}
              </span>
            </v-avatar>
          </template>

          <v-list-item-title class="d-flex align-center">
            <span class="text-body-1 font-weight-bold mr-2">{{ formatNT(Number(fee.amount)) }}</span>
            <v-chip
              :color="statusColor(fee.status)"
              size="small"
              variant="tonal"
              class="mr-2"
            >
              {{ statusLabel(fee.status) }}
            </v-chip>
            <v-icon
              v-if="(fee.attachments?.length ?? 0) > 0"
              size="16"
              class="text-grey mr-1"
            >
              mdi-paperclip
            </v-icon>
            <span v-if="(fee.attachments?.length ?? 0) > 0" class="text-caption text-grey">
              {{ fee.attachments!.length }}
            </span>
          </v-list-item-title>

          <v-list-item-subtitle class="text-caption">
            {{ fee.submitter?.name ?? '—' }} · {{ formatTime(fee.created_at) }}
            <template v-if="fee.note"> · {{ truncate(fee.note, 40) }}</template>
          </v-list-item-subtitle>

          <template #append>
            <div class="d-flex align-center" @click.stop>
              <!-- Submitter actions: pending/rejected -->
              <template v-if="isSubmitter(fee) && (fee.status === 'pending' || fee.status === 'rejected')">
                <v-btn
                  icon="mdi-pencil-outline"
                  size="x-small"
                  variant="text"
                  @click="openEdit(fee)"
                >
                  <v-icon>mdi-pencil-outline</v-icon>
                  <v-tooltip activator="parent" text="編輯" />
                </v-btn>
                <v-btn
                  icon
                  size="x-small"
                  variant="text"
                  color="error"
                  @click="onDelete(fee)"
                >
                  <v-icon>mdi-delete-outline</v-icon>
                  <v-tooltip activator="parent" text="撤回" />
                </v-btn>
              </template>

              <!-- Submitter: resubmit (rejected) -->
              <v-btn
                v-if="isSubmitter(fee) && fee.status === 'rejected'"
                icon
                size="x-small"
                variant="text"
                color="primary"
                @click="onResubmit(fee)"
              >
                <v-icon>mdi-refresh</v-icon>
                <v-tooltip activator="parent" text="重新提交" />
              </v-btn>

              <!-- Manager/Admin: pending -->
              <template v-if="canReview && fee.status === 'pending'">
                <v-btn
                  icon
                  size="x-small"
                  variant="text"
                  color="success"
                  @click="onApprove(fee)"
                >
                  <v-icon>mdi-check</v-icon>
                  <v-tooltip activator="parent" text="核准" />
                </v-btn>
                <v-btn
                  icon
                  size="x-small"
                  variant="text"
                  color="error"
                  @click="openReason('reject', fee)"
                >
                  <v-icon>mdi-close</v-icon>
                  <v-tooltip activator="parent" text="駁回" />
                </v-btn>
                <v-btn
                  icon
                  size="x-small"
                  variant="text"
                  color="warning"
                  @click="openReason('request_receipt', fee)"
                >
                  <v-icon>mdi-receipt-text-outline</v-icon>
                  <v-tooltip activator="parent" text="要求補件" />
                </v-btn>
              </template>

              <!-- Manager/Admin: disbursed (撤回核發) -->
              <v-btn
                v-if="canDisburse && fee.status === 'disbursed'"
                icon
                size="x-small"
                variant="text"
                color="warning"
                @click="openReason('unapprove', fee)"
              >
                <v-icon>mdi-undo-variant</v-icon>
                <v-tooltip activator="parent" text="反悔" />
              </v-btn>

              <v-icon size="18" class="text-grey ml-1">
                {{ expanded[fee.id] ? 'mdi-chevron-up' : 'mdi-chevron-down' }}
              </v-icon>
            </div>
          </template>
        </v-list-item>

        <!-- Expanded details -->
        <v-expand-transition>
          <div v-if="expanded[fee.id]" class="pms-fee-detail px-4 py-3 mb-2">
            <div v-if="fee.note" class="mb-2">
              <div class="text-caption text-grey">備註</div>
              <div class="text-body-2">{{ fee.note }}</div>
            </div>
            <div v-if="fee.reject_reason" class="mb-2">
              <div class="text-caption text-error">駁回原因</div>
              <div class="text-body-2">{{ fee.reject_reason }}</div>
            </div>
            <div v-if="fee.unapprove_reason" class="mb-2">
              <div class="text-caption text-warning">反悔原因</div>
              <div class="text-body-2">{{ fee.unapprove_reason }}</div>
            </div>
            <div v-if="fee.attachments && fee.attachments.length" class="mb-1">
              <div class="text-caption text-grey mb-1">收據</div>
              <v-list density="compact" class="pa-0 bg-transparent">
                <v-list-item
                  v-for="att in fee.attachments"
                  :key="att.id"
                  :href="att.download_url"
                  :target="att.is_previewable ? '_blank' : undefined"
                  :download="att.is_previewable ? undefined : att.original_name"
                  class="px-2"
                  rounded="lg"
                >
                  <template #prepend>
                    <v-icon size="18" class="mr-2">mdi-paperclip</v-icon>
                  </template>
                  <v-list-item-title class="text-body-2" style="word-break: break-all;">
                    {{ att.original_name }}
                  </v-list-item-title>
                  <v-list-item-subtitle class="text-caption">
                    {{ att.size_human }}
                  </v-list-item-subtitle>
                </v-list-item>
              </v-list>
            </div>
          </div>
        </v-expand-transition>
      </template>
    </v-list>

    <!-- Form dialog -->
    <TaskFeeForm
      v-if="formOpen"
      :project-id="projectId"
      :task-id="taskId"
      :fee="editingFee"
      @close="closeForm"
      @saved="closeForm"
    />

    <!-- Reason dialog -->
    <v-dialog v-model="reasonDialog.open" max-width="440" persistent>
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-3 d-flex align-center justify-space-between bg-primary rounded-t-xl">
          <span class="text-body-1 font-weight-semibold text-white">
            {{ reasonTitle }}
          </span>
          <v-btn
            icon="mdi-close"
            variant="text"
            size="small"
            color="white"
            @click="closeReason"
          />
        </v-card-title>
        <v-card-text class="pa-5 pt-4">
          <v-textarea
            v-model="reasonDialog.text"
            :label="reasonLabel"
            rows="3"
            auto-grow
            autofocus
          />
          <div class="d-flex">
            <v-btn
              variant="outlined"
              color="grey"
              class="grow mr-3"
              :disabled="reasonDialog.saving"
              @click="closeReason"
            >
              取消
            </v-btn>
            <v-btn
              color="primary"
              class="grow"
              :loading="reasonDialog.saving"
              :disabled="reasonRequired && !reasonDialog.text.trim()"
              @click="confirmReason"
            >
              確認
            </v-btn>
          </div>
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useFeeStore } from '@/stores/fee'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import EmptyState from '@/components/ui/EmptyState.vue'
import TaskFeeForm from '@/components/fee/TaskFeeForm.vue'
import type { TaskFee, FeeStatus } from '@/types/fee'

const props = defineProps<{ projectId: number; taskId: number }>()

const feeStore = useFeeStore()
const auth = useAuthStore()
const toast = useToast()

const fees = computed<TaskFee[]>(() => feeStore.byTask[props.taskId] ?? [])
const approvedTotal = computed(() =>
  fees.value
    .filter(f => f.status === 'disbursed')
    .reduce((sum, f) => sum + Number(f.amount), 0),
)

const canReview   = computed(() => auth.canReviewFee)
const canDisburse = computed(() => auth.canDisburseFee)
const isSubmitter = (fee: TaskFee) => auth.user?.id === fee.submitted_by

const expanded = reactive<Record<number, boolean>>({})
function toggleExpand(id: number) {
  expanded[id] = !expanded[id]
}

// ── Form dialog state ─────────────────────────────────────────────────
const formOpen = ref(false)
const editingFee = ref<TaskFee | null>(null)
function openCreate() {
  editingFee.value = null
  formOpen.value = true
}
function openEdit(fee: TaskFee) {
  editingFee.value = fee
  formOpen.value = true
}
function closeForm() {
  formOpen.value = false
  editingFee.value = null
}

// ── Reason dialog (shared for reject / unapprove / request_receipt) ──
type ReasonMode = 'reject' | 'unapprove' | 'request_receipt'
const reasonDialog = reactive({
  open: false,
  mode: 'reject' as ReasonMode,
  text: '',
  fee: null as TaskFee | null,
  saving: false,
})

const reasonTitle = computed(() => {
  switch (reasonDialog.mode) {
    case 'reject': return '駁回原因'
    case 'unapprove': return '反悔原因'
    case 'request_receipt': return '請補件'
  }
})
const reasonLabel = computed(() => {
  if (reasonDialog.mode === 'request_receipt') return '訊息（選填）'
  return '原因'
})
const reasonRequired = computed(() => reasonDialog.mode !== 'request_receipt')

function openReason(mode: ReasonMode, fee: TaskFee) {
  reasonDialog.mode = mode
  reasonDialog.fee = fee
  reasonDialog.text = ''
  reasonDialog.open = true
}
function closeReason() {
  reasonDialog.open = false
  reasonDialog.fee = null
  reasonDialog.text = ''
}
async function confirmReason() {
  if (!reasonDialog.fee) return
  const fee = reasonDialog.fee
  const text = reasonDialog.text.trim()
  if (reasonRequired.value && !text) return
  reasonDialog.saving = true
  try {
    if (reasonDialog.mode === 'reject') {
      await feeStore.rejectTaskFee(fee, text)
      toast.success('已駁回')
    } else if (reasonDialog.mode === 'unapprove') {
      await feeStore.unreviewTaskFee(fee, text)
      toast.success('已改回待審')
    } else {
      await feeStore.requestReceipt(fee, text || undefined)
      toast.success('已要求補件')
    }
    closeReason()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    toast.error(e?.response?.data?.message ?? '操作失敗')
  } finally {
    reasonDialog.saving = false
  }
}

// ── Direct actions ────────────────────────────────────────────────────
async function onApprove(fee: TaskFee) {
  try {
    // 暫時 C5 過渡：點「核准」=「會計審核通過 + 老闆核發」一次完成
    // C6 會把按鈕拆成「審核」「核發」
    if (fee.status === 'pending') await feeStore.reviewTaskFee(fee)
    if (fee.status === 'reviewed' && auth.canDisburseFee) await feeStore.disburseTaskFee(fee)
    toast.success('已核准')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    toast.error(e?.response?.data?.message ?? '核准失敗')
  }
}

async function onResubmit(fee: TaskFee) {
  try {
    await feeStore.resubmitTaskFee(fee)
    toast.success('已重新提交')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    toast.error(e?.response?.data?.message ?? '提交失敗')
  }
}

async function onDelete(fee: TaskFee) {
  if (!confirm('確定要撤回這筆費用？')) return
  try {
    await feeStore.deleteTaskFee(fee)
    toast.success('已撤回')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    toast.error(e?.response?.data?.message ?? '撤回失敗')
  }
}

// ── Helpers ───────────────────────────────────────────────────────────
function formatNT(n: number) {
  return 'NT$' + n.toLocaleString()
}

function statusColor(s: FeeStatus) {
  if (s === 'approved') return 'success'
  if (s === 'rejected') return 'error'
  return 'warning'
}
function statusLabel(s: FeeStatus) {
  if (s === 'approved') return '已核准'
  if (s === 'rejected') return '已駁回'
  return '待審'
}

function truncate(s: string, n: number) {
  return s.length > n ? s.slice(0, n) + '…' : s
}

function formatTime(iso: string) {
  const d = new Date(iso)
  const now = Date.now()
  const diff = (now - d.getTime()) / 1000
  if (diff < 60) return '剛剛'
  if (diff < 3600) return `${Math.floor(diff / 60)} 分鐘前`
  if (diff < 86400) return `${Math.floor(diff / 3600)} 小時前`
  if (diff < 86400 * 7) return `${Math.floor(diff / 86400)} 天前`
  return new Intl.DateTimeFormat('zh-TW', {
    month: 'numeric',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(d)
}

onMounted(() => {
  void feeStore.fetchTaskFees(props.projectId, props.taskId)
})
</script>

<style scoped>
.pms-fees-panel {
  min-height: 320px;
}

.pms-fee-row {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.06);
  transition: background-color 0.15s;
}

.pms-fee-row:hover {
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}

.pms-fee-detail {
  background-color: rgba(var(--v-theme-on-surface), 0.03);
  border-radius: 10px;
}
</style>
