<template>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="text-gray-400 text-sm">載入中...</div>
    </div>

    <table v-else class="w-full">
      <thead>
        <tr class="border-b border-gray-100 bg-gray-50">
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">#</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">專案名稱</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">專案負責人</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">開始日期</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">預計結束日期</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 w-40">進度</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">優先級</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">狀態</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 w-20"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <tr
          v-for="(project, idx) in projects"
          :key="project.id"
          class="hover:bg-gray-50 transition-colors cursor-pointer"
          @click="router.push(`/projects/${project.id}`)"
        >
          <td class="px-4 py-3 text-xs text-gray-400">{{ idx + 1 }}</td>
          <td class="px-4 py-3">
            <div>
              <p class="text-sm font-medium text-gray-800">{{ project.name }}</p>
              <p v-if="project.project_no" class="text-xs text-gray-400">{{ project.project_no }}</p>
            </div>
          </td>
          <td class="px-4 py-3">
            <div v-if="project.owner" class="flex items-center gap-2">
              <div class="w-6 h-6 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center font-medium shrink-0">
                {{ project.owner.name.charAt(0) }}
              </div>
              <span class="text-xs text-gray-600">{{ project.owner.name }}</span>
            </div>
          </td>
          <td class="px-4 py-3 text-xs text-gray-600">{{ formatDate(project.start_date) }}</td>
          <td class="px-4 py-3">
            <span class="text-xs" :class="isOverdue(project) ? 'text-red-600 font-medium' : 'text-gray-600'">
              {{ project.due_date ? formatDate(project.due_date) : '—' }}
              <span v-if="isOverdue(project)" class="material-icons text-sm leading-none align-middle ml-0.5">warning</span>
            </span>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-2">
              <div class="flex-1 bg-gray-100 rounded-full h-1.5">
                <div
                  class="h-1.5 rounded-full transition-all"
                  :class="project.progress_percent >= 100 ? 'bg-green-500' : 'bg-blue-500'"
                  :style="{ width: project.progress_percent + '%' }"
                />
              </div>
              <span class="text-xs text-gray-500 w-8 text-right">{{ project.progress_percent }}%</span>
            </div>
          </td>
          <td class="px-4 py-3">
            <span
              v-if="project.priority"
              class="inline-flex px-2 py-0.5 rounded text-xs font-medium"
              :style="{ backgroundColor: project.priority.color + '20', color: project.priority.color }"
            >
              {{ project.priority.name }}
            </span>
          </td>
          <td class="px-4 py-3">
            <span
              v-if="project.status"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium"
              :style="{ backgroundColor: project.status.color + '20', color: project.status.color }"
            >
              <span class="material-icons text-xs leading-none">{{ project.status.icon }}</span>
              {{ project.status.name }}
            </span>
          </td>
          <td class="px-4 py-3" @click.stop>
            <div v-if="auth.canManageMembers" class="flex gap-1">
              <button
                @click="emit('edit', project)"
                class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                title="編輯"
              ><span class="material-icons text-base leading-none">edit</span></button>
              <button
                @click="emit('delete', project)"
                class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
                title="刪除"
              ><span class="material-icons text-base leading-none">delete</span></button>
            </div>
          </td>
        </tr>
        <tr v-if="projects.length === 0">
          <td colspan="9" class="px-4 py-12 text-center text-sm text-gray-400">
            目前沒有專案，點擊「新增專案」開始
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { ProjectListItem } from '@/stores/project'

defineProps<{ projects: ProjectListItem[]; loading?: boolean }>()
const emit = defineEmits<{ edit: [project: ProjectListItem]; delete: [project: ProjectListItem] }>()

const auth = useAuthStore()
const router = useRouter()

function formatDate(dateStr: string) {
  return dateStr ? dateStr.slice(0, 10) : '—'
}

function isOverdue(project: ProjectListItem) {
  if (!project.due_date || project.is_completed) return false
  return new Date(project.due_date) < new Date()
}
</script>
