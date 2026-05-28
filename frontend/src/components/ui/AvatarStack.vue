<script setup lang="ts">
// AvatarStack — 多名成員頭像疊圖，超過 max 顯示 +N
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  names: string[]
  max?: number
  size?: number
}>(), {
  max: 5,
  size: 28,
})

const visible = computed(() => props.names.slice(0, props.max))
const overflow = computed(() => Math.max(0, props.names.length - props.max))

// 同一份 names 經過簡單 hash 給出穩定顏色
const palette = ['primary', 'success', 'warning', 'error', 'info', 'purple']
function colorFor(name: string) {
  let h = 0
  for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) >>> 0
  return palette[h % palette.length]
}
function initial(name: string) {
  return name.trim().charAt(0).toUpperCase()
}
</script>

<template>
  <div class="pms-avatar-stack d-inline-flex align-center">
    <v-tooltip
      v-for="(n, i) in visible"
      :key="n + i"
      location="top"
      :text="n"
    >
      <template #activator="{ props: tip }">
        <v-avatar
          v-bind="tip"
          :size="size"
          :color="colorFor(n)"
          class="pms-avatar-stack__item"
          :style="{ marginLeft: i === 0 ? '0' : `-${Math.round(size * 0.3)}px` }"
        >
          <span class="text-caption font-weight-bold text-white">{{ initial(n) }}</span>
        </v-avatar>
      </template>
    </v-tooltip>

    <v-avatar
      v-if="overflow > 0"
      :size="size"
      color="surface-variant"
      class="pms-avatar-stack__item pms-avatar-stack__more"
      :style="{ marginLeft: `-${Math.round(size * 0.3)}px` }"
    >
      <span class="text-caption font-weight-bold">+{{ overflow }}</span>
    </v-avatar>
  </div>
</template>

<style scoped>
.pms-avatar-stack__item {
  border: 2px solid rgb(var(--v-theme-surface));
  box-sizing: content-box;
}
.pms-avatar-stack__more {
  color: rgb(var(--v-theme-on-surface));
}
</style>
