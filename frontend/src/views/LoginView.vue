<template>
  <v-app>
    <v-main>
      <div class="pms-login-shell">
        <!-- Left brand column (hidden on small screens) -->
        <aside class="pms-login-brand hidden-md-and-down">
          <div class="pms-login-brand__inner">
            <div class="d-flex align-center gap-3 mb-8">
              <div class="pms-login-brand__logo">
                <v-icon icon="mdi-briefcase-variant" color="white" size="28" />
              </div>
              <div>
                <div class="text-h6 font-weight-bold text-white">專案管理系統</div>
                <div class="text-caption text-white" style="opacity: 0.8">
                  Project Management Suite
                </div>
              </div>
            </div>

            <h1 class="text-h3 font-weight-bold text-white mb-4">
              一個地方，<br />追蹤所有專案進度。
            </h1>
            <p class="text-body-1 text-white mb-10" style="opacity: 0.9; max-width: 480px">
              整合任務、附件、留言與即時通知，讓團隊協作不再分心。
            </p>

            <ul class="pms-login-brand__features">
              <li v-for="f in features" :key="f.title">
                <v-icon :icon="f.icon" color="white" size="20" />
                <div>
                  <div class="text-body-2 font-weight-bold text-white">{{ f.title }}</div>
                  <div class="text-caption text-white" style="opacity: 0.85">
                    {{ f.desc }}
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </aside>

        <!-- Right form column -->
        <section class="pms-login-form-col">
          <div class="pms-login-form-wrap">
            <!-- Mobile brand header -->
            <div class="d-md-none mb-6 text-center">
              <v-icon icon="mdi-briefcase-variant" color="primary" size="40" />
              <div class="text-h6 font-weight-bold mt-2">專案管理系統</div>
            </div>

            <div class="mb-6">
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
            <div class="pms-login-demo mt-6">
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
          </div>
        </section>
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

const features = [
  {
    icon: 'mdi-chart-timeline-variant',
    title: '即時進度視覺化',
    desc: 'Gantt、Donut、Trend 一目了然',
  },
  {
    icon: 'mdi-account-multiple-outline',
    title: '團隊協作',
    desc: '留言、附件、@提及、活動日誌',
  },
  {
    icon: 'mdi-bell-ring-outline',
    title: '即時通知',
    desc: '任務指派、留言提及第一時間送達',
  },
]

const demoAccounts = [
  { label: 'Admin', email: 'admin@demo.com', color: '#ef4444' },
  { label: '王經理 (Manager)', email: 'manager@demo.com', color: '#3b82f6' },
  { label: '吳侑庭 (Manager)', email: 'mr.oldfive@gmail.com', color: '#3b82f6' },
  { label: '王奕翔 (Manager)', email: 'timwu.trip@gmail.com', color: '#3b82f6' },
  { label: '李小明 (Member)', email: 'member@demo.com', color: '#10b981' },
  { label: '吳潔茹 (Member)', email: 'oliviawu0301@gmail.com', color: '#10b981' },
  { label: '葉芸嘉 (Member)', email: 'jsp@gmail.com', color: '#10b981' },
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
  display: flex;
  min-height: 100vh;
  background-color: rgb(var(--v-theme-background));
}

.pms-login-brand {
  flex: 1 1 0;
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #2563eb 100%);
  padding: 64px;
  display: flex;
  align-items: center;
  color: white;
}

.pms-login-brand::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.15) 0%, transparent 40%),
    radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.1) 0%, transparent 40%);
  pointer-events: none;
}

.pms-login-brand__inner {
  position: relative;
  max-width: 560px;
}

.pms-login-brand__logo {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background-color: rgba(255, 255, 255, 0.18);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
}

.pms-login-brand__features {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.pms-login-brand__features li {
  display: flex;
  align-items: flex-start;
  gap: 14px;
}

.pms-login-form-col {
  flex: 1 1 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
}

.pms-login-form-wrap {
  width: 100%;
  max-width: 420px;
}

.pms-login-demo__grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 6px;
  max-height: 260px;
  overflow-y: auto;
  padding-right: 4px;
}

.pms-login-demo__item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
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

.gap-3 {
  gap: 12px;
}
</style>
