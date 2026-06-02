<template>
  <v-dialog
    :model-value="true"
    max-width="520"
    persistent
    @update:model-value="$emit('close')"
  >
    <v-card rounded="xl" class="pms-fee-form">
      <!-- Header -->
      <v-card-title class="pa-5 pb-3 d-flex align-center justify-space-between bg-primary rounded-t-xl">
        <span class="text-body-1 font-weight-semibold text-white">
          {{ isEdit ? '編輯費用' : '新增費用' }}
        </span>
        <v-btn icon="mdi-close" variant="text" size="small" color="white" @click="$emit('close')" />
      </v-card-title>

      <v-card-text class="pa-5 pt-4">
        <v-form @submit.prevent="handleSubmit">
          <v-text-field
            v-model.number="form.amount"
            label="金額"
            type="number"
            prefix="NT$"
            min="0"
            required
            class="mb-3"
            :error-messages="amountError ? [amountError] : []"
          />

          <v-textarea
            v-model="form.note"
            label="備註（選填）"
            rows="3"
            auto-grow
            class="mb-3"
          />

          <!-- Existing attachments (edit mode) -->
          <div v-if="isEdit && existingAttachments.length" class="mb-3">
            <div class="text-caption text-grey mb-2">現有收據</div>
            <v-list density="compact" class="pa-0">
              <v-list-item
                v-for="att in existingAttachments"
                :key="att.id"
                :href="att.is_previewable ? att.download_url : undefined"
                :target="att.is_previewable ? '_blank' : undefined"
                class="px-2"
                rounded="lg"
              >
                <template #prepend>
                  <v-icon size="20" class="mr-2">mdi-paperclip</v-icon>
                </template>
                <v-list-item-title class="text-body-2" style="word-break: break-all;">
                  {{ att.original_name }}
                </v-list-item-title>
                <v-list-item-subtitle class="text-caption">
                  {{ att.size_human }}
                </v-list-item-subtitle>
              </v-list-item>
            </v-list>
          </div>

          <!-- Upload zone -->
          <div
            class="pms-fee-uploader mb-3"
            :class="{ 'pms-fee-uploader--dragging': isDragging }"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
          >
            <v-icon size="28" class="mb-2 text-medium-emphasis">mdi-receipt-text-outline</v-icon>
            <div class="text-body-2 mb-1">拖拉收據到這裡，或點擊選擇</div>
            <div class="text-caption text-grey mb-2">圖片或 PDF</div>
            <v-file-input
              v-model="pendingFile"
              hide-details
              prepend-icon=""
              prepend-inner-icon="mdi-paperclip"
              variant="outlined"
              density="compact"
              label="選擇檔案"
              class="pms-fee-uploader__input"
            />
          </div>

          <v-alert
            v-if="errorMsg"
            type="error"
            variant="tonal"
            density="compact"
            class="mb-3 text-body-2"
          >
            {{ errorMsg }}
          </v-alert>

          <div class="d-flex">
            <v-btn
              variant="outlined"
              color="grey"
              class="grow mr-3"
              :disabled="saving"
              @click="$emit('close')"
            >
              取消
            </v-btn>
            <v-btn
              type="submit"
              color="primary"
              class="grow"
              :loading="saving"
              :disabled="!isAmountValid"
            >
              儲存
            </v-btn>
          </div>
        </v-form>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import api from '@/lib/axios'
import { useFeeStore } from '@/stores/fee'
import { useToast } from '@/composables/useToast'
import type { TaskFee, TaskFeeAttachment } from '@/types/fee'

const props = defineProps<{
  projectId: number
  taskId: number
  fee?: TaskFee | null
}>()
const emit = defineEmits<{ close: []; saved: [] }>()

const feeStore = useFeeStore()
const toast = useToast()

const isEdit = computed(() => !!props.fee)
const existingAttachments = computed<TaskFeeAttachment[]>(() => props.fee?.attachments ?? [])

const form = reactive({
  amount: props.fee ? Number(props.fee.amount) : 0,
  note: props.fee?.note ?? '',
})

const pendingFile = ref<File | null>(null)
const isDragging = ref(false)
const saving = ref(false)
const errorMsg = ref('')

const isAmountValid = computed(() => Number.isFinite(form.amount) && form.amount >= 0)
const amountError = computed(() => (form.amount < 0 ? '金額不可為負' : ''))

function onDrop(e: DragEvent) {
  isDragging.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file) pendingFile.value = file
}

async function uploadReceipt(feeId: number, file: File) {
  const fd = new FormData()
  fd.append('file', file)
  fd.append('task_fee_id', String(feeId))
  await api.post(
    `/projects/${props.projectId}/tasks/${props.taskId}/attachments`,
    fd,
    { headers: { 'Content-Type': 'multipart/form-data' } },
  )
}

async function handleSubmit() {
  if (!isAmountValid.value || saving.value) return
  saving.value = true
  errorMsg.value = ''
  try {
    let feeId: number
    if (props.fee) {
      const updated = await feeStore.updateTaskFee(props.fee, {
        amount: form.amount,
        note: form.note || null,
      })
      feeId = updated.id
    } else {
      const created = await feeStore.createTaskFee(props.projectId, props.taskId, {
        amount: form.amount,
        note: form.note || undefined,
      })
      feeId = created.id
    }

    if (pendingFile.value) {
      await uploadReceipt(feeId, pendingFile.value)
    }

    // Refresh to pick up attachments
    await feeStore.fetchTaskFees(props.projectId, props.taskId)

    toast.success(isEdit.value ? '已更新' : '已新增')
    emit('saved')
    emit('close')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    errorMsg.value = e?.response?.data?.message ?? '儲存失敗，請重試'
    toast.error(errorMsg.value)
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.pms-fee-form {
  overflow: hidden;
}

.pms-fee-uploader {
  border: 2px dashed rgba(var(--v-theme-on-surface), 0.18);
  border-radius: 12px;
  padding: 16px;
  text-align: center;
  transition: border-color 0.15s, background-color 0.15s;
}

.pms-fee-uploader--dragging {
  border-color: rgb(var(--v-theme-primary));
  background-color: rgba(var(--v-theme-primary), 0.04);
}

.pms-fee-uploader__input {
  max-width: 320px;
  margin: 0 auto;
}
</style>
