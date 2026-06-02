<template>
  <div class="gantt-wrap">
    <div class="d-flex align-center gap-2 mb-3 flex-wrap">
      <span class="text-caption text-grey">檢視模式：</span>
      <v-btn-toggle v-model="currentMode" mandatory density="compact" rounded="lg" color="primary">
        <v-btn v-for="m in modes" :key="m.value" :value="m.value" size="small" @click="switchMode(m.value)">
          {{ m.label }}
        </v-btn>
      </v-btn-toggle>
      <v-spacer />
      <v-switch
        v-model="hideCompleted"
        color="primary"
        density="compact"
        hide-details
        inset
        label="隱藏已完成"
        class="pms-hide-done"
      />
    </div>
    <div class="gantt-outer">
      <EmptyState
        v-if="visibleTasks.length === 0"
        icon="mdi-chart-gantt"
        :title="hideCompleted && props.tasks.length > 0 ? '所有任務已完成' : '尚無任務'"
        :sub="hideCompleted && props.tasks.length > 0 ? '關閉「隱藏已完成」可看到歷程' : '新增任務後甘特圖將自動顯示'"
      />
      <div v-show="visibleTasks.length > 0" ref="ganttEl" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import Gantt from 'frappe-gantt'
import type { Task } from '@/stores/project'
import EmptyState from '@/components/ui/EmptyState.vue'

const LS_HIDE_DONE = 'pms.gantt.hideCompleted'

const props = defineProps<{ tasks: Task[] }>()
const emit = defineEmits<{
  taskClick: [task: Task]
  taskDateChange: [id: number, start: string, end: string]
}>()

const ganttEl = ref<HTMLElement | null>(null)
const currentMode = ref<'Day' | 'Week' | 'Month'>('Day')
const hideCompleted = ref<boolean>(
  (() => {
    const v = localStorage.getItem(LS_HIDE_DONE)
    return v === null ? true : v === '1'
  })(),
)
let ganttInstance: InstanceType<typeof Gantt> | null = null

const modes = [
  { label: '日', value: 'Day' as const },
  { label: '週', value: 'Week' as const },
  { label: '月', value: 'Month' as const },
]

const visibleTasks = computed(() =>
  hideCompleted.value ? props.tasks.filter(t => !t.is_completed) : props.tasks,
)

function toGanttTasks() {
  return visibleTasks.value.map(t => ({
    id: String(t.id),
    name: t.name,
    start: t.start_date.slice(0, 10),
    end: t.end_date.slice(0, 10),
    progress: t.progress,
    custom_class: t.is_completed ? 'done' : '',
  }))
}

function initGantt() {
  if (!ganttEl.value || visibleTasks.value.length === 0) return
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

function destroyGantt() {
  ganttInstance = null
  if (ganttEl.value) ganttEl.value.innerHTML = ''
}

async function rerender() {
  await nextTick()
  if (visibleTasks.value.length === 0) {
    destroyGantt()
    return
  }
  if (!ganttInstance) {
    initGantt()
  } else {
    ganttInstance.refresh(toGanttTasks())
  }
}

function switchMode(mode: 'Day' | 'Week' | 'Month') {
  currentMode.value = mode
  if (ganttInstance) ganttInstance.change_view_mode(mode)
}

onMounted(async () => {
  await nextTick()
  initGantt()
})

watch(() => props.tasks, rerender, { deep: true })

watch(hideCompleted, (v) => {
  localStorage.setItem(LS_HIDE_DONE, v ? '1' : '0')
  rerender()
})

onBeforeUnmount(destroyGantt)
</script>

<style>
.gantt-outer {
  overflow: auto;
  max-height: 600px;
  border-radius: 8px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
}
.pms-hide-done :deep(.v-label) {
  font-size: 12px;
  opacity: 0.75;
}

.gantt .bar-wrapper .bar        { fill: #ffe1ba; outline: none;}
.gantt .bar-wrapper.done .bar   { fill: #43A047; }
.gantt .bar-progress            { fill: #fda150; }
.gantt .bar-wrapper.done .bar-progress { fill: rgb(16, 185, 129);}
</style>
