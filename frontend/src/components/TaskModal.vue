<template>
  <v-dialog :model-value="true" max-width="520" scrollable persistent @update:model-value="$emit('close')">
    <v-card rounded="xl">
      <v-card-title class="pa-5 pb-3 d-flex align-center justify-space-between bg-primary rounded-t-xl">
        <span class="text-body-1 font-weight-semibold text-white">{{ task ? '編輯任務' : '新增任務' }}</span>
        <v-btn icon="mdi-close" variant="text" size="small" color="white" @click="$emit('close')" />
      </v-card-title>
      <v-card-text class="pa-5 pt-2">
        <v-form @submit.prevent="handleSubmit">
          <v-text-field v-model="form.name" label="任務名稱" required autofocus class="mb-3" />

          <v-row dense class="mb-1">
            <v-col cols="6">
              <v-text-field v-model="form.start_date" label="開始日期" type="date" required />
            </v-col>
            <v-col cols="6">
              <v-text-field v-model="form.end_date" label="結束日期" type="date" required />
            </v-col>
          </v-row>

          <v-row dense class="mb-1">
            <v-col cols="6">
              <v-select
                v-model="form.status_id"
                label="狀態"
                :items="lookup.statuses.map(s => ({ title: s.name, value: s.id }))"
                required
              />
            </v-col>
            <v-col cols="6">
              <v-select
                v-model="form.priority_id"
                label="優先級"
                :items="lookup.priorities.map(p => ({ title: p.name, value: p.id }))"
                required
              />
            </v-col>
          </v-row>

          <v-select
            v-model="form.assignee_id"
            label="負責人"
            :items="[{ title: '未指派', value: null }, ...lookup.users.map(u => ({ title: u.name, value: u.id }))]"
            clearable
            class="mb-3"
          />

          <div class="mb-4">
            <div class="d-flex justify-space-between mb-1">
              <span class="text-caption text-grey">進度</span>
              <span class="text-caption font-weight-bold text-primary">{{ form.progress }}%</span>
            </div>
            <v-slider
              v-model="form.progress"
              min="0"
              max="100"
              step="5"
              color="primary"
              track-color="grey-lighten-2"
              thumb-label
              hide-details
            />
          </div>

          <v-textarea v-model="form.note" label="備註" rows="2" auto-grow class="mb-3" />

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
import { reactive, ref, watch, onMounted } from 'vue'
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

const STATUS_PROGRESS: Record<string, number> = {
  '準備中': 0, '待確認': 25, '進行中': 50, '追蹤中': 75, '已完成': 100,
}

watch(() => form.status_id, (id) => {
  if (!id) return
  const status = lookup.statuses.find(s => s.id === id)
  if (status && status.name in STATUS_PROGRESS) {
    form.progress = STATUS_PROGRESS[status.name]!
  }
})

onMounted(async () => {
  await lookup.fetch()
  if (props.task) {
    form.name        = props.task.name
    form.start_date  = props.task.start_date.slice(0, 10)
    form.end_date    = props.task.end_date.slice(0, 10)
    form.status_id   = props.task.status?.id ?? ''
    form.priority_id = props.task.priority?.id ?? ''
    form.assignee_id = props.task.assignee?.id ?? null
    form.progress    = props.task.progress
    form.note        = props.task.note ?? ''
  } else {
    form.start_date = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Taipei' }).format(new Date())
    const end = new Date()
    end.setDate(end.getDate() + 7)
    form.end_date = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Taipei' }).format(end)
    if (lookup.statuses.length)  form.status_id   = lookup.statuses[0]!.id
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
    const selectedStatus = lookup.statuses.find(s => s.id === form.status_id)
    const payload = {
      name: form.name,
      start_date: form.start_date,
      end_date: form.end_date,
      status_id: form.status_id,
      priority_id: form.priority_id,
      assignee_id: form.assignee_id,
      progress: form.progress,
      note: form.note || null,
      is_completed: selectedStatus?.name === '已完成',
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
