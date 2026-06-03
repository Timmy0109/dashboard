<template>
  <v-card rounded="xl" class="mb-5">
    <v-card-title class="text-body-1 font-weight-semibold border-b">
      <div class="d-flex align-center gap-4 py-4 w-100">
        <v-icon icon="mdi-cash-multiple" size="18" color="primary" />
        行政費用
        <v-spacer />
        <v-btn
          v-if="canManageAdminFees"
          color="primary"
          prepend-icon="mdi-plus"
          rounded="lg"
          size="small"
          @click="openCreate"
        >
          新增行政費用
        </v-btn>
      </div>
    </v-card-title>

    <v-card-text class="pa-5">
      <EmptyState
        v-if="!canManageAdminFees"
        icon="mdi-lock-outline"
        title="僅管理員可檢視行政費用"
        sub="此區域對一般成員隱藏"
      />

      <template v-else>
        <v-skeleton-loader v-if="loading && fees.length === 0" type="list-item-three-line@3" />

        <EmptyState
          v-else-if="fees.length === 0"
          icon="mdi-cash-multiple"
          title="尚無行政費用"
          sub="點擊上方按鈕新增第一筆"
        />

        <v-list v-else density="comfortable" class="bg-transparent pa-0">
          <template v-for="(fee, idx) in fees" :key="fee.id">
            <v-list-item class="pms-fee-row rounded-lg mb-2" @click="toggleExpand(fee.id)">
              <template #prepend>
                <v-avatar v-if="fee.creator" color="primary" size="32" class="mr-2">
                  <span class="text-caption text-white font-weight-bold">
                    {{ fee.creator.name.charAt(0) }}
                  </span>
                </v-avatar>
              </template>

              <v-list-item-title class="d-flex align-center gap-3 flex-wrap">
                <span class="text-body-1 font-weight-semibold pms-tnum">
                  {{ fmt(fee.amount) }}
                </span>
                <span class="text-caption text-medium-emphasis">
                  {{ fee.incurred_on?.slice(0, 10) ?? fee.created_at.slice(0, 10) }}
                </span>
                <v-chip v-if="(fee.attachments?.length ?? 0) > 0" size="x-small" variant="tonal">
                  <v-icon start icon="mdi-paperclip" size="12" />
                  {{ fee.attachments!.length }}
                </v-chip>
              </v-list-item-title>

              <v-list-item-subtitle class="text-body-2 text-medium-emphasis">
                <span v-if="fee.creator">{{ fee.creator.name }}</span>
                <span v-if="fee.note"> · {{ truncate(fee.note) }}</span>
              </v-list-item-subtitle>

              <template #append>
                <div class="d-flex align-center gap-1" @click.stop>
                  <v-btn
                    icon="mdi-pencil"
                    size="small"
                    variant="text"
                    color="grey"
                    @click="openEdit(fee)"
                  />
                  <v-btn
                    icon="mdi-delete"
                    size="small"
                    variant="text"
                    color="error"
                    @click="handleDelete(fee)"
                  />
                  <v-btn
                    :icon="expanded[fee.id] ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                    size="small"
                    variant="text"
                    color="grey"
                    @click="toggleExpand(fee.id)"
                  />
                </div>
              </template>
            </v-list-item>

            <v-expand-transition>
              <div v-if="expanded[fee.id]" class="px-4 pb-3 pms-fee-expand">
                <div v-if="fee.note" class="text-body-2 mb-3" style="white-space: pre-wrap">
                  {{ fee.note }}
                </div>

                <div class="d-flex align-center justify-space-between mb-2">
                  <div class="text-caption text-medium-emphasis font-weight-semibold">
                    收據／單據（{{ fee.attachments?.length ?? 0 }}）
                  </div>
                  <v-btn
                    size="x-small"
                    variant="text"
                    color="primary"
                    prepend-icon="mdi-plus"
                    @click="openEdit(fee)"
                  >
                    新增收據
                  </v-btn>
                </div>

                <div v-if="fee.attachments && fee.attachments.length > 0">
                  <v-list density="compact" class="bg-transparent pa-0">
                    <v-list-item
                      v-for="att in fee.attachments"
                      :key="att.id"
                      class="px-2 rounded-lg mb-1 pms-att-item"
                    >
                      <template #prepend>
                        <v-icon icon="mdi-file-document-outline" size="18" />
                      </template>
                      <v-list-item-title class="text-body-2">
                        {{ att.original_name }}
                      </v-list-item-title>
                      <v-list-item-subtitle class="text-caption">
                        {{ att.size_human }}
                      </v-list-item-subtitle>
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
                </div>
                <div v-else class="text-caption text-medium-emphasis">尚無附件</div>
              </div>
            </v-expand-transition>

            <v-divider v-if="idx < fees.length - 1" class="my-1" />
          </template>
        </v-list>
      </template>
    </v-card-text>

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
import { computed, onMounted, reactive, ref } from 'vue'
import { useFeeStore } from '@/stores/fee'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import EmptyState from '@/components/ui/EmptyState.vue'
import ProjectAdminFeeForm from './ProjectAdminFeeForm.vue'
import type { ProjectAdminFee } from '@/types/fee'

const props = defineProps<{ projectId: number }>()
const feeStore = useFeeStore()
const auth = useAuthStore()
const toast = useToast()

const canManageAdminFees = computed(() => auth.canManage && !auth.isAdmin)
const fees = computed<ProjectAdminFee[]>(() => feeStore.adminByProject[props.projectId] ?? [])
const loading = computed(() => feeStore.loading.admin)

const expanded = reactive<Record<number, boolean>>({})
const showForm = ref(false)
const editingFee = ref<ProjectAdminFee | null>(null)

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
  if (canManageAdminFees.value) {
    feeStore.fetchAdminFees(props.projectId)
  }
})
</script>

<style scoped>
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
</style>
