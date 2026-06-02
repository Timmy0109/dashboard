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
          label="已駁回"
          :value="kpi?.rejected_count ?? 0"
          sub="本月內被駁回的費用"
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
      {{ kpi?.receipt_requested_count ?? 0 }} 筆已退回補件。逐筆核准、駁回或請成員補件。
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
          <div v-if="item.status === 'pending'" class="d-flex align-center gap-1">
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
              icon="mdi-close"
              size="x-small"
              variant="text"
              color="error"
              :loading="busyId === item.id && busyAction === 'reject'"
              @click="onReject(item)"
            />
          </div>
          <span v-else-if="item.receipt_requested_at" class="text-caption text-medium-emphasis">
            等待成員補件
          </span>
          <span v-else class="text-caption text-medium-emphasis">—</span>
        </template>
      </v-data-table>

      <div class="px-5 py-3 text-caption text-medium-emphasis">
        顯示 {{ items.length }} 筆
      </div>
    </v-card>

    <!-- Reject reason dialog -->
    <v-dialog v-model="rejectDialog" max-width="480">
      <v-card rounded="xl">
        <v-card-title class="text-body-1 font-weight-semibold">駁回費用</v-card-title>
        <v-card-text>
          <v-textarea
            v-model="rejectReason"
            label="駁回原因"
            rows="3"
            auto-grow
            variant="outlined"
            density="comfortable"
            :rules="[(v: string) => !!v?.trim() || '請輸入駁回原因']"
          />
        </v-card-text>
        <v-card-actions class="px-6 pb-4">
          <v-spacer />
          <v-btn variant="text" @click="rejectDialog = false">取消</v-btn>
          <v-btn color="error" :disabled="!rejectReason.trim()" @click="confirmReject">確認駁回</v-btn>
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
const busyAction = ref<'approve' | 'reject' | 'receipt' | null>(null)
const rejectDialog = ref(false)
const rejectReason = ref('')
const pendingReject = ref<TaskFee | null>(null)

const tabItems = computed(() => [
  { value: 'pending', label: `待處理 (${kpi.value?.pending_count ?? 0})` },
  { value: 'approved', label: `已核准 (${kpi.value?.approved_this_month ?? 0})` },
  { value: 'rejected', label: `已駁回 (${kpi.value?.rejected_count ?? 0})` },
  { value: 'all', label: `全部 (${kpi.value?.total_count ?? 0})` },
])

const headers = [
  { title: '申請人', key: 'submitter', sortable: false },
  { title: '金額', key: 'amount', sortable: false },
  { title: '所屬任務 / 專案', key: 'task', sortable: false },
  { title: '收據', key: 'receipt', sortable: false, align: 'center' as const },
  { title: '送出時間', key: 'created_at', sortable: false },
  { title: '狀態', key: 'status', sortable: false, align: 'center' as const },
  { title: '審核', key: 'actions', sortable: false, align: 'end' as const },
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
  if (fee.status === 'rejected') return '已駁回'
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
    toast.success('已駁回')
    await store.fetch()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? '駁回失敗')
  } finally {
    busyId.value = null
    busyAction.value = null
    pendingReject.value = null
  }
}

async function onRequestReceipt(fee: TaskFee) {
  busyId.value = fee.id
  busyAction.value = 'receipt'
  try {
    await feeStore.requestReceipt(fee)
    toast.success('已通知提交者補件')
    await store.fetch()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? '操作失敗')
  } finally {
    busyId.value = null
    busyAction.value = null
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
</style>
