<template>
  <v-dialog :model-value="true" max-width="440" persistent @update:model-value="$emit('close')">
    <v-card rounded="xl">
      <v-card-title class="pa-5 pb-3 d-flex bg-primary align-center justify-space-between">
        <span class="text-body-1 font-weight-semibold text-white">{{ isEdit ? '編輯' : '新增' }}{{ typeLabel[type] }}</span>
        <v-btn icon="mdi-close" variant="text" size="small" color="white" @click="$emit('close')" />
      </v-card-title>
      <v-card-text class="pa-5">
        <v-form @submit.prevent="handleSubmit">
          <v-text-field
            v-if="type === 'statuses'"
            v-model="form.icon"
            label="圖示（MDI 名稱）"
            placeholder="如 clock-outline, check-circle"
            maxlength="60"
            class="mb-3"
          />
          <v-text-field
            v-model="form.name"
            label="名稱"
            required
            autofocus
            class="mb-3"
          />

          <!-- Color picker row -->
          <div class="mb-3">
            <div class="text-caption text-grey mb-1">顏色</div>
            <div class="d-flex align-center gap-3">
              <input
                v-model="form.color"
                type="color"
                style="width:40px;height:40px;border:1px solid rgba(0,0,0,.2);border-radius:8px;cursor:pointer;padding:2px"
              />
              <v-text-field
                v-model="form.color"
                placeholder="#3b82f6"
                hide-details
                density="compact"
              />
            </div>
          </div>

          <v-text-field
            v-if="type !== 'categories'"
            v-model.number="form.sort_order"
            label="排序"
            type="number"
            min="0"
            class="mb-3"
          />

          <v-switch
            v-model="form.is_active"
            label="啟用"
            color="primary"
            hide-details
            density="compact"
            class="mb-4"
          />

          <v-alert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-3 text-body-2">
            {{ errorMsg }}
          </v-alert>

          <div class="d-flex">
            <v-btn variant="outlined" color="grey" class="flex-grow-1 mr-3" @click="$emit('close')">取消</v-btn>
            <v-btn type="submit" color="primary" class="flex-grow-1" :loading="saving">儲存</v-btn>
          </div>
        </v-form>
      </v-card-text>
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

const saving = ref(false)
const errorMsg = ref('')

const typeLabel: Record<string, string> = {
  categories: '專案類型',
  priorities: '優先級',
  statuses: '狀態規則',
}

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
}, { immediate: true })

async function handleSubmit() {
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
