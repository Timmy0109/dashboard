<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900">成員管理</h2>
        <p class="text-sm text-gray-500 mt-0.5">查看公司所有成員</p>
      </div>
      <button
        @click="openPendingDialog"
        class="relative inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-50 shadow-sm transition-colors"
      >
        <span class="material-icons text-base leading-none text-orange-500">how_to_reg</span>
        待審核申請
        <span
          v-if="pendingCount > 0"
          class="absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-1 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center"
        >{{ pendingCount }}</span>
      </button>
    </div>

    <!-- All members table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div v-if="loading" class="py-16 text-center text-sm text-gray-400">載入中...</div>
      <table v-else class="w-full">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">姓名</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Email</th>
            <th v-if="auth.isAdmin" class="px-4 py-3 text-left text-xs font-medium text-gray-500">公司</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">狀態</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">加入日期</th>
            <th class="px-4 py-3 w-28"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="m in members" :key="m.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full text-sm flex items-center justify-center font-medium shrink-0"
                  :class="m.status === 'active' ? 'bg-blue-100 text-blue-600' : m.status === 'pending' ? 'bg-orange-100 text-orange-600' : 'bg-gray-100 text-gray-400'">
                  {{ m.name.charAt(0) }}
                </div>
                <span class="text-sm font-medium text-gray-800">{{ m.name }}</span>
              </div>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ m.email }}</td>
            <td v-if="auth.isAdmin" class="px-4 py-3 text-sm text-gray-600">{{ m.company_name ?? '—' }}</td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full"
                :class="{
                  'bg-green-50 text-green-700': m.status === 'active',
                  'bg-orange-50 text-orange-600': m.status === 'pending',
                  'bg-gray-100 text-gray-400': m.status === 'inactive',
                }">
                {{ statusLabel[m.status] ?? m.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-xs text-gray-400">{{ m.created_at }}</td>
            <td class="px-4 py-3">
              <div class="flex gap-1">
                <button
                  v-if="m.status !== 'pending'"
                  @click="toggleStatus(m)"
                  class="p-1.5 rounded transition-colors"
                  :class="m.status === 'active'
                    ? 'text-gray-400 hover:text-orange-600 hover:bg-orange-50'
                    : 'text-gray-400 hover:text-green-600 hover:bg-green-50'"
                  :title="m.status === 'active' ? '停用' : '啟用'"
                >
                  <span class="material-icons text-base leading-none">{{ m.status === 'active' ? 'block' : 'check_circle' }}</span>
                </button>
                <button
                  @click="openEdit(m)"
                  class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                  title="編輯"
                >
                  <span class="material-icons text-base leading-none">edit</span>
                </button>
                <button
                  @click="deleteMember(m)"
                  class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
                  title="刪除"
                >
                  <span class="material-icons text-base leading-none">delete</span>
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="members.length === 0">
            <td :colspan="auth.isAdmin ? 6 : 5" class="px-4 py-12 text-center text-sm text-gray-400">此公司尚無成員</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Edit member dialog -->
    <div v-if="editingMember" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50" @click="editingMember = null" />
      <div class="relative bg-white rounded-xl shadow-xl w-full max-w-sm">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-800">編輯成員</h3>
          <button @click="editingMember = null" class="text-gray-400 hover:text-gray-600 text-lg leading-none">×</button>
        </div>
        <form @submit.prevent="saveEdit" class="p-5 space-y-4">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">姓名 <span class="text-red-400">*</span></label>
            <input v-model="editForm.name" type="text" required placeholder="請輸入姓名"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Email <span class="text-red-400">*</span></label>
            <input v-model="editForm.email" type="email" required placeholder="請輸入 Email"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">新密碼（留空則不變更）</label>
            <input v-model="editForm.password" type="password" placeholder="至少 8 個字元" autocomplete="new-password"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">狀態</label>
            <select v-model="editForm.status"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400 bg-white">
              <option value="active">啟用</option>
              <option value="inactive">停用</option>
            </select>
          </div>
          <div v-if="editError" class="text-xs text-red-500 bg-red-50 px-3 py-2 rounded-lg">{{ editError }}</div>
          <div class="flex gap-3 pt-1">
            <button type="button" @click="editingMember = null"
              class="flex-1 px-4 py-2 border border-gray-200 text-sm text-gray-600 rounded-lg hover:bg-gray-50 transition-colors">取消</button>
            <button type="submit" :disabled="editSaving"
              class="flex-1 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors">
              {{ editSaving ? '儲存中...' : '儲存' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Pending approvals dialog -->
    <div v-if="showPending" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50" @click="showPending = false" />
      <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[80vh] flex flex-col">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <div>
            <h3 class="text-base font-semibold text-gray-900">待審核申請</h3>
            <p class="text-xs text-gray-400 mt-0.5">透過邀請碼申請加入的成員</p>
          </div>
          <button @click="showPending = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
        </div>

        <div class="overflow-y-auto flex-1">
          <div v-if="pendingLoading" class="py-12 text-center text-sm text-gray-400">載入中...</div>
          <div v-else-if="pending.length === 0" class="py-12 text-center text-sm text-gray-400">
            <span class="material-icons text-3xl text-gray-200 block mb-2">check_circle</span>
            目前沒有待審核的申請
          </div>
          <div v-else class="divide-y divide-gray-50">
            <div v-for="p in pending" :key="p.id" class="px-6 py-4 flex items-center gap-3">
              <div class="w-9 h-9 rounded-full bg-orange-100 text-orange-600 text-sm flex items-center justify-center font-medium shrink-0">
                {{ p.name.charAt(0) }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 truncate">{{ p.name }}</p>
                <p class="text-xs text-gray-400 truncate">{{ p.email }}</p>
                <p v-if="auth.isAdmin && p.company_name" class="text-xs text-blue-500 mt-0.5">{{ p.company_name }}</p>
                <p class="text-xs text-gray-300 mt-0.5">{{ p.created_at }}</p>
              </div>
              <div class="flex gap-2 shrink-0">
                <button
                  @click="approve(p)"
                  class="px-3 py-1.5 text-xs font-medium bg-green-50 text-green-700 border border-green-200 rounded-lg hover:bg-green-100 transition-colors"
                >核准</button>
                <button
                  @click="reject(p)"
                  class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 border border-red-200 rounded-lg hover:bg-red-100 transition-colors"
                >拒絕</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/lib/axios'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'

interface Member {
  id: number
  name: string
  email: string
  status: string
  company_name?: string | null
  created_at: string
}

interface PendingMember {
  id: number
  name: string
  email: string
  company_name: string | null
  created_at: string
}

const auth = useAuthStore()
const toast = useToast()

const loading = ref(false)
const members = ref<Member[]>([])

const showPending = ref(false)
const pendingLoading = ref(false)
const pending = ref<PendingMember[]>([])

const pendingCount = computed(() => pending.value.length)

const editingMember = ref<Member | null>(null)
const editForm = reactive({ name: '', email: '', password: '', status: 'active' })
const editSaving = ref(false)
const editError = ref('')

const statusLabel: Record<string, string> = {
  active: '啟用',
  pending: '待審核',
  inactive: '停用',
}

async function fetchMembers() {
  loading.value = true
  try {
    const { data } = await api.get('/manager/members')
    members.value = data
  } finally {
    loading.value = false
  }
}

async function fetchPending() {
  pendingLoading.value = true
  try {
    const { data } = await api.get('/manager/members/pending')
    pending.value = data
  } finally {
    pendingLoading.value = false
  }
}

async function openPendingDialog() {
  showPending.value = true
  await fetchPending()
}

async function approve(p: PendingMember) {
  try {
    await api.post(`/manager/members/${p.id}/approve`)
    pending.value = pending.value.filter(m => m.id !== p.id)
    const idx = members.value.findIndex(m => m.id === p.id)
    if (idx !== -1) members.value[idx]!.status = 'active'
    else await fetchMembers()
    toast.success(`已核准 ${p.name}`)
  } catch (err: any) {
    toast.error(err?.response?.data?.message ?? '操作失敗')
  }
}

async function reject(p: PendingMember) {
  if (!confirm(`確定要拒絕 ${p.name} 的申請？`)) return
  try {
    await api.post(`/manager/members/${p.id}/reject`)
    pending.value = pending.value.filter(m => m.id !== p.id)
    const idx = members.value.findIndex(m => m.id === p.id)
    if (idx !== -1) members.value[idx]!.status = 'inactive'
    else await fetchMembers()
    toast.success('已拒絕申請')
  } catch (err: any) {
    toast.error(err?.response?.data?.message ?? '操作失敗')
  }
}

async function toggleStatus(m: Member) {
  const next = m.status === 'active' ? 'inactive' : 'active'
  try {
    await api.put(`/manager/members/${m.id}`, { status: next })
    m.status = next
    toast.success(next === 'active' ? `已啟用 ${m.name}` : `已停用 ${m.name}`)
  } catch (err: any) {
    toast.error(err?.response?.data?.message ?? '操作失敗')
  }
}

function openEdit(m: Member) {
  editingMember.value = m
  editForm.name = m.name
  editForm.email = m.email
  editForm.password = ''
  editForm.status = m.status === 'pending' ? 'active' : m.status
  editError.value = ''
}

async function saveEdit() {
  if (!editingMember.value) return
  editSaving.value = true
  editError.value = ''
  try {
    const payload: Record<string, string> = {
      name: editForm.name,
      email: editForm.email,
      status: editForm.status,
    }
    if (editForm.password) payload.password = editForm.password
    const { data } = await api.put(`/manager/members/${editingMember.value.id}`, payload)
    const idx = members.value.findIndex(m => m.id === data.id)
    if (idx !== -1) Object.assign(members.value[idx]!, data)
    editingMember.value = null
    toast.success('成員資料已更新')
  } catch (err: any) {
    const errors = err?.response?.data?.errors
    editError.value = errors
      ? Object.values(errors).flat().join('、')
      : (err?.response?.data?.message ?? '儲存失敗')
  } finally {
    editSaving.value = false
  }
}

async function deleteMember(m: Member) {
  if (!confirm(`確定要刪除「${m.name}」？此操作無法復原。`)) return
  try {
    await api.delete(`/manager/members/${m.id}`)
    members.value = members.value.filter(u => u.id !== m.id)
    toast.success(`已刪除 ${m.name}`)
  } catch (err: any) {
    toast.error(err?.response?.data?.message ?? '刪除失敗')
  }
}

onMounted(async () => {
  await Promise.all([fetchMembers(), fetchPending()])
})
</script>
