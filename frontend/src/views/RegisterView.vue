<template>
  <v-app>
    <v-main class="pms-auth-bg">
      <div class="pms-auth-shell">
        <v-card class="pms-auth-card" :elevation="0">
          <div class="pms-auth-grid">
            <!-- Left: brand column -->
            <aside class="pms-auth-brand">
              <div class="pms-auth-brand__bubble pms-auth-brand__bubble--tr" />
              <div class="pms-auth-brand__bubble pms-auth-brand__bubble--bl" />

              <div class="pms-auth-brand__top">
                <div class="pms-auth-brand__logo">
                  <v-icon icon="mdi-briefcase-variant" color="white" size="22" />
                </div>
                <h1 class="text-h4 font-weight-bold text-white mt-8 mb-3">
                  加入你的團隊
                </h1>
                <p class="text-body-1 text-white pms-auth-brand__sub">
                  使用邀請碼快速申請成為公司成員，與夥伴一起追蹤專案、管理任務、創造成果。
                </p>
              </div>

              <ul class="pms-auth-brand__features">
                <li v-for="f in features" :key="f.text">
                  <span class="pms-auth-brand__check">
                    <v-icon icon="mdi-check" color="white" size="14" />
                  </span>
                  <span class="text-body-2 text-white">{{ f.text }}</span>
                </li>
              </ul>
            </aside>

            <!-- Right: form column -->
            <section class="pms-auth-form-col">
              <div class="pms-auth-form-wrap">
                <!-- Success state -->
                <div v-if="success" class="text-center py-6">
                  <v-icon icon="mdi-check-circle" color="success" size="56" class="mb-4" />
                  <div class="text-h6 font-weight-bold mb-2">註冊成功</div>
                  <p class="text-body-2 text-medium-emphasis mb-6">{{ successMsg }}</p>
                  <RouterLink to="/login" class="text-decoration-none">
                    <button type="button" class="pms-auth-submit">前往登入</button>
                  </RouterLink>
                </div>

                <template v-else>
                  <div class="mb-6">
                    <h2 class="text-h5 font-weight-bold mb-1">申請加入</h2>
                    <p class="text-body-2 text-medium-emphasis">使用邀請碼申請成為成員</p>
                  </div>

                  <!-- Step indicator -->
                  <div class="pms-auth-steps mb-5">
                    <div class="pms-auth-step" :class="{ 'pms-auth-step--active': step === 1, 'pms-auth-step--done': step === 2 }">
                      <span class="pms-auth-step__dot">
                        <v-icon v-if="step === 2" icon="mdi-check" size="14" color="white" />
                        <span v-else>1</span>
                      </span>
                      <span class="text-caption">驗證邀請碼</span>
                    </div>
                    <div class="pms-auth-step__line" :class="{ 'pms-auth-step__line--done': step === 2 }" />
                    <div class="pms-auth-step" :class="{ 'pms-auth-step--active': step === 2 }">
                      <span class="pms-auth-step__dot">
                        <span>2</span>
                      </span>
                      <span class="text-caption">填寫資料</span>
                    </div>
                  </div>

                  <!-- Step 1 -->
                  <div v-if="step === 1">
                    <div class="mb-1 text-body-2 font-weight-medium">邀請碼</div>
                    <v-text-field
                      v-model="inviteCode"
                      prepend-inner-icon="mdi-key-variant"
                      placeholder="請輸入邀請碼"
                      variant="outlined"
                      density="comfortable"
                      rounded="lg"
                      color="primary"
                      style="text-transform: uppercase"
                      class="mb-3"
                      :error-messages="codeError ? [codeError] : []"
                      @keyup.enter="verifyCode"
                    />

                    <button
                      type="button"
                      class="pms-auth-submit"
                      :disabled="!inviteCode.trim() || verifying"
                      @click="verifyCode"
                    >
                      <v-progress-circular
                        v-if="verifying"
                        indeterminate
                        size="18"
                        width="2"
                        color="white"
                        class="mr-2"
                      />
                      驗證邀請碼
                    </button>

                    <p class="text-body-2 text-medium-emphasis text-center mt-4 mb-0">
                      已有帳號？
                      <RouterLink
                        to="/login"
                        class="pms-auth-link font-weight-medium text-decoration-none"
                      >
                        登入
                      </RouterLink>
                    </p>
                  </div>

                  <!-- Step 2 -->
                  <div v-else>
                    <v-alert
                      type="info"
                      variant="tonal"
                      density="compact"
                      icon="mdi-domain"
                      class="mb-4 text-body-2"
                    >
                      加入 <strong>{{ companyName }}</strong>
                    </v-alert>

                    <div class="mb-1 text-body-2 font-weight-medium">姓名</div>
                    <v-text-field
                      v-model="form.name"
                      prepend-inner-icon="mdi-account-outline"
                      placeholder="你的姓名"
                      autocomplete="name"
                      variant="outlined"
                      density="comfortable"
                      rounded="lg"
                      color="primary"
                      class="mb-3"
                    />

                    <div class="mb-1 text-body-2 font-weight-medium">Email</div>
                    <v-text-field
                      v-model="form.email"
                      type="email"
                      prepend-inner-icon="mdi-email-outline"
                      placeholder="your@email.com"
                      autocomplete="email"
                      variant="outlined"
                      density="comfortable"
                      rounded="lg"
                      color="primary"
                      class="mb-3"
                    />

                    <div class="mb-1 text-body-2 font-weight-medium">密碼</div>
                    <v-text-field
                      v-model="form.password"
                      :type="showPwd ? 'text' : 'password'"
                      prepend-inner-icon="mdi-lock-outline"
                      :append-inner-icon="showPwd ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                      autocomplete="new-password"
                      placeholder="至少 8 個字元"
                      variant="outlined"
                      density="comfortable"
                      rounded="lg"
                      color="primary"
                      class="mb-3"
                      @click:append-inner="showPwd = !showPwd"
                    />

                    <div class="mb-1 text-body-2 font-weight-medium">確認密碼</div>
                    <v-text-field
                      v-model="form.password_confirmation"
                      :type="showPwd ? 'text' : 'password'"
                      prepend-inner-icon="mdi-lock-check-outline"
                      autocomplete="new-password"
                      placeholder="再輸入一次密碼"
                      variant="outlined"
                      density="comfortable"
                      rounded="lg"
                      color="primary"
                      class="mb-3"
                      @keyup.enter="handleRegister"
                    />

                    <v-alert
                      v-if="formError"
                      type="error"
                      variant="tonal"
                      density="compact"
                      class="mb-3 text-body-2"
                    >
                      {{ formError }}
                    </v-alert>

                    <div class="d-flex ga-3">
                      <button
                        type="button"
                        class="pms-auth-submit pms-auth-submit--ghost"
                        @click="backToStep1"
                      >
                        返回
                      </button>
                      <button
                        type="button"
                        class="pms-auth-submit flex-grow-1"
                        :disabled="submitting"
                        @click="handleRegister"
                      >
                        <v-progress-circular
                          v-if="submitting"
                          indeterminate
                          size="18"
                          width="2"
                          color="white"
                          class="mr-2"
                        />
                        送出申請
                      </button>
                    </div>
                  </div>
                </template>
              </div>
            </section>
          </div>
        </v-card>
      </div>
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import axios from 'axios'
import api from '@/lib/axios'

onMounted(async () => {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
})

const step = ref<1 | 2>(1)
const inviteCode = ref('')
const companyName = ref('')
const verifying = ref(false)
const codeError = ref('')
const showPwd = ref(false)

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})
const formError = ref('')
const submitting = ref(false)
const success = ref(false)
const successMsg = ref('')

const features = [
  { text: '邀請碼 30 分鐘有效' },
  { text: '加入後自動分配角色' },
  { text: '完整稽核紀錄' },
  { text: '隨時切換多個專案' },
]

function backToStep1() {
  step.value = 1
  codeError.value = ''
}

async function verifyCode() {
  if (!inviteCode.value.trim() || verifying.value) return
  verifying.value = true
  codeError.value = ''
  try {
    const { data } = await api.post('/register/validate-code', {
      invite_code: inviteCode.value.trim().toUpperCase(),
    })
    companyName.value = data.company_name
    step.value = 2
  } catch (err: any) {
    codeError.value = err?.response?.data?.errors?.invite_code?.[0]
      ?? err?.response?.data?.message
      ?? '驗證失敗'
  } finally {
    verifying.value = false
  }
}

async function handleRegister() {
  formError.value = ''
  if (!form.name || !form.email || !form.password || !form.password_confirmation) {
    formError.value = '請填寫所有欄位'
    return
  }
  if (form.password !== form.password_confirmation) {
    formError.value = '兩次密碼不一致'
    return
  }
  if (form.password.length < 8) {
    formError.value = '密碼至少 8 個字元'
    return
  }
  submitting.value = true
  try {
    const { data } = await api.post('/register', {
      name: form.name,
      email: form.email,
      password: form.password,
      password_confirmation: form.password_confirmation,
      invite_code: inviteCode.value.trim().toUpperCase(),
    })
    successMsg.value = data.message
    success.value = true
  } catch (err: any) {
    const errors = err?.response?.data?.errors
    if (errors) {
      formError.value = Object.values(errors).flat().join('、')
    } else {
      formError.value = err?.response?.data?.message ?? '註冊失敗，請再試一次'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.pms-auth-bg {
  background-color: #eef1f4;
}

.pms-auth-shell {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

.pms-auth-card {
  width: 100%;
  max-width: 1100px;
  border-radius: 18px !important;
  background: white;
  box-shadow:
    0 1px 2px rgba(15, 23, 42, 0.04),
    0 12px 32px -8px rgba(15, 23, 42, 0.12);
  overflow: hidden;
  animation: pms-card-in 0.6s ease-out both;
}

@keyframes pms-card-in {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

.pms-auth-grid {
  display: grid;
  grid-template-columns: 58% 42%;
  min-height: 640px;
}

/* ── Left brand column ───────────────────────────────────────── */
.pms-auth-brand {
  position: relative;
  padding: 48px 48px 40px;
  background-color: #00806F;
  color: white;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  overflow: hidden;
}

.pms-auth-brand__bubble {
  position: absolute;
  border-radius: 50%;
  background-color: rgba(255, 255, 255, 0.06);
  pointer-events: none;
}
.pms-auth-brand__bubble--tr {
  width: 260px;
  height: 260px;
  top: -60px;
  right: -80px;
  animation: pms-float-a 12s ease-in-out infinite;
}
.pms-auth-brand__bubble--bl {
  width: 200px;
  height: 200px;
  bottom: -60px;
  left: -50px;
  background-color: rgba(255, 255, 255, 0.05);
  animation: pms-float-b 14s ease-in-out infinite;
}

@keyframes pms-float-a {
  0%, 100% { transform: translate(0, 0); }
  50%      { transform: translate(-12px, 16px); }
}
@keyframes pms-float-b {
  0%, 100% { transform: translate(0, 0); }
  50%      { transform: translate(14px, -10px); }
}

.pms-auth-brand__top {
  position: relative;
  max-width: 440px;
}

.pms-auth-brand__logo {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  background-color: rgba(255, 255, 255, 0.16);
  display: flex;
  align-items: center;
  justify-content: center;
}

.pms-auth-brand__sub {
  opacity: 0.85;
  line-height: 1.7;
  max-width: 380px;
}

.pms-auth-brand__features {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px 24px;
  position: relative;
}

.pms-auth-brand__features li {
  display: flex;
  align-items: center;
  gap: 10px;
}

.pms-auth-brand__check {
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
.pms-auth-form-col {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px;
  background-color: white;
}

.pms-auth-form-wrap {
  width: 100%;
  max-width: 380px;
}

.pms-auth-link {
  color: #00806F;
}

.pms-auth-submit {
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
.pms-auth-submit:hover:not(:disabled) {
  background-color: #006e60;
}
.pms-auth-submit:disabled {
  background-color: #66b8ab;
  cursor: not-allowed;
}

.pms-auth-submit--ghost {
  width: auto;
  padding: 0 18px;
  background-color: transparent;
  color: #475569;
  border: 1px solid #cbd5e1;
}
.pms-auth-submit--ghost:hover:not(:disabled) {
  background-color: #f1f5f9;
}

/* ── Step indicator ──────────────────────────────────────────── */
.pms-auth-steps {
  display: flex;
  align-items: center;
  gap: 12px;
}
.pms-auth-step {
  display: flex;
  align-items: center;
  gap: 8px;
  color: rgba(15, 23, 42, 0.48);
}
.pms-auth-step__dot {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background-color: #e2e8f0;
  color: rgba(15, 23, 42, 0.6);
  font-size: 12px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.pms-auth-step--active .pms-auth-step__dot {
  background-color: #00806F;
  color: white;
}
.pms-auth-step--active {
  color: #0f172a;
}
.pms-auth-step--done .pms-auth-step__dot {
  background-color: #00806F;
  color: white;
}
.pms-auth-step__line {
  flex-grow: 1;
  height: 2px;
  background-color: #e2e8f0;
  border-radius: 2px;
}
.pms-auth-step__line--done {
  background-color: #00806F;
}

@media (prefers-reduced-motion: reduce) {
  .pms-auth-card,
  .pms-auth-brand__bubble--tr,
  .pms-auth-brand__bubble--bl {
    animation: none;
  }
}

@media (max-width: 900px) {
  .pms-auth-grid {
    grid-template-columns: 1fr;
    min-height: auto;
  }
  .pms-auth-brand {
    padding: 32px 28px 28px;
  }
  .pms-auth-brand__features {
    margin-top: 24px;
  }
  .pms-auth-form-col {
    padding: 32px 24px;
  }
}
</style>
