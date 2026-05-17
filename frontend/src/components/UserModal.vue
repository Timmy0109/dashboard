<template>
  <v-dialog :model-value="true" max-width="440" persistent @update:model-value="$emit('close')">
    <v-card rounded="xl">
      <v-card-title class="pa-5 pb-3 d-flex align-center justify-space-between">
        <span class="text-body-1 font-weight-semibold">{{ isEdit ? '編輯使用者' : '新增使用者' }}</span>
        <v-btn icon="mdi-close" variant="text" size="small" @click="$emit('close')" />
      </v-card-title>
      <v-card-text class="pa-5 pt-2">
        <v-form @submit.prevent="handleSubmit">
          <v-text-field v-model="form.name" label="名稱" required autofocus class="mb-3" />
          <v-text-field v-model="form.email" label="Email" type="email" required class="mb-3" />
          <v-text-field
            v-model="form.password"
            :label="isEdit ? '新密碼（留空則不變更）' : '密碼'"
            type="password"
            :required="!isEdit"
            autocomplete="new-password"
            class="mb-3"
          />
          <v-select
            v-model="form.role"
            label="角色"
            :items="[
              { title: '管理員', value: 'admin' },
              { title: '經理',   value: 'manager' },
              { title: '成員',   value: 'member' },
            ]"
            required
            class="mb-3"
          />
          <v-select
            v-model="form.status"
            label="狀態"
            :items="[
              { title: '啟用', value: 'active' },
              { title: '停用', value: 'inactive' },
            ]"
            class="mb-3"
          />
          <v-alert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-3 text-body-2">
            {{ errorMsg }}
          </v-alert>
          <div class="d-flex gap-3">
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
  user: Record<string, unknown> | null
  companyId?: number | null
}>()

const emit = defineEmits<{ close: []; saved: [] }>()

const saving = ref(false)
const errorMsg = ref('')
const isEdit = computed(() => !!props.user?.id)

const defaultForm = () => ({
  name: '',
  email: '',
  password: '',
  role: 'member' as string,
  status: 'active' as string,
})

const form = ref(defaultForm())

watch(() => props.user, (val) => {
  form.value = val ? {
    name: String(val.name ?? ''),
    email: String(val.email ?? ''),
    password: '',
    role: String(val.role ?? 'member'),
    status: String(val.status ?? 'active'),
  } : defaultForm()
  errorMsg.value = ''
}, { immediate: true })

async function handleSubmit() {
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
