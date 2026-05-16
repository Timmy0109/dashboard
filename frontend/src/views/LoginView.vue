<template>
  <div class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
      <div class="bg-white rounded-xl shadow-lg p-8">
        <div class="text-center mb-8">
          <h1 class="text-2xl font-bold text-gray-900">專案管理系統</h1>
          <p class="text-sm text-gray-500 mt-1">請登入以繼續</p>
        </div>

        <form @submit.prevent="handleLogin" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input
              v-model="form.email"
              type="email"
              required
              autocomplete="email"
              placeholder="your@email.com"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">密碼</label>
            <input
              v-model="form.password"
              type="password"
              required
              autocomplete="current-password"
              placeholder="••••••••"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <div v-if="errorMsg" class="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">
            {{ errorMsg }}
          </div>

          <button
            type="submit"
            :disabled="auth.loading"
            class="w-full bg-blue-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {{ auth.loading ? '登入中...' : '登入' }}
          </button>
        </form>

        <p class="text-center text-sm text-gray-500 mt-5">
          沒有帳號？
          <RouterLink to="/register" class="text-blue-600 hover:underline">使用邀請碼申請</RouterLink>
        </p>

        <div class="mt-4 pt-4 border-t border-gray-100">
          <p class="text-xs text-gray-400 text-center">Demo 帳號</p>
          <div class="mt-2 space-y-1">
            <button
              v-for="demo in demoAccounts"
              :key="demo.email"
              @click="fillDemo(demo)"
              class="w-full text-left px-3 py-1.5 rounded text-xs text-gray-500 hover:bg-gray-50 transition-colors"
            >
              {{ demo.label }} — {{ demo.email }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const form = reactive({ email: '', password: '' })
const errorMsg = ref('')

const demoAccounts = [
  { label: 'Admin', email: 'admin@demo.com' },
  { label: '王經理 (Manager)', email: 'manager@demo.com' },
  { label: '李小明 (Member)', email: 'member@demo.com' },
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
