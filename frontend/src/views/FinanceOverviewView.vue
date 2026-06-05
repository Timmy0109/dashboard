<template>
  <div class="pa-6">
    <div class="d-flex align-center mb-6">
      <div>
        <h1 class="text-h5 font-weight-bold">財務總覽</h1>
        <div class="text-body-2 text-medium-emphasis">公司專案預算與支出（唯讀）</div>
      </div>
      <v-spacer />
      <v-btn
        variant="text"
        color="primary"
        prepend-icon="mdi-refresh"
        :loading="loading"
        @click="fetchOverview"
      >重新整理</v-btn>
    </div>

    <!-- KPI -->
    <v-row dense class="mb-5">
      <v-col cols="12" sm="6" md="3">
        <KPICard icon="mdi-wallet-outline" color="primary" label="總預算" :value="fmt(totals.budget)" />
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <KPICard icon="mdi-cash-minus" color="error" label="已支出（核發+行政）" :value="fmt(totals.spent)" />
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <KPICard icon="mdi-progress-clock" color="warning" label="進行中費用" :value="fmt(totals.task_in_flight)" />
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <KPICard icon="mdi-piggy-bank-outline" color="success" label="預算餘額" :value="fmt(totals.remaining)" />
      </v-col>
    </v-row>

    <!-- 專案明細 -->
    <v-card rounded="xl">
      <v-card-title class="text-body-1 font-weight-semibold border-b py-4">
        <v-icon icon="mdi-folder-table-outline" size="18" color="primary" class="mr-2" />
        各專案收支
      </v-card-title>

      <v-skeleton-loader v-if="loading && rows.length === 0" type="table-tbody" />

      <EmptyState
        v-else-if="rows.length === 0"
        icon="mdi-folder-off-outline"
        title="尚無專案"
        sub="公司內還沒有任何專案"
      />

      <v-data-table
        v-else
        :headers="headers"
        :items="rows"
        :items-per-page="25"
        hover
        class="pms-finance-table"
        @click:row="(_: unknown, { item }: { item: ProjectFinance }) => openProject(item)"
      >
        <template #item.total_budget="{ item }">
          <span class="pms-tnum">{{ fmt(item.total_budget) }}</span>
        </template>
        <template #item.task_disbursed="{ item }">
          <span class="pms-tnum">{{ fmt(item.task_disbursed) }}</span>
        </template>
        <template #item.admin_total="{ item }">
          <span class="pms-tnum">{{ fmt(item.admin_total) }}</span>
        </template>
        <template #item.task_in_flight="{ item }">
          <span class="pms-tnum text-warning">{{ fmt(item.task_in_flight) }}</span>
        </template>
        <template #item.remaining="{ item }">
          <v-chip
            size="small"
            variant="tonal"
            :color="item.remaining < 0 ? 'error' : 'success'"
            class="pms-tnum"
          >{{ fmt(item.remaining) }}</v-chip>
        </template>
      </v-data-table>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/axios'
import { useToast } from '@/composables/useToast'
import KPICard from '@/components/ui/KPICard.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

interface ProjectFinance {
  id: number
  name: string
  total_budget: number
  task_disbursed: number
  task_in_flight: number
  admin_total: number
  spent: number
  remaining: number
}

interface Totals {
  budget: number
  spent: number
  task_in_flight: number
  remaining: number
}

const router = useRouter()
const toast = useToast()

const loading = ref(false)
const rows = ref<ProjectFinance[]>([])
const totals = ref<Totals>({ budget: 0, spent: 0, task_in_flight: 0, remaining: 0 })

const headers = [
  { title: '專案', key: 'name' },
  { title: '總預算', key: 'total_budget', align: 'end' as const },
  { title: '已核發費用', key: 'task_disbursed', align: 'end' as const },
  { title: '行政費用', key: 'admin_total', align: 'end' as const },
  { title: '進行中', key: 'task_in_flight', align: 'end' as const },
  { title: '餘額', key: 'remaining', align: 'end' as const },
]

function fmt(amount: number) {
  return 'NT$' + Number(amount ?? 0).toLocaleString()
}

function openProject(item: ProjectFinance) {
  router.push(`/projects/${item.id}`)
}

async function fetchOverview() {
  loading.value = true
  try {
    const { data } = await api.get('/finance/overview')
    rows.value = data.projects
    totals.value = data.totals
  } catch {
    toast.error('載入財務總覽失敗，請重試')
  } finally {
    loading.value = false
  }
}

onMounted(fetchOverview)
</script>

<style scoped>
.pms-tnum {
  font-variant-numeric: tabular-nums;
}
.pms-finance-table :deep(tbody tr) {
  cursor: pointer;
}
</style>
