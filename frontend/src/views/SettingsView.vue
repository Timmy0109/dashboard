<template>
  <div>
    <div class="mb-6 d-flex align-center justify-space-between flex-wrap gap-3">
      <div>
        <h2 class="text-h6 font-weight-bold">設定管理</h2>
        <p class="text-body-2 text-grey">管理專案類型、優先級與狀態規則</p>
      </div>
    </div>

    <v-tabs v-model="activeTab" color="primary" class="mb-5" rounded="lg">
      <v-tab v-for="tab in tabs" :key="tab.key" :value="tab.key">{{ tab.label }}</v-tab>
    </v-tabs>

    <v-tabs-window v-model="activeTab">
      <v-tabs-window-item value="categories">
        <SettingTable
          title="專案類型"
          :items="categories"
          :loading="loading"
          :fields="categoryFields"
          @add="openAdd('categories')"
          @edit="openEdit('categories', $event)"
          @delete="handleDelete('categories', $event)"
        />
      </v-tabs-window-item>

      <v-tabs-window-item value="priorities">
        <SettingTable
          title="優先級"
          :items="priorities"
          :loading="loading"
          :fields="priorityFields"
          @add="openAdd('priorities')"
          @edit="openEdit('priorities', $event)"
          @delete="handleDelete('priorities', $event)"
        />
      </v-tabs-window-item>

      <v-tabs-window-item value="statuses">
        <SettingTable
          title="狀態規則"
          :items="statuses"
          :loading="loading"
          :fields="statusFields"
          @add="openAdd('statuses')"
          @edit="openEdit('statuses', $event)"
          @delete="handleDelete('statuses', $event)"
        />
      </v-tabs-window-item>
    </v-tabs-window>

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
  { key: 'statuses',   label: '狀態規則' },
]

const categoryFields = [
  { key: 'name',      label: '名稱' },
  { key: 'color',     label: '顏色',  type: 'color' },
  { key: 'is_active', label: '啟用',  type: 'bool' },
]

const priorityFields = [
  { key: 'name',       label: '名稱' },
  { key: 'color',      label: '顏色',  type: 'color' },
  { key: 'sort_order', label: '排序' },
  { key: 'is_active',  label: '啟用',  type: 'bool' },
]

const statusFields = [
  { key: 'icon',       label: '圖示' },
  { key: 'name',       label: '名稱' },
  { key: 'color',      label: '顏色',  type: 'color' },
  { key: 'sort_order', label: '排序' },
  { key: 'is_active',  label: '啟用',  type: 'bool' },
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
    statuses.value   = sta.data
  } finally {
    loading.value = false
  }
}

function openAdd(type: string) {
  modalType.value  = type
  editingItem.value = null
  showModal.value  = true
}

function openEdit(type: string, item: Record<string, unknown>) {
  modalType.value  = type
  editingItem.value = item
  showModal.value  = true
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
