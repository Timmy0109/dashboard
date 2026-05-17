<template>
  <v-app>
    <v-main class="bg-grey-lighten-3">
      <v-container class="fill-height" fluid>
        <v-row align="center" justify="center">
          <v-col cols="12" sm="8" md="5" lg="4" xl="3">
            <v-card elevation="3" rounded="xl" class="pa-4">
              <v-card-title class="text-center pt-4 pb-2">
                <div class="d-flex flex-column align-center">
                  <v-icon icon="mdi-briefcase-variant" color="primary" size="40" class="mb-3" />
                  <span class="text-h6 font-weight-bold">專案管理系統</span>
                  <span class="text-body-2 text-grey mt-1">請登入以繼續</span>
                </div>
              </v-card-title>

              <v-card-text class="pt-4">
                <v-form @submit.prevent="handleLogin">
                  <v-text-field
                    v-model="form.email"
                    label="Email"
                    type="email"
                    prepend-inner-icon="mdi-email-outline"
                    autocomplete="email"
                    placeholder="your@email.com"
                    required
                    class="mb-2"
                  />
                  <v-text-field
                    v-model="form.password"
                    label="密碼"
                    :type="showPassword ? 'text' : 'password'"
                    prepend-inner-icon="mdi-lock-outline"
                    :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                    @click:append-inner="showPassword = !showPassword"
                    autocomplete="current-password"
                    required
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
                    :loading="auth.loading"
                  >
                    登入
                  </v-btn>
                </v-form>

                <p class="text-center text-body-2 text-grey mt-4">
                  沒有帳號？
                  <RouterLink to="/register" class="text-primary text-decoration-none font-weight-medium">使用邀請碼申請</RouterLink>
                </p>

                <!-- Demo accounts -->
                <v-divider class="my-4" />
                <p class="text-caption text-grey text-center mb-2">Demo 帳號（密碼 password）</p>
                <v-list density="compact" rounded="lg" bg-color="grey-lighten-4" class="pa-0">
                  <v-list-item
                    v-for="demo in demoAccounts"
                    :key="demo.email"
                    :title="demo.label"
                    :subtitle="demo.email"
                    rounded="lg"
                    density="compact"
                    class="py-1"
                    @click="fillDemo(demo)"
                  >
                    <template #prepend>
                      <v-avatar color="primary" size="28" class="mr-2">
                        <span class="text-caption text-white font-weight-bold">{{ demo.label.charAt(0) }}</span>
                      </v-avatar>
                    </template>
                  </v-list-item>
                </v-list>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-container>
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const form = reactive({ email: '', password: '' })
const errorMsg = ref('')
const showPassword = ref(false)

const demoAccounts = [
  { label: 'Admin', email: 'admin@demo.com' },
  { label: '王經理 (Manager)', email: 'manager@demo.com' },
  { label: '吳侑庭 (Manager)', email: 'mr.oldfive@gmail.com' },
  { label: '王奕翔 (Manager)', email: 'timwu.trip@gmail.com' },
  { label: '李小明 (Member)', email: 'member@demo.com' },
  { label: '吳潔茹 (Member)', email: 'oliviawu0301@gmail.com' },
  { label: '葉芸嘉 (Member)', email: 'jsp@gmail.com' },
]

function fillDemo(demo: { label: string; email: string }) {
  form.email = demo.email
  form.password = 'password'
  errorMsg.value = ''
}

async function handleLogin() {
  errorMsg.value = ''
  try {
    await auth.login(form.email, form.password)
    router.push('/')
  } catch (err: any) {
    errorMsg.value = err?.response?.data?.message ?? '登入失敗，請檢查帳號密碼'
  }
}
</script>
