<template>
  <v-app>
    <v-main>
      <div class="pms-register-shell">
        <!-- Left brand column (hidden on md and down) -->
        <aside class="pms-register-brand d-none d-lg-flex">
          <div class="pms-register-brand__inner">
            <div class="d-flex align-center mb-8">
              <v-icon icon="mdi-briefcase-variant" size="36" class="text-white mr-3" />
              <span class="text-h5 font-weight-bold text-white">專案管理系統</span>
            </div>
            <h1 class="text-h3 font-weight-bold text-white mb-4 pms-register-brand__title">
              加入您的團隊
            </h1>
            <p class="text-body-1 text-white mb-10 pms-register-brand__subtitle">
              使用邀請碼快速申請成為公司成員，與夥伴一起追蹤專案、管理任務、創造成果。
            </p>

            <div class="pms-register-brand__features">
              <div v-for="feat in features" :key="feat.title" class="d-flex align-start mb-5">
                <div class="pms-register-brand__feature-icon mr-4">
                  <v-icon :icon="feat.icon" color="white" />
                </div>
                <div>
                  <div class="text-subtitle-1 font-weight-bold text-white mb-1">{{ feat.title }}</div>
                  <div class="text-body-2 text-white pms-register-brand__feature-desc">{{ feat.desc }}</div>
                </div>
              </div>
            </div>
          </div>

          <div class="pms-register-brand__decor pms-register-brand__decor--a" />
          <div class="pms-register-brand__decor pms-register-brand__decor--b" />
        </aside>

        <!-- Right form column -->
        <section class="pms-register-form">
          <div class="pms-register-form__inner">
            <v-card elevation="3" rounded="xl" class="pa-4 pa-sm-6">
              <!-- Success state -->
              <v-card-text v-if="success" class="text-center py-8">
                <v-icon icon="mdi-check-circle" color="success" size="56" class="mb-4" />
                <div class="text-h6 font-weight-bold mb-2">註冊成功</div>
                <p class="text-body-2 text-grey mb-6">{{ successMsg }}</p>
                <v-btn color="primary" :to="'/login'" rounded="lg">前往登入</v-btn>
              </v-card-text>

              <template v-else>
                <v-card-title class="text-center pt-4 pb-2">
                  <div class="d-flex flex-column align-center">
                    <v-icon icon="mdi-account-plus" color="primary" size="40" class="mb-3" />
                    <span class="text-h6 font-weight-bold">申請加入</span>
                    <span class="text-body-2 text-grey mt-1">使用邀請碼申請成為成員</span>
                  </div>
                </v-card-title>

                <v-card-text class="pt-2">
                  <!-- Step indicator -->
                  <v-stepper
                    :model-value="step"
                    alt-labels
                    flat
                    class="mb-4 elevation-0 bg-transparent"
                  >
                    <v-stepper-header>
                      <v-stepper-item
                        title="驗證邀請碼"
                        value="1"
                        :complete="step === 2"
                        color="primary"
                      />
                      <v-divider />
                      <v-stepper-item title="填寫資料" value="2" color="primary" />
                    </v-stepper-header>
                  </v-stepper>

                  <!-- Step 1 -->
                  <div v-if="step === 1">
                    <v-text-field
                      v-model="inviteCode"
                      label="邀請碼"
                      prepend-inner-icon="mdi-key-variant"
                      placeholder="請輸入邀請碼"
                      class="mb-2"
                      style="text-transform: uppercase"
                      @keyup.enter="verifyCode"
                    />

                    <v-alert
                      v-if="codeError"
                      type="error"
                      variant="tonal"
                      density="compact"
                      class="mb-3 text-body-2"
                    >
                      {{ codeError }}
                    </v-alert>

                    <v-btn
                      color="primary"
                      block
                      size="large"
                      :loading="verifying"
                      :disabled="!inviteCode.trim()"
                      @click="verifyCode"
                    >
                      驗證邀請碼
                    </v-btn>

                    <p class="text-center text-body-2 text-grey mt-4 mb-0">
                      已有帳號？
                      <RouterLink
                        to="/login"
                        class="text-primary text-decoration-none font-weight-medium"
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

                    <v-text-field
                      v-model="form.name"
                      label="姓名"
                      prepend-inner-icon="mdi-account-outline"
                      placeholder="您的姓名"
                      autocomplete="name"
                      class="mb-2"
                    />
                    <v-text-field
                      v-model="form.email"
                      label="Email"
                      type="email"
                      prepend-inner-icon="mdi-email-outline"
                      placeholder="your@email.com"
                      autocomplete="email"
                      class="mb-2"
                    />
                    <v-text-field
                      v-model="form.password"
                      label="密碼"
                      :type="showPwd ? 'text' : 'password'"
                      prepend-inner-icon="mdi-lock-outline"
                      :append-inner-icon="showPwd ? 'mdi-eye-off' : 'mdi-eye'"
                      autocomplete="new-password"
                      placeholder="至少 8 個字元"
                      class="mb-2"
                      @click:append-inner="showPwd = !showPwd"
                    />
                    <v-text-field
                      v-model="form.password_confirmation"
                      label="確認密碼"
                      :type="showPwd ? 'text' : 'password'"
                      prepend-inner-icon="mdi-lock-check-outline"
                      autocomplete="new-password"
                      placeholder="再輸入一次密碼"
                      class="mb-2"
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
                      <v-btn
                        variant="outlined"
                        color="grey"
                        @click="backToStep1"
                      >
                        返回
                      </v-btn>
                      <v-btn
                        color="primary"
                        class="flex-grow-1"
                        :loading="submitting"
                        @click="handleRegister"
                      >
                        送出申請
                      </v-btn>
                    </div>
                  </div>
                </v-card-text>
              </template>
            </v-card>

            <p class="text-center text-caption text-grey mt-6 mb-0">
              &copy; {{ year }} 專案管理系統
            </p>
          </div>
        </section>
      </div>
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
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

const year = computed(() => new Date().getFullYear())

const features = [
  {
    icon: 'mdi-folder-multiple-outline',
    title: '專案集中管理',
    desc: '一站式追蹤所有專案進度、任務與里程碑。',
  },
  {
    icon: 'mdi-account-group-outline',
    title: '團隊協作',
    desc: '指派任務、即時討論、檔案共享一氣呵成。',
  },
  {
    icon: 'mdi-chart-timeline-variant',
    title: '進度可視化',
    desc: '甘特圖、KPI、趨勢圖讓決策更有底氣。',
  },
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
.pms-register-shell {
  display: grid;
  grid-template-columns: 1fr;
  min-height: 100vh;
  background: rgb(var(--v-theme-background));
}

@media (min-width: 1280px) {
  .pms-register-shell {
    grid-template-columns: 1.05fr 1fr;
  }
}

.pms-register-brand {
  position: relative;
  overflow: hidden;
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-primary-darken-1, var(--v-theme-primary))) 50%,
    rgb(var(--v-theme-secondary, var(--v-theme-primary))) 100%
  );
  color: #fff;
  padding: 64px 56px;
  align-items: center;
}

.pms-register-brand__inner {
  position: relative;
  z-index: 2;
  max-width: 520px;
  width: 100%;
}

.pms-register-brand__title {
  letter-spacing: -0.5px;
  line-height: 1.2;
}

.pms-register-brand__subtitle {
  opacity: 0.88;
  line-height: 1.7;
}

.pms-register-brand__feature-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.16);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.pms-register-brand__feature-desc {
  opacity: 0.82;
  line-height: 1.55;
}

.pms-register-brand__decor {
  position: absolute;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.08);
  z-index: 1;
  pointer-events: none;
}

.pms-register-brand__decor--a {
  width: 360px;
  height: 360px;
  top: -120px;
  right: -120px;
}

.pms-register-brand__decor--b {
  width: 240px;
  height: 240px;
  bottom: -80px;
  left: -80px;
  background: rgba(255, 255, 255, 0.06);
}

.pms-register-form {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32px 16px;
}

.pms-register-form__inner {
  width: 100%;
  max-width: 480px;
}

@media (min-width: 600px) {
  .pms-register-form {
    padding: 48px 32px;
  }
}
</style>
