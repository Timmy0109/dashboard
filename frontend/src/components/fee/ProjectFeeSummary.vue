<template>
  <v-card rounded="xl" class="mb-5 pms-fee-summary">
    <v-card-title class="px-5 py-4 d-flex align-center gap-2 border-b">
      <v-icon icon="mdi-cash-multiple" size="18" color="primary" />
      <span class="text-body-1 font-weight-semibold">費用與行政費用總覽</span>
      <v-tooltip location="bottom" max-width="280">
        <template #activator="{ props: a }">
          <v-icon v-bind="a" icon="mdi-information-outline" size="14" class="text-medium-emphasis" />
        </template>
        <span v-if="isManagerView">
          總費用 = 已核定任務費用 + 行政費用。預算使用率以總費用 ÷ 專案總預算計算。
        </span>
        <span v-else>
          僅顯示你提交的費用：已核定金額已撥付、審核中尚未確認、退件中代表被駁回。
        </span>
      </v-tooltip>
    </v-card-title>

    <v-card-text class="pa-5">
      <v-skeleton-loader v-if="loading" type="article" />

      <!-- Manager / Admin -->
      <template v-else-if="summary && summary.scope === 'all'">
        <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-4">
          <div>
            <div class="text-caption text-medium-emphasis mb-1">專案總預算</div>
            <div class="text-h4 font-weight-bold pms-tnum text-primary">
              {{ fmt(summary.total_budget) }}
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
              總費用 {{ fmt(summary.total) }}
            </div>
          </div>

          <div class="d-flex align-center gap-3">
            <v-progress-circular
              :model-value="Math.min(usagePct, 100)"
              :size="76"
              :width="9"
              :color="usagePct > 100 ? 'error' : usagePct >= 80 ? 'warning' : 'primary'"
              class="pms-gauge"
            >
              <span class="text-body-2 font-weight-bold pms-tnum">{{ usagePct }}%</span>
            </v-progress-circular>
            <div>
              <div class="text-caption text-medium-emphasis">預算使用率</div>
              <div class="text-caption">
                已使用 <span class="font-weight-semibold pms-tnum">{{ fmt(summary.total) }}</span>
              </div>
              <div class="text-caption" :class="summary.over_budget ? 'text-error' : ''">
                {{ summary.over_budget ? '超出' : '剩餘' }}
                <span class="font-weight-semibold pms-tnum">{{ fmt(Math.abs(summary.remaining)) }}</span>
              </div>
            </div>
          </div>
        </div>

        <v-alert
          v-if="summary.over_budget"
          type="error"
          variant="tonal"
          density="compact"
          class="mb-3"
          icon="mdi-alert"
        >
          已用費用已超出總預算
        </v-alert>

        <v-row dense>
          <v-col cols="6" md="3">
            <FeeCell
              tone="success"
              icon="mdi-check-circle"
              label="已核定任務費用"
              :amount="summary.task_fees_approved"
              :ratio="pctOf(summary.task_fees_approved)"
            />
          </v-col>
          <v-col cols="6" md="3">
            <FeeCell
              tone="warning"
              icon="mdi-clock-outline"
              label="待審任務費用"
              :amount="summary.task_fees_pending"
              :ratio="pctOf(summary.task_fees_pending)"
            />
          </v-col>
          <v-col cols="6" md="3">
            <FeeCell
              tone="info"
              icon="mdi-briefcase-outline"
              label="行政費用"
              :amount="summary.admin_fees"
              :ratio="pctOf(summary.admin_fees)"
            />
          </v-col>
          <v-col cols="6" md="3">
            <FeeCell
              :tone="summary.over_budget ? 'error' : 'neutral'"
              icon="mdi-wallet-outline"
              :label="summary.over_budget ? '超出預算' : '剩餘預算'"
              :amount="Math.abs(summary.remaining)"
              :ratio="pctOf(Math.abs(summary.remaining))"
            />
          </v-col>
        </v-row>
      </template>

      <!-- Member -->
      <template v-else-if="summary && summary.scope === 'self'">
        <div class="text-caption text-medium-emphasis mb-3">我提交的費用</div>
        <v-row dense>
          <v-col cols="6" md="3">
            <FeeCell
              tone="success"
              icon="mdi-check-circle"
              label="已核定"
              :amount="summary.own_approved"
            />
          </v-col>
          <v-col cols="6" md="3">
            <FeeCell
              tone="warning"
              icon="mdi-clock-outline"
              label="審核中"
              :amount="summary.own_pending"
            />
          </v-col>
          <v-col cols="6" md="3">
            <FeeCell
              tone="error"
              icon="mdi-close-circle"
              label="退件中"
              :amount="summary.own_rejected"
            />
          </v-col>
          <v-col cols="6" md="3">
            <FeeCell
              tone="neutral"
              icon="mdi-sigma"
              label="目前支出總額"
              :amount="summary.own_total"
            />
          </v-col>
        </v-row>
      </template>

      <div v-else class="text-body-2 text-medium-emphasis">尚無費用資料</div>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { computed, onMounted, watch, defineComponent, h, resolveComponent } from 'vue'
import { useFeeStore } from '@/stores/fee'

const props = defineProps<{ projectId: number }>()
const feeStore = useFeeStore()

const summary = computed(() => feeStore.summaryByProject[props.projectId])
const loading = computed(() => feeStore.loading.summary && !summary.value)
const isManagerView = computed(() => summary.value?.scope === 'all')

const usagePct = computed(() => {
  const s = summary.value
  if (!s || s.scope !== 'all' || !s.total_budget) return 0
  return Math.round((s.total / s.total_budget) * 100)
})

function pctOf(n: number) {
  const s = summary.value
  if (!s || s.scope !== 'all' || !s.total_budget) return 0
  return Math.round((n / s.total_budget) * 100)
}

function fmt(n: number | undefined | null) {
  if (n === undefined || n === null) return 'NT$0'
  return 'NT$' + Number(n).toLocaleString()
}

// inline cell to avoid splitting into another file
const FeeCell = defineComponent({
  props: {
    tone: { type: String, default: 'neutral' },
    icon: { type: String, required: true },
    label: { type: String, required: true },
    amount: { type: Number, required: true },
    ratio: { type: Number, default: null },
  },
  setup(p) {
    const VIcon = resolveComponent('VIcon')
    const toneClass = computed(() => `pms-fee-cell pms-tone-${p.tone}`)
    const amountClass = computed(() => `text-h6 font-weight-bold pms-tnum text-${
      p.tone === 'neutral' ? 'primary' : p.tone
    }`)
    return () => h('div', { class: toneClass.value }, [
      h('div', { class: 'd-flex align-center justify-space-between mb-1' }, [
        h('span', { class: 'text-caption text-medium-emphasis' }, p.label),
        h(VIcon as never, { icon: p.icon, size: 14, class: 'text-medium-emphasis' }),
      ]),
      h('div', { class: amountClass.value }, fmt(p.amount)),
      p.ratio !== null
        ? h('div', { class: 'text-caption text-medium-emphasis mt-1' }, `占預算 ${p.ratio}%`)
        : null,
    ])
  },
})

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
  padding: 12px 14px;
  border-radius: 12px;
  background-color: rgba(0, 0, 0, 0.025);
  border: 1px solid transparent;
  height: 100%;
}
.pms-tone-success {
  background-color: rgba(var(--v-theme-success), 0.08);
  border-color: rgba(var(--v-theme-success), 0.18);
}
.pms-tone-warning {
  background-color: rgba(var(--v-theme-warning), 0.08);
  border-color: rgba(var(--v-theme-warning), 0.18);
}
.pms-tone-error {
  background-color: rgba(var(--v-theme-error), 0.08);
  border-color: rgba(var(--v-theme-error), 0.18);
}
.pms-tone-info {
  background-color: rgba(var(--v-theme-info), 0.08);
  border-color: rgba(var(--v-theme-info), 0.18);
}
.pms-tone-neutral {
  background-color: rgba(var(--v-theme-primary), 0.05);
  border-color: rgba(var(--v-theme-primary), 0.12);
}
.pms-gauge :deep(.v-progress-circular__overlay) {
  transition: stroke-dashoffset 0.4s ease;
}
</style>
