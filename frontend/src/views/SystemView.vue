<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900">系統管理</h2>
        <p class="text-sm text-gray-500 mt-0.5">管理系統使用者帳號</p>
      </div>
      <button
        @click="openAdd"
        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
      >
        ＋ 新增使用者
      </button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div v-if="loading" class="py-16 text-center text-sm text-gray-400">載入中...</div>

      <table v-else class="w-full">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">名稱</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Email</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">角色</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">狀態</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">最後登入</th>
            <th class="px-4 py-3 w-24"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center font-medium">
                  {{ user.name.charAt(0) }}
                </div>
                <span class="text-sm font-medium text-gray-800">{{ user.name }}</span>
              </div>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ user.email }}</td>
            <td class="px-4 py-3">
              <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium" :class="roleStyle[user.role]">
                {{ roleLabel[user.role] }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center gap-1 text-xs"
                :class="user.status === 'active' ? 'text-green-600' : 'text-gray-400'">
                <span class="w-1.5 h-1.5 rounded-full inline-block"
                  :class="user.status === 'active' ? 'bg-green-500' : 'bg-gray-300'"></span>
                {{ user.status === 'active' ? '啟用' : '停用' }}
              </span>
            </td>
            <td class="px-4 py-3 text-xs text-gray-400">
              {{ user.last_login_at ? user.last_login_at.slice(0, 10) : '從未' }}
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-1">
                <button @click="openEdit(user)"
                  class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors">✏️</button>
                <button @click="handleDelete(user)"
                  class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors">🗑️</button>
              </div>
            </td>
          </tr>
          <tr v-if="users.length === 0">
            <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">無使用者</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- User Modal -->
    <UserModal
      v-if="showModal"
      :user="editingUser"
      @close="showModal = false"
      @saved="onSaved"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/lib/axios'
import { useToast } from '@/composables/useToast'
import UserModal from '@/components/UserModal.vue'

const toast = useToast()
const loading = ref(false)
const showModal = ref(false)
const editingUser = ref<any>(null)
const users = ref<any[]>([])

const roleLabel: Record<string, string> = { admin: '管理員', manager: '經理', member: '成員' }
const roleStyle: Record<string, string> = {
  admin: 'bg-purple-100 text-purple-700',
  manager: 'bg-blue-100 text-blue-700',
  member: 'bg-gray-100 text-gray-600',
}

async function fetchUsers() {
  loading.value = true
  try {
    const res = await api.get('/users')
    users.value = res.data
  } finally {
    loading.value = false
  }
}

function openAdd() {
  editingUser.value = null
  showModal.value = true
}

function openEdit(user: any) {
  editingUser.value = user
  showModal.value = true
}

async function handleDelete(user: any) {
  if (!confirm(`確定要刪除「${user.name}」？`)) return
  try {
    await api.delete(`/users/${user.id}`)
    users.value = users.value.filter(u => u.id !== user.id)
    toast.success('已刪除使用者')
  } catch (err: any) {
    toast.error(err?.response?.data?.message ?? '刪除失敗')
  }
}

function onSaved() {
  showModal.value = false
  fetchUsers()
  toast.success('儲存成功')
}

onMounted(() => fetchUsers())
</script>
