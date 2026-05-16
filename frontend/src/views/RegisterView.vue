<template>
  <div class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
      <div class="bg-white rounded-xl shadow-lg p-8">

        <!-- Success state -->
        <div v-if="success" class="text-center py-4">
          <span class="material-icons text-5xl text-green-500 block mb-3">check_circle</span>
          <h2 class="text-lg font-semibold text-gray-900 mb-2">註冊成功</h2>
          <p class="text-sm text-gray-500 mb-6">{{ successMsg }}</p>
          <RouterLink
            to="/login"
            class="inline-block px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
          >前往登入</RouterLink>
        </div>

        <template v-else>
          <div class="text-center mb-7">
            <h1 class="text-2xl font-bold text-gray-900">申請加入</h1>
            <p class="text-sm text-gray-500 mt-1">使用邀請碼申請成為成員</p>
          </div>

          <!-- Step 1: invite code -->
          <div v-if="step === 1" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">邀請碼</label>
              <input
                v-model="inviteCode"
                type="text"
                placeholder="請輸入邀請碼"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 uppercase tracking-widest"
                @keyup.enter="verifyCode"
              />
            </div>

            <div v-if="codeError" class="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">
              {{ codeError }}
            </div>

            <button
              @click="verifyCode"
              :disabled="verifying || !inviteCode.trim()"
              class="w-full bg-blue-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors"
            >
              {{ verifying ? '驗證中...' : '驗證邀請碼' }}
            </button>

            <p class="text-center text-sm text-gray-400">
              已有帳號？
              <RouterLink to="/login" class="text-blue-600 hover:underline">登入</RouterLink>
            </p>
          </div>

          <!-- Step 2: registration form -->
          <div v-else class="space-y-4">
            <div class="flex items-center gap-2 px-3 py-2 bg-blue-50 rounded-lg text-sm text-blue-700">
              <span class="material-icons text-base leading-none">business</span>
              加入 <strong>{{ companyName }}</strong>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">姓名</label>
              <input
                v-model="form.name"
                type="text"
                placeholder="您的姓名"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input
                v-model="form.email"
                type="email"
                placeholder="your@email.com"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">密碼</label>
              <input
                v-model="form.password"
                type="password"
                placeholder="至少 8 個字元"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">確認密碼</label>
              <input
                v-model="form.password_confirmation"
                type="password"
                placeholder="再輸入一次密碼"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                @keyup.enter="handleRegister"
              />
            </div>

            <div v-if="formError" class="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">
              {{ formError }}
            </div>

            <div class="flex gap-3">
              <button
                @click="step = 1; codeError = ''"
                class="px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition-colors"
              >返回</button>
              <button
                @click="handleRegister"
                :disabled="submitting"
                class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors"
              >
                {{ submitting ? '提交中...' : '送出申請' }}
              </button>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
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
