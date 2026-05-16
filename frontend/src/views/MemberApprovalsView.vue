<template>
  <div>
    <div class="mb-6">
      <h2 class="text-xl font-bold text-gray-900">成員審核</h2>
      <p class="text-sm text-gray-500 mt-0.5">審核透過邀請碼申請加入的成員</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div v-if="loading" class="py-16 text-center text-sm text-gray-400">載入中...</div>

      <table v-else class="w-full">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">姓名</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Email</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">申請時間</th>
            <th class="px-4 py-3 w-36"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="member in pending" :key="member.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 text-sm flex items-center justify-center font-medium">
                  {{ member.name.charAt(0) }}
                </div>
                <span class="text-sm font-medium text-gray-800">{{ member.name }}</span>
              </div>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ member.email }}</td>
            <td class="px-4 py-3 text-xs text-gray-400">{{ member.created_at }}</td>
            <td class="px-4 py-3">
              <div class="flex gap-2">
                <button
                  @click="approve(member)"
                  class="px-3 py-1.5 text-xs font-medium bg-green-50 text-green-700 border border-green-200 rounded-lg hover:bg-green-100 transition-colors"
                >核准</button>
                <button
                  @click="reject(member)"
                  class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 border border-red-200 rounded-lg hover:bg-red-100 transition-colors"
                >拒絕</button>
              </div>
            </td>
          </tr>
          <tr v-if="pending.length === 0">
            <td colspan="4" class="px-4 py-12 text-center text-sm text-gray-400">
              <span class="material-icons text-3xl text-gray-200 block mb-2">check_circle</span>
              目前沒有待審核的申請
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/lib/axios'
import { useToast } from '@/composables/useToast'

interface PendingMember {
  id: number
  name: string
  email: string
  created_at: string
}

const toast = useToast()
const loading = ref(false)
const pending = ref<PendingMember[]>([])

async function fetchPending() {
  loading.value = true
  try {
    const { data } = await api.get('/manager/members/pending')
    pending.value = data
  } finally {
    loading.value = false
  }
}

async function approve(member: PendingMember) {
  await api.post(`/manager/members/${member.id}/approve`)
  pending.value = pending.value.filter(m => m.id !== member.id)
  toast.success(`已核准 ${member.name}`)
}

async function reject(member: PendingMember) {
  if (!confirm(`確定要拒絕 ${member.name} 的申請？`)) return
  await api.post(`/manager/members/${member.id}/reject`)
  pending.value = pending.value.filter(m => m.id !== member.id)
  toast.success('已拒絕申請')
}

onMounted(fetchPending)
</script>
