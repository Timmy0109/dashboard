<template>
  <v-app>
    <v-main class="pms-login-bg">
      <div class="pms-login-shell">
        <v-card class="pms-login-card" :elevation="0">
          <div class="pms-login-grid">
            <!-- Left: brand column -->
            <aside class="pms-login-brand">
              <div class="pms-login-brand__bubble pms-login-brand__bubble--tr" />
              <div class="pms-login-brand__bubble pms-login-brand__bubble--bl" />

              <div class="pms-login-brand__top">
                <div class="pms-login-brand__logo">
                  <v-icon icon="mdi-briefcase-variant" color="white" size="22" />
                </div>
                <h1 class="text-h4 font-weight-bold text-white mt-8 mb-3">
                  專案管理系統
                </h1>
                <p class="text-body-1 text-white pms-login-brand__sub">
                  集中管理你的專案、任務與團隊。即時看見進度、瓶頸與每位成員的工作負載。
                </p>
              </div>

              <ul class="pms-login-brand__features">
                <li v-for="f in features" :key="f.text">
                  <span class="pms-login-brand__check">
                    <v-icon icon="mdi-check" color="white" size="14" />
                  </span>
                  <span class="text-body-2 text-white">{{ f.text }}</span>
                </li>
              </ul>
            </aside>

            <!-- Right: form column -->
            <section class="pms-login-form-col">
              <div class="pms-login-form-wrap">
                <div class="mb-6">
                  <h2 class="text-h5 font-weight-bold mb-1">歡迎回來</h2>
                  <p class="text-body-2 text-medium-emphasis">請使用你的帳號密碼登入</p>
                </div>

                <v-form ref="formRef" @submit.prevent="handleLogin">
                  <div class="mb-1 text-body-2 font-weight-medium">Email</div>
                  <v-text-field
                    v-model="form.email"
                    type="email"
                    prepend-inner-icon="mdi-email-outline"
                    autocomplete="email"
                    placeholder="your@email.com"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    color="primary"
                    :rules="emailRules"
                    :error-messages="errors.email"
                    required
                    class="mb-3"
                  />

                  <div class="mb-1 text-body-2 font-weight-medium">密碼</div>
                  <v-text-field
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    prepend-inner-icon="mdi-lock-outline"
                    :append-inner-icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                    autocomplete="current-password"
                    placeholder="••••••••"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    color="primary"
                    :rules="passwordRules"
                    :error-messages="errors.password"
                    required
                    class="mb-1"
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
                      class="text-body-2 text-decoration-none pms-login-link"
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

                  <button
                    type="submit"
                    class="pms-login-submit"
                    :disabled="auth.loading"
                  >
                    <v-progress-circular
                      v-if="auth.loading"
                      indeterminate
                      size="18"
                      width="2"
                      color="white"
                      class="mr-2"
                    />
                    登入
                  </button>
                </v-form>

                <p class="text-body-2 text-medium-emphasis text-center mt-4">
                  沒有帳號？
                  <RouterLink to="/register" class="pms-login-link font-weight-medium text-decoration-none">
                    使用邀請碼申請
                  </RouterLink>
                </p>

                <!-- Demo accounts -->
                <div class="mt-6">
                  <div class="d-flex align-center mb-3">
                    <v-divider />
                    <span class="text-caption text-medium-emphasis px-3 text-uppercase pms-login-demo__heading">
                      Demo 帳號 — 密碼皆為 PASSWORD
                    </span>
                    <v-divider />
                  </div>
                  <div class="d-flex flex-column ga-2">
                    <button
                      v-for="demo in demoAccounts"
                      :key="demo.email"
                      type="button"
                      class="pms-login-demo__item"
                      @click="fillDemo(demo)"
                    >
                      <span class="pms-login-demo__avatar" :style="{ backgroundColor: demo.color }">
                        {{ demo.label.charAt(0) }}
                      </span>
                      <div class="text-left flex-grow-1">
                        <div class="text-body-2 font-weight-medium">{{ demo.label }}</div>
                        <div class="text-caption text-medium-emphasis">{{ demo.email }}</div>
                      </div>
                      <v-icon icon="mdi-chevron-right" size="18" color="grey" />
                    </button>
                  </div>
                </div>
              </div>
            </section>
          </div>
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
const errors = reactive<{ email: string[]; password: string[] }>({ email: [], password: [] })
const showPassword = ref(false)
const remember = ref(true)
const formRef = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null)

const features = [
  { text: '即時甘特圖視圖' },
  { text: '多角色權限管理' },
  { text: '匯入匯出 Excel' },
  { text: '完整稽核紀錄' },
]

const demoAccounts = [
  { label: 'Admin', email: 'admin@demo.com', color: '#3b82f6' },
  { label: '王經理 (Manager)', email: 'manager@demo.com', color: '#ef4444' },
  { label: '李小明 (Member)', email: 'member@demo.com', color: '#00897B' },
]

const emailRules = [
  (v: string) => !!v || '請輸入 Email',
  (v: string) => /.+@.+\..+/.test(v) || 'Email 格式不正確',
]
const passwordRules = [
  (v: string) => !!v || '請輸入密碼',
  (v: string) => v.length >= 6 || '密碼至少 6 個字元',
]

function fillDemo(demo: { label: string; email: string }) {
  form.email = demo.email
  form.password = 'password'
  errorMsg.value = ''
  errors.email = []
  errors.password = []
}

async function handleLogin() {
  errorMsg.value = ''
  errors.email = []
  errors.password = []
  const valid = await formRef.value?.validate()
  if (valid && !valid.valid) return

  try {
    await auth.login(form.email, form.password)
    router.push('/')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const apiErrors = e?.response?.data?.errors
    if (apiErrors?.email) errors.email = apiErrors.email
    if (apiErrors?.password) errors.password = apiErrors.password
    errorMsg.value = e?.response?.data?.message ?? '登入失敗，請檢查帳號密碼'
  }
}
</script>

<style scoped>
.pms-login-bg {
  background-color: #eef1f4;
}

.pms-login-shell {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

.pms-login-card {
  width: 100%;
  max-width: 1100px;
  border-radius: 18px !important;
  background: white;
  box-shadow:
    0 1px 2px rgba(15, 23, 42, 0.04),
    0 12px 32px -8px rgba(15, 23, 42, 0.12);
  overflow: hidden;
}

.pms-login-grid {
  display: grid;
  grid-template-columns: 58% 42%;
  min-height: 640px;
}

/* ── Left brand column ───────────────────────────────────────── */
.pms-login-brand {
  position: relative;
  padding: 48px 48px 40px;
  background-color: #00806F;
  color: white;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  overflow: hidden;
}

.pms-login-brand__bubble {
  position: absolute;
  border-radius: 50%;
  background-color: rgba(255, 255, 255, 0.06);
  pointer-events: none;
}
.pms-login-brand__bubble--tr {
  width: 260px;
  height: 260px;
  top: -60px;
  right: -80px;
}
.pms-login-brand__bubble--bl {
  width: 200px;
  height: 200px;
  bottom: -60px;
  left: -50px;
  background-color: rgba(255, 255, 255, 0.05);
}

.pms-login-brand__top {
  position: relative;
  max-width: 440px;
}

.pms-login-brand__logo {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  background-color: rgba(255, 255, 255, 0.16);
  display: flex;
  align-items: center;
  justify-content: center;
}

.pms-login-brand__sub {
  opacity: 0.85;
  line-height: 1.7;
  max-width: 380px;
}

.pms-login-brand__features {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px 24px;
  position: relative;
}

.pms-login-brand__features li {
  display: flex;
  align-items: center;
  gap: 10px;
}

.pms-login-brand__check {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background-color: rgba(255, 255, 255, 0.18);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

/* ── Right form column ───────────────────────────────────────── */
.pms-login-form-col {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 48px 40px;
  background-color: white;
}

.pms-login-form-wrap {
  width: 100%;
  max-width: 380px;
}

.pms-login-link {
  color: #00806F;
}

.pms-login-submit {
  width: 100%;
  height: 48px;
  border-radius: 10px;
  background-color: #008C7A;
  color: white;
  font-weight: 600;
  font-size: 15px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: none;
  cursor: pointer;
  transition: background-color 0.15s ease;
}
.pms-login-submit:hover:not(:disabled) {
  background-color: #006e60;
}
.pms-login-submit:disabled {
  background-color: #66b8ab;
  cursor: not-allowed;
}

.pms-login-demo__heading {
  letter-spacing: 0.08em;
  white-space: nowrap;
}

.pms-login-demo__item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  border-radius: 10px;
  background-color: #f5f7f9;
  border: 1px solid transparent;
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease;
  width: 100%;
  text-align: left;
}
.pms-login-demo__item:hover {
  background-color: #eef3f1;
  border-color: rgba(0, 128, 111, 0.25);
}

.pms-login-demo__avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  color: white;
  font-weight: 700;
  font-size: 13px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

/* ── Responsive: stack vertically on small screens ──────────── */
@media (max-width: 900px) {
  .pms-login-grid {
    grid-template-columns: 1fr;
    min-height: auto;
  }
  .pms-login-brand {
    padding: 32px 28px 28px;
  }
  .pms-login-brand__features {
    margin-top: 24px;
  }
  .pms-login-form-col {
    padding: 32px 24px;
  }
}
</style>
