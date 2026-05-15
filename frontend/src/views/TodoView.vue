<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900">今日待辦</h2>
        <p class="text-sm text-gray-500 mt-0.5">所有未完成任務，依截止日期排序</p>
      </div>
    </div>

    <!-- Filter tabs -->
    <div class="flex gap-2 mb-4">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        @click="activeTab = tab.value"
        class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors"
        :class="activeTab === tab.value
          ? 'bg-blue-600 text-white'
          : 'bg-white text-gray-500 border border-gray-200 hover:border-gray-300'"
      >
        {{ tab.label }}
        <span class="ml-1 text-xs opacity-70">({{ getTabCount(tab.value) }})</span>
      </button>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div v-if="store.loading" class="py-16 text-center text-sm text-gray-400">載入中...</div>

      <table v-else class="w-full">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 w-8"></th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">任務名稱</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">所屬專案</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">負責人</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">截止日期</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">優先級</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">狀態</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 w-24">進度</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr
            v-for="task in filteredTasks"
            :key="task.id"
            class="hover:bg-gray-50 transition-colors"
          >
            <td class="px-4 py-3">
              <div class="w-4 h-4 rounded border-2 border-gray-300 cursor-pointer hover:border-blue-400" />
            </td>
            <td class="px-4 py-3">
              <span class="text-sm text-gray-800 font-medium">{{ task.name }}</span>
            </td>
            <td class="px-4 py-3">
              <span class="text-xs text-gray-500">{{ task.project_name }}</span>
            </td>
            <td class="px-4 py-3">
              <div v-if="task.assignee" class="flex items-center gap-1.5">
                <div class="w-6 h-6 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center font-medium shrink-0">
                  {{ task.assignee.name.charAt(0) }}
                </div>
                <span class="text-xs text-gray-600">{{ task.assignee.name }}</span>
              </div>
              <span v-else class="text-xs text-gray-400">未指派</span>
            </td>
            <td class="px-4 py-3">
              <span
                class="text-xs font-medium"
                :class="task.is_overdue ? 'text-red-600' : 'text-gray-600'"
              >
                {{ task.is_overdue ? '⚠️ ' : '' }}{{ task.end_date }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span
                v-if="task.priority"
                class="inline-flex px-2 py-0.5 rounded text-xs font-medium"
                :style="{ backgroundColor: task.priority.color + '20', color: task.priority.color }"
              >
                {{ task.priority.name }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span
                v-if="task.status"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium"
                :style="{ backgroundColor: task.status.color + '20', color: task.status.color }"
              >
                {{ task.status.icon }} {{ task.status.name }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-1.5">
                <div class="flex-1 bg-gray-100 rounded-full h-1.5">
                  <div
                    class="h-1.5 rounded-full"
                    :class="task.is_overdue ? 'bg-red-400' : 'bg-blue-500'"
                    :style="{ width: task.progress + '%' }"
                  />
                </div>
                <span class="text-xs text-gray-400 w-7 text-right">{{ task.progress }}%</span>
              </div>
            </td>
          </tr>

          <tr v-if="filteredTasks.length === 0">
            <td colspan="8" class="px-4 py-12 text-center text-sm text-gray-400">
              {{ activeTab === 'overdue' ? '沒有逾期任務 🎉' : '目前沒有待辦任務' }}
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Legend -->
      <div class="px-4 py-3 border-t border-gray-100 bg-gray-50 flex gap-4 text-xs text-gray-400">
        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span> 逾期</span>
        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-yellow-400 inline-block"></span> 高優先級</span>
        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-400 inline-block"></span> 進行中</span>
        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span> 已完成</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useTodoStore } from '@/stores/todo'

const store = useTodoStore()
const activeTab = ref('all')

const tabs = [
  { label: '全部', value: 'all' },
  { label: '待辦', value: 'pending' },
  { label: '進行中', value: 'in_progress' },
  { label: '逾期', value: 'overdue' },
]

const filteredTasks = computed(() => {
  if (activeTab.value === 'overdue') return store.tasks.filter(t => t.is_overdue)
  if (activeTab.value === 'pending') return store.tasks.filter(t => t.progress === 0)
  if (activeTab.value === 'in_progress') return store.tasks.filter(t => t.progress > 0 && t.progress < 100)
  return store.tasks
})

function getTabCount(tab: string) {
  if (tab === 'overdue') return store.tasks.filter(t => t.is_overdue).length
  if (tab === 'pending') return store.tasks.filter(t => t.progress === 0).length
  if (tab === 'in_progress') return store.tasks.filter(t => t.progress > 0 && t.progress < 100).length
  return store.tasks.length
}

onMounted(() => store.fetch())
</script>
