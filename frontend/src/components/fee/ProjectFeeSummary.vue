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

      <div v-else-if="summary">
        <div class="text-caption text-medium-emphasis mb-1">總費用</div>
        <div class="text-h4 font-weight-bold pms-tnum text-primary mb-4">
          {{ fmt(summary.total) }}
        </div>

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
          <v-col v-if="summary.admin_fees !== undefined" cols="12" sm="4">
            <div class="pms-fee-cell">
              <div class="text-caption text-medium-emphasis">行政費用</div>
              <div class="text-body-1 font-weight-semibold pms-tnum">
                {{ fmt(summary.admin_fees) }}
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

// Refetch when invalidated (store deletes the key after mutations)
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
