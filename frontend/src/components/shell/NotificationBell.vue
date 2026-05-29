<script setup lang="ts">
// NotificationBell — 上方 app-bar 的通知鈴鐺。
// pulse-dot badge 表示有未讀，點開顯示 10 筆最近通知 + 全部已讀 + 查看全部。
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import { useRouter } from 'vue-router'
import EmptyState from '@/components/ui/EmptyState.vue'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notification'
import type { Notification } from '@/types/notification'

const auth = useAuthStore()
const notificationStore = useNotificationStore()

const router = useRouter()
const menuOpen = ref(false)
const expanded = ref(false)

onMounted(async () => {
  await notificationStore.fetch()
  if (auth.user?.id) {
    notificationStore.listenForRealtime(auth.user.id)
  }
})

onBeforeUnmount(() => {
  notificationStore.stopListening()
})

const VISIBLE_COLLAPSED = 10

const visible = computed<Notification[]>(() =>
  expanded.value
    ? notificationStore.notifications
    : notificationStore.notifications.slice(0, VISIBLE_COLLAPSED),
)

const unreadCount = computed(() => notificationStore.unreadCount)
const totalCount = computed(() => notificationStore.notifications.length)
const hasMore = computed(() => notificationStore.nextCursor !== null)
const loading = computed(() => notificationStore.loading)

function titleOf(n: Notification): string {
  // payload shape varies per type — cast to any locally for ergonomics.
  const p = n.payload as any
  switch (n.type) {
    case 'task_assigned':
      return `${p.actor_name} 指派了任務「${p.task_name}」`
    case 'task_mentioned':
      return `${p.actor_name} 在留言提及了你`
    case 'task_status_changed':
      return `「${p.task_name}」狀態變更為 ${p.to}`
    case 'task_replied':
      return `${p.actor_name} 回覆了你的留言`
    default:
      return '新的通知'
  }
}

function snippetOf(n: Notification): string {
  const p = n.payload as any
  if (n.type === 'task_mentioned' || n.type === 'task_replied') {
    return p.snippet ?? ''
  }
  return ''
}

function iconOf(n: Notification): string {
  switch (n.type) {
    case 'task_assigned': return 'mdi-account-plus'
    case 'task_mentioned': return 'mdi-at'
    case 'task_status_changed': return 'mdi-flag'
    case 'task_replied': return 'mdi-reply'
    default: return 'mdi-bell'
  }
}

function onClickItem(n: Notification) {
  const p = n.payload as any
  const projectId = p.project_id
  const taskId = p.task_id
  notificationStore.markAsRead(n.id)
  menuOpen.value = false
  router.push({
    path: `/projects/${projectId}`,
    query: { openTask: String(taskId), taskTab: 'comments' },
  })
}

function onMarkAllRead() {
  notificationStore.markAllRead()
}

async function onExpand() {
  expanded.value = true
  if (notificationStore.nextCursor) {
    await notificationStore.fetchMore()
  }
}

function onCollapse() {
  expanded.value = false
}

async function onLoadMore() {
  await notificationStore.fetchMore()
}
</script>

<template>
  <v-menu
    v-model="menuOpen"
    :close-on-content-click="false"
    location="bottom end"
    offset="8"
  >
    <template #activator="{ props: activatorProps }">
      <v-btn
        v-bind="activatorProps"
        icon
        variant="text"
        density="comfortable"
        title="通知"
        class="mr-1 pms-bell"
      >
        <v-icon icon="mdi-bell-outline" />
        <span v-if="unreadCount > 0" class="pms-bell__dot" />
      </v-btn>
    </template>

    <v-card width="380" class="pms-bell-panel">
      <div class="d-flex align-center justify-space-between pa-3">
        <div class="d-flex align-center gap-2">
          <span class="text-subtitle-2 font-weight-semibold">通知</span>
          <v-chip
            v-if="unreadCount > 0"
            size="x-small"
            color="error"
            variant="tonal"
            density="compact"
          >
            {{ unreadCount }} 未讀
          </v-chip>
        </div>
        <v-btn
          v-if="unreadCount > 0"
          variant="text"
          size="small"
          density="comfortable"
          @click="onMarkAllRead"
        >
          全部已讀
        </v-btn>
      </div>
      <v-divider />

      <div v-if="visible.length === 0" class="py-4">
        <EmptyState
          icon="mdi-bell-off-outline"
          title="目前沒有通知"
          sub="新的指派、提及或回覆會出現在這裡"
        />
      </div>

      <v-list
        v-else
        density="compact"
        nav
        class="py-1"
        :max-height="expanded ? 560 : 420"
      >
        <v-list-item
          v-for="n in visible"
          :key="n.id"
          :prepend-icon="iconOf(n)"
          :class="{ 'pms-bell-item--unread': !n.read_at }"
          rounded="lg"
          @click="onClickItem(n)"
        >
          <v-list-item-title class="text-body-2">
            {{ titleOf(n) }}
          </v-list-item-title>
          <v-list-item-subtitle v-if="snippetOf(n)" class="text-caption">
            {{ snippetOf(n) }}
          </v-list-item-subtitle>
        </v-list-item>

        <div v-if="expanded && hasMore" class="d-flex justify-center pa-2">
          <v-btn
            variant="text"
            size="small"
            :loading="loading"
            @click="onLoadMore"
          >
            載入更多
          </v-btn>
        </div>
      </v-list>

      <v-divider />
      <div class="pa-2 d-flex justify-center">
        <v-btn
          v-if="!expanded && totalCount > VISIBLE_COLLAPSED"
          variant="text"
          size="small"
          block
          :loading="loading"
          @click="onExpand"
        >
          查看全部（{{ totalCount }}）
        </v-btn>
        <v-btn
          v-else-if="!expanded && hasMore"
          variant="text"
          size="small"
          block
          :loading="loading"
          @click="onExpand"
        >
          查看全部
        </v-btn>
        <v-btn
          v-else-if="expanded"
          variant="text"
          size="small"
          block
          @click="onCollapse"
        >
          收合
        </v-btn>
      </div>
    </v-card>
  </v-menu>
</template>

<style scoped>
.pms-bell {
  position: relative;
}
.pms-bell__dot {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: rgb(var(--v-theme-error));
  box-shadow: 0 0 0 2px rgb(var(--v-theme-surface));
  animation: pms-bell-pulse 1.6s ease-in-out infinite;
}
@keyframes pms-bell-pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%      { opacity: 0.6; transform: scale(1.25); }
}
.pms-bell-item--unread {
  background-color: rgba(var(--v-theme-primary), 0.06);
}
</style>
