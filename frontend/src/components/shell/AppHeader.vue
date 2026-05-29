<script setup lang="ts">
// AppHeader — 上方 app-bar：標題、主題切換、通知鈴鐺、使用者 chip。
import { computed } from 'vue'
import { useTheme } from 'vuetify'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'
import NotificationBell from './NotificationBell.vue'

defineProps<{
  title: string
}>()

const emit = defineEmits<{
  (e: 'openProfile'): void
}>()

const auth = useAuthStore()
const themeStore = useThemeStore()
const vuetifyTheme = useTheme()

const isDark = computed(() => themeStore.current === 'dark')

function onToggleTheme() {
  themeStore.toggle(vuetifyTheme)
}

function openProfile() {
  emit('openProfile')
}
</script>

<template>
  <v-app-bar flat :color="isDark ? 'surface' : 'white'" border="b">
    <v-app-bar-title class="text-subtitle-1 font-weight-semibold">
      {{ title }}
    </v-app-bar-title>

    <template #append>
      <v-btn
        :icon="isDark ? 'mdi-weather-sunny' : 'mdi-weather-night'"
        variant="text"
        density="comfortable"
        :title="isDark ? '切換至淺色' : '切換至深色'"
        class="mr-1"
        @click="onToggleTheme"
      />

      <NotificationBell />

      <div
        class="d-flex align-center gap-2 pr-4"
        style="cursor:pointer"
        @click="openProfile"
      >
        <v-avatar color="primary" size="32" class="mr-1">
          <v-img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" cover />
          <span v-else class="text-caption font-weight-bold text-white">
            {{ auth.user?.name?.charAt(0) }}
          </span>
        </v-avatar>
        <span class="text-body-2 d-none d-sm-inline">{{ auth.user?.name }}</span>
        <v-icon icon="mdi-chevron-down" size="16" color="grey" />
      </div>
    </template>
  </v-app-bar>
</template>
