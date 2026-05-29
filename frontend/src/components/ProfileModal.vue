<script setup lang="ts">
// ProfileModal — 個人資料 + 變更密碼
// 保留 emit('close') 對外 API
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import Tabs from '@/components/ui/Tabs.vue'

const emit = defineEmits<{ close: [] }>()

const auth = useAuthStore()
const toast = useToast()

const tab = ref<'profile' | 'password'>('profile')

const tabItems = [
  { value: 'profile',  label: '個人資料' },
  { value: 'password', label: '變更密碼' },
]

// ---------- 個人資料 ----------
const name = ref(auth.user?.name ?? '')
const savingName = ref(false)
const uploadingAvatar = ref(false)
const nameError = ref('')
const profileError = ref('')
const previewUrl = ref<string | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

const nameChanged = computed(
  () => name.value.trim() !== '' && name.value.trim() !== auth.user?.name,
)

const initial = computed(() => auth.user?.name?.charAt(0)?.toUpperCase() ?? '?')

function triggerFileInput() {
  fileInput.value?.click()
}

async function onFileChange(e: Event) {
  const target = e.target as HTMLInputElement
  const file = target.files?.[0]
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
  if (!trimmed) {
    nameError.value = '名稱不可空白'
    return
  }
  nameError.value = ''
  profileError.value = ''
  savingName.value = true
  try {
    await auth.updateProfile(trimmed)
    toast.success('名稱已更新')
  } catch (err) {
    const e = err as { response?: { data?: { message?: string } } }
    profileError.value = e?.response?.data?.message ?? '儲存失敗，請重試'
  } finally {
    savingName.value = false
  }
}

// ---------- 變更密碼 ----------
const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const showCurrent = ref(false)
const showNew = ref(false)
const showConfirm = ref(false)
const savingPassword = ref(false)
const passwordError = ref('')

const passwordValid = computed(() => {
  return (
    currentPassword.value.length > 0 &&
    newPassword.value.length >= 8 &&
    newPassword.value === confirmPassword.value
  )
})

const passwordHint = computed(() => {
  if (newPassword.value.length === 0) return ''
  if (newPassword.value.length < 8) return '密碼至少 8 個字元'
  if (confirmPassword.value && newPassword.value !== confirmPassword.value) return '兩次輸入不一致'
  return ''
})

async function savePassword() {
  if (!passwordValid.value) return
  passwordError.value = ''
  savingPassword.value = true
  try {
    await auth.updatePassword(currentPassword.value, newPassword.value, confirmPassword.value)
    toast.success('密碼已更新')
    currentPassword.value = ''
    newPassword.value = ''
    confirmPassword.value = ''
    tab.value = 'profile'
  } catch (err) {
    const e = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const firstErr = e?.response?.data?.errors
      ? Object.values(e.response.data.errors)[0]?.[0]
      : undefined
    passwordError.value = firstErr ?? e?.response?.data?.message ?? '密碼變更失敗，請重試'
  } finally {
    savingPassword.value = false
  }
}
</script>

<template>
  <v-dialog :model-value="true" max-width="480" persistent @update:model-value="emit('close')">
    <v-card rounded="xl">
      <v-card-title class="pa-5 pb-3 d-flex align-center justify-space-between bg-primary rounded-t-xl">
        <span class="text-body-1 font-weight-bold text-white">個人資料</span>
        <v-btn icon="mdi-close" variant="text" size="small" color="white" @click="emit('close')" />
      </v-card-title>

      <Tabs v-model="tab" :items="tabItems" class="px-3" />
      <v-divider />

      <v-card-text class="pa-6">
        <!-- 個人資料 tab -->
        <template v-if="tab === 'profile'">
          <div class="d-flex flex-column align-center mb-6">
            <div class="position-relative mb-3" style="width:96px;height:96px">
              <v-avatar size="96" color="primary" style="cursor:pointer" @click="triggerFileInput">
                <v-img
                  v-if="previewUrl || auth.user?.avatar_url"
                  :src="(previewUrl || auth.user?.avatar_url) as string"
                  cover
                />
                <span v-else class="text-h5 font-weight-bold text-white">{{ initial }}</span>
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
            <span class="text-caption text-medium-emphasis">點擊頭像更換照片（JPG / PNG，最大 2MB）</span>
            <input
              ref="fileInput"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              style="display:none"
              @change="onFileChange"
            />
          </div>

          <v-progress-linear
            v-if="uploadingAvatar"
            indeterminate
            color="primary"
            rounded
            class="mb-4"
          />

          <v-divider class="mb-5" />

          <v-text-field
            v-model="name"
            label="顯示名稱"
            prepend-inner-icon="mdi-account"
            variant="outlined"
            density="comfortable"
            class="mb-2"
            :error-messages="nameError"
          />

          <div class="text-caption text-medium-emphasis mb-4 d-flex align-center">
            <v-icon size="14" icon="mdi-email-outline" class="mr-1" />
            {{ auth.user?.email }}
          </div>

          <v-alert
            v-if="profileError"
            type="error"
            variant="tonal"
            density="compact"
            class="mb-4 text-body-2"
          >{{ profileError }}</v-alert>

          <div class="d-flex gap-3">
            <v-btn variant="outlined" color="grey" style="flex:1" @click="emit('close')">取消</v-btn>
            <v-btn
              color="primary"
              variant="flat"
              style="flex:1"
              :loading="savingName"
              :disabled="!nameChanged"
              @click="saveName"
            >儲存名稱</v-btn>
          </div>
        </template>

        <!-- 變更密碼 tab -->
        <template v-else>
          <div class="text-caption text-medium-emphasis mb-4">
            密碼至少 8 個字元，請使用英數字混合以提升安全性。
          </div>

          <v-text-field
            v-model="currentPassword"
            label="目前密碼"
            :type="showCurrent ? 'text' : 'password'"
            prepend-inner-icon="mdi-lock-outline"
            :append-inner-icon="showCurrent ? 'mdi-eye-off' : 'mdi-eye'"
            variant="outlined"
            density="comfortable"
            class="mb-2"
            autocomplete="current-password"
            @click:append-inner="showCurrent = !showCurrent"
          />

          <v-text-field
            v-model="newPassword"
            label="新密碼"
            :type="showNew ? 'text' : 'password'"
            prepend-inner-icon="mdi-lock-plus-outline"
            :append-inner-icon="showNew ? 'mdi-eye-off' : 'mdi-eye'"
            variant="outlined"
            density="comfortable"
            class="mb-2"
            autocomplete="new-password"
            @click:append-inner="showNew = !showNew"
          />

          <v-text-field
            v-model="confirmPassword"
            label="確認新密碼"
            :type="showConfirm ? 'text' : 'password'"
            prepend-inner-icon="mdi-lock-check-outline"
            :append-inner-icon="showConfirm ? 'mdi-eye-off' : 'mdi-eye'"
            variant="outlined"
            density="comfortable"
            class="mb-1"
            autocomplete="new-password"
            :error-messages="passwordHint"
            @click:append-inner="showConfirm = !showConfirm"
          />

          <v-alert
            v-if="passwordError"
            type="error"
            variant="tonal"
            density="compact"
            class="mt-3 mb-2 text-body-2"
          >{{ passwordError }}</v-alert>

          <div class="d-flex gap-3 mt-4">
            <v-btn variant="outlined" color="grey" style="flex:1" @click="emit('close')">取消</v-btn>
            <v-btn
              color="primary"
              variant="flat"
              style="flex:1"
              :loading="savingPassword"
              :disabled="!passwordValid"
              @click="savePassword"
            >更新密碼</v-btn>
          </div>
        </template>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>
