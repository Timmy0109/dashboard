<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="$emit('close')">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm mx-4 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-800">{{ isEdit ? '編輯使用者' : '新增使用者' }}</h3>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 text-lg leading-none">×</button>
      </div>

      <form @submit.prevent="handleSubmit" class="p-5 space-y-4">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">名稱 <span class="text-red-400">*</span></label>
          <input v-model="form.name" type="text" required placeholder="請輸入名稱"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400" />
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Email <span class="text-red-400">*</span></label>
          <input v-model="form.email" type="email" required placeholder="請輸入 Email"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400" />
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">
            密碼{{ isEdit ? '（留空則不變更）' : '' }} {{ !isEdit ? '*' : '' }}
          </label>
          <input v-model="form.password" type="password" :required="!isEdit" placeholder="請輸入密碼"
            autocomplete="new-password"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400" />
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">角色 <span class="text-red-400">*</span></label>
          <select v-model="form.role" required
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400 bg-white">
            <option value="admin">管理員</option>
            <option value="manager">經理</option>
            <option value="member">成員</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">狀態</label>
          <select v-model="form.status"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400 bg-white">
            <option value="active">啟用</option>
            <option value="inactive">停用</option>
          </select>
        </div>

        <div v-if="errorMsg" class="text-xs text-red-500 bg-red-50 px-3 py-2 rounded-lg">{{ errorMsg }}</div>

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
  user: Record<string, unknown> | null
}>()

const emit = defineEmits<{
  close: []
  saved: []
}>()

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
  if (val) {
    form.value = {
      name: String(val.name ?? ''),
      email: String(val.email ?? ''),
      password: '',
      role: String(val.role ?? 'member'),
      status: String(val.status ?? 'active'),
    }
  } else {
    form.value = defaultForm()
  }
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
