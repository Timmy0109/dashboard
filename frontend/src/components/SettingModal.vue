<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="$emit('close')">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm mx-4 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-800">{{ isEdit ? '編輯' : '新增' }}{{ typeLabel[type] }}</h3>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 text-lg leading-none">×</button>
      </div>

      <form @submit.prevent="handleSubmit" class="p-5 space-y-4">
        <div v-if="type === 'statuses'">
          <label class="block text-xs font-medium text-gray-600 mb-1">圖示</label>
          <input v-model="form.icon" type="text" placeholder="如 pending, check_circle" maxlength="40"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400" />
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">名稱 <span class="text-red-400">*</span></label>
          <input v-model="form.name" type="text" required placeholder="請輸入名稱"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400" />
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">顏色</label>
          <div class="flex items-center gap-3">
            <input v-model="form.color" type="color"
              class="w-10 h-9 rounded border border-gray-200 cursor-pointer p-0.5" />
            <input v-model="form.color" type="text" placeholder="#3b82f6"
              class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400" />
          </div>
        </div>

        <div v-if="type !== 'categories'">
          <label class="block text-xs font-medium text-gray-600 mb-1">排序</label>
          <input v-model.number="form.sort_order" type="number" min="0"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400" />
        </div>

        <div class="flex items-center gap-2">
          <input v-model="form.is_active" type="checkbox" id="is_active"
            class="w-4 h-4 rounded border-gray-300 text-blue-600" />
          <label for="is_active" class="text-sm text-gray-700">啟用</label>
        </div>

        <div class="flex gap-3 pt-1">
          <button type="button" @click="$emit('close')"
            class="flex-1 px-4 py-2 border border-gray-200 text-sm text-gray-600 rounded-lg hover:bg-gray-50 transition-colors">
            取消
          </button>
          <button type="submit" :disabled="saving"
            class="flex-1 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors">
            {{ saving ? '儲存中...' : '儲存' }}
          </button>
        </div>
      </form>
    </div>
  </div>
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

const typeLabel: Record<string, string> = {
  categories: '專案類型',
  priorities: '優先級',
  statuses: '狀態規則',
}

const isEdit = computed(() => !!props.item?.id)

const defaultForm = () => ({
  icon: '',
  name: '',
  color: '#3b82f6',
  sort_order: 0,
  is_active: true,
})

const form = ref(defaultForm())

watch(() => props.item, (val) => {
  if (val) {
    form.value = {
      icon: String(val.icon ?? ''),
      name: String(val.name ?? ''),
      color: String(val.color ?? '#3b82f6'),
      sort_order: Number(val.sort_order ?? 0),
      is_active: Boolean(val.is_active ?? true),
    }
  } else {
    form.value = defaultForm()
  }
}, { immediate: true })

async function handleSubmit() {
  saving.value = true
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
  } finally {
    saving.value = false
  }
}
</script>
