<template>
  <v-dialog :model-value="true" max-width="440" persistent @update:model-value="$emit('close')">
    <v-card rounded="xl">
      <v-card-title class="pa-5 pb-3 d-flex align-center justify-space-between bg-primary rounded-t-xl">
        <span class="text-body-1 font-weight-semibold text-white">個人資料</span>
        <v-btn icon="mdi-close" variant="text" size="small" color="white" @click="$emit('close')" />
      </v-card-title>

      <v-card-text class="pa-6">
        <!-- Avatar section -->
        <div class="d-flex flex-column align-center mb-6">
          <div class="position-relative mb-3" style="width:96px;height:96px">
            <v-avatar size="96" color="primary" style="cursor:pointer" @click="triggerFileInput">
              <v-img v-if="previewUrl || auth.user?.avatar_url" :src="previewUrl || auth.user?.avatar_url!" cover />
              <span v-else class="text-h5 font-weight-bold text-white">
                {{ auth.user?.name?.charAt(0)?.toUpperCase() }}
              </span>
            </v-avatar>
            <v-btn
              icon="mdi-camera"
              size="x-small"
              color="primary"
              style="position:absolute;bottom:0;right:0"
              elevation="2"
              @click="triggerFileInput"
            />
          </div>
          <span class="text-caption text-grey">點擊頭像更換照片（JPG / PNG，最大 2MB）</span>
          <input
            ref="fileInput"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            style="display:none"
            @change="onFileChange"
          />
        </div>

        <!-- Upload progress -->
        <v-progress-linear
          v-if="uploadingAvatar"
          indeterminate
          color="primary"
          rounded
          class="mb-4"
        />

        <v-divider class="mb-5" />

        <!-- Name field -->
        <v-text-field
          v-model="name"
          label="顯示名稱"
          prepend-inner-icon="mdi-account"
          variant="outlined"
          density="comfortable"
          class="mb-2"
          :error-messages="nameError"
        />

        <div class="text-caption text-grey mb-4">
          <v-icon size="14" icon="mdi-email-outline" class="mr-1" />
          {{ auth.user?.email }}
        </div>

        <v-alert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-4 text-body-2">
          {{ errorMsg }}
        </v-alert>

        <div class="d-flex gap-4">
          <v-btn variant="outlined" color="grey" style="flex:1" @click="$emit('close')">取消</v-btn>
          <v-btn color="primary" style="flex:1" :loading="saving" :disabled="!nameChanged" @click="saveName">儲存名稱</v-btn>
        </div>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'

const emit = defineEmits<{ close: [] }>()

const auth = useAuthStore()
const toast = useToast()

const name = ref(auth.user?.name ?? '')
const saving = ref(false)
const uploadingAvatar = ref(false)
const errorMsg = ref('')
const nameError = ref('')
const previewUrl = ref<string | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

const nameChanged = computed(() => name.value.trim() !== '' && name.value.trim() !== auth.user?.name)

function triggerFileInput() {
  fileInput.value?.click()
}

async function onFileChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return

  if (file.size > 2 * 1024 * 1024) {
    toast.error('檔案大小不可超過 2MB')
    return
  }

  previewUrl.value = URL.createObjectURL(file)
  uploadingAvatar.value = true
  try {
    await auth.updateAvatar(file)
    toast.success('頭像已更新')
  } catch {
    toast.error('頭像上傳失敗')
    previewUrl.value = null
  } finally {
    uploadingAvatar.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

async function saveName() {
  const trimmed = name.value.trim()
  if (!trimmed) { nameError.value = '名稱不可空白'; return }
  nameError.value = ''
  errorMsg.value = ''
  saving.value = true
  try {
    await auth.updateProfile(trimmed)
    toast.success('名稱已更新')
    emit('close')
  } catch (err: any) {
    errorMsg.value = err?.response?.data?.message ?? '儲存失敗，請重試'
  } finally {
    saving.value = false
  }
}
</script>
