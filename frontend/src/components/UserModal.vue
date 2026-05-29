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
          <v-icon
            :icon="isEdit ? 'mdi-account-edit-outline' : 'mdi-account-plus-outline'"
            color="white"
            size="20"
          />
          <span class="text-body-1 font-weight-semibold text-white">
            {{ isEdit ? '編輯使用者' : '新增使用者' }}
          </span>
        </div>
        <v-btn icon="mdi-close" variant="text" size="small" color="white" @click="$emit('close')" />
      </v-card-title>

      <v-card-text class="pa-6">
        <!-- Avatar preview -->
        <div class="d-flex align-center mb-5">
          <v-avatar size="56" :color="avatarColor" class="mr-3">
            <span class="text-h6 font-weight-bold text-white">{{ initials }}</span>
          </v-avatar>
          <div>
            <div class="text-body-1 font-weight-semibold">
              {{ form.name || '新使用者' }}
            </div>
            <div class="text-caption text-grey">
              {{ form.email || '尚未填寫 Email' }}
            </div>
          </div>
        </div>

        <v-form ref="formRef" @submit.prevent="handleSubmit">
          <!-- 基本資料 -->
          <div class="pms-section">
            <div class="pms-section-title">
              <v-icon icon="mdi-card-account-details-outline" size="16" class="mr-1" />基本資料
            </div>
            <v-row dense>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.name"
                  label="姓名"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  :rules="[(v: string) => !!v || '請輸入姓名']"
                  required
                  autofocus
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.email"
                  label="Email"
                  type="email"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  :rules="[
                    (v: string) => !!v || '請輸入 Email',
                    (v: string) => /.+@.+\..+/.test(v) || 'Email 格式不正確',
                  ]"
                  required
                />
              </v-col>
            </v-row>
          </div>

          <!-- 密碼 -->
          <div class="pms-section">
            <div class="pms-section-title">
              <v-icon icon="mdi-lock-outline" size="16" class="mr-1" />
              {{ isEdit ? '密碼（留空表示不變更）' : '密碼' }}
            </div>
            <v-text-field
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              :placeholder="isEdit ? '不變更' : '至少 8 個字元'"
              variant="outlined"
              density="comfortable"
              autocomplete="new-password"
              hide-details="auto"
              :rules="isEdit ? [] : [(v: string) => !!v || '請設定密碼']"
              :required="!isEdit"
              :append-inner-icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
              @click:append-inner="showPassword = !showPassword"
            />
          </div>

          <!-- 角色 & 狀態 -->
          <div class="pms-section">
            <div class="pms-section-title">
              <v-icon icon="mdi-shield-account-outline" size="16" class="mr-1" />權限與狀態
            </div>
            <v-row dense>
              <v-col cols="12" sm="6">
                <v-select
                  v-model="form.role"
                  label="角色"
                  :items="[
                    { title: '管理員', value: 'admin' },
                    { title: '經理', value: 'manager' },
                    { title: '成員', value: 'member' },
                  ]"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  required
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-select
                  v-model="form.status"
                  label="狀態"
                  :items="[
                    { title: '啟用', value: 'active' },
                    { title: '停用', value: 'inactive' },
                  ]"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                />
              </v-col>
            </v-row>
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
          {{ isEdit ? '儲存變更' : '建立使用者' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import api from '@/lib/axios'

const props = defineProps<{
  user: Record<string, unknown> | null
  companyId?: number | null
}>()

const emit = defineEmits<{ close: []; saved: [] }>()

const formRef = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null)
const saving = ref(false)
const errorMsg = ref('')
const showPassword = ref(false)
const isEdit = computed(() => !!props.user?.id)

const defaultForm = () => ({
  name: '',
  email: '',
  password: '',
  role: 'member' as string,
  status: 'active' as string,
})

const form = ref(defaultForm())

const initials = computed(() => {
  const name = form.value.name?.trim()
  if (!name) return '?'
  const parts = name.split(/\s+/)
  if (parts.length === 1) return name.slice(0, 2).toUpperCase()
  return (parts[0]![0]! + parts[1]![0]!).toUpperCase()
})

const avatarColor = computed(() => {
  switch (form.value.role) {
    case 'admin':   return 'deep-purple'
    case 'manager': return 'teal'
    default:        return 'primary'
  }
})

watch(() => props.user, (val) => {
  form.value = val ? {
    name: String(val.name ?? ''),
    email: String(val.email ?? ''),
    password: '',
    role: String(val.role ?? 'member'),
    status: String(val.status ?? 'active'),
  } : defaultForm()
  errorMsg.value = ''
  showPassword.value = false
}, { immediate: true })

async function handleSubmit() {
  const valid = await formRef.value?.validate()
  if (valid && !valid.valid) return

  saving.value = true
  errorMsg.value = ''
  try {
    const payload: Record<string, unknown> = {
      name: form.value.name,
      email: form.value.email,
      role: form.value.role,
      status: form.value.status,
    }
    if (form.value.password) payload.password = form.value.password

    if (isEdit.value) {
      await api.put(`/users/${props.user!.id}`, payload)
    } else {
      if (props.companyId != null) payload.company_id = props.companyId
      await api.post('/users', payload)
    }
    emit('saved')
  } catch (err: any) {
    errorMsg.value = err?.response?.data?.message ?? '儲存失敗，請確認資料後再試'
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
</style>
