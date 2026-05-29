<script setup lang="ts">
// ConfirmDialog — 通用確認對話框 primitive
// 受控元件 (v-model)，可選 danger 變體 (紅色確認鈕)
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    title?: string
    message: string
    confirmText?: string
    cancelText?: string
    danger?: boolean
    loading?: boolean
  }>(),
  {
    title: '確認',
    confirmText: '確認',
    cancelText: '取消',
    danger: false,
    loading: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  confirm: []
  cancel: []
}>()

const open = computed({
  get: () => props.modelValue,
  set: (v: boolean) => emit('update:modelValue', v),
})

const confirmColor = computed(() => (props.danger ? 'error' : 'primary'))
const icon = computed(() => (props.danger ? 'mdi-alert-circle' : 'mdi-help-circle'))
const iconColor = computed(() => (props.danger ? 'error' : 'primary'))

function onCancel() {
  emit('cancel')
  open.value = false
}

function onConfirm() {
  emit('confirm')
}
</script>

<template>
  <v-dialog v-model="open" max-width="420" persistent>
    <v-card rounded="xl">
      <v-card-text class="pa-6">
        <div class="d-flex align-start gap-4">
          <div class="pms-confirm__icon" :class="{ 'pms-confirm__icon--danger': danger }">
            <v-icon :icon="icon" :color="iconColor" size="28" />
          </div>
          <div class="flex-grow-1">
            <div class="text-subtitle-1 font-weight-bold mb-2">{{ title }}</div>
            <div class="text-body-2 text-medium-emphasis" style="white-space: pre-line">
              {{ message }}
            </div>
          </div>
        </div>
      </v-card-text>

      <v-card-actions class="pa-4 pt-0">
        <v-spacer />
        <v-btn variant="text" :disabled="loading" @click="onCancel">{{ cancelText }}</v-btn>
        <v-btn
          :color="confirmColor"
          variant="flat"
          :loading="loading"
          @click="onConfirm"
        >
          {{ confirmText }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.pms-confirm__icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background-color: rgba(var(--v-theme-primary), 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.pms-confirm__icon--danger {
  background-color: rgba(var(--v-theme-error), 0.1);
}
</style>
