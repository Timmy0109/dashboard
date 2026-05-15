<template>
  <div>
    <div class="mb-6">
      <h2 class="text-xl font-bold text-gray-900">設定管理</h2>
      <p class="text-sm text-gray-500 mt-0.5">管理專案類型、優先級與狀態規則</p>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-5 bg-gray-100 p-1 rounded-lg w-fit">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        @click="activeTab = tab.key"
        class="px-4 py-1.5 text-sm rounded-md transition-colors"
        :class="activeTab === tab.key ? 'bg-white text-gray-900 shadow-sm font-medium' : 'text-gray-500 hover:text-gray-700'"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Categories -->
    <SettingTable
      v-if="activeTab === 'categories'"
      title="專案類型"
      :items="categories"
      :loading="loading"
      :fields="categoryFields"
      @add="openAdd('categories')"
      @edit="openEdit('categories', $event)"
      @delete="handleDelete('categories', $event)"
    />

    <!-- Priorities -->
    <SettingTable
      v-if="activeTab === 'priorities'"
      title="優先級"
      :items="priorities"
      :loading="loading"
      :fields="priorityFields"
      @add="openAdd('priorities')"
      @edit="openEdit('priorities', $event)"
      @delete="handleDelete('priorities', $event)"
    />

    <!-- Statuses -->
    <SettingTable
      v-if="activeTab === 'statuses'"
      title="狀態規則"
      :items="statuses"
      :loading="loading"
      :fields="statusFields"
      @add="openAdd('statuses')"
      @edit="openEdit('statuses', $event)"
      @delete="handleDelete('statuses', $event)"
    />

    <!-- Edit/Add Modal -->
    <SettingModal
      v-if="showModal"
      :type="modalType"
      :item="editingItem"
      @close="showModal = false"
      @saved="onSaved"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/lib/axios'
import { useToast } from '@/composables/useToast'
import SettingTable from '@/components/SettingTable.vue'
import SettingModal from '@/components/SettingModal.vue'

const toast = useToast()
const loading = ref(false)
const activeTab = ref('categories')
const showModal = ref(false)
const modalType = ref('categories')
const editingItem = ref<Record<string, unknown> | null>(null)

const categories = ref<any[]>([])
const priorities = ref<any[]>([])
const statuses = ref<any[]>([])

const tabs = [
  { key: 'categories', label: '專案類型' },
  { key: 'priorities', label: '優先級' },
  { key: 'statuses', label: '狀態規則' },
]

const categoryFields = [
  { key: 'name', label: '名稱' },
  { key: 'color', label: '顏色', type: 'color' },
  { key: 'is_active', label: '啟用', type: 'bool' },
]

const priorityFields = [
  { key: 'name', label: '名稱' },
  { key: 'color', label: '顏色', type: 'color' },
  { key: 'sort_order', label: '排序' },
  { key: 'is_active', label: '啟用', type: 'bool' },
]

const statusFields = [
  { key: 'icon', label: '圖示' },
  { key: 'name', label: '名稱' },
  { key: 'color', label: '顏色', type: 'color' },
  { key: 'sort_order', label: '排序' },
  { key: 'is_active', label: '啟用', type: 'bool' },
]

async function fetchAll() {
  loading.value = true
  try {
    const [cat, pri, sta] = await Promise.all([
      api.get('/settings/categories'),
      api.get('/settings/priorities'),
      api.get('/settings/statuses'),
    ])
    categories.value = cat.data
    priorities.value = pri.data
    statuses.value = sta.data
  } finally {
    loading.value = false
  }
}

function openAdd(type: string) {
  modalType.value = type
  editingItem.value = null
  showModal.value = true
}

function openEdit(type: string, item: Record<string, unknown>) {
  modalType.value = type
  editingItem.value = item
  showModal.value = true
}

async function handleDelete(type: string, item: Record<string, unknown>) {
  if (!confirm(`確定要刪除「${item.name}」？`)) return
  try {
    await api.delete(`/settings/${type}/${item.id}`)
    await fetchAll()
    toast.success('已刪除')
  } catch {
    toast.error('刪除失敗')
  }
}

function onSaved() {
  showModal.value = false
  fetchAll()
  toast.success('儲存成功')
}

onMounted(() => fetchAll())
</script>
