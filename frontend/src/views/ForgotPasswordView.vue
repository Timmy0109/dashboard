<template>
  <v-app>
    <v-main class="bg-grey-lighten-3">
      <v-container class="fill-height" fluid>
        <v-row align="center" justify="center">
          <v-col cols="12" sm="8" md="5" lg="4" xl="3">
            <v-card elevation="3" rounded="xl" class="pa-4">
              <v-card-title class="text-center pt-4 pb-2">
                <div class="d-flex flex-column align-center">
                  <v-icon icon="mdi-lock-reset" color="primary" size="40" class="mb-3" />
                  <span class="text-h6 font-weight-bold">忘記密碼</span>
                  <span class="text-body-2 text-grey mt-1">輸入您的 Email，我們將寄送重設連結</span>
                </div>
              </v-card-title>

              <v-card-text class="pt-4">
                <template v-if="!sent">
                  <v-form @submit.prevent="submit">
                    <v-text-field
                      v-model="email"
                      label="Email"
                      type="email"
                      prepend-inner-icon="mdi-email-outline"
                      autocomplete="email"
                      placeholder="your@email.com"
                      :error-messages="errorMsg ? [errorMsg] : []"
                      required
                      class="mb-2"
                    />
                    <v-btn
                      type="submit"
                      color="primary"
                      block
                      size="large"
                      :loading="loading"
                    >
                      寄送重設連結
                    </v-btn>
                  </v-form>
                </template>

                <template v-else>
                  <v-alert type="success" variant="tonal" class="mb-4">
                    重設連結已寄出！請查看您的信箱，連結有效期為 60 分鐘。
                  </v-alert>
                </template>

                <p class="text-center text-body-2 text-grey mt-4">
                  想起密碼了？
                  <RouterLink to="/login" class="text-primary text-decoration-none font-weight-medium">返回登入</RouterLink>
                </p>
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
import api from '@/lib/axios'

const email   = ref('')
const loading = ref(false)
const sent    = ref(false)
const errorMsg = ref('')

async function submit() {
  loading.value  = true
  errorMsg.value = ''
  try {
    await api.post('/forgot-password', { email: email.value })
    sent.value = true
  } catch (err: any) {
    errorMsg.value = err?.response?.data?.errors?.email?.[0] ?? '發送失敗，請稍後再試'
  } finally {
    loading.value = false
  }
}
</script>
