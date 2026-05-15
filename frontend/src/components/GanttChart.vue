<template>
  <div>
    <div class="flex items-center gap-2 mb-3">
      <span class="text-xs text-gray-500">檢視模式：</span>
      <div class="flex gap-1">
        <button
          v-for="m in modes"
          :key="m.value"
          @click="currentMode = m.value; renderGantt()"
          class="px-2.5 py-1 text-xs rounded transition-colors"
          :class="currentMode === m.value
            ? 'bg-blue-600 text-white'
            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
        >
          {{ m.label }}
        </button>
      </div>
    </div>
    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
      <div ref="ganttEl" class="gantt-wrapper" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import Gantt from 'frappe-gantt'
import type { Task } from '@/stores/project'

const props = defineProps<{ tasks: Task[] }>()
const emit = defineEmits<{
  taskClick: [task: Task]
  taskDateChange: [id: number, start: string, end: string]
}>()

const ganttEl = ref<HTMLElement | null>(null)
const currentMode = ref<'Day' | 'Week' | 'Month'>('Week')
let ganttInstance: InstanceType<typeof Gantt> | null = null

const modes = [
  { label: '日', value: 'Day' as const },
  { label: '週', value: 'Week' as const },
  { label: '月', value: 'Month' as const },
]

function toGanttTasks() {
  return props.tasks.map(t => ({
    id: String(t.id),
    name: t.name,
    start: t.start_date.slice(0, 10),
    end: t.end_date.slice(0, 10),
    progress: t.progress,
    custom_class: t.is_completed ? 'gantt-task-done' : '',
  }))
}

function renderGantt() {
  if (!ganttEl.value || props.tasks.length === 0) return

  ganttEl.value.innerHTML = ''

  ganttInstance = new Gantt(ganttEl.value, toGanttTasks(), {
    view_mode: currentMode.value,
    date_format: 'YYYY-MM-DD',
    popup: false,
    on_click: (task: { id: string }) => {
      const original = props.tasks.find(t => String(t.id) === task.id)
      if (original) emit('taskClick', original)
    },
    on_date_change: (task: { id: string }, start: Date, end: Date) => {
      const toStr = (d: Date) => d.toISOString().slice(0, 10)
      emit('taskDateChange', Number(task.id), toStr(start), toStr(end))
    },
  })
}

onMounted(() => renderGantt())
watch(() => props.tasks, () => renderGantt(), { deep: true })
onBeforeUnmount(() => { ganttInstance = null })
</script>

<style>
.gantt-wrapper svg {
  width: 100%;
}
.gantt .bar-wrapper .bar {
  fill: #3b82f6;
}
.gantt .bar-wrapper.gantt-task-done .bar {
  fill: #10b981;
}
.gantt .bar-progress {
  fill: #1d4ed8;
}
.gantt .bar-wrapper.gantt-task-done .bar-progress {
  fill: #059669;
}
</style>
