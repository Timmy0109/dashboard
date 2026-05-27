<template>
  <v-app>
    <v-main class="bg-background">
      <v-container class="fill-height" fluid>
        <v-row align="center" justify="center">
          <v-col cols="12" sm="8" md="5" lg="4" xl="3">
            <v-card elevation="3" rounded="xl" class="pa-4">

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
                  <v-stepper :model-value="step" alt-labels flat class="mb-4 elevation-0 bg-transparent">
                    <v-stepper-header>
                      <v-stepper-item title="驗證邀請碼" value="1" :complete="step === 2" color="primary" />
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

                    <v-alert v-if="codeError" type="error" variant="tonal" density="compact" class="mb-3 text-body-2">
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

                    <p class="text-center text-body-2 text-grey mt-4">
                      已有帳號？
                      <RouterLink to="/login" class="text-primary text-decoration-none font-weight-medium">登入</RouterLink>
                    </p>
                  </div>

                  <!-- Step 2 -->
                  <div v-else>
                    <v-alert type="info" variant="tonal" density="compact" icon="mdi-domain" class="mb-4 text-body-2">
                      加入 <strong>{{ companyName }}</strong>
                    </v-alert>

                    <v-text-field v-model="form.name" label="姓名" prepend-inner-icon="mdi-account-outline" placeholder="您的姓名" class="mb-2" />
                    <v-text-field v-model="form.email" label="Email" type="email" prepend-inner-icon="mdi-email-outline" placeholder="your@email.com" class="mb-2" />
                    <v-text-field
                      v-model="form.password"
                      label="密碼"
                      :type="showPwd ? 'text' : 'password'"
                      prepend-inner-icon="mdi-lock-outline"
                      :append-inner-icon="showPwd ? 'mdi-eye-off' : 'mdi-eye'"
                      @click:append-inner="showPwd = !showPwd"
                      placeholder="至少 8 個字元"
                      class="mb-2"
                    />
                    <v-text-field
                      v-model="form.password_confirmation"
                      label="確認密碼"
                      :type="showPwd ? 'text' : 'password'"
                      prepend-inner-icon="mdi-lock-check-outline"
                      placeholder="再輸入一次密碼"
                      class="mb-2"
                      @keyup.enter="handleRegister"
                    />

                    <v-alert v-if="formError" type="error" variant="tonal" density="compact" class="mb-3 text-body-2">
                      {{ formError }}
                    </v-alert>

                    <div class="d-flex gap-3">
                      <v-btn variant="outlined" color="grey" @click="step = 1; codeError = ''">返回</v-btn>
                      <v-btn color="primary" flex-grow-1 :loading="submitting" @click="handleRegister" class="flex-grow-1">
                        送出申請
                      </v-btn>
                    </div>
                  </div>
                </v-card-text>
              </template>

            </v-card>
          </v-col>
        </v-row>
      </v-container>
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
