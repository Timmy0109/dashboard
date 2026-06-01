<template>
  <v-card rounded="xl" class="mb-5">
    <v-card-title class="text-body-1 font-weight-semibold border-b">
      <div class="d-flex align-center gap-4 py-4 w-100">
        <v-icon icon="mdi-cash" size="18" color="primary" />
        費用總覽
      </div>
    </v-card-title>

    <v-card-text class="pa-5">
      <v-skeleton-loader v-if="loading" type="article" />

      <!-- Manager / Admin: 完整視圖 + 預算 -->
      <div v-else-if="summary && summary.scope === 'all'">
        <v-row dense class="mb-4">
          <v-col cols="12" sm="4">
            <div class="text-caption text-medium-emphasis mb-1">總預算</div>
            <div class="text-h5 font-weight-bold pms-tnum">
              {{ fmt(summary.total_budget) }}
            </div>
          </v-col>
          <v-col cols="12" sm="4">
            <div class="text-caption text-medium-emphasis mb-1">已用費用</div>
            <div
              class="text-h5 font-weight-bold pms-tnum"
              :class="summary.over_budget ? 'text-error' : 'text-primary'"
            >
              {{ fmt(summary.total) }}
            </div>
          </v-col>
          <v-col cols="12" sm="4">
            <div class="text-caption text-medium-emphasis mb-1">
              {{ summary.over_budget ? '超出預算' : '預算剩餘' }}
            </div>
            <div
              class="text-h5 font-weight-bold pms-tnum"
              :class="summary.over_budget ? 'text-error' : 'text-success'"
            >
              {{ fmt(Math.abs(summary.remaining)) }}
            </div>
          </v-col>
        </v-row>

        <v-alert
          v-if="summary.over_budget"
          type="error"
          variant="tonal"
          density="compact"
          class="mb-4"
          icon="mdi-alert"
        >
          已用費用已超出總預算，請檢視後續支出
        </v-alert>

        <v-divider class="mb-4" />

        <v-row dense>
          <v-col cols="12" sm="4">
            <div class="pms-fee-cell">
              <div class="text-caption text-medium-emphasis">已核定任務費用</div>
              <div class="text-body-1 font-weight-semibold pms-tnum text-success">
                {{ fmt(summary.task_fees_approved) }}
              </div>
            </div>
          </v-col>
          <v-col cols="12" sm="4">
            <div class="pms-fee-cell">
              <div class="text-caption text-medium-emphasis">待審任務費用</div>
              <div class="text-body-1 font-weight-semibold pms-tnum text-warning">
                {{ fmt(summary.task_fees_pending) }}
              </div>
            </div>
          </v-col>
          <v-col cols="12" sm="4">
            <div class="pms-fee-cell">
              <div class="text-caption text-medium-emphasis">行政費用</div>
              <div class="text-body-1 font-weight-semibold pms-tnum">
                {{ fmt(summary.admin_fees) }}
              </div>
            </div>
          </v-col>
        </v-row>
      </div>

      <!-- Member: 只看自己的費用 -->
      <div v-else-if="summary && summary.scope === 'self'">
        <div class="text-caption text-medium-emphasis mb-1">我提交的費用</div>
        <v-row dense>
          <v-col cols="12" sm="6">
            <div class="pms-fee-cell">
              <div class="text-caption text-medium-emphasis">已核定</div>
              <div class="text-h6 font-weight-bold pms-tnum text-success">
                {{ fmt(summary.own_approved) }}
              </div>
            </div>
          </v-col>
          <v-col cols="12" sm="6">
            <div class="pms-fee-cell">
              <div class="text-caption text-medium-emphasis">審核中</div>
              <div class="text-h6 font-weight-bold pms-tnum text-warning">
                {{ fmt(summary.own_pending) }}
              </div>
            </div>
          </v-col>
        </v-row>
      </div>

      <div v-else class="text-body-2 text-medium-emphasis">尚無費用資料</div>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useFeeStore } from '@/stores/fee'

const props = defineProps<{ projectId: number }>()
const feeStore = useFeeStore()

const summary = computed(() => feeStore.summaryByProject[props.projectId])
const loading = computed(() => feeStore.loading.summary && !summary.value)

function fmt(n: number | undefined | null) {
  if (n === undefined || n === null) return 'NT$0'
  return 'NT$' + Number(n).toLocaleString()
}

onMounted(() => {
  if (!summary.value) feeStore.fetchSummary(props.projectId)
})

watch(
  () => feeStore.summaryByProject[props.projectId],
  (val) => {
    if (val === undefined) feeStore.fetchSummary(props.projectId)
  },
)
</script>

<style scoped>
.pms-tnum {
  font-variant-numeric: tabular-nums;
}
.pms-fee-cell {
  padding: 10px 12px;
  background-color: rgba(0, 0, 0, 0.025);
  border-radius: 10px;
}
</style>
