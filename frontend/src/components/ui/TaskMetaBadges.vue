<script setup lang="ts">
// TaskMetaBadges — 任務的附件/留言計數小標。兩者皆 0 時不渲染
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  attachmentsCount?: number
  commentsCount?: number
}>(), {
  attachmentsCount: 0,
  commentsCount: 0,
})

const showAttachments = computed(() => (props.attachmentsCount ?? 0) > 0)
const showComments = computed(() => (props.commentsCount ?? 0) > 0)
const renderNothing = computed(() => !showAttachments.value && !showComments.value)
</script>

<template>
  <div
    v-if="!renderNothing"
    class="pms-task-meta d-inline-flex align-center text-caption text-medium-emphasis"
  >
    <span v-if="showAttachments" class="pms-task-meta__item">
      <v-icon icon="mdi-paperclip" size="14" />
      <span class="pms-tnum">{{ attachmentsCount }}</span>
    </span>
    <span v-if="showComments" class="pms-task-meta__item">
      <v-icon icon="mdi-comment-outline" size="14" />
      <span class="pms-tnum">{{ commentsCount }}</span>
    </span>
  </div>
</template>

<style scoped>
.pms-task-meta {
  gap: 10px;
}
.pms-task-meta__item {
  display: inline-flex;
  align-items: center;
  gap: 3px;
}
.pms-tnum {
  font-variant-numeric: tabular-nums;
}
</style>
