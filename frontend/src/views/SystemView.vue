<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900">系統管理</h2>
        <p class="text-sm text-gray-500 mt-0.5">管理所有公司帳戶與功能權限</p>
      </div>
      <button
        @click="showCreate = true"
        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
      >
        <span class="material-icons text-base leading-none">add</span>
        新增公司
      </button>
    </div>

    <!-- Company list -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div v-if="store.loading" class="py-16 text-center text-sm text-gray-400">載入中...</div>
      <table v-else class="w-full">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">公司名稱</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">狀態</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Manager</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">成員數</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">建立日期</th>
            <th class="px-4 py-3 w-28"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr
            v-for="company in store.list"
            :key="company.id"
            class="hover:bg-blue-50 cursor-pointer transition-colors"
            @click="openEmployees(company)"
          >
            <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ company.name }}</td>
            <td class="px-4 py-3">
              <span
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                :class="company.status === 'active' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'"
              >
                {{ company.status === 'active' ? '運作中' : '已停用' }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ company.managers_count }} 人</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ company.members_count }} 人</td>
            <td class="px-4 py-3 text-xs text-gray-400">{{ company.created_at }}</td>
            <td class="px-4 py-3">
              <div class="flex gap-1" @click.stop>
                <button
                  @click="openFeatures(company)"
                  class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                  title="功能設定"
                >
                  <span class="material-icons text-base leading-none">tune</span>
                </button>
                <button
                  @click="toggleStatus(company)"
                  class="p-1.5 text-gray-400 hover:text-orange-600 hover:bg-orange-50 rounded transition-colors"
                  :title="company.status === 'active' ? '停用' : '啟用'"
                >
                  <span class="material-icons text-base leading-none">{{ company.status === 'active' ? 'block' : 'check_circle' }}</span>
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="store.list.length === 0">
            <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">尚無公司，點擊新增</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create company modal -->
    <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50" @click="showCreate = false" />
      <div class="relative bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">新增公司</h3>
        <input
          v-model="newName"
          type="text"
          placeholder="公司名稱"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 mb-4"
          @keyup.enter="createCompany"
        />
        <div class="flex gap-3">
          <button @click="showCreate = false" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50">取消</button>
          <button @click="createCompany" :disabled="!newName.trim() || creating" class="flex-1 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
            {{ creating ? '建立中...' : '建立' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Feature matrix modal -->
    <div v-if="featureCompany" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50" @click="featureCompany = null" />
      <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[85vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white">
          <h3 class="text-base font-semibold text-gray-900">功能設定 — {{ featureCompany.name }}</h3>
          <div class="flex items-center gap-3">
            <button @click="copyInviteCode" class="text-xs text-blue-600 hover:underline flex items-center gap-1">
              <span class="material-icons text-sm leading-none">content_copy</span>
              邀請碼
            </button>
            <button @click="featureCompany = null" class="text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
          </div>
        </div>

        <div v-if="featuresLoading" class="py-12 text-center text-sm text-gray-400">載入中...</div>

        <div v-else class="px-6 py-4">
          <div v-for="category in categories" :key="category.key" class="mb-6">
            <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">{{ category.label }}</h4>
            <div class="space-y-2">
              <div
                v-for="f in featuresByCategory(category.key)"
                :key="f.key"
                class="flex items-center justify-between py-2.5 px-3 rounded-lg border border-gray-100 hover:bg-gray-50"
              >
                <div>
                  <p class="text-sm font-medium text-gray-800">{{ f.name }}</p>
                  <p class="text-xs text-gray-400">{{ f.description }}</p>
                </div>
                <button
                  @click="toggleFeature(f)"
                  class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors shrink-0"
                  :class="f.enabled ? 'bg-blue-600' : 'bg-gray-200'"
                >
                  <span
                    class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                    :class="f.enabled ? 'translate-x-6' : 'translate-x-1'"
                  />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Employee panel modal -->
    <div v-if="employeeCompany" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50" @click="employeeCompany = null" />
      <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[85vh] flex flex-col">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white">
          <div>
            <h3 class="text-base font-semibold text-gray-900">{{ employeeCompany.name }}</h3>
            <p class="text-xs text-gray-400 mt-0.5">公司成員管理</p>
          </div>
          <div class="flex items-center gap-3">
            <button
              @click="openAddUser"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-lg hover:bg-blue-700 transition-colors"
            >
              <span class="material-icons text-sm leading-none">person_add</span>
              新增使用者
            </button>
            <button @click="employeeCompany = null" class="text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
          </div>
        </div>

        <div class="overflow-y-auto flex-1">
          <div v-if="employeesLoading" class="py-12 text-center text-sm text-gray-400">載入中...</div>
          <table v-else class="w-full">
            <thead>
              <tr class="border-b border-gray-100 bg-gray-50">
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">名稱</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Email</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">角色</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">狀態</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">加入日期</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="emp in employees" :key="emp.id" class="hover:bg-gray-50">
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center font-medium shrink-0">
                      {{ emp.name.charAt(0) }}
                    </div>
                    <span class="text-sm font-medium text-gray-800">{{ emp.name }}</span>
                  </div>
                </td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ emp.email }}</td>
                <td class="px-4 py-3">
                  <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium" :class="roleStyle[emp.role]">
                    {{ roleLabel[emp.role] }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span class="inline-flex items-center gap-1 text-xs"
                    :class="emp.status === 'active' ? 'text-green-600' : 'text-gray-400'">
                    <span class="w-1.5 h-1.5 rounded-full inline-block"
                      :class="emp.status === 'active' ? 'bg-green-500' : 'bg-gray-300'"></span>
                    {{ emp.status === 'active' ? '啟用' : '停用' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-400">{{ emp.created_at }}</td>
              </tr>
              <tr v-if="employees.length === 0">
                <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-400">此公司尚無成員</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- User Modal -->
    <UserModal
      v-if="showUserModal"
      :user="null"
      :company-id="employeeCompany?.id ?? null"
      @close="showUserModal = false"
      @saved="onUserSaved"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useCompanyStore, type Company, type CompanyFeature } from '@/stores/company'
import { useToast } from '@/composables/useToast'
import UserModal from '@/components/UserModal.vue'
import api from '@/lib/axios'

const store = useCompanyStore()
const toast = useToast()

const showCreate = ref(false)
const newName = ref('')
const creating = ref(false)

const featureCompany = ref<Company | null>(null)
const featureList = ref<CompanyFeature[]>([])
const featuresLoading = ref(false)

const employeeCompany = ref<Company | null>(null)
const employees = ref<any[]>([])
const employeesLoading = ref(false)
const showUserModal = ref(false)

const roleLabel: Record<string, string> = { admin: '管理員', manager: '經理', member: '成員' }
const roleStyle: Record<string, string> = {
  admin: 'bg-purple-100 text-purple-700',
  manager: 'bg-blue-100 text-blue-700',
  member: 'bg-gray-100 text-gray-600',
}

const categories = [
  { key: 'member',  label: '成員管理' },
  { key: 'project', label: '專案功能' },
  { key: 'report',  label: '報表分析' },
  { key: 'system',  label: '系統設定' },
]

function featuresByCategory(cat: string) {
  return featureList.value.filter(f => f.category === cat)
}

onMounted(() => store.fetchList())

async function createCompany() {
  if (!newName.value.trim() || creating.value) return
  creating.value = true
  try {
    await store.create(newName.value.trim())
    toast.success('公司已建立')
    newName.value = ''
    showCreate.value = false
  } catch {
    toast.error('建立失敗')
  } finally {
    creating.value = false
  }
}

async function toggleStatus(company: Company) {
  const next = company.status === 'active' ? 'suspended' : 'active'
  try {
    await store.update(company.id, { status: next })
    toast.success(next === 'active' ? '已啟用' : '已停用')
  } catch {
    toast.error('操作失敗，請再試一次')
  }
}

async function openFeatures(company: Company) {
  featureCompany.value = company
  featuresLoading.value = true
  try {
    featureList.value = await store.fetchFeatures(company.id)
  } finally {
    featuresLoading.value = false
  }
}

async function toggleFeature(f: CompanyFeature) {
  if (!featureCompany.value) return
  const prev = f.enabled
  f.enabled = !f.enabled
  try {
    await store.toggleFeature(featureCompany.value.id, f.key, f.enabled)
  } catch {
    f.enabled = prev
    toast.error('操作失敗，請再試一次')
  }
}

async function copyInviteCode() {
  if (!featureCompany.value) return
  let code = featureCompany.value.invite_code
  if (!code) {
    code = await store.regenerateInviteCode(featureCompany.value.id)
  }
  await navigator.clipboard.writeText(code)
  toast.success(`邀請碼已複製：${code}`)
}

async function openEmployees(company: Company) {
  employeeCompany.value = company
  employeesLoading.value = true
  employees.value = []
  try {
    const { data } = await api.get(`/admin/companies/${company.id}/users`)
    employees.value = data
  } finally {
    employeesLoading.value = false
  }
}

function openAddUser() {
  showUserModal.value = true
}

async function onUserSaved() {
  showUserModal.value = false
  toast.success('使用者已新增')
  if (employeeCompany.value) {
    await openEmployees(employeeCompany.value)
    await store.fetchList()
  }
}
</script>
