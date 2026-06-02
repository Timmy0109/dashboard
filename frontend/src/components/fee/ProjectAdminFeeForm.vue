<template>
  <v-dialog
    :model-value="true"
    max-width="520"
    scrollable
    persistent
    @update:model-value="$emit('close')"
  >
    <v-card rounded="xl" class="pms-modal">
      <v-card-title
        class="pa-5 pb-4 d-flex align-center justify-space-between bg-primary rounded-t-xl"
      >
        <div class="d-flex align-center gap-2">
          <v-icon
            :icon="props.fee ? 'mdi-cash-edit' : 'mdi-cash-plus'"
            color="white"
            size="20"
          />
          <span class="text-body-1 font-weight-semibold text-white">
            {{ props.fee ? '編輯行政費用' : '新增行政費用' }}
          </span>
        </div>
        <v-btn icon="mdi-close" variant="text" size="small" color="white" @click="$emit('close')" />
      </v-card-title>

      <v-card-text class="pa-6">
        <v-form ref="formRef" @submit.prevent="handleSubmit">
          <div class="pms-section">
            <div class="pms-section-title">
              <v-icon icon="mdi-cash" size="16" class="mr-1" />費用資訊
            </div>
            <v-row dense>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model.number="form.amount"
                  label="金額"
                  type="number"
                  prefix="NT$"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  :rules="[(v: number) => (v !== null && v !== undefined && v !== ('' as any) && v >= 0) || '請輸入金額']"
                  required
                  autofocus
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.incurred_on"
                  label="發生日期"
                  type="date"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                />
              </v-col>
            </v-row>
          </div>

          <div class="pms-section">
            <div class="pms-section-title">
              <v-icon icon="mdi-note-text-outline" size="16" class="mr-1" />備註
            </div>
            <v-textarea
              v-model="form.note"
              rows="2"
              auto-grow
              variant="outlined"
              density="comfortable"
              placeholder="可選：用途、廠商、說明..."
              hide-details="auto"
            />
          </div>

          <div class="pms-section">
            <div class="pms-section-title">
              <v-icon icon="mdi-paperclip" size="16" class="mr-1" />收據／單據
            </div>

            <v-file-input
              v-model="newFiles"
              label="附加收據"
              variant="outlined"
              density="comfortable"
              multiple
              prepend-icon=""
              prepend-inner-icon="mdi-paperclip"
              hide-details="auto"
              show-size
            />

            <div v-if="existingAttachments.length" class="mt-3">
              <div class="text-caption text-medium-emphasis mb-1">已附加</div>
              <v-list density="compact" class="pa-0 bg-transparent">
                <v-list-item
                  v-for="att in existingAttachments"
                  :key="att.id"
                  class="px-2 rounded-lg mb-1 pms-att-row"
                >
                  <template #prepend>
                    <v-icon icon="mdi-file-document-outline" size="18" />
                  </template>
                  <v-list-item-title class="text-body-2">
                    {{ att.original_name }}
                  </v-list-item-title>
                  <v-list-item-subtitle class="text-caption">
                    {{ att.size_human }}
                  </v-list-item-subtitle>
                  <template #append>
                    <v-btn
                      icon="mdi-delete"
                      size="x-small"
                      variant="text"
                      color="error"
                      @click="removeAttachment(att.id)"
                    />
                  </template>
                </v-list-item>
              </v-list>
            </div>
          </div>

          <v-alert
            v-if="errorMsg"
            type="error"
            variant="tonal"
            density="compact"
            class="mt-2 mb-1 text-body-2"
          >
            {{ errorMsg }}
          </v-alert>
        </v-form>
      </v-card-text>

      <v-divider />

      <v-card-actions class="px-6 py-4">
        <v-btn variant="text" color="grey-darken-1" :disabled="saving" @click="$emit('close')">
          取消
        </v-btn>
        <v-spacer />
        <v-btn color="primary" rounded="lg" :loading="saving" @click="handleSubmit">
          {{ props.fee ? '儲存變更' : '建立' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { reactive, ref, computed, onMounted } from 'vue'
import { useFeeStore } from '@/stores/fee'
import { useToast } from '@/composables/useToast'
import api from '@/lib/axios'
import type { ProjectAdminFee, ProjectAdminFeeAttachment } from '@/types/fee'

const props = defineProps<{ projectId: number; fee?: ProjectAdminFee | null }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const feeStore = useFeeStore()
const toast = useToast()

const formRef = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null)
const saving = ref(false)
const errorMsg = ref('')

const form = reactive({
  amount: '' as number | '',
  incurred_on: '',
  note: '',
})

const newFiles = ref<File[]>([])
const existingAttachments = ref<ProjectAdminFeeAttachment[]>([])

const todayStr = computed(() => new Date().toISOString().slice(0, 10))

onMounted(() => {
  if (props.fee) {
    form.amount = Number(props.fee.amount)
    form.incurred_on = props.fee.incurred_on?.slice(0, 10) ?? ''
    form.note = props.fee.note ?? ''
    existingAttachments.value = [...(props.fee.attachments ?? [])]
  } else {
    form.incurred_on = todayStr.value
  }
})

async function handleSubmit() {
  const valid = await formRef.value?.validate()
  if (valid && !valid.valid) return

  saving.value = true
  errorMsg.value = ''
  try {
    let target: ProjectAdminFee
    if (props.fee) {
      target = await feeStore.updateAdminFee(props.fee, {
        amount: Number(form.amount),
        note: form.note || null,
        incurred_on: form.incurred_on || null,
      })
    } else {
      target = await feeStore.createAdminFee(props.projectId, {
        amount: Number(form.amount),
        note: form.note || undefined,
        incurred_on: form.incurred_on || undefined,
      })
    }

    if (newFiles.value.length) {
      for (const file of newFiles.value) {
        const fd = new FormData()
        fd.append('file', file)
        await api.post(`/project-admin-fees/${target.id}/attachments`, fd)
      }
      // refresh list so attachments reflect on edit/list view
      await feeStore.fetchAdminFees(props.projectId)
    }

    toast.success(props.fee ? '已更新行政費用' : '已新增行政費用')
    emit('saved')
  } catch (err: any) {
    const msg = err?.response?.data?.message ?? '儲存失敗，請重試'
    errorMsg.value = msg
    toast.error(msg)
  } finally {
    saving.value = false
  }
}

async function removeAttachment(id: number) {
  if (!confirm('確定要移除這份收據？')) return
  try {
    await api.delete(`/project-admin-fee-attachments/${id}`)
    existingAttachments.value = existingAttachments.value.filter(a => a.id !== id)
    toast.success('已移除收據')
    // refresh store list
    await feeStore.fetchAdminFees(props.projectId)
  } catch {
    toast.error('移除失敗，請重試')
  }
}
</script>

<style scoped>
.pms-modal :deep(.v-field) {
  border-radius: 10px;
}
.pms-section + .pms-section {
  margin-top: 18px;
}
.pms-section-title {
  display: flex;
  align-items: center;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.04em;
  color: rgba(0, 0, 0, 0.6);
  margin-bottom: 10px;
  text-transform: uppercase;
}
.pms-att-row {
  background-color: rgba(0, 0, 0, 0.03);
}
</style>
