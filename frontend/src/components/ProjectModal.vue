<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/50" @click="$emit('close')" />

    <!-- Dialog -->
    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
      <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-base font-semibold text-gray-900">
          {{ project ? '編輯專案' : '新增專案' }}
        </h3>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
      </div>

      <form @submit.prevent="handleSubmit" class="px-6 py-5 space-y-4">
        <!-- Name -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">專案名稱 <span class="text-red-500">*</span></label>
          <input
            v-model="form.name"
            required
            type="text"
            placeholder="輸入專案名稱"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <!-- Project No -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">專案編號</label>
          <input
            v-model="form.project_no"
            type="text"
            placeholder="例：P-2026-001"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <!-- Category + Priority -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">類型 <span class="text-red-500">*</span></label>
            <select
              v-model="form.category_id"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">選擇類型</option>
              <option v-for="c in lookup.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
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

        <!-- Status -->
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
            <label class="block text-sm font-medium text-gray-700 mb-1">預計結束</label>
            <input
              v-model="form.due_date"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>

        <!-- Note -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">備註</label>
          <textarea
            v-model="form.note"
            rows="3"
            placeholder="專案說明..."
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
import { useProjectStore, type ProjectListItem } from '@/stores/project'
import { useLookupStore } from '@/stores/lookup'

const props = defineProps<{ project: ProjectListItem | null }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const projectStore = useProjectStore()
const lookup = useLookupStore()

const saving = ref(false)
const errorMsg = ref('')

const form = reactive({
  name: '',
  project_no: '',
  category_id: '' as number | '',
  priority_id: '' as number | '',
  status_id: '' as number | '',
  start_date: '',
  due_date: '',
  note: '',
})

onMounted(async () => {
  await lookup.fetch()
  if (props.project) {
    form.name = props.project.name
    form.project_no = props.project.project_no ?? ''
    form.category_id = props.project.category?.id ?? ''
    form.priority_id = props.project.priority?.id ?? ''
    form.status_id = props.project.status?.id ?? ''
    form.start_date = props.project.start_date?.slice(0, 10) ?? ''
    form.due_date = props.project.due_date?.slice(0, 10) ?? ''
  } else {
    form.start_date = new Date().toISOString().slice(0, 10)
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
      project_no: form.project_no || null,
      category_id: form.category_id,
      priority_id: form.priority_id,
      status_id: form.status_id,
      start_date: form.start_date,
      due_date: form.due_date || null,
      note: form.note || null,
    }
    if (props.project) {
      await projectStore.updateProject(props.project.id, payload)
    } else {
      await projectStore.createProject(payload)
    }
    emit('saved')
  } catch (err: any) {
    errorMsg.value = err?.response?.data?.message ?? '儲存失敗，請重試'
  } finally {
    saving.value = false
  }
}
</script>
