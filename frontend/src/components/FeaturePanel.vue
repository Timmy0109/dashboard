<script setup lang="ts">
// FeaturePanel — 顯示目前公司啟用的功能模組
// Admin (帶 companyId)：可切換 toggle
// Member：只讀 chip 顯示
import { ref, computed, onMounted, watch } from 'vue'
import { useFeatureStore } from '@/stores/feature'
import { useAuthStore } from '@/stores/auth'
import { useCompanyStore, type CompanyFeature } from '@/stores/company'
import { useToast } from '@/composables/useToast'
import EmptyState from '@/components/ui/EmptyState.vue'

const props = withDefaults(
  defineProps<{
    companyId?: number | null
    title?: string
  }>(),
  {
    companyId: null,
    title: '功能模組',
  },
)

const feature = useFeatureStore()
const auth = useAuthStore()
const companyStore = useCompanyStore()
const toast = useToast()

const adminFeatures = ref<CompanyFeature[]>([])
const loading = ref(false)
const togglingKey = ref<string | null>(null)

// Admin 模式：有 companyId 且使用者為 admin
const isAdminMode = computed(() => auth.isAdmin && props.companyId != null)

const memberFeatures = computed(() => feature.features)

const categoryLabel: Record<string, string> = {
  member:  '成員管理',
  project: '專案管理',
  report:  '報表分析',
  system:  '系統設定',
}

const grouped = computed(() => {
  const map = new Map<string, CompanyFeature[]>()
  for (const f of adminFeatures.value) {
    const arr = map.get(f.category) ?? []
    arr.push(f)
    map.set(f.category, arr)
  }
  return Array.from(map.entries()).map(([cat, items]) => ({
    category: cat,
    label: categoryLabel[cat] ?? cat,
    items,
  }))
})

async function loadAdminFeatures() {
  if (!isAdminMode.value || props.companyId == null) return
  loading.value = true
  try {
    adminFeatures.value = await companyStore.fetchFeatures(props.companyId)
  } catch {
    toast.error('載入功能列表失敗')
  } finally {
    loading.value = false
  }
}

async function onToggle(item: CompanyFeature, enabled: boolean) {
  if (props.companyId == null) return
  togglingKey.value = item.key
  const prev = item.enabled
  item.enabled = enabled
  try {
    await companyStore.toggleFeature(props.companyId, item.key, enabled)
    toast.success(enabled ? `已啟用 ${item.name}` : `已停用 ${item.name}`)
  } catch {
    item.enabled = prev
    toast.error('切換失敗，請重試')
  } finally {
    togglingKey.value = null
  }
}

onMounted(async () => {
  if (isAdminMode.value) {
    await loadAdminFeatures()
  } else if (!feature.loaded) {
    try { await feature.fetch() } catch { /* ignore */ }
  }
})

watch(() => props.companyId, () => {
  if (isAdminMode.value) loadAdminFeatures()
})
</script>

<template>
  <v-card rounded="xl" variant="flat" class="pms-feature-panel">
    <v-card-title class="pa-5 pb-3 d-flex align-center gap-2">
      <v-icon icon="mdi-puzzle-outline" color="primary" />
      <span class="text-subtitle-1 font-weight-bold">{{ title }}</span>
    </v-card-title>

    <v-divider />

    <v-card-text class="pa-5">
      <!-- Loading -->
      <div v-if="loading" class="d-flex justify-center py-10">
        <v-progress-circular indeterminate color="primary" />
      </div>

      <!-- Admin: 分類顯示可切換 toggle -->
      <template v-else-if="isAdminMode">
        <EmptyState
          v-if="adminFeatures.length === 0"
          icon="mdi-puzzle-outline"
          title="尚無功能模組"
          sub="目前沒有可設定的功能"
        />
        <div v-else class="d-flex flex-column gap-5">
          <div v-for="group in grouped" :key="group.category">
            <div class="text-caption text-medium-emphasis font-weight-bold text-uppercase mb-2">
              {{ group.label }}
            </div>
            <div class="d-flex flex-column">
              <div
                v-for="item in group.items"
                :key="item.key"
                class="pms-feature-row d-flex align-start gap-3 py-3"
              >
                <div class="flex-grow-1">
                  <div class="d-flex align-center gap-2">
                    <span class="text-body-2 font-weight-semibold">{{ item.name }}</span>
                    <v-chip
                      v-if="item.is_default"
                      size="x-small"
                      variant="tonal"
                      color="primary"
                      density="compact"
                    >預設</v-chip>
                  </div>
                  <div class="text-caption text-medium-emphasis mt-1">
                    {{ item.description }}
                  </div>
                </div>
                <v-switch
                  :model-value="item.enabled"
                  color="primary"
                  density="compact"
                  hide-details
                  inset
                  :loading="togglingKey === item.key"
                  :disabled="togglingKey !== null && togglingKey !== item.key"
                  @update:model-value="(v) => onToggle(item, !!v)"
                />
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Member: 唯讀 chip -->
      <template v-else>
        <EmptyState
          v-if="memberFeatures.length === 0"
          icon="mdi-puzzle-outline"
          title="尚未啟用任何功能"
          sub="請聯絡系統管理員開通功能模組"
        />
        <div v-else>
          <div class="text-caption text-medium-emphasis mb-3">
            您的公司目前啟用以下功能：
          </div>
          <div class="d-flex flex-wrap gap-2">
            <v-chip
              v-for="key in memberFeatures"
              :key="key"
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="mdi-check"
            >
              {{ key }}
            </v-chip>
          </div>
        </div>
      </template>
    </v-card-text>
  </v-card>
</template>

<style scoped>
.pms-feature-row + .pms-feature-row {
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.06);
}
</style>
