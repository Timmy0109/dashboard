<template>
  <div>
    <div class="mb-6 d-flex align-center justify-space-between flex-wrap gap-3">
      <div>
        <h2 class="text-h6 font-weight-bold">系統管理</h2>
        <p class="text-body-2 text-grey">管理所有公司帳戶與功能權限</p>
      </div>
      <v-btn color="primary" prepend-icon="mdi-plus" rounded="lg" @click="showCreate = true">新增公司</v-btn>
    </div>

    <!-- Company list -->
    <v-card rounded="xl">
      <v-data-table
        :headers="companyHeaders"
        :items="store.list"
        :loading="store.loading"
        :search="search"
        hover
        item-value="id"
        @click:row="(_e: Event, { item }: any) => openEmployees(item)"
      >
        <template #top>
          <v-toolbar flat rounded="t-xl">
            <v-text-field
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              placeholder="搜尋公司..."
              variant="outlined"
              density="compact"
              hide-details
              rounded="lg"
              class="mx-4 my-2"
              style="max-width:300px"
            />
          </v-toolbar>
        </template>

        <template #item.status="{ item }">
          <v-chip :color="item.status === 'active' ? 'success' : 'error'" size="small" variant="tonal">
            {{ item.status === 'active' ? '運作中' : '已停用' }}
          </v-chip>
        </template>

        <template #item.managers_count="{ item }">{{ item.managers_count }} 人</template>
        <template #item.members_count="{ item }">{{ item.members_count }} 人</template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-1" @click.stop>
            <v-btn icon="mdi-tune" size="small" variant="text" color="grey" title="功能設定" @click="openFeatures(item)" />
            <v-btn
              :icon="item.status === 'active' ? 'mdi-domain-off' : 'mdi-domain'"
              size="small" variant="text"
              :color="item.status === 'active' ? 'warning' : 'success'"
              :title="item.status === 'active' ? '停用' : '啟用'"
              @click="toggleStatus(item)"
            />
          </div>
        </template>

        <template #no-data>
          <div class="text-center py-8 text-grey">尚無公司，點擊新增</div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Create company dialog -->
    <v-dialog v-model="showCreate" max-width="400" persistent>
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-3">新增公司</v-card-title>
        <v-card-text class="pa-5 pt-0">
          <v-text-field v-model="newName" label="公司名稱" autofocus @keyup.enter="createCompany" />
        </v-card-text>
        <v-card-actions class="pa-4 pt-0 gap-2">
          <v-btn variant="outlined" color="grey" class="flex-grow-1" @click="showCreate = false">取消</v-btn>
          <v-btn color="white" class="flex-grow-1 bg-primary" :disabled="!newName.trim()" :loading="creating" @click="createCompany">建立</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Feature matrix dialog -->
    <v-dialog v-model="showFeatures" max-width="680" scrollable>
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-3 d-flex align-center justify-space-between">
          <span class="text-body-1 font-weight-semibold">功能設定 — {{ featureCompany?.name }}</span>
          <div class="d-flex align-center gap-2">
            <v-btn variant="text" color="primary" prepend-icon="mdi-content-copy" size="small" @click="copyInviteCode">邀請碼</v-btn>
            <v-btn icon="mdi-close" variant="text" size="small" @click="showFeatures = false" />
          </div>
        </v-card-title>
        <v-divider />
        <v-card-text>
          <div v-if="featuresLoading" class="d-flex justify-center py-8">
            <v-progress-circular indeterminate color="primary" />
          </div>
          <div v-else>
            <div v-for="cat in categories" :key="cat.key" class="mb-5">
              <div class="text-caption text-grey font-weight-bold text-uppercase mb-2">{{ cat.label }}</div>
              <v-list rounded="lg" bg-color="grey-lighten-5" class="pa-0">
                <template v-for="(f, idx) in featuresByCategory(cat.key)" :key="f.key">
                  <v-list-item class="px-4 py-2">
                    <template #title>
                      <span class="text-body-2 font-weight-medium">{{ f.name }}</span>
                    </template>
                    <template #subtitle>
                      <span class="text-caption text-grey">{{ f.description }}</span>
                    </template>
                    <template #append>
                      <v-switch
                        :model-value="f.enabled"
                        color="primary"
                        hide-details
                        density="compact"
                        @update:model-value="toggleFeature(f)"
                      />
                    </template>
                  </v-list-item>
                  <v-divider v-if="idx < featuresByCategory(cat.key).length - 1" />
                </template>
              </v-list>
            </div>
          </div>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- Employee panel dialog -->
    <v-dialog v-model="showEmployees" max-width="720" scrollable>
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-3 d-flex align-center justify-space-between">
          <div>
            <div class="text-body-1 font-weight-semibold">{{ employeeCompany?.name }}</div>
            <div class="text-caption text-grey">公司成員管理</div>
          </div>
          <div class="d-flex align-center gap-2">
            <v-btn color="primary" prepend-icon="mdi-account-plus" size="small" rounded="lg" @click="showUserModal = true">新增使用者</v-btn>
            <v-btn icon="mdi-close" variant="text" size="small" @click="showEmployees = false" />
          </div>
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-0">
          <div v-if="employeesLoading" class="d-flex justify-center py-8">
            <v-progress-circular indeterminate color="primary" />
          </div>
          <v-data-table
            v-else
            :headers="employeeHeaders"
            :items="employees"
            density="compact"
            hide-default-footer
            item-value="id"
          >
            <template #item.name="{ item }">
              <div class="d-flex align-center gap-2 py-1">
                <v-avatar color="primary" size="28">
                  <span class="text-caption text-white font-weight-bold">{{ item.name.charAt(0) }}</span>
                </v-avatar>
                <span class="text-body-2 font-weight-medium">{{ item.name }}</span>
              </div>
            </template>
            <template #item.role="{ item }">
              <v-chip
                size="x-small"
                :color="item.role === 'admin' ? 'deep-purple' : item.role === 'manager' ? 'primary' : 'default'"
                variant="tonal"
              >
                {{ roleLabel[item.role] ?? item.role }}
              </v-chip>
            </template>
            <template #item.status="{ item }">
              <v-chip :color="item.status === 'active' ? 'success' : 'default'" size="x-small" variant="tonal">
                {{ item.status === 'active' ? '啟用' : '停用' }}
              </v-chip>
            </template>
          </v-data-table>
        </v-card-text>
      </v-card>
    </v-dialog>

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
const search = ref('')

const showCreate = ref(false)
const newName = ref('')
const creating = ref(false)

const showFeatures = ref(false)
const featureCompany = ref<Company | null>(null)
const featureList = ref<CompanyFeature[]>([])
const featuresLoading = ref(false)

const showEmployees = ref(false)
const employeeCompany = ref<Company | null>(null)
const employees = ref<any[]>([])
const employeesLoading = ref(false)
const showUserModal = ref(false)

const roleLabel: Record<string, string> = { admin: '管理員', manager: '經理', member: '成員' }

const companyHeaders = [
  { title: '公司名稱', key: 'name',           sortable: true },
  { title: '狀態',    key: 'status',          sortable: true },
  { title: 'Manager', key: 'managers_count',  sortable: true },
  { title: '成員數',   key: 'members_count',   sortable: true },
  { title: '建立日期', key: 'created_at',      sortable: true },
  { title: '',       key: 'actions',          sortable: false, width: '100px' },
]

const employeeHeaders = [
  { title: '名稱',   key: 'name',       sortable: true },
  { title: 'Email', key: 'email',      sortable: true },
  { title: '角色',   key: 'role',       sortable: true },
  { title: '狀態',   key: 'status',     sortable: true },
  { title: '加入日期', key: 'created_at', sortable: true },
]

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
    toast.error('操作失敗')
  }
}

async function openFeatures(company: Company) {
  featureCompany.value = company
  showFeatures.value = true
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
    toast.error('操作失敗')
  }
}

async function copyInviteCode() {
  if (!featureCompany.value) return
  let code = featureCompany.value.invite_code
  if (!code) code = await store.regenerateInviteCode(featureCompany.value.id)
  await navigator.clipboard.writeText(code)
  toast.success(`邀請碼已複製：${code}`)
}

async function openEmployees(company: Company) {
  employeeCompany.value = company
  showEmployees.value = true
  employeesLoading.value = true
  employees.value = []
  try {
    const { data } = await api.get(`/admin/companies/${company.id}/users`)
    employees.value = data
  } finally {
    employeesLoading.value = false
  }
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
