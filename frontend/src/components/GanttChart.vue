<template>
  <div>
    <div class="flex items-center gap-2 mb-3">
      <span class="text-xs text-gray-500">檢視模式：</span>
      <div class="flex gap-1">
        <button
          v-for="m in modes"
          :key="m.value"
          @click="switchMode(m.value)"
          class="px-2.5 py-1 text-xs rounded transition-colors"
          :class="currentMode === m.value
            ? 'bg-blue-600 text-white'
            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
        >
          {{ m.label }}
        </button>
      </div>
    </div>
    <div class="gantt-outer">
      <div ref="ganttEl" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
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
    custom_class: t.is_completed ? 'done' : '',
  }))
}

function initGantt() {
  if (!ganttEl.value || props.tasks.length === 0) return
  ganttInstance = new Gantt(ganttEl.value, toGanttTasks(), {
    view_mode: currentMode.value,
    popup: false,
    today_button: false,
    view_mode_select: false,
    on_click: (task: { id: string }) => {
      const original = props.tasks.find(t => String(t.id) === task.id)
      if (original) emit('taskClick', original)
    },
    on_date_change: (task: { id: string }, start: Date, end: Date) => {
      const fmt = (d: Date) => d.toISOString().slice(0, 10)
      emit('taskDateChange', Number(task.id), fmt(start), fmt(end))
    },
  })
}

function switchMode(mode: 'Day' | 'Week' | 'Month') {
  currentMode.value = mode
  if (ganttInstance) {
    ganttInstance.change_view_mode(mode)
  }
}

onMounted(async () => {
  await nextTick()
  initGantt()
})

watch(() => props.tasks, async () => {
  await nextTick()
  if (!ganttInstance) {
    initGantt()
  } else {
    ganttInstance.refresh(toGanttTasks())
  }
}, { deep: true })

onBeforeUnmount(() => {
  ganttInstance = null
  if (ganttEl.value) ganttEl.value.innerHTML = ''
})
</script>

<style>
/* Override frappe-gantt CSS variables for our color scheme */
.gantt-outer {
  overflow-x: auto;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
}
.gantt-outer .gantt-container {
  border-radius: 0;
  border: none;
}
/* Bar colors */
.gantt-outer :root,
.gantt-outer {
  --g-bar-color: #3b82f6;
  --g-bar-border: #2563eb;
  --g-progress-color: #1d4ed8;
}
.gantt .bar-wrapper .bar {
  fill: #3b82f6;
  stroke: #2563eb;
}
.gantt .bar-wrapper.done .bar {
  fill: #10b981;
  stroke: #059669;
}
.gantt .bar-progress {
  fill: #1d4ed8;
}
.gantt .bar-wrapper.done .bar-progress {
  fill: #047857;
}
</style>
