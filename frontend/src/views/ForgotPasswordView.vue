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
                  重設你的密碼
                </h1>
                <p class="text-body-1 text-white pms-auth-brand__sub">
                  輸入註冊時使用的 Email，我們會寄送重設連結，協助你快速回到工作。
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
                <div class="mb-6">
                  <h2 class="text-h5 font-weight-bold mb-1">忘記密碼</h2>
                  <p class="text-body-2 text-medium-emphasis">
                    輸入你的 Email，我們將寄送重設連結
                  </p>
                </div>

                <template v-if="!sent">
                  <v-form ref="formRef" @submit.prevent="submit">
                    <div class="mb-1 text-body-2 font-weight-medium">Email</div>
                    <v-text-field
                      v-model="email"
                      type="email"
                      prepend-inner-icon="mdi-email-outline"
                      autocomplete="email"
                      placeholder="your@email.com"
                      variant="outlined"
                      density="comfortable"
                      rounded="lg"
                      color="primary"
                      :rules="emailRules"
                      :error-messages="errorMsg ? [errorMsg] : []"
                      required
                      class="mb-4"
                    />

                    <button
                      type="submit"
                      class="pms-auth-submit"
                      :disabled="loading"
                    >
                      <v-progress-circular
                        v-if="loading"
                        indeterminate
                        size="18"
                        width="2"
                        color="white"
                        class="mr-2"
                      />
                      寄送重設連結
                    </button>
                  </v-form>
                </template>

                <template v-else>
                  <v-alert
                    type="success"
                    variant="tonal"
                    density="compact"
                    class="text-body-2"
                  >
                    重設連結已寄出！請查看你的信箱，連結有效期為 60 分鐘。
                  </v-alert>
                </template>

                <p class="text-body-2 text-medium-emphasis text-center mt-6">
                  想起密碼了？
                  <RouterLink
                    to="/login"
                    class="pms-auth-link font-weight-medium text-decoration-none"
                  >
                    返回登入
                  </RouterLink>
                </p>
              </div>
            </section>
          </div>
        </v-card>
      </div>
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/axios'

const email = ref('')
const loading = ref(false)
const sent = ref(false)
const errorMsg = ref('')
const formRef = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null)

const features = [
  { text: '60 分鐘安全有效期' },
  { text: '加密重設流程' },
  { text: '單一連結僅能使用一次' },
  { text: '帳號完整稽核紀錄' },
]

const emailRules = [
  (v: string) => !!v || '請輸入 Email',
  (v: string) => /.+@.+\..+/.test(v) || 'Email 格式不正確',
]

async function submit() {
  errorMsg.value = ''
  const valid = await formRef.value?.validate()
  if (valid && !valid.valid) return

  loading.value = true
  try {
    await api.post('/forgot-password', { email: email.value })
    sent.value = true
  } catch (err: any) {
    errorMsg.value = err?.response?.data?.errors?.email?.[0]
      ?? err?.response?.data?.message
      ?? '發送失敗，請稍後再試'
  } finally {
    loading.value = false
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
  min-height: 560px;
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
  padding: 48px 40px;
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
