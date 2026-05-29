<template>
  <v-dialog
    :model-value="true"
    max-width="640"
    persistent
    @update:model-value="$emit('close')"
  >
    <v-card rounded="xl" class="pms-modal">
      <!-- Header -->
      <v-card-title class="pa-5 pb-4 d-flex align-center justify-space-between bg-primary rounded-t-xl">
        <div class="d-flex align-center gap-2">
          <v-icon :icon="typeIcon[type]" color="white" size="20" />
          <span class="text-body-1 font-weight-semibold text-white">
            {{ isEdit ? '編輯' : '新增' }}{{ typeLabel[type] }}
          </span>
        </div>
        <v-btn icon="mdi-close" variant="text" size="small" color="white" @click="$emit('close')" />
      </v-card-title>

      <v-card-text class="pa-6">
        <!-- Live preview chip -->
        <div class="pms-preview mb-5">
          <div class="text-caption text-grey font-weight-bold mb-2" style="letter-spacing:.04em">預覽</div>
          <v-chip
            :color="form.color"
            variant="flat"
            size="large"
            class="text-white"
            :prepend-icon="type === 'statuses' && form.icon ? `mdi-${form.icon}` : undefined"
          >
            {{ form.name || `未命名${typeLabel[type]}` }}
          </v-chip>
        </div>

        <v-form ref="formRef" @submit.prevent="handleSubmit">
          <!-- 圖示 (statuses only) -->
          <div v-if="type === 'statuses'" class="pms-section">
            <div class="pms-section-title">
              <v-icon icon="mdi-shape-outline" size="16" class="mr-1" />圖示
            </div>
            <v-text-field
              v-model="form.icon"
              placeholder="MDI 名稱，如 clock-outline、check-circle"
              variant="outlined"
              density="comfortable"
              maxlength="60"
              hide-details="auto"
              :prepend-inner-icon="form.icon ? `mdi-${form.icon}` : 'mdi-help-circle-outline'"
            />
          </div>

          <!-- 名稱 -->
          <div class="pms-section">
            <div class="pms-section-title">
              <v-icon icon="mdi-format-title" size="16" class="mr-1" />名稱
            </div>
            <v-text-field
              v-model="form.name"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              :rules="[(v: string) => !!v || '請輸入名稱']"
              required
              autofocus
              placeholder="顯示名稱"
            />
          </div>

          <!-- 顏色 -->
          <div class="pms-section">
            <div class="pms-section-title">
              <v-icon icon="mdi-palette-outline" size="16" class="mr-1" />顏色
            </div>
            <div class="d-flex flex-wrap align-center" style="gap:8px">
              <button
                v-for="preset in presetColors"
                :key="preset"
                type="button"
                class="pms-swatch"
                :class="{ 'pms-swatch--active': form.color.toLowerCase() === preset.toLowerCase() }"
                :style="{ background: preset }"
                :aria-label="preset"
                @click="form.color = preset"
              />
            </div>
            <div class="d-flex align-center mt-3" style="gap:12px">
              <div class="pms-color-input-wrap">
                <input
                  v-model="form.color"
                  type="color"
                  class="pms-color-native"
                  aria-label="自訂顏色"
                />
              </div>
              <v-text-field
                v-model="form.color"
                placeholder="#3b82f6"
                variant="outlined"
                density="comfortable"
                hide-details
                style="max-width:200px"
              />
            </div>
          </div>

          <!-- 排序 (非 categories) -->
          <div v-if="type !== 'categories'" class="pms-section">
            <div class="pms-section-title">
              <v-icon icon="mdi-sort-numeric-ascending" size="16" class="mr-1" />排序
            </div>
            <v-text-field
              v-model.number="form.sort_order"
              type="number"
              min="0"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              placeholder="數字越小越前面"
              style="max-width:200px"
            />
          </div>

          <!-- 啟用 -->
          <div class="pms-section">
            <div class="pms-section-title">
              <v-icon icon="mdi-power" size="16" class="mr-1" />狀態
            </div>
            <v-switch
              v-model="form.is_active"
              :label="form.is_active ? '啟用中' : '已停用'"
              color="primary"
              hide-details
              density="compact"
              inset
            />
          </div>

          <v-alert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mt-2 mb-1 text-body-2">
            {{ errorMsg }}
          </v-alert>
        </v-form>
      </v-card-text>

      <v-divider />

      <v-card-actions class="px-6 py-4">
        <v-btn variant="text" color="grey-darken-1" :disabled="saving" @click="$emit('close')">
          取消
        </v-btn>
        <v-spacer />
        <v-btn color="primary" rounded="lg" :loading="saving" @click="handleSubmit">
          {{ isEdit ? '儲存變更' : '新增' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import api from '@/lib/axios'

const props = defineProps<{
  type: string
  item: Record<string, unknown> | null
}>()

const emit = defineEmits<{
  close: []
  saved: []
}>()

const formRef = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null)
const saving = ref(false)
const errorMsg = ref('')

const typeLabel: Record<string, string> = {
  categories: '專案類型',
  priorities: '優先級',
  statuses: '狀態規則',
}

const typeIcon: Record<string, string> = {
  categories: 'mdi-shape-outline',
  priorities: 'mdi-flag-outline',
  statuses: 'mdi-traffic-light-outline',
}

const presetColors = [
  '#00897B', '#1976D2', '#7E57C2', '#E53935',
  '#FB8C00', '#FDD835', '#43A047', '#26A69A',
  '#5E35B1', '#EC407A', '#546E7A', '#757575',
]

const isEdit = computed(() => !!props.item?.id)

const defaultForm = () => ({
  icon: '',
  name: '',
  color: '#00897B',
  sort_order: 0,
  is_active: true,
})

const form = ref(defaultForm())

watch(() => props.item, (val) => {
  if (val) {
    form.value = {
      icon: String(val.icon ?? ''),
      name: String(val.name ?? ''),
      color: String(val.color ?? '#00897B'),
      sort_order: Number(val.sort_order ?? 0),
      is_active: Boolean(val.is_active ?? true),
    }
  } else {
    form.value = defaultForm()
  }
  errorMsg.value = ''
}, { immediate: true })

async function handleSubmit() {
  const valid = await formRef.value?.validate()
  if (valid && !valid.valid) return

  saving.value = true
  errorMsg.value = ''
  try {
    const payload: Record<string, unknown> = {
      name: form.value.name,
      color: form.value.color,
      is_active: form.value.is_active,
    }
    if (props.type !== 'categories') payload.sort_order = form.value.sort_order
    if (props.type === 'statuses') payload.icon = form.value.icon

    if (isEdit.value) {
      await api.put(`/settings/${props.type}/${props.item!.id}`, payload)
    } else {
      await api.post(`/settings/${props.type}`, payload)
    }
    emit('saved')
  } catch (err: any) {
    errorMsg.value = err?.response?.data?.message ?? '儲存失敗，請重試'
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.pms-modal :deep(.v-field) {
  border-radius: 10px;
}
.pms-section + .pms-section {
  margin-top: 18px;
}
.pms-section-title {
  display: flex;
  align-items: center;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: .04em;
  color: rgba(0, 0, 0, .6);
  margin-bottom: 10px;
  text-transform: uppercase;
}
.pms-preview {
  background: rgba(0, 0, 0, .025);
  border-radius: 12px;
  padding: 14px 16px;
}
.pms-swatch {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  border: 2px solid transparent;
  cursor: pointer;
  transition: transform .15s ease, border-color .15s ease;
  padding: 0;
}
.pms-swatch:hover {
  transform: scale(1.08);
}
.pms-swatch--active {
  border-color: rgba(0, 0, 0, .55);
  box-shadow: 0 0 0 2px rgba(255, 255, 255, .9) inset;
}
.pms-color-input-wrap {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  border: 1px solid rgba(0, 0, 0, .15);
  overflow: hidden;
  padding: 0;
}
.pms-color-native {
  width: 100%;
  height: 100%;
  border: none;
  background: none;
  cursor: pointer;
  padding: 0;
}
</style>
