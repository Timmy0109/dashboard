<template>
  <AppSidebar @logout="handleLogout" />

  <AppHeader :title="currentPageTitle" @open-profile="showProfile = true" />

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
import { useTheme } from 'vuetify'
import { useAuthStore } from '@/stores/auth'
import { useFeatureStore } from '@/stores/feature'
import { useThemeStore } from '@/stores/theme'
import { useToastStore } from '@/stores/toast'
import ProfileModal from '@/components/ProfileModal.vue'
import AppSidebar from '@/components/shell/AppSidebar.vue'
import AppHeader from '@/components/shell/AppHeader.vue'

const auth = useAuthStore()
const feature = useFeatureStore()
const themeStore = useThemeStore()
const vuetifyTheme = useTheme()
const toast = useToastStore()
const route = useRoute()
const router = useRouter()
const showProfile = ref(false)

onMounted(() => {
  themeStore.init(vuetifyTheme)
  if (!feature.loaded) feature.fetch()
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

async function handleLogout() {
  feature.reset()
  await auth.logout()
  router.push('/login')
}
</script>
