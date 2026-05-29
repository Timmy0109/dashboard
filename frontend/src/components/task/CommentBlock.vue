<script setup lang="ts">
import { computed } from 'vue'
import type { Comment } from '@/types/comment'

const props = withDefaults(
  defineProps<{
    comment: Comment
    canDelete?: boolean
    showReply?: boolean
  }>(),
  {
    canDelete: false,
    showReply: true,
  },
)

const emit = defineEmits<{
  reply: []
  delete: []
}>()

/** Highlight @mentions in body — split into spans. */
interface BodyPart {
  type: 'text' | 'mention'
  value: string
  key: string
}

const bodyParts = computed<BodyPart[]>(() => {
  const text = props.comment.body
  // match @ followed by non-whitespace characters
  const re = /@([^\s@]+)/g
  const out: BodyPart[] = []
  let lastIdx = 0
  let m: RegExpExecArray | null
  let i = 0
  while ((m = re.exec(text)) !== null) {
    if (m.index > lastIdx) {
      out.push({ type: 'text', value: text.slice(lastIdx, m.index), key: `t${i++}` })
    }
    out.push({ type: 'mention', value: m[0], key: `m${i++}` })
    lastIdx = m.index + m[0].length
  }
  if (lastIdx < text.length) {
    out.push({ type: 'text', value: text.slice(lastIdx), key: `t${i++}` })
  }
  return out
})

function formatTime(iso: string) {
  return new Intl.DateTimeFormat('zh-TW', {
    month: 'numeric',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(new Date(iso))
}
</script>

<template>
  <div class="d-flex">
    <v-avatar size="32" color="primary" class="mr-2 shrink-0 mt-1">
      <v-img v-if="comment.user.avatar_url" :src="comment.user.avatar_url" />
      <span v-else class="text-caption text-white font-weight-bold">
        {{ comment.user.name.charAt(0) }}
      </span>
    </v-avatar>
    <div class="grow">
      <div class="d-flex align-center justify-space-between">
        <div class="d-flex align-center">
          <span class="text-body-2 font-weight-bold">{{ comment.user.name }}</span>
          <span class="text-caption text-grey ml-2">{{ formatTime(comment.created_at) }}</span>
          <v-chip
            v-if="comment._optimistic"
            size="x-small"
            variant="tonal"
            color="grey"
            class="ml-2"
          >
            傳送中
          </v-chip>
        </div>
        <v-menu v-if="canDelete && !comment._optimistic" offset-y>
          <template #activator="{ props: mProps }">
            <v-btn
              v-bind="mProps"
              icon="mdi-dots-vertical"
              size="x-small"
              variant="text"
              density="compact"
            />
          </template>
          <v-list density="compact">
            <v-list-item
              prepend-icon="mdi-delete-outline"
              title="刪除"
              @click="emit('delete')"
            />
          </v-list>
        </v-menu>
      </div>
      <div class="pms-comment__body mt-1">
        <template v-for="p in bodyParts" :key="p.key">
          <span v-if="p.type === 'mention'" class="pms-mention">{{ p.value }}</span>
          <span v-else>{{ p.value }}</span>
        </template>
      </div>
      <div v-if="showReply && !comment._optimistic" class="mt-1">
        <v-btn
          size="x-small"
          variant="text"
          density="compact"
          prepend-icon="mdi-reply"
          @click="emit('reply')"
        >
          回覆
        </v-btn>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pms-comment__body {
  white-space: pre-wrap;
  word-break: break-word;
  font-size: 0.875rem;
  line-height: 1.5;
}

.pms-mention {
  color: rgb(var(--v-theme-primary));
  background-color: rgba(var(--v-theme-primary), 0.08);
  border-radius: 4px;
  padding: 0 4px;
  font-weight: 500;
}
</style>
