<script setup lang="ts">
// ProjectsChartStrip — 專案列表頂部 3 區圖表 strip:
//   1) 2x2 KPI grid（總數 / 已完成 / 進行中 / 逾期）
//   2) 狀態 Donut + Legend
//   3) 負責人 HBar
import { computed } from 'vue'
import type { ProjectListItem } from '@/stores/project'
import KPICard from '@/components/ui/KPICard.vue'
import DonutChart from '@/components/charts/DonutChart.vue'
import HBarChart from '@/components/charts/HBarChart.vue'

const props = defineProps<{ projects: ProjectListItem[] }>()

// 「已完成」= manager / admin 手動勾選結案旗標
function isOverdue(p: ProjectListItem) {
  if (!p.due_date || p.is_completed) return false
  return new Date(p.due_date) < new Date()
}

const total = computed(() => props.projects.length)
const completed = computed(() => props.projects.filter(p => p.is_completed).length)
const inProgress = computed(() => props.projects.filter(p => !p.is_completed && p.progress_percent > 0).length)
const overdue = computed(() => props.projects.filter(isOverdue).length)

interface DonutDatum { label: string; value: number; color: string }

const statusData = computed<DonutDatum[]>(() => {
  const map = new Map<string, DonutDatum>()
  for (const p of props.projects) {
    if (!p.status) continue
    const key = p.status.name
    if (!map.has(key)) {
      map.set(key, { label: p.status.name, value: 0, color: p.status.color })
    }
    map.get(key)!.value++
  }
  return Array.from(map.values())
})

const ownerData = computed(() => {
  const map = new Map<string, number>()
  for (const p of props.projects) {
    if (!p.owner) continue
    map.set(p.owner.name, (map.get(p.owner.name) ?? 0) + 1)
  }
  return Array.from(map.entries())
    .map(([label, value]) => ({ label, value }))
    .sort((a, b) => b.value - a.value)
    .slice(0, 6)
})
</script>

<template>
  <v-row>
    <!-- KPI 2x2 grid -->
    <v-col cols="12" md="4">
      <v-row dense>
        <v-col cols="6">
          <KPICard
            label="總專案數"
            :value="total"
            icon="mdi-folder-multiple-outline"
            icon-color="primary"
            accent="primary"
          />
        </v-col>
        <v-col cols="6">
          <KPICard
            label="已完成"
            :value="completed"
            icon="mdi-check-circle-outline"
            icon-color="success"
            accent="success"
          />
        </v-col>
        <v-col cols="6">
          <KPICard
            label="進行中"
            :value="inProgress"
            icon="mdi-progress-clock"
            icon-color="info"
            accent="info"
          />
        </v-col>
        <v-col cols="6">
          <KPICard
            label="逾期"
            :value="overdue"
            icon="mdi-alert-circle-outline"
            icon-color="error"
            accent="error"
          />
        </v-col>
      </v-row>
    </v-col>

    <!-- 狀態 Donut -->
    <v-col cols="12" md="4">
      <v-card rounded="xl" height="100%">
        <v-card-text class="pa-5">
          <div class="text-body-2 font-weight-semibold mb-3">狀態分佈</div>
          <div class="d-flex align-center gap-4 flex-wrap">
            <DonutChart
              :data="statusData"
              :size="140"
              :stroke="18"
              center-sub="總專案"
            />
            <div class="flex-grow-1" style="min-width:120px">
              <div
                v-for="s in statusData"
                :key="s.label"
                class="d-flex align-center justify-space-between py-1"
              >
                <div class="d-flex align-center gap-2">
                  <span
                    class="pms-dot"
                    :style="{ backgroundColor: s.color }"
                  />
                  <span class="text-caption">{{ s.label }}</span>
                </div>
                <span class="text-caption font-weight-medium pms-tnum">{{ s.value }}</span>
              </div>
            </div>
          </div>
        </v-card-text>
      </v-card>
    </v-col>

    <!-- 負責人 HBar -->
    <v-col cols="12" md="4">
      <v-card rounded="xl" height="100%">
        <v-card-text class="pa-5">
          <div class="text-body-2 font-weight-semibold mb-3">負責人專案數</div>
          <HBarChart :data="ownerData" />
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>
</template>

<style scoped>
.pms-dot {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 999px;
}
.pms-tnum {
  font-variant-numeric: tabular-nums;
}
</style>
