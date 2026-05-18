<template>
  <v-app>
    <v-main class="bg-grey-lighten-3">
      <v-container class="fill-height" fluid>
        <v-row align="center" justify="center">
          <v-col cols="12" sm="8" md="5" lg="4" xl="3">
            <v-card elevation="3" rounded="xl" class="pa-4">
              <v-card-title class="text-center pt-4 pb-2">
                <div class="d-flex flex-column align-center">
                  <v-icon icon="mdi-lock-check-outline" color="primary" size="40" class="mb-3" />
                  <span class="text-h6 font-weight-bold">設定新密碼</span>
                  <span class="text-body-2 text-grey mt-1">請輸入您的新密碼</span>
                </div>
              </v-card-title>

              <v-card-text class="pt-4">
                <template v-if="!done">
                  <v-form @submit.prevent="submit">
                    <v-text-field
                      v-model="form.password"
                      label="新密碼"
                      :type="show ? 'text' : 'password'"
                      prepend-inner-icon="mdi-lock-outline"
                      :append-inner-icon="show ? 'mdi-eye-off' : 'mdi-eye'"
                      @click:append-inner="show = !show"
                      autocomplete="new-password"
                      hint="至少 8 個字元"
                      persistent-hint
                      class="mb-2"
                    />
                    <v-text-field
                      v-model="form.password_confirmation"
                      label="確認新密碼"
                      :type="show ? 'text' : 'password'"
                      prepend-inner-icon="mdi-lock-outline"
                      autocomplete="new-password"
                      class="mb-2"
                    />

                    <v-alert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-3 text-body-2">
                      {{ errorMsg }}
                    </v-alert>

                    <v-btn
                      type="submit"
                      color="primary"
                      block
                      size="large"
                      :loading="loading"
                    >
                      確認重設密碼
                    </v-btn>
                  </v-form>
                </template>

                <template v-else>
                  <v-alert type="success" variant="tonal" class="mb-4">
                    密碼重設成功！請使用新密碼登入。
                  </v-alert>
                  <v-btn color="primary" block :to="{ name: 'login' }">前往登入</v-btn>
                </template>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-container>
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/lib/axios'

const route    = useRoute()
const loading  = ref(false)
const done     = ref(false)
const show     = ref(false)
const errorMsg = ref('')

const form = ref({
  token:                 route.query.token as string ?? '',
  email:                 route.query.email as string ?? '',
  password:              '',
  password_confirmation: '',
})

async function submit() {
  loading.value  = true
  errorMsg.value = ''
  try {
    await api.post('/reset-password', form.value)
    done.value = true
  } catch (err: any) {
    const errors = err?.response?.data?.errors ?? {}
    errorMsg.value = errors?.email?.[0] ?? errors?.password?.[0] ?? '重設失敗，請重新嘗試'
  } finally {
    loading.value = false
  }
}
</script>
