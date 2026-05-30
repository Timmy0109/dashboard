<template>
  <v-app>
    <v-main>
      <div class="pms-login-shell">
        <v-card class="pms-login-card" rounded="xl" elevation="8">
          <!-- Brand header -->
          <div class="pms-login-header">
            <div class="pms-login-header__logo">
              <v-icon icon="mdi-briefcase-variant" color="white" size="28" />
            </div>
            <div class="text-h6 font-weight-bold text-white">專案管理系統</div>
            <div class="text-caption text-white" style="opacity: 0.9">
              Project Management Suite
            </div>
          </div>

          <v-card-text class="pa-8">
            <div class="mb-6 text-center">
              <h2 class="text-h5 font-weight-bold mb-1">歡迎回來</h2>
              <p class="text-body-2 text-medium-emphasis">請登入以繼續使用</p>
            </div>

            <v-form @submit.prevent="handleLogin">
              <v-text-field
                v-model="form.email"
                label="Email"
                type="email"
                prepend-inner-icon="mdi-email-outline"
                autocomplete="email"
                placeholder="your@email.com"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                color="primary"
                required
                class="mb-3"
              />
              <v-text-field
                v-model="form.password"
                label="密碼"
                :type="showPassword ? 'text' : 'password'"
                prepend-inner-icon="mdi-lock-outline"
                :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                autocomplete="current-password"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                color="primary"
                required
                class="mb-2"
                @click:append-inner="showPassword = !showPassword"
              />

              <div class="d-flex align-center justify-space-between mb-4">
                <v-checkbox
                  v-model="remember"
                  label="記住我"
                  density="compact"
                  hide-details
                  color="primary"
                />
                <RouterLink
                  to="/forgot-password"
                  class="text-body-2 text-primary text-decoration-none"
                >
                  忘記密碼？
                </RouterLink>
              </div>

              <v-alert
                v-if="errorMsg"
                type="error"
                variant="tonal"
                density="compact"
                class="mb-4 text-body-2"
              >
                {{ errorMsg }}
              </v-alert>

              <v-btn
                type="submit"
                color="primary"
                block
                size="large"
                rounded="lg"
                :loading="auth.loading"
              >
                登入
              </v-btn>
            </v-form>

            <p class="text-body-2 text-medium-emphasis text-center mt-4">
              沒有帳號？
              <RouterLink
                to="/register"
                class="text-primary text-decoration-none font-weight-medium"
              >
                使用邀請碼申請
              </RouterLink>
            </p>

            <!-- Demo accounts -->
            <div class="mt-6">
              <div class="d-flex align-center mb-3">
                <v-divider />
                <span class="text-caption text-medium-emphasis px-3">
                  Demo 帳號（密碼 password）
                </span>
                <v-divider />
              </div>
              <div class="pms-login-demo__grid">
                <button
                  v-for="demo in demoAccounts"
                  :key="demo.email"
                  type="button"
                  class="pms-login-demo__item"
                  @click="fillDemo(demo)"
                >
                  <v-avatar :color="demo.color" size="30">
                    <span class="text-caption text-white font-weight-bold">
                      {{ demo.label.charAt(0) }}
                    </span>
                  </v-avatar>
                  <div class="text-left">
                    <div class="text-body-2 font-weight-medium">{{ demo.label }}</div>
                    <div class="text-caption text-medium-emphasis">{{ demo.email }}</div>
                  </div>
                </button>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </div>
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
const remember = ref(true)

const demoAccounts = [
  { label: 'Admin', email: 'admin@demo.com', color: '#ef4444' },
  { label: '王經理 (Manager)', email: 'manager@demo.com', color: '#00897B' },
  { label: '吳侑庭 (Manager)', email: 'mr.oldfive@gmail.com', color: '#00897B' },
  { label: '王奕翔 (Manager)', email: 'timwu.trip@gmail.com', color: '#00897B' },
  { label: '李小明 (Member)', email: 'member@demo.com', color: '#26A69A' },
  { label: '吳潔茹 (Member)', email: 'oliviawu0301@gmail.com', color: '#26A69A' },
  { label: '葉芸嘉 (Member)', email: 'jsp@gmail.com', color: '#26A69A' },
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
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    errorMsg.value = e?.response?.data?.message ?? '登入失敗，請檢查帳號密碼'
  }
}
</script>

<style scoped>
.pms-login-shell {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background:
    radial-gradient(circle at 15% 20%, rgba(0, 137, 123, 0.12) 0%, transparent 45%),
    radial-gradient(circle at 85% 80%, rgba(38, 166, 154, 0.10) 0%, transparent 45%),
    linear-gradient(180deg, #f4faf8 0%, #e8f4f1 100%);
}

.pms-login-card {
  width: 50vw;
  min-width: 380px;
  max-width: 720px;
  overflow: hidden;
}

.pms-login-header {
  background: linear-gradient(135deg, #00897B 0%, #26A69A 100%);
  padding: 32px 24px 28px;
  text-align: center;
  position: relative;
  overflow: hidden;
}

.pms-login-header::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.15) 0%, transparent 45%),
    radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.08) 0%, transparent 45%);
  pointer-events: none;
}

.pms-login-header__logo {
  width: 56px;
  height: 56px;
  border-radius: 14px;
  background-color: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 12px;
  position: relative;
}

.pms-login-demo__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
  max-height: 220px;
  overflow-y: auto;
  padding-right: 4px;
}

.pms-login-demo__item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 10px;
  background: transparent;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease;
  width: 100%;
}

.pms-login-demo__item:hover {
  background-color: rgba(var(--v-theme-primary), 0.06);
  border-color: rgba(var(--v-theme-primary), 0.3);
}

@media (max-width: 768px) {
  .pms-login-card {
    width: 100%;
    min-width: 0;
  }
  .pms-login-demo__grid {
    grid-template-columns: 1fr;
  }
}
</style>
