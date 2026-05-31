<template>
  <v-dialog
    :model-value="true"
    max-width="640"
    scrollable
    persistent
    @update:model-value="$emit('close')"
  >
    <v-card rounded="xl" class="pms-modal">
      <!-- Header -->
      <v-card-title class="pa-5 pb-4 d-flex align-center justify-space-between bg-primary rounded-t-xl">
        <div class="d-flex align-center gap-2">
          <v-icon
            :icon="props.project ? 'mdi-folder-edit-outline' : 'mdi-folder-plus-outline'"
            color="white"
            size="20"
          />
          <span class="text-body-1 font-weight-semibold text-white">
            {{ props.project ? '編輯專案' : '新增專案' }}
          </span>
        </div>
        <v-btn icon="mdi-close" variant="text" size="small" color="white" @click="$emit('close')" />
      </v-card-title>

      <v-card-text class="pa-6">
        <v-form ref="formRef" @submit.prevent="handleSubmit">
          <!-- 基本資訊 -->
          <div class="pms-section">
            <div class="pms-section-title">
              <v-icon icon="mdi-information-outline" size="16" class="mr-1" />基本資訊
            </div>
            <v-row dense>
              <v-col cols="12" sm="8">
                <v-text-field
                  v-model="form.name"
                  label="專案名稱"
                  variant="outlined"
                  density="comfortable"
                  :rules="[(v: string) => !!v || '請輸入專案名稱']"
                  required
                  autofocus
                  hide-details="auto"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="form.project_no"
                  label="專案編號"
                  placeholder="例：P-2026-001"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                />
              </v-col>
            </v-row>
          </div>

          <!-- 分類 -->
          <div class="pms-section">
            <div class="pms-section-title">
              <v-icon icon="mdi-tag-outline" size="16" class="mr-1" />分類與狀態
            </div>
            <v-row dense>
              <v-col cols="12" sm="4">
                <v-select
                  v-model="form.category_id"
                  label="類型"
                  :items="lookup.categories.map(c => ({ title: c.name, value: c.id }))"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  :rules="[(v: number | '') => v !== '' || '請選擇類型']"
                  required
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-select
                  v-model="form.priority_id"
                  label="優先級"
                  :items="lookup.priorities.map(p => ({ title: p.name, value: p.id }))"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  :rules="[(v: number | '') => v !== '' || '請選擇優先級']"
                  required
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-select
                  v-model="form.status_id"
                  label="狀態"
                  :items="lookup.statuses.map(s => ({ title: s.name, value: s.id }))"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  :rules="[(v: number | '') => v !== '' || '請選擇狀態']"
                  required
                />
              </v-col>
            </v-row>
          </div>

          <!-- 期程 -->
          <div class="pms-section">
            <div class="pms-section-title">
              <v-icon icon="mdi-calendar-range" size="16" class="mr-1" />期程
            </div>
            <v-row dense>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.start_date"
                  label="開始日期"
                  type="date"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  :rules="[(v: string) => !!v || '請選擇開始日期']"
                  required
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.due_date"
                  label="預計結束"
                  type="date"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                />
              </v-col>
            </v-row>

            <!-- 結案旗標：僅編輯既有專案時顯示 -->
            <div v-if="props.project" class="pms-completed-row mt-3 pa-3 rounded-lg">
              <v-switch
                v-model="form.is_completed"
                color="success"
                density="compact"
                hide-details
                inset
              >
                <template #label>
                  <div class="d-flex flex-column">
                    <span class="text-body-2 font-weight-medium">結案</span>
                    <span class="text-caption text-medium-emphasis">
                      勾選代表此專案已結案，會從「進行中」列表移出並計入「已完成」統計
                    </span>
                  </div>
                </template>
              </v-switch>
            </div>
          </div>

          <!-- 成員 -->
          <div class="pms-section">
            <div class="pms-section-title">
              <v-icon icon="mdi-account-group-outline" size="16" class="mr-1" />指派成員
            </div>
            <v-autocomplete
              v-model="form.member_ids"
              :items="memberOptions"
              item-title="name"
              item-value="id"
              variant="outlined"
              density="comfortable"
              multiple
              chips
              closable-chips
              clearable
              hide-details="auto"
              placeholder="搜尋成員姓名..."
              no-data-text="無可指派的成員"
            />
          </div>

          <!-- 備註 -->
          <div class="pms-section">
            <div class="pms-section-title">
              <v-icon icon="mdi-note-text-outline" size="16" class="mr-1" />備註
            </div>
            <v-textarea
              v-model="form.note"
              rows="2"
              auto-grow
              variant="outlined"
              density="comfortable"
              placeholder="可選：補充說明、目標、注意事項..."
              hide-details="auto"
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
          {{ props.project ? '儲存變更' : '建立專案' }}
        </v-btn>
      </v-card-actions>
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

const formRef = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null)
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
  is_completed: false,
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
    form.is_completed = props.project.is_completed ?? false
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
  const valid = await formRef.value?.validate()
  if (valid && !valid.valid) return

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
    if (props.project) payload.is_completed = form.is_completed
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
.pms-completed-row {
  background-color: rgba(var(--v-theme-success), 0.06);
  border: 1px solid rgba(var(--v-theme-success), 0.18);
}
</style>
