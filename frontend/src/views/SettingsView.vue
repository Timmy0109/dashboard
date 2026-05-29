<template>
  <div>
    <!-- Page header -->
    <div class="mb-6 d-flex align-center justify-space-between flex-wrap gap-3">
      <div>
        <h2 class="text-h6 font-weight-bold">設定管理</h2>
        <p class="text-body-2 text-medium-emphasis">管理專案類型、優先級與狀態規則</p>
      </div>
    </div>

    <!-- KPI strip -->
    <v-row dense class="mb-4">
      <v-col cols="12" sm="4">
        <KPICard
          label="專案類型"
          :value="categories.length"
          icon="mdi-shape-outline"
          icon-color="primary"
          accent="primary"
          sub="已建立的分類"
        />
      </v-col>
      <v-col cols="12" sm="4">
        <KPICard
          label="優先級"
          :value="priorities.length"
          icon="mdi-flag-variant-outline"
          icon-color="warning"
          accent="warning"
          sub="可選的優先級"
        />
      </v-col>
      <v-col cols="12" sm="4">
        <KPICard
          label="狀態規則"
          :value="statuses.length"
          icon="mdi-list-status"
          icon-color="success"
          accent="success"
          sub="任務生命週期"
        />
      </v-col>
    </v-row>

    <!-- Tabs -->
    <v-card rounded="xl" class="pa-2">
      <Tabs
        v-model="activeTab"
        :items="tabItems"
        class="mb-2 px-2 pt-2"
      />
      <v-divider />

      <v-tabs-window v-model="activeTab">
        <v-tabs-window-item value="categories">
          <div v-if="!loading && categories.length === 0" class="py-8">
            <EmptyState
              icon="mdi-shape-outline"
              title="尚未建立專案類型"
              sub="新增專案類型以幫助分類與篩選專案。"
            >
              <template #action>
                <v-btn class="mt-4" color="primary" rounded="lg" prepend-icon="mdi-plus" @click="openAdd('categories')">
                  新增專案類型
                </v-btn>
              </template>
            </EmptyState>
          </div>
          <SettingTable
            v-else
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
          <div v-if="!loading && priorities.length === 0" class="py-8">
            <EmptyState
              icon="mdi-flag-variant-outline"
              title="尚未建立優先級"
              sub="新增優先級以區分任務的重要程度。"
            >
              <template #action>
                <v-btn class="mt-4" color="primary" rounded="lg" prepend-icon="mdi-plus" @click="openAdd('priorities')">
                  新增優先級
                </v-btn>
              </template>
            </EmptyState>
          </div>
          <SettingTable
            v-else
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
          <div v-if="!loading && statuses.length === 0" class="py-8">
            <EmptyState
              icon="mdi-list-status"
              title="尚未建立狀態規則"
              sub="新增狀態規則以定義任務的進度流程。"
            >
              <template #action>
                <v-btn class="mt-4" color="primary" rounded="lg" prepend-icon="mdi-plus" @click="openAdd('statuses')">
                  新增狀態規則
                </v-btn>
              </template>
            </EmptyState>
          </div>
          <SettingTable
            v-else
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
    </v-card>

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
import { ref, computed, onMounted } from 'vue'
import api from '@/lib/axios'
import { useToast } from '@/composables/useToast'
import SettingTable from '@/components/SettingTable.vue'
import SettingModal from '@/components/SettingModal.vue'
import KPICard from '@/components/ui/KPICard.vue'
import Tabs from '@/components/ui/Tabs.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

interface SettingRow {
  id: number
  name: string
  color?: string
  icon?: string
  sort_order?: number
  is_active?: boolean
  [key: string]: unknown
}

type SettingType = 'categories' | 'priorities' | 'statuses'

const toast = useToast()
const loading = ref(false)
const activeTab = ref<SettingType>('categories')
const showModal = ref(false)
const modalType = ref<SettingType>('categories')
const editingItem = ref<Record<string, unknown> | null>(null)

const categories = ref<SettingRow[]>([])
const priorities = ref<SettingRow[]>([])
const statuses = ref<SettingRow[]>([])

const tabItems = computed(() => [
  { value: 'categories' as const, label: '專案類型', count: categories.value.length },
  { value: 'priorities' as const, label: '優先級',   count: priorities.value.length },
  { value: 'statuses'   as const, label: '狀態規則', count: statuses.value.length },
])

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

function openAdd(type: SettingType) {
  modalType.value  = type
  editingItem.value = null
  showModal.value  = true
}

function openEdit(type: SettingType, item: Record<string, unknown>) {
  modalType.value  = type
  editingItem.value = item
  showModal.value  = true
}

async function handleDelete(type: SettingType, item: Record<string, unknown>) {
  if (!confirm(`確定要刪除「${item.name as string}」？`)) return
  try {
    await api.delete(`/settings/${type}/${item.id}`)
    await fetchAll()
    toast.success('已刪除')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    toast.error(e?.response?.data?.message ?? '刪除失敗')
  }
}

function onSaved() {
  showModal.value = false
  fetchAll()
  toast.success('儲存成功')
}

onMounted(() => fetchAll())
</script>
