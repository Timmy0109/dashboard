<template>
  <v-dialog :model-value="true" max-width="560" scrollable persistent @update:model-value="$emit('close')">
    <v-card rounded="xl">
      <v-card-title class="pa-5 pb-3 d-flex align-center justify-space-between">
        <span class="text-body-1 font-weight-semibold">{{ project ? '編輯專案' : '新增專案' }}</span>
        <v-btn icon="mdi-close" variant="text" size="small" @click="$emit('close')" />
      </v-card-title>
      <v-divider />
      <v-card-text class="pa-5">
        <v-form @submit.prevent="handleSubmit">
          <v-text-field v-model="form.name" label="專案名稱" required autofocus class="mb-3" />
          <v-text-field v-model="form.project_no" label="專案編號" placeholder="例：P-2026-001" class="mb-3" />

          <v-row dense class="mb-1">
            <v-col cols="6">
              <v-select
                v-model="form.category_id"
                label="類型"
                :items="lookup.categories.map(c => ({ title: c.name, value: c.id }))"
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
            v-model="form.status_id"
            label="狀態"
            :items="lookup.statuses.map(s => ({ title: s.name, value: s.id }))"
            required
            class="mb-3"
          />

          <v-row dense class="mb-1">
            <v-col cols="6">
              <v-text-field v-model="form.start_date" label="開始日期" type="date" required />
            </v-col>
            <v-col cols="6">
              <v-text-field v-model="form.due_date" label="預計結束" type="date" />
            </v-col>
          </v-row>

          <!-- Member autocomplete with chips -->
          <v-autocomplete
            v-model="form.member_ids"
            :items="memberOptions"
            item-title="name"
            item-value="id"
            label="指派成員"
            multiple
            chips
            closable-chips
            clearable
            hide-details
            class="mb-3"
            placeholder="搜尋成員..."
            no-data-text="無可指派的成員"
          />

          <v-textarea v-model="form.note" label="備註" rows="2" auto-grow class="mb-3" />

          <v-alert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-3 text-body-2">
            {{ errorMsg }}
          </v-alert>

          <div class="d-flex gap-3">
            <v-btn variant="outlined" color="grey" class="flex-grow-1" @click="$emit('close')">取消</v-btn>
            <v-btn type="submit" color="primary" class="flex-grow-1" :loading="saving">儲存</v-btn>
          </div>
        </v-form>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { reactive, ref, computed, onMounted } from 'vue'
import { useProjectStore, type ProjectListItem } from '@/stores/project'
import { useLookupStore } from '@/stores/lookup'
import { useToast } from '@/composables/useToast'
import api from '@/lib/axios'

const props = defineProps<{ project: ProjectListItem | null; companyId?: number | null }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const projectStore = useProjectStore()
const lookup = useLookupStore()
const toast = useToast()

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
  member_ids: [] as number[],
})

const memberOptions = computed(() =>
  lookup.users.filter(u => u.role === 'member' || u.role === 'manager')
)

onMounted(async () => {
  await lookup.fetch(props.companyId)
  if (props.project) {
    form.name        = props.project.name
    form.project_no  = props.project.project_no ?? ''
    form.category_id = props.project.category?.id ?? ''
    form.priority_id = props.project.priority?.id ?? ''
    form.status_id   = props.project.status?.id ?? ''
    form.start_date  = props.project.start_date?.slice(0, 10) ?? ''
    form.due_date    = props.project.due_date?.slice(0, 10) ?? ''
  } else {
    form.start_date = new Date().toISOString().slice(0, 10)
    if (lookup.statuses.length)   form.status_id   = lookup.statuses[0]!.id
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
    const payload: Record<string, unknown> = {
      name: form.name,
      project_no: form.project_no || null,
      category_id: form.category_id,
      priority_id: form.priority_id,
      status_id: form.status_id,
      start_date: form.start_date,
      due_date: form.due_date || null,
      note: form.note || null,
    }
    if (!props.project && props.companyId != null) payload.company_id = props.companyId

    if (props.project) {
      await projectStore.updateProject(props.project.id, payload)
      await syncMembers(props.project.id)
    } else {
      const created = await projectStore.createProject(payload)
      await syncMembers(created.id)
    }
    toast.success(props.project ? '專案已更新' : '專案已建立')
    emit('saved')
  } catch (err: any) {
    const msg = err?.response?.data?.message ?? '儲存失敗，請重試'
    errorMsg.value = msg
    toast.error(msg)
  } finally {
    saving.value = false
  }
}

async function syncMembers(projectId: number) {
  if (form.member_ids.length === 0) return
  await Promise.all(
    form.member_ids.map(userId =>
      api.post(`/projects/${projectId}/members`, { user_id: userId }).catch(() => {})
    )
  )
}
</script>
