<script setup lang="ts">
// CompanyCard — admin step-1 grid item. Title + status dot + 3 mini-KPIs
// (projects / in-progress / managers) + avg-progress bar + chevron.
import type { Company } from '@/views/ProjectsView.vue'

defineProps<{ company: Company }>()
defineEmits<{ select: [c: Company] }>()
</script>

<template>
  <v-card
    rounded="xl"
    hover
    ripple
    class="pms-company-card"
    @click="$emit('select', company)"
  >
    <v-card-text class="pa-5">
      <!-- Title row -->
      <div class="d-flex align-start justify-space-between gap-2 mb-2">
        <div style="min-width:0" class="flex-grow-1">
          <div class="text-body-1 font-weight-semibold text-truncate" :title="company.name">
            {{ company.name }}
          </div>
          <div class="d-flex align-center gap-2 mt-1">
            <span
              class="pms-company-card__dot"
              :class="company.status === 'active' ? 'pms-company-card__dot--active' : 'pms-company-card__dot--suspended'"
            />
            <span class="text-caption text-medium-emphasis">
              {{ company.status === 'active' ? '運作中' : '已停用' }}
            </span>
          </div>
        </div>
        <v-icon icon="mdi-chevron-right" color="grey-lighten-1" />
      </div>

      <!-- Mini KPI row -->
      <div class="pms-company-card__kpis mt-3">
        <div class="pms-company-card__kpi">
          <div class="text-caption text-medium-emphasis">專案</div>
          <div class="text-h6 font-weight-bold pms-tnum">
            {{ company.projects_count ?? 0 }}
          </div>
        </div>
        <div class="pms-company-card__kpi">
          <div class="text-caption text-medium-emphasis">進行中</div>
          <div class="text-h6 font-weight-bold text-info pms-tnum">
            {{ company.projects_in_progress_count ?? 0 }}
          </div>
        </div>
        <div class="pms-company-card__kpi">
          <div class="text-caption text-medium-emphasis">經理</div>
          <div class="text-h6 font-weight-bold text-primary pms-tnum">
            {{ company.managers_count ?? 0 }}
          </div>
        </div>
      </div>

      <!-- Avg progress -->
      <div class="d-flex justify-space-between mt-4 mb-1">
        <span class="text-caption text-medium-emphasis">平均進度</span>
        <span
          class="text-caption font-weight-bold pms-tnum"
          :class="(company.avg_progress_percent ?? 0) >= 100 ? 'text-success' : 'text-primary'"
        >
          {{ company.avg_progress_percent ?? 0 }}%
        </span>
      </div>
      <v-progress-linear
        :model-value="company.avg_progress_percent ?? 0"
        :color="(company.avg_progress_percent ?? 0) >= 100 ? 'success' : 'primary'"
        bg-color="grey-lighten-3"
        rounded
        height="6"
      />
    </v-card-text>
  </v-card>
</template>

<style scoped>
.pms-company-card {
  cursor: pointer;
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.pms-company-card:hover {
  transform: translateY(-1px);
}

.pms-company-card__dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
  flex-shrink: 0;
}
.pms-company-card__dot--active {
  background-color: rgb(var(--v-theme-success));
}
.pms-company-card__dot--suspended {
  background-color: rgb(var(--v-theme-error));
}

.pms-company-card__kpis {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 12px;
  padding: 12px 14px;
  background-color: rgba(var(--v-theme-on-surface), 0.03);
  border-radius: 10px;
}
.pms-company-card__kpi {
  text-align: left;
}

.pms-tnum {
  font-variant-numeric: tabular-nums;
}
</style>
