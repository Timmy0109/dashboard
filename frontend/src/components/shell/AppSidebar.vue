<script setup lang="ts">
// AppSidebar — 左側永遠深色導航抽屜，支援 rail 模式收合。
// 內部直接讀 auth / feature store；登出動作透過 emit 由上層處理。
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useFeatureStore } from '@/stores/feature'

const emit = defineEmits<{
  (e: 'logout'): void
}>()

const auth = useAuthStore()
const feature = useFeatureStore()
const route = useRoute()

const drawer = ref(true)
const rail = ref(false)

interface NavItem {
  to: string
  icon: string
  label: string
}

const navItems = computed<NavItem[]>(() => {
  if (auth.isAdmin) {
    // 成員管理已整合進系統管理頁面，admin 側欄不再單獨列出
    return [
      { to: '/projects', icon: 'mdi-folder-multiple', label: '專案管理' },
      { to: '/settings', icon: 'mdi-cog', label: '設定管理' },
      { to: '/system', icon: 'mdi-domain', label: '系統管理' },
    ]
  }

  const items: NavItem[] = [
    { to: '/', icon: 'mdi-view-dashboard', label: '首頁總覽' },
    { to: '/projects', icon: 'mdi-folder-multiple', label: '專案管理' },
    { to: '/todo', icon: 'mdi-checkbox-marked-outline', label: '每日任務' },
  ]

  if (feature.has('report.stats_dashboard')) {
    items.push({ to: '/stats', icon: 'mdi-chart-bar', label: '統計分析' })
  }

  if (auth.isManager && feature.has('member.approval_required')) {
    items.push({ to: '/manager/approvals', icon: 'mdi-account-check', label: '成員管理' })
  }

  return items
})

function isActive(path: string) {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}

function handleLogout() {
  emit('logout')
}
</script>

<template>
  <v-navigation-drawer v-model="drawer" :rail="rail" permanent color="grey-darken-4" width="220">
    <v-list-item
      prepend-icon="mdi-briefcase-variant"
      title="專案管理系統"
      :subtitle="auth.user?.name"
      nav
      class="py-4"
    />

    <!-- 收合 / 展開切換 — rail 模式下保持可點 -->
    <v-list density="compact" nav class="py-0">
      <v-list-item
        :prepend-icon="rail ? 'mdi-chevron-right' : 'mdi-chevron-left'"
        :title="rail ? '展開選單' : '收合選單'"
        rounded="lg"
        @click="rail = !rail"
      />
    </v-list>

    <v-divider color="grey-darken-3" />

    <v-list density="compact" nav class="mt-1">
      <v-list-item
        v-for="item in navItems"
        :key="item.to"
        :prepend-icon="item.icon"
        :title="item.label"
        :to="item.to"
        :active="isActive(item.to)"
        color="primary"
        rounded="lg"
        class="mb-0.5"
      />
    </v-list>

    <template #append>
      <v-divider color="grey-darken-3" />
      <v-list density="compact" nav class="py-1">
        <v-list-item
          prepend-icon="mdi-logout"
          title="登出"
          rounded="lg"
          @click="handleLogout"
        />
      </v-list>
    </template>
  </v-navigation-drawer>
</template>
