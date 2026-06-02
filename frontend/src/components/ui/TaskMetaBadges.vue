<script setup lang="ts">
// TaskMetaBadges — 任務的附件 / 留言 / 費用計數小標。
// 全部 0 時不渲染；費用有 pending 時點顯紅 + 變黃色字
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  attachmentsCount?: number
  commentsCount?: number
  feesCount?: number
  feesPendingCount?: number
}>(), {
  attachmentsCount: 0,
  commentsCount: 0,
  feesCount: 0,
  feesPendingCount: 0,
})

const showAttachments = computed(() => (props.attachmentsCount ?? 0) > 0)
const showComments    = computed(() => (props.commentsCount ?? 0) > 0)
const showFees        = computed(() => (props.feesCount ?? 0) > 0)
const hasPendingFees  = computed(() => (props.feesPendingCount ?? 0) > 0)
const renderNothing   = computed(() =>
  !showAttachments.value && !showComments.value && !showFees.value,
)
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
    <span
      v-if="showFees"
      class="pms-task-meta__item pms-task-meta__fees"
      :class="{ 'pms-task-meta__fees--pending': hasPendingFees }"
      :title="hasPendingFees ? `${feesPendingCount} 筆待審` : '已核定費用'"
    >
      <v-icon icon="mdi-cash" size="14" />
      <span class="pms-tnum">{{ feesCount }}</span>
      <span v-if="hasPendingFees" class="pms-task-meta__dot" />
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
.pms-task-meta__fees {
  position: relative;
}
.pms-task-meta__fees--pending {
  color: rgb(var(--v-theme-warning));
}
.pms-task-meta__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: rgb(var(--v-theme-error));
  margin-left: 2px;
}
.pms-tnum {
  font-variant-numeric: tabular-nums;
}
</style>
