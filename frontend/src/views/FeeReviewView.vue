<template>
  <div>
    <!-- Header -->
    <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-5">
      <div>
        <h2 class="text-h5 font-weight-bold mb-1">費用審核</h2>
        <div class="text-body-2 text-medium-emphasis">
          集中審核各專案成員提交的任務費用申請
        </div>
      </div>
      <v-btn
        variant="outlined"
        prepend-icon="mdi-download"
        rounded="lg"
        @click="exportReport"
      >
        匯出報表
      </v-btn>
    </div>

    <!-- KPI -->
    <v-row class="mb-5" dense>
      <v-col cols="12" sm="6" md="3">
        <KPICard
          label="待審核筆數"
          :value="kpi?.pending_count ?? 0"
          :sub="`金額合計 NT$${(kpi?.pending_amount ?? 0).toLocaleString()}`"
          icon="mdi-clock-outline"
          icon-color="warning"
          accent="warning"
        />
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <KPICard
          label="待補件"
          :value="kpi?.receipt_requested_count ?? 0"
          sub="已退回成員補件"
          icon="mdi-email-outline"
          icon-color="info"
          accent="info"
        />
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <KPICard
          label="本月已核准"
          :value="kpi?.approved_this_month ?? 0"
          :sub="`金額合計 NT$${(kpi?.approved_this_month_amount ?? 0).toLocaleString()}`"
          icon="mdi-check-circle-outline"
          icon-color="success"
          accent="success"
        />
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <KPICard
          label="已退件"
          :value="kpi?.rejected_count ?? 0"
          sub="本月內被退件的費用"
          icon="mdi-close-circle-outline"
          icon-color="error"
          accent="error"
        />
      </v-col>
    </v-row>

    <!-- 提示 -->
    <v-alert
      v-if="(kpi?.pending_count ?? 0) > 0 || (kpi?.receipt_requested_count ?? 0) > 0"
      type="warning"
      variant="tonal"
      density="compact"
      icon="mdi-alert"
      class="mb-4"
    >
      有 {{ kpi?.pending_count ?? 0 }} 筆費用等待審核，另有
      {{ kpi?.receipt_requested_count ?? 0 }} 筆已退回補件。逐筆核准、退件或請成員補件。
    </v-alert>

    <!-- 主表 -->
    <v-card rounded="xl">
      <v-card-title class="px-5 py-4 border-b">
        <div class="d-flex align-center gap-3 flex-wrap w-100">
          <v-text-field
            v-model="search"
            prepend-inner-icon="mdi-magnify"
            placeholder="搜尋成員 / 任務 / 專案..."
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            style="max-width: 320px"
            @update:model-value="onSearch"
          />
          <ChipGroup v-model="store.status" :items="tabItems" @update:modelValue="onStatusChange" />
        </div>
      </v-card-title>

      <v-data-table
        :headers="headers"
        :items="items"
        :loading="loading.list"
        item-value="id"
        hover
        @click:row="(_e: Event, { item }: { item: TaskFee }) => openDetail(item)"
      >
        <template #item.submitter="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <v-avatar :color="avatarColor(item.submitter?.id ?? 0)" size="28">
              <span class="text-caption text-white font-weight-bold">
                {{ item.submitter?.name?.charAt(0) ?? '?' }}
              </span>
            </v-avatar>
            <span class="text-body-2 font-weight-medium">{{ item.submitter?.name ?? '—' }}</span>
          </div>
        </template>

        <template #item.amount="{ item }">
          <div class="d-flex flex-column">
            <span class="text-body-2 font-weight-bold pms-tnum">NT${{ Number(item.amount).toLocaleString() }}</span>
            <span v-if="item.note" class="text-caption text-medium-emphasis text-truncate" style="max-width:200px">
              {{ item.note }}
            </span>
          </div>
        </template>

        <template #item.task="{ item }">
          <div class="d-flex flex-column">
            <span class="text-body-2 font-weight-medium">{{ item.task?.name ?? '—' }}</span>
            <span class="text-caption text-medium-emphasis">{{ item.task?.project?.name ?? '—' }}</span>
          </div>
        </template>

        <template #item.receipt="{ item }">
          <v-btn
            v-if="(item.attachments?.length ?? 0) > 0"
            size="x-small"
            variant="tonal"
            color="primary"
            prepend-icon="mdi-eye-outline"
          >檢視</v-btn>
          <span v-else class="text-caption text-medium-emphasis">無收據</span>
        </template>

        <template #item.created_at="{ item }">
          <span class="text-caption">{{ relativeTime(item.created_at) }}</span>
        </template>

        <template #item.status="{ item }">
          <v-chip
            :color="statusColor(item)"
            size="x-small"
            variant="flat"
            density="compact"
            class="pms-status-chip"
          >
            {{ statusLabel(item) }}
          </v-chip>
        </template>

        <template #item.actions="{ item }">
          <div v-if="item.status === 'pending'" class="d-flex align-center justify-center gap-1" @click.stop>
            <v-btn
              color="success"
              size="x-small"
              variant="flat"
              prepend-icon="mdi-check"
              :loading="busyId === item.id && busyAction === 'approve'"
              @click="onApprove(item)"
            >核准</v-btn>
            <v-btn
              size="x-small"
              variant="outlined"
              color="warning"
              prepend-icon="mdi-email-outline"
              :loading="busyId === item.id && busyAction === 'receipt'"
              @click="onRequestReceipt(item)"
            >補件</v-btn>
            <v-btn
              size="x-small"
              variant="outlined"
              color="error"
              prepend-icon="mdi-close"
              :loading="busyId === item.id && busyAction === 'reject'"
              @click="onReject(item)"
            >退件</v-btn>
          </div>
          <div v-else-if="item.status === 'approved'" class="d-flex align-center justify-center" @click.stop>
            <v-btn
              size="x-small"
              variant="outlined"
              color="grey-darken-1"
              prepend-icon="mdi-undo-variant"
              :loading="busyId === item.id && busyAction === 'unapprove'"
              @click="onUnapprove(item)"
            >改回待審</v-btn>
          </div>
          <div v-else-if="item.status === 'rejected'" class="d-flex align-center justify-center" @click.stop>
            <v-btn
              size="x-small"
              variant="outlined"
              color="grey-darken-1"
              prepend-icon="mdi-restore"
              :loading="busyId === item.id && busyAction === 'resubmit'"
              @click="onResubmit(item)"
            >改回待審</v-btn>
          </div>
        </template>
      </v-data-table>

      <div class="px-5 py-3 text-caption text-medium-emphasis">
        顯示 {{ items.length }} 筆
      </div>
    </v-card>

    <!-- Detail dialog -->
    <v-dialog v-model="detailDialog" max-width="640" scrollable>
      <v-card v-if="detailFee" rounded="xl">
        <v-card-title class="d-flex align-center gap-3 px-5 py-4 border-b">
          <v-avatar :color="avatarColor(detailFee.submitter?.id ?? 0)" size="36">
            <span class="text-body-2 text-white font-weight-bold">
              {{ detailFee.submitter?.name?.charAt(0) ?? '?' }}
            </span>
          </v-avatar>
          <div class="flex-1-1">
            <div class="d-flex align-center gap-2">
              <span class="text-body-1 font-weight-semibold">
                {{ detailFee.submitter?.name ?? '—' }}
              </span>
              <v-chip
                :color="statusColor(detailFee)"
                size="x-small"
                variant="flat"
                density="compact"
                class="pms-status-chip"
              >
                {{ statusLabel(detailFee) }}
              </v-chip>
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ detailFee.task?.project?.name ?? '—' }} · {{ detailFee.task?.name ?? '—' }}
            </div>
          </div>
          <v-btn icon="mdi-close" variant="text" size="small" @click="detailDialog = false" />
        </v-card-title>

        <v-card-text class="pa-5">
          <!-- 基本資訊 -->
          <div class="pms-detail-section">
            <div class="d-flex align-baseline gap-3 mb-2">
              <span class="text-caption text-medium-emphasis">金額</span>
              <span class="text-h5 font-weight-bold pms-tnum text-primary">
                NT${{ Number(detailFee.amount).toLocaleString() }}
              </span>
            </div>
            <div v-if="detailFee.note" class="text-body-2" style="white-space: pre-wrap">
              {{ detailFee.note }}
            </div>
            <div v-else class="text-caption text-medium-emphasis">無備註</div>
            <div class="text-caption text-medium-emphasis mt-2">
              提交於 {{ formatDate(detailFee.created_at) }}
            </div>
          </div>

          <!-- 收據附件 -->
          <v-divider class="my-4" />
          <div class="d-flex align-center gap-2 mb-3">
            <v-icon icon="mdi-paperclip" size="16" color="primary" />
            <span class="text-body-2 font-weight-semibold">收據／附件</span>
            <v-chip
              v-if="(detailFee.attachments?.length ?? 0) > 0"
              size="x-small"
              variant="tonal"
              class="ml-1"
            >{{ detailFee.attachments!.length }}</v-chip>
          </div>
          <v-list
            v-if="(detailFee.attachments?.length ?? 0) > 0"
            density="compact"
            class="bg-transparent pa-0"
          >
            <v-list-item
              v-for="att in detailFee.attachments"
              :key="att.id"
              class="px-2 mb-1 rounded-lg pms-att-item"
            >
              <template #prepend>
                <v-icon icon="mdi-file-document-outline" size="18" />
              </template>
              <v-list-item-title class="text-body-2">{{ att.original_name }}</v-list-item-title>
              <v-list-item-subtitle class="text-caption">{{ att.size_human }}</v-list-item-subtitle>
              <template #append>
                <v-btn
                  :href="att.download_url"
                  target="_blank"
                  icon="mdi-download"
                  size="x-small"
                  variant="text"
                  color="primary"
                />
              </template>
            </v-list-item>
          </v-list>
          <div v-else class="text-caption text-medium-emphasis">尚未上傳收據</div>

          <!-- 改回待審原因 -->
          <template v-if="detailFee.unapprove_reason">
            <v-divider class="my-4" />
            <div class="d-flex align-center gap-2 mb-2">
              <v-icon icon="mdi-undo-variant" size="16" color="grey-darken-1" />
              <span class="text-body-2 font-weight-semibold">改回待審原因</span>
              <span v-if="detailFee.unapprover" class="text-caption text-medium-emphasis">
                · {{ detailFee.unapprover.name }} · {{ detailFee.unapproved_at ? formatDate(detailFee.unapproved_at) : '' }}
              </span>
            </div>
            <v-alert color="grey-lighten-3" variant="tonal" density="compact">
              <div style="white-space: pre-wrap">{{ detailFee.unapprove_reason }}</div>
            </v-alert>
          </template>

          <!-- 退件原因 -->
          <template v-if="detailFee.reject_reason">
            <v-divider class="my-4" />
            <div class="d-flex align-center gap-2 mb-2">
              <v-icon icon="mdi-close-circle-outline" size="16" color="error" />
              <span class="text-body-2 font-weight-semibold">退件原因</span>
              <span v-if="detailFee.reviewer" class="text-caption text-medium-emphasis">
                · {{ detailFee.reviewer.name }} · {{ detailFee.reviewed_at ? formatDate(detailFee.reviewed_at) : '' }}
              </span>
            </div>
            <v-alert type="error" variant="tonal" density="compact">
              <div style="white-space: pre-wrap">{{ detailFee.reject_reason }}</div>
            </v-alert>
          </template>

          <!-- 補件留言 -->
          <template v-if="detailFee.receipt_requested_at">
            <v-divider class="my-4" />
            <div class="d-flex align-center gap-2 mb-2">
              <v-icon icon="mdi-email-outline" size="16" color="info" />
              <span class="text-body-2 font-weight-semibold">補件留言</span>
              <span v-if="detailFee.receipt_requester" class="text-caption text-medium-emphasis">
                · {{ detailFee.receipt_requester.name }} · {{ formatDate(detailFee.receipt_requested_at) }}
              </span>
            </div>
            <v-alert type="info" variant="tonal" density="compact">
              <div v-if="detailFee.receipt_request_message" style="white-space: pre-wrap">
                {{ detailFee.receipt_request_message }}
              </div>
              <div v-else class="text-caption">無留言（已請成員補件）</div>
            </v-alert>
          </template>
        </v-card-text>

        <v-card-actions v-if="detailFee.status === 'pending'" class="px-5 pb-4">
          <v-spacer />
          <v-btn
            variant="outlined"
            color="error"
            prepend-icon="mdi-close"
            @click="onReject(detailFee); detailDialog = false"
          >退件</v-btn>
          <v-btn
            variant="outlined"
            color="warning"
            prepend-icon="mdi-email-outline"
            @click="onRequestReceiptFromDetail(detailFee)"
          >補件</v-btn>
          <v-btn
            color="success"
            prepend-icon="mdi-check"
            @click="onApprove(detailFee).then(() => (detailDialog = false))"
          >核准</v-btn>
        </v-card-actions>

        <v-card-actions v-else-if="detailFee.status === 'approved'" class="px-5 pb-4">
          <v-spacer />
          <v-btn
            variant="outlined"
            color="grey-darken-1"
            prepend-icon="mdi-undo-variant"
            @click="onUnapprove(detailFee)"
          >改回待審</v-btn>
        </v-card-actions>

        <v-card-actions v-else-if="detailFee.status === 'rejected'" class="px-5 pb-4">
          <v-spacer />
          <v-btn
            variant="outlined"
            color="grey-darken-1"
            prepend-icon="mdi-restore"
            @click="onResubmit(detailFee).then(() => (detailDialog = false))"
          >改回待審</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Receipt request message dialog -->
    <v-dialog v-model="receiptDialog" max-width="480">
      <v-card rounded="xl">
        <v-card-title class="d-flex align-center gap-2 px-5 py-4 border-b">
          <v-icon icon="mdi-email-outline" color="warning" size="20" />
          <span class="text-body-1 font-weight-semibold">通知補件</span>
          <v-spacer />
          <v-btn icon="mdi-close" variant="text" size="small" @click="receiptDialog = false" />
        </v-card-title>
        <v-card-text class="pa-5">
          <div class="text-caption text-medium-emphasis mb-2">
            提交者將收到通知，可留言說明需要補上哪些資料
          </div>
          <v-textarea
            v-model="receiptMessage"
            label="留言給提交者（可選）"
            placeholder="例：請補上正式發票，謝謝"
            rows="3"
            auto-grow
            variant="outlined"
            density="comfortable"
            hide-details="auto"
          />
        </v-card-text>
        <v-card-actions class="px-5 pb-4">
          <v-spacer />
          <v-btn variant="text" @click="receiptDialog = false">取消</v-btn>
          <v-btn color="warning" variant="flat" @click="confirmReceiptRequest">送出</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- 改回待審 reason dialog -->
    <v-dialog v-model="unapproveDialog" max-width="480">
      <v-card rounded="xl">
        <v-card-title class="d-flex align-center gap-2 px-5 py-4 border-b">
          <v-icon icon="mdi-undo-variant" color="grey-darken-1" size="20" />
          <span class="text-body-1 font-weight-semibold">改回待審</span>
          <v-spacer />
          <v-btn icon="mdi-close" variant="text" size="small" @click="unapproveDialog = false" />
        </v-card-title>
        <v-card-text class="pa-5">
          <div class="text-caption text-medium-emphasis mb-2">
            費用會回到待審狀態，請簡述原因（會通知提交者）
          </div>
          <v-textarea
            v-model="unapproveReason"
            label="原因"
            rows="3"
            auto-grow
            variant="outlined"
            density="comfortable"
            hide-details="auto"
            :rules="[(v: string) => !!v?.trim() || '請輸入原因']"
          />
        </v-card-text>
        <v-card-actions class="px-5 pb-4">
          <v-spacer />
          <v-btn variant="text" @click="unapproveDialog = false">取消</v-btn>
          <v-btn
            color="grey-darken-1"
            variant="flat"
            :disabled="!unapproveReason.trim()"
            @click="confirmUnapprove"
          >確認</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Reject reason dialog -->
    <v-dialog v-model="rejectDialog" max-width="480">
      <v-card rounded="xl">
        <v-card-title class="d-flex align-center gap-2 px-5 py-4 border-b">
          <v-icon icon="mdi-close-circle-outline" color="error" size="20" />
          <span class="text-body-1 font-weight-semibold">退件費用</span>
          <v-spacer />
          <v-btn icon="mdi-close" variant="text" size="small" @click="rejectDialog = false" />
        </v-card-title>
        <v-card-text class="pa-5">
          <div class="text-caption text-medium-emphasis mb-2">
            提交者會收到通知，請簡述原因
          </div>
          <v-textarea
            v-model="rejectReason"
            label="退件原因"
            rows="3"
            auto-grow
            variant="outlined"
            density="comfortable"
            hide-details="auto"
            :rules="[(v: string) => !!v?.trim() || '請輸入退件原因']"
          />
        </v-card-text>
        <v-card-actions class="px-5 pb-4">
          <v-spacer />
          <v-btn variant="text" @click="rejectDialog = false">取消</v-btn>
          <v-btn color="error" variant="flat" :disabled="!rejectReason.trim()" @click="confirmReject">確認退件</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useFeeReviewStore, type ReviewStatus } from '@/stores/feeReview'
import { useFeeStore } from '@/stores/fee'
import { useToast } from '@/composables/useToast'
import KPICard from '@/components/ui/KPICard.vue'
import ChipGroup from '@/components/ui/ChipGroup.vue'
import type { TaskFee } from '@/types/fee'

const store = useFeeReviewStore()
const feeStore = useFeeStore()
const toast = useToast()

const kpi = computed(() => store.kpi)
const items = computed<TaskFee[]>(() => store.items)
const loading = computed(() => store.loading)

const search = ref(store.search)
const busyId = ref<number | null>(null)
const busyAction = ref<'approve' | 'reject' | 'receipt' | 'unapprove' | 'resubmit' | null>(null)
const rejectDialog = ref(false)
const rejectReason = ref('')
const pendingReject = ref<TaskFee | null>(null)

const detailDialog = ref(false)
const detailFee = ref<TaskFee | null>(null)

const receiptDialog = ref(false)
const receiptMessage = ref('')
const pendingReceipt = ref<TaskFee | null>(null)

const unapproveDialog = ref(false)
const unapproveReason = ref('')
const pendingUnapprove = ref<TaskFee | null>(null)

function openDetail(fee: TaskFee) {
  detailFee.value = fee
  detailDialog.value = true
}

function formatDate(iso: string): string {
  const d = new Date(iso)
  return d.toLocaleString('zh-TW', { dateStyle: 'short', timeStyle: 'short' })
}

function onRequestReceiptFromDetail(fee: TaskFee) {
  pendingReceipt.value = fee
  receiptMessage.value = ''
  receiptDialog.value = true
}

function onUnapprove(fee: TaskFee) {
  pendingUnapprove.value = fee
  unapproveReason.value = ''
  unapproveDialog.value = true
}

async function confirmUnapprove() {
  const fee = pendingUnapprove.value
  if (!fee) return
  busyId.value = fee.id
  busyAction.value = 'unapprove'
  unapproveDialog.value = false
  detailDialog.value = false
  try {
    await feeStore.unapproveTaskFee(fee, unapproveReason.value.trim())
    toast.success('已改回待審')
    await store.fetch()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? '改回待審失敗')
  } finally {
    busyId.value = null
    busyAction.value = null
    pendingUnapprove.value = null
  }
}

async function onResubmit(fee: TaskFee) {
  busyId.value = fee.id
  busyAction.value = 'resubmit'
  try {
    await feeStore.resubmitTaskFee(fee)
    toast.success('已改回待審')
    await store.fetch()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? '改回待審失敗')
  } finally {
    busyId.value = null
    busyAction.value = null
  }
}

const tabItems = computed(() => [
  { value: 'pending', label: `待處理 (${kpi.value?.pending_count ?? 0})` },
  { value: 'approved', label: `已核准 (${kpi.value?.approved_this_month ?? 0})` },
  { value: 'rejected', label: `已退件 (${kpi.value?.rejected_count ?? 0})` },
  { value: 'all', label: `全部 (${kpi.value?.total_count ?? 0})` },
])

const headers = [
  { title: '申請人', key: 'submitter', sortable: false },
  { title: '金額', key: 'amount', sortable: false },
  { title: '所屬任務 / 專案', key: 'task', sortable: false },
  { title: '收據', key: 'receipt', sortable: false, align: 'center' as const },
  { title: '送出時間', key: 'created_at', sortable: false },
  { title: '狀態', key: 'status', sortable: false, align: 'center' as const },
  { title: '審核', key: 'actions', sortable: false, align: 'center' as const, width: 280 },
]

let searchTimer: ReturnType<typeof setTimeout> | null = null
function onSearch(v: string) {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => store.setSearch(v), 300)
}
function onStatusChange(s: unknown) {
  store.setStatus(s as ReviewStatus)
}

function statusColor(fee: TaskFee): string {
  if (fee.status === 'approved') return 'success'
  if (fee.status === 'rejected') return 'error'
  if (fee.receipt_requested_at) return 'info'
  return 'warning'
}
function statusLabel(fee: TaskFee): string {
  if (fee.status === 'approved') return '已核准'
  if (fee.status === 'rejected') return '已退件'
  if (fee.receipt_requested_at) return '補件'
  return '待審核'
}

function relativeTime(iso: string): string {
  const diff = (Date.now() - new Date(iso).getTime()) / 1000
  if (diff < 60) return '剛剛'
  if (diff < 3600) return `${Math.floor(diff / 60)} 分鐘前`
  if (diff < 86400) return `${Math.floor(diff / 3600)} 小時前`
  const days = Math.floor(diff / 86400)
  if (days < 30) return `${days} 天前`
  return new Date(iso).toISOString().slice(0, 10)
}

function avatarColor(id: number): string {
  const colors = ['primary', 'secondary', 'info', 'success', 'warning', 'error', 'deep-purple']
  return colors[id % colors.length]!
}

async function onApprove(fee: TaskFee) {
  busyId.value = fee.id
  busyAction.value = 'approve'
  try {
    await feeStore.approveTaskFee(fee)
    toast.success('已核准')
    await store.fetch()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? '核准失敗')
  } finally {
    busyId.value = null
    busyAction.value = null
  }
}

function onReject(fee: TaskFee) {
  pendingReject.value = fee
  rejectReason.value = ''
  rejectDialog.value = true
}

async function confirmReject() {
  const fee = pendingReject.value
  if (!fee) return
  busyId.value = fee.id
  busyAction.value = 'reject'
  rejectDialog.value = false
  try {
    await feeStore.rejectTaskFee(fee, rejectReason.value.trim())
    toast.success('已退件')
    await store.fetch()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? '退件失敗')
  } finally {
    busyId.value = null
    busyAction.value = null
    pendingReject.value = null
  }
}

function onRequestReceipt(fee: TaskFee) {
  // 從 row inline 也走 message dialog，留言可選
  pendingReceipt.value = fee
  receiptMessage.value = ''
  receiptDialog.value = true
}

async function confirmReceiptRequest() {
  const fee = pendingReceipt.value
  if (!fee) return
  busyId.value = fee.id
  busyAction.value = 'receipt'
  receiptDialog.value = false
  detailDialog.value = false
  try {
    await feeStore.requestReceipt(fee, receiptMessage.value.trim() || undefined)
    toast.success('已通知提交者補件')
    await store.fetch()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? '操作失敗')
  } finally {
    busyId.value = null
    busyAction.value = null
    pendingReceipt.value = null
  }
}

function exportReport() {
  toast.info('匯出功能即將推出')
}

onMounted(() => store.fetch())
</script>

<style scoped>
.pms-tnum {
  font-variant-numeric: tabular-nums;
}
.pms-status-chip {
  font-weight: 600;
  letter-spacing: 0.02em;
}
.pms-att-item {
  background-color: rgba(0, 0, 0, 0.03);
}
.pms-detail-section {
  background: rgba(0, 0, 0, 0.025);
  padding: 14px 16px;
  border-radius: 12px;
}
</style>
