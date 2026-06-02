<template>
  <v-card rounded="xl" class="pms-fees-panel">
    <!-- Header：標題 + 副標 + CTA -->
    <div class="px-5 py-4 d-flex align-center gap-3 border-b">
      <v-icon icon="mdi-cash-multiple" size="20" color="primary" />
      <div class="flex-1-1">
        <div class="d-flex align-center gap-1">
          <span class="text-body-1 font-weight-semibold">費用與行政費用總覽</span>
          <v-tooltip location="bottom" max-width="280">
            <template #activator="{ props: a }">
              <v-icon v-bind="a" icon="mdi-information-outline" size="14" class="text-medium-emphasis" />
            </template>
            <span v-if="isManagerView">總費用 = 已核定任務費用 + 行政費用。預算使用率以總費用 ÷ 專案總預算計算。</span>
            <span v-else>僅顯示你提交的費用：已核定金額已撥付、審核中尚未確認、退件中代表被駁回。</span>
          </v-tooltip>
        </div>
        <div class="text-caption text-medium-emphasis">
          集中查看專案預算、任務費用與行政支出
        </div>
      </div>
      <v-btn
        v-if="canManageAdminFees"
        color="primary"
        prepend-icon="mdi-plus"
        rounded="lg"
        size="default"
        elevation="1"
        @click="openCreate"
      >
        新增行政費用
      </v-btn>
    </div>

    <!-- Body -->
    <div class="pa-5">
      <!-- 上半：費用 summary（embedded 模式） -->
      <ProjectFeeSummary :project-id="projectId" embedded />

      <!-- 中段：任務費用明細（manager 看全部、member 看自己） -->
      <v-divider class="my-5" />

      <div class="d-flex align-center gap-2 mb-3">
        <v-icon icon="mdi-file-document-multiple-outline" size="16" color="primary" />
        <span class="text-body-2 font-weight-semibold">
          {{ isManagerView ? '任務費用明細' : '我提交的任務費用' }}
        </span>
        <v-chip v-if="taskFees.length > 0" size="x-small" variant="tonal" class="ml-1">
          {{ taskFees.length }} 筆
        </v-chip>
        <v-spacer />
        <span class="text-caption text-medium-emphasis">
          {{ isManagerView ? '審核任務費用請從任務內進入' : '由你的負責人審核' }}
        </span>
      </div>

      <v-skeleton-loader
        v-if="loadingTaskFees && taskFees.length === 0"
        type="list-item-two-line@2"
      />

      <EmptyState
        v-else-if="taskFees.length === 0"
        icon="mdi-file-document-outline"
        :title="isManagerView ? '尚無任務費用' : '你還沒提交過任務費用'"
        :sub="isManagerView ? '成員提交後將顯示在這' : '可從任務內的「費用」分頁提交'"
      />

      <v-list v-else density="compact" class="bg-transparent pa-0">
        <v-list-item
          v-for="(fee, idx) in visibleTaskFees"
          :key="fee.id"
          class="pms-task-fee-row rounded-lg mb-1"
          :class="{ 'mb-2': idx === visibleTaskFees.length - 1 }"
        >
          <v-list-item-title class="d-flex align-center gap-2 flex-wrap">
            <v-chip
              :color="statusColor(fee.status)"
              size="x-small"
              variant="flat"
              density="compact"
              class="pms-status-chip"
            >
              {{ statusLabel(fee.status) }}
            </v-chip>
            <span class="text-body-2 font-weight-medium flex-grow-1 text-truncate">
              {{ fee.task?.name ?? `任務 #${fee.task_id}` }}
            </span>
            <span class="text-body-2 font-weight-bold pms-tnum text-primary">
              NT${{ Number(fee.amount).toLocaleString() }}
            </span>
          </v-list-item-title>

          <v-list-item-subtitle class="d-flex align-center gap-2 text-caption mt-1">
            <span>{{ (fee.submitter?.name ?? '—') }}</span>
            <span>·</span>
            <span>{{ fee.created_at.slice(0, 10) }}</span>
            <v-chip
              v-if="(fee.attachments?.length ?? 0) > 0"
              size="x-small"
              variant="tonal"
              density="compact"
            >
              <v-icon start icon="mdi-paperclip" size="11" />
              {{ fee.attachments!.length }}
            </v-chip>
          </v-list-item-subtitle>
        </v-list-item>
      </v-list>

      <div v-if="taskFees.length > TASK_FEES_PREVIEW" class="mt-2 text-center">
        <v-btn
          variant="text"
          size="small"
          color="primary"
          append-icon="mdi-chevron-right"
          @click="showTaskFeesDialog = true"
        >
          查看全部 {{ taskFees.length }} 筆
        </v-btn>
      </div>

      <!-- 下半：行政費用明細（僅 manager/admin） -->
      <template v-if="canManageAdminFees">
        <v-divider class="my-5" />

        <div class="d-flex align-center gap-2 mb-3">
          <v-icon icon="mdi-format-list-bulleted" size="16" color="primary" />
          <span class="text-body-2 font-weight-semibold">行政費用明細</span>
          <v-chip v-if="fees.length > 0" size="x-small" variant="tonal" class="ml-1">
            {{ fees.length }} 筆 · NT${{ adminTotal.toLocaleString() }}
          </v-chip>
          <v-spacer />
          <span class="text-caption text-medium-emphasis">由專案負責人填寫，無需審核</span>
        </div>

        <v-skeleton-loader v-if="loading && fees.length === 0" type="list-item-three-line@2" />

        <EmptyState
          v-else-if="fees.length === 0"
          icon="mdi-cash-multiple"
          title="尚無行政費用"
          sub="點擊右上角「新增行政費用」開始記錄"
        />

        <v-list v-else density="comfortable" class="bg-transparent pa-0">
          <template v-for="(fee, idx) in visibleAdminFees" :key="fee.id">
            <v-list-item class="pms-fee-row rounded-lg mb-2" @click="toggleExpand(fee.id)">
              <template #prepend>
                <v-avatar v-if="fee.creator" color="primary" size="28" class="mr-2">
                  <span class="text-caption text-white font-weight-bold">
                    {{ fee.creator.name.charAt(0) }}
                  </span>
                </v-avatar>
              </template>

              <v-list-item-title class="d-flex align-center gap-3 flex-wrap">
                <span class="text-body-2 font-weight-semibold flex-grow-1">
                  {{ fee.note ? truncate(fee.note, 28) : '行政費用' }}
                </span>
                <span class="text-body-1 font-weight-bold pms-tnum text-primary">
                  {{ fmt(fee.amount) }}
                </span>
              </v-list-item-title>

              <v-list-item-subtitle class="d-flex align-center gap-2 text-caption mt-1">
                <span>{{ fee.incurred_on?.slice(0, 10) ?? fee.created_at.slice(0, 10) }}</span>
                <span>·</span>
                <span>{{ fee.creator?.name ?? '—' }}</span>
                <v-chip v-if="(fee.attachments?.length ?? 0) > 0" size="x-small" variant="tonal" density="compact">
                  <v-icon start icon="mdi-paperclip" size="12" />
                  {{ fee.attachments!.length }}
                </v-chip>
              </v-list-item-subtitle>

              <template #append>
                <div class="d-flex align-center" @click.stop>
                  <v-btn icon="mdi-pencil" size="x-small" variant="text" color="grey" @click="openEdit(fee)" />
                  <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" @click="handleDelete(fee)" />
                  <v-btn
                    :icon="expanded[fee.id] ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                    size="x-small" variant="text" color="grey"
                    @click="toggleExpand(fee.id)"
                  />
                </div>
              </template>
            </v-list-item>

            <v-expand-transition>
              <div v-if="expanded[fee.id]" class="px-4 pb-3 pms-fee-expand">
                <div v-if="fee.note" class="text-body-2 mb-3" style="white-space: pre-wrap">{{ fee.note }}</div>

                <div class="d-flex align-center justify-space-between mb-2">
                  <span class="text-caption text-medium-emphasis font-weight-semibold">
                    收據／單據（{{ fee.attachments?.length ?? 0 }}）
                  </span>
                  <v-btn size="x-small" variant="text" color="primary" prepend-icon="mdi-plus" @click="openEdit(fee)">
                    新增收據
                  </v-btn>
                </div>

                <v-list v-if="fee.attachments && fee.attachments.length > 0" density="compact" class="bg-transparent pa-0">
                  <v-list-item
                    v-for="att in fee.attachments"
                    :key="att.id"
                    class="px-2 rounded-lg mb-1 pms-att-item"
                  >
                    <template #prepend>
                      <v-icon icon="mdi-file-document-outline" size="16" />
                    </template>
                    <v-list-item-title class="text-body-2">{{ att.original_name }}</v-list-item-title>
                    <v-list-item-subtitle class="text-caption">{{ att.size_human }}</v-list-item-subtitle>
                    <template #append>
                      <v-btn :href="att.download_url" target="_blank"
                        icon="mdi-download" size="x-small" variant="text" color="primary" />
                    </template>
                  </v-list-item>
                </v-list>
                <div v-else class="text-caption text-medium-emphasis">尚無附件</div>
              </div>
            </v-expand-transition>

            <v-divider v-if="idx < visibleAdminFees.length - 1" class="my-1" />
          </template>
        </v-list>

        <div v-if="fees.length > ADMIN_FEES_PREVIEW" class="mt-2 text-center">
          <v-btn
            variant="text"
            size="small"
            color="primary"
            append-icon="mdi-chevron-right"
            @click="showAdminFeesDialog = true"
          >
            查看全部 {{ fees.length }} 筆
          </v-btn>
        </div>
      </template>
    </div>

    <!-- All task fees dialog -->
    <v-dialog v-model="showTaskFeesDialog" max-width="720" scrollable>
      <v-card rounded="xl">
        <v-card-title class="d-flex align-center gap-2 px-5 py-4 border-b">
          <v-icon icon="mdi-file-document-multiple-outline" color="primary" />
          <span class="text-body-1 font-weight-semibold">
            {{ isManagerView ? '任務費用明細' : '我提交的任務費用' }}
          </span>
          <v-chip size="x-small" variant="tonal" class="ml-1">{{ taskFees.length }} 筆</v-chip>
          <v-spacer />
          <v-btn icon="mdi-close" variant="text" size="small" @click="showTaskFeesDialog = false" />
        </v-card-title>
        <v-card-text class="pa-5">
          <v-list density="compact" class="bg-transparent pa-0">
            <v-list-item
              v-for="fee in taskFees"
              :key="fee.id"
              class="pms-task-fee-row rounded-lg mb-1"
            >
              <v-list-item-title class="d-flex align-center gap-2 flex-wrap">
                <v-chip :color="statusColor(fee.status)" size="x-small" variant="flat" density="compact" class="pms-status-chip">
                  {{ statusLabel(fee.status) }}
                </v-chip>
                <span class="text-body-2 font-weight-medium flex-grow-1 text-truncate">
                  {{ fee.task?.name ?? `任務 #${fee.task_id}` }}
                </span>
                <span class="text-body-2 font-weight-bold pms-tnum text-primary">
                  NT${{ Number(fee.amount).toLocaleString() }}
                </span>
              </v-list-item-title>
              <v-list-item-subtitle class="d-flex align-center gap-2 text-caption mt-1">
                <span>{{ fee.submitter?.name ?? '—' }}</span>
                <span>·</span>
                <span>{{ fee.created_at.slice(0, 10) }}</span>
                <v-chip v-if="(fee.attachments?.length ?? 0) > 0" size="x-small" variant="tonal" density="compact">
                  <v-icon start icon="mdi-paperclip" size="11" />
                  {{ fee.attachments!.length }}
                </v-chip>
              </v-list-item-subtitle>
            </v-list-item>
          </v-list>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- All admin fees dialog -->
    <v-dialog v-model="showAdminFeesDialog" max-width="720" scrollable>
      <v-card rounded="xl">
        <v-card-title class="d-flex align-center gap-2 px-5 py-4 border-b">
          <v-icon icon="mdi-format-list-bulleted" color="primary" />
          <span class="text-body-1 font-weight-semibold">行政費用明細</span>
          <v-chip size="x-small" variant="tonal" class="ml-1">
            {{ fees.length }} 筆 · NT${{ adminTotal.toLocaleString() }}
          </v-chip>
          <v-spacer />
          <v-btn icon="mdi-close" variant="text" size="small" @click="showAdminFeesDialog = false" />
        </v-card-title>
        <v-card-text class="pa-5">
          <v-list density="comfortable" class="bg-transparent pa-0">
            <template v-for="(fee, idx) in fees" :key="fee.id">
              <v-list-item class="pms-fee-row rounded-lg mb-2">
                <template #prepend>
                  <v-avatar v-if="fee.creator" color="primary" size="28" class="mr-2">
                    <span class="text-caption text-white font-weight-bold">{{ fee.creator.name.charAt(0) }}</span>
                  </v-avatar>
                </template>
                <v-list-item-title class="d-flex align-center gap-3 flex-wrap">
                  <span class="text-body-2 font-weight-semibold flex-grow-1">
                    {{ fee.note ? truncate(fee.note, 40) : '行政費用' }}
                  </span>
                  <span class="text-body-1 font-weight-bold pms-tnum text-primary">{{ fmt(fee.amount) }}</span>
                </v-list-item-title>
                <v-list-item-subtitle class="d-flex align-center gap-2 text-caption mt-1">
                  <span>{{ fee.incurred_on?.slice(0, 10) ?? fee.created_at.slice(0, 10) }}</span>
                  <span>·</span>
                  <span>{{ fee.creator?.name ?? '—' }}</span>
                  <v-chip v-if="(fee.attachments?.length ?? 0) > 0" size="x-small" variant="tonal" density="compact">
                    <v-icon start icon="mdi-paperclip" size="12" />
                    {{ fee.attachments!.length }}
                  </v-chip>
                </v-list-item-subtitle>
                <template #append>
                  <div class="d-flex align-center" @click.stop>
                    <v-btn icon="mdi-pencil" size="x-small" variant="text" color="grey" @click="openEdit(fee)" />
                    <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" @click="handleDelete(fee)" />
                  </div>
                </template>
              </v-list-item>
              <v-divider v-if="idx < fees.length - 1" class="my-1" />
            </template>
          </v-list>
        </v-card-text>
      </v-card>
    </v-dialog>

    <ProjectAdminFeeForm
      v-if="showForm"
      :project-id="projectId"
      :fee="editingFee"
      @close="closeForm"
      @saved="onSaved"
    />
  </v-card>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useFeeStore } from '@/stores/fee'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import EmptyState from '@/components/ui/EmptyState.vue'
import ProjectFeeSummary from './ProjectFeeSummary.vue'
import ProjectAdminFeeForm from './ProjectAdminFeeForm.vue'
import type { ProjectAdminFee, TaskFee, FeeStatus } from '@/types/fee'

const props = defineProps<{ projectId: number }>()
const feeStore = useFeeStore()
const auth = useAuthStore()
const toast = useToast()

const canManageAdminFees = computed(() => auth.isAdmin || auth.isManager)
const fees = computed<ProjectAdminFee[]>(() => feeStore.adminByProject[props.projectId] ?? [])
const loading = computed(() => feeStore.loading.admin)

const summary = computed(() => feeStore.summaryByProject[props.projectId])
const isManagerView = computed(() => summary.value?.scope === 'all')
const adminTotal = computed(() => fees.value.reduce((s, f) => s + Number(f.amount), 0))

const taskFees = computed<TaskFee[]>(() => feeStore.taskFeesByProject[props.projectId] ?? [])
const loadingTaskFees = computed(() => feeStore.loading.projectTask)

function statusColor(s: FeeStatus): string {
  return s === 'approved' ? 'success' : s === 'pending' ? 'warning' : 'error'
}
function statusLabel(s: FeeStatus): string {
  return s === 'approved' ? '已核定' : s === 'pending' ? '審核中' : '退件'
}

const expanded = reactive<Record<number, boolean>>({})
const showForm = ref(false)
const editingFee = ref<ProjectAdminFee | null>(null)

const TASK_FEES_PREVIEW = 3
const ADMIN_FEES_PREVIEW = 1
const showTaskFeesDialog = ref(false)
const showAdminFeesDialog = ref(false)
const visibleTaskFees = computed(() => taskFees.value.slice(0, TASK_FEES_PREVIEW))
const visibleAdminFees = computed(() => fees.value.slice(0, ADMIN_FEES_PREVIEW))

function fmt(amount: string | number) {
  return 'NT$' + Number(amount).toLocaleString()
}
function truncate(s: string, n = 60) {
  return s.length > n ? s.slice(0, n) + '…' : s
}
function toggleExpand(id: number) {
  expanded[id] = !expanded[id]
}
function openCreate() {
  editingFee.value = null
  showForm.value = true
}
function openEdit(fee: ProjectAdminFee) {
  editingFee.value = fee
  showForm.value = true
}
function closeForm() {
  showForm.value = false
  editingFee.value = null
}
function onSaved() {
  closeForm()
}
async function handleDelete(fee: ProjectAdminFee) {
  if (!confirm(`確定要刪除這筆行政費用（${fmt(fee.amount)}）？`)) return
  try {
    await feeStore.deleteAdminFee(fee)
    toast.success('已刪除行政費用')
  } catch {
    toast.error('刪除失敗，請重試')
  }
}

onMounted(() => {
  feeStore.fetchProjectTaskFees(props.projectId)
  if (canManageAdminFees.value) {
    feeStore.fetchAdminFees(props.projectId)
  }
})

// summary 被 invalidate 後 taskFeesByProject 也會被清掉 → 自動 refetch
watch(
  () => feeStore.taskFeesByProject[props.projectId],
  (val) => {
    if (val === undefined) feeStore.fetchProjectTaskFees(props.projectId)
  },
)
</script>

<style scoped>
.pms-fees-panel {
  height: 100%;
  display: flex;
  flex-direction: column;
}
.pms-fees-panel > .pa-5 {
  flex: 1 1 auto;
  overflow-y: auto;
}
.pms-tnum {
  font-variant-numeric: tabular-nums;
}
.pms-fee-row {
  background-color: rgba(0, 0, 0, 0.025);
  cursor: pointer;
}
.pms-fee-row:hover {
  background-color: rgba(0, 0, 0, 0.045);
}
.pms-fee-expand {
  background-color: rgba(0, 0, 0, 0.015);
  border-radius: 10px;
  margin-bottom: 8px;
}
.pms-att-item {
  background-color: rgba(0, 0, 0, 0.03);
}
.pms-task-fee-row {
  background-color: rgba(0, 0, 0, 0.02);
  border: 1px solid rgba(0, 0, 0, 0.04);
}
.pms-task-fee-row:hover {
  background-color: rgba(0, 0, 0, 0.04);
}
.pms-status-chip {
  font-weight: 600;
  letter-spacing: 0.02em;
}
</style>
