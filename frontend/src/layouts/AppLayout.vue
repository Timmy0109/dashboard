<template>
  <v-navigation-drawer v-model="drawer" :rail="rail" permanent color="grey-darken-4" width="220">
    <!-- Logo / title -->
    <v-list-item
      prepend-icon="mdi-briefcase-variant"
      title="專案管理系統"
      :subtitle="auth.user?.name"
      nav
      class="py-4"
    >
      <template #append>
        <v-btn
          :icon="rail ? 'mdi-chevron-right' : 'mdi-chevron-left'"
          variant="text"
          color="grey-lighten-1"
          size="small"
          @click="rail = !rail"
        />
      </template>
    </v-list-item>

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

  <v-app-bar flat color="white" border="b">
    <v-app-bar-title class="text-subtitle-1 font-weight-semibold text-grey-darken-3">
      {{ currentPageTitle }}
    </v-app-bar-title>

    <template #append>
      <div class="d-flex align-center gap-2 pr-4" style="cursor:pointer" @click="showProfile = true">
        <v-avatar color="primary" size="32" class="mr-1">
          <v-img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" cover />
          <span v-else class="text-caption font-weight-bold text-white">{{ auth.user?.name?.charAt(0) }}</span>
        </v-avatar>
        <span class="text-body-2 text-grey-darken-1 d-none d-sm-inline">{{ auth.user?.name }}</span>
        <v-icon icon="mdi-chevron-down" size="16" color="grey" />
      </div>
    </template>
  </v-app-bar>

  <v-main>
    <v-container fluid class="pa-6">
      <RouterView />
    </v-container>
  </v-main>

  <!-- Profile modal -->
  <ProfileModal v-if="showProfile" @close="showProfile = false" />

  <!-- Global toast queue -->
  <v-snackbar
    v-model="toast.visible"
    :color="toast.current?.color"
    :timeout="toast.current?.timeout ?? 3000"
    location="bottom right"
    rounded="lg"
    @update:model-value="v => { if (!v) toast.dismiss() }"
  >
    <div class="d-flex align-center gap-2">
      <v-icon
        :icon="toast.current?.color === 'success' ? 'mdi-check-circle' :
               toast.current?.color === 'error'   ? 'mdi-alert-circle' :
               toast.current?.color === 'warning'  ? 'mdi-alert' : 'mdi-information'"
        size="18"
      />
      <span class="text-body-2">{{ toast.current?.message }}</span>
    </div>
    <template #actions>
      <v-btn variant="text" icon="mdi-close" size="small" @click="toast.dismiss()" />
    </template>
  </v-snackbar>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useFeatureStore } from '@/stores/feature'
import { useToastStore } from '@/stores/toast'
import ProfileModal from '@/components/ProfileModal.vue'

const auth = useAuthStore()
const feature = useFeatureStore()
const toast = useToastStore()
const route = useRoute()
const router = useRouter()
const drawer = ref(true)
const rail = ref(false)
const showProfile = ref(false)

onMounted(() => {
  if (!feature.loaded) feature.fetch()
})

const navItems = computed(() => {
  if (auth.isAdmin) {
    return [
      { to: '/projects', icon: 'mdi-folder-multiple', label: '專案管理' },
      { to: '/settings', icon: 'mdi-cog', label: '設定管理' },
      { to: '/system', icon: 'mdi-domain', label: '系統管理' },
      { to: '/manager/approvals', icon: 'mdi-account-check', label: '成員管理' },
    ]
  }

  const items = [
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

const pageTitles: Record<string, string> = {
  dashboard: '首頁總覽',
  projects: '專案管理',
  'project-detail': '專案詳情',
  todo: '每日任務',
  stats: '統計分析',
  settings: '設定管理',
  system: '系統管理',
  'member-approvals': '成員管理',
}

const currentPageTitle = computed(() => pageTitles[route.name as string] ?? '')

function isActive(path: string) {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}

async function handleLogout() {
  feature.reset()
  await auth.logout()
  router.push('/login')
}
</script>
