<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" @click="$emit('close')" />
    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
      <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-base font-semibold text-gray-900">
          {{ task ? '編輯任務' : '新增任務' }}
        </h3>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
      </div>

      <form @submit.prevent="handleSubmit" class="px-6 py-5 space-y-4">
        <!-- Name -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">任務名稱 <span class="text-red-500">*</span></label>
          <input
            v-model="form.name"
            required
            type="text"
            placeholder="輸入任務名稱"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <!-- Dates -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">開始日期 <span class="text-red-500">*</span></label>
            <input
              v-model="form.start_date"
              required
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">結束日期 <span class="text-red-500">*</span></label>
            <input
              v-model="form.end_date"
              required
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>

        <!-- Status + Priority -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">狀態 <span class="text-red-500">*</span></label>
            <select
              v-model="form.status_id"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">選擇狀態</option>
              <option v-for="s in lookup.statuses" :key="s.id" :value="s.id">{{ s.icon }} {{ s.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">優先級 <span class="text-red-500">*</span></label>
            <select
              v-model="form.priority_id"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">選擇優先級</option>
              <option v-for="p in lookup.priorities" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
        </div>

        <!-- Assignee -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">負責人</label>
          <select
            v-model="form.assignee_id"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option :value="null">未指派</option>
            <option v-for="u in lookup.users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>

        <!-- Progress -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            進度 <span class="ml-1 text-blue-600 font-semibold">{{ form.progress }}%</span>
          </label>
          <input
            v-model.number="form.progress"
            type="range"
            min="0"
            max="100"
            step="5"
            class="w-full accent-blue-600"
          />
          <div class="flex justify-between text-xs text-gray-400 mt-0.5">
            <span>0%</span><span>50%</span><span>100%</span>
          </div>
        </div>

        <!-- Note -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">備註</label>
          <textarea
            v-model="form.note"
            rows="2"
            placeholder="任務說明..."
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
          />
        </div>

        <div v-if="errorMsg" class="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">
          {{ errorMsg }}
        </div>

        <div class="flex gap-3 pt-1">
          <button
            type="button"
            @click="$emit('close')"
            class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition-colors"
          >
            取消
          </button>
          <button
            type="submit"
            :disabled="saving"
            class="flex-1 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
          >
            {{ saving ? '儲存中...' : '儲存' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import { useProjectStore, type Task } from '@/stores/project'
import { useLookupStore } from '@/stores/lookup'

const props = defineProps<{ task: Task | null; projectId: number }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const projectStore = useProjectStore()
const lookup = useLookupStore()

const saving = ref(false)
const errorMsg = ref('')

const form = reactive({
  name: '',
  start_date: '',
  end_date: '',
  status_id: '' as number | '',
  priority_id: '' as number | '',
  assignee_id: null as number | null,
  progress: 0,
  note: '',
})

onMounted(async () => {
  await lookup.fetch()
  if (props.task) {
    form.name = props.task.name
    form.start_date = props.task.start_date.slice(0, 10)
    form.end_date = props.task.end_date.slice(0, 10)
    form.status_id = props.task.status?.id ?? ''
    form.priority_id = props.task.priority?.id ?? ''
    form.assignee_id = props.task.assignee?.id ?? null
    form.progress = props.task.progress
    form.note = props.task.note ?? ''
  } else {
    form.start_date = new Date().toISOString().slice(0, 10)
    const end = new Date()
    end.setDate(end.getDate() + 7)
    form.end_date = end.toISOString().slice(0, 10)
    if (lookup.statuses.length) form.status_id = lookup.statuses[0]!.id
    if (lookup.priorities.length) {
      const mid = lookup.priorities.find(p => p.sort_order === 2)
      form.priority_id = mid?.id ?? lookup.priorities[0]!.id
    }
  }
})

async function handleSubmit() {
  saving.value = true
  errorMsg.value = ''
  try {
    const payload = {
      name: form.name,
      start_date: form.start_date,
      end_date: form.end_date,
      status_id: form.status_id,
      priority_id: form.priority_id,
      assignee_id: form.assignee_id,
      progress: form.progress,
      note: form.note || null,
    }
    if (props.task) {
      await projectStore.updateTask(props.projectId, props.task.id, payload)
    } else {
      await projectStore.createTask(props.projectId, payload)
    }
    emit('saved')
  } catch (err: any) {
    errorMsg.value = err?.response?.data?.message ?? '儲存失敗，請重試'
  } finally {
    saving.value = false
  }
}
</script>
