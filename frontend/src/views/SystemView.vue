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
        :items="filteredCompanies"
        :loading="store.loading"
        :search="search"
        hover
        item-value="id"
        @click:row="(_e: Event, { item }: any) => !item.deleted_at && openEmployees(item)"
      >
        <template #top>
          <v-toolbar flat rounded="t-xl">
            <div class="d-flex align-center gap-3 pa-3 w-100 flex-wrap">
              <v-text-field
                v-model="search"
                prepend-inner-icon="mdi-magnify"
                placeholder="搜尋公司..."
                variant="outlined"
                density="compact"
                hide-details
                rounded="lg"
                style="max-width:260px"
              />
              <v-btn-toggle v-model="showTrashed" mandatory density="compact" rounded="lg" color="primary">
                <v-btn :value="false" size="small">啟用中</v-btn>
                <v-btn :value="true" size="small">已刪除</v-btn>
              </v-btn-toggle>
            </div>
          </v-toolbar>
        </template>

        <template #item.name="{ item }">
          <span :class="item.deleted_at ? 'text-grey text-decoration-line-through' : 'font-weight-medium'">
            {{ item.name }}
          </span>
        </template>

        <template #item.status="{ item }">
          <v-chip
            :color="item.deleted_at ? 'grey' : item.status === 'active' ? 'success' : 'error'"
            size="small" variant="tonal"
          >
            {{ item.deleted_at ? '已刪除' : item.status === 'active' ? '運作中' : '已停用' }}
          </v-chip>
        </template>

        <template #item.managers_count="{ item }">{{ item.managers_count }} 人</template>
        <template #item.members_count="{ item }">{{ item.members_count }} 人</template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-1" @click.stop>
            <template v-if="!item.deleted_at">
              <v-btn icon="mdi-tune" size="small" variant="text" color="grey" title="功能設定" @click="openFeatures(item)" />
              <v-btn
                :icon="item.status === 'active' ? 'mdi-domain-off' : 'mdi-domain'"
                size="small" variant="text"
                :color="item.status === 'active' ? 'warning' : 'success'"
                :title="item.status === 'active' ? '停用' : '啟用'"
                @click="toggleStatus(item)"
              />
              <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" title="刪除公司" @click="confirmDelete(item)" />
            </template>
            <template v-else>
              <v-btn icon="mdi-restore" size="small" variant="text" color="success" title="還原公司" @click="handleRestore(item)" />
            </template>
          </div>
        </template>

        <template #no-data>
          <div class="text-center py-8 text-grey">
            {{ showTrashed ? '沒有已刪除的公司' : '尚無公司，點擊新增' }}
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Create company dialog -->
    <v-dialog v-model="showCreate" max-width="420" persistent>
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-3 d-flex align-center justify-space-between bg-primary rounded-t-xl">
          <span class="text-body-1 font-weight-semibold text-white">新增公司</span>
          <v-btn icon="mdi-close" variant="text" size="small" color="white" @click="showCreate = false" />
        </v-card-title>
        <v-card-text class="pa-5">
          <v-text-field v-model="newName" label="公司名稱" autofocus class="mb-2" @keyup.enter="createCompany" />
        </v-card-text>
        <v-card-actions class="pa-5 pt-0 gap-3">
          <v-btn variant="outlined" color="grey" style="flex:1" @click="showCreate = false">取消</v-btn>
          <v-btn color="primary" style="flex:1" :disabled="!newName.trim()" :loading="creating" @click="createCompany">建立</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete confirm dialog -->
    <v-dialog v-model="showDeleteConfirm" max-width="380" persistent>
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-3 d-flex align-center justify-space-between bg-error rounded-t-xl">
          <span class="text-body-1 font-weight-semibold text-white">確認刪除公司</span>
          <v-btn icon="mdi-close" variant="text" size="small" color="white" @click="showDeleteConfirm = false" />
        </v-card-title>
        <v-card-text class="pa-5">
          <p class="text-body-2">確定要刪除公司「<strong>{{ deleteTarget?.name }}</strong>」？</p>
          <p class="text-caption text-grey mt-1">公司資料將被軟刪除，可在「已刪除」頁籤中還原。</p>
        </v-card-text>
        <v-card-actions class="pa-5 pt-0 gap-3">
          <v-btn variant="outlined" color="grey" class="flex-grow-1" @click="showDeleteConfirm = false">取消</v-btn>
          <v-btn color="error" class="flex-grow-1" :loading="deleting" @click="handleDelete">刪除</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Feature matrix dialog -->
    <v-dialog v-model="showFeatures" max-width="680" scrollable>
      <v-card rounded="xl" height="600">
        <v-card-title class="pa-5 pb-3 d-flex align-center justify-space-between bg-primary rounded-t-xl">
          <span class="text-body-1 font-weight-semibold text-white">功能設定 — {{ featureCompany?.name }}</span>
          <div class="d-flex align-center gap-2">
            <v-btn variant="tonal" color="white" prepend-icon="mdi-content-copy" size="small" @click="copyInviteCode">邀請碼</v-btn>
            <v-btn icon="mdi-close" variant="text" size="small" color="white" @click="showFeatures = false" />
          </div>
        </v-card-title>
        <v-divider />
        <v-card-text class="overflow-y-auto">
          <div v-if="featuresLoading" class="d-flex justify-center py-8">
            <v-progress-circular indeterminate color="primary" />
          </div>
          <div v-else class="pt-2">
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
    <v-dialog v-model="showEmployees" max-width="760" scrollable>
      <v-card rounded="xl" height="620">
        <v-card-title class="pa-5 pb-3 d-flex align-center justify-space-between bg-primary rounded-t-xl" style="flex-shrink:0">
          <div>
            <div class="text-body-1 font-weight-semibold text-white">{{ employeeCompany?.name }}</div>
            <div class="text-caption" style="color:rgba(255,255,255,.7)">公司成員管理</div>
          </div>
          <div class="d-flex align-center gap-2">
            <v-btn color="white" variant="tonal" prepend-icon="mdi-account-plus" size="small" rounded="lg" @click="showUserModal = true">新增使用者</v-btn>
            <v-btn icon="mdi-close" variant="text" size="small" color="white" @click="showEmployees = false" />
          </div>
        </v-card-title>
        <v-divider />

        <!-- Search bar -->
        <div class="px-4 py-3" style="flex-shrink:0">
          <v-text-field
            v-model="employeeSearch"
            prepend-inner-icon="mdi-magnify"
            placeholder="搜尋員工姓名 / Email..."
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            clearable
          />
        </div>
        <v-divider />

        <v-card-text class="pa-0 overflow-y-auto" style="flex:1">
          <div v-if="employeesLoading" class="d-flex justify-center py-8">
            <v-progress-circular indeterminate color="primary" />
          </div>
          <v-data-table
            v-else
            :headers="employeeHeaders"
            :items="employees"
            :search="employeeSearch"
            density="comfortable"
            item-value="id"
            hide-default-footer
            fixed-header
          >
            <template #item.name="{ item }">
              <div class="d-flex align-center gap-2 py-1">
                <v-avatar color="primary" size="30">
                  <span class="text-caption text-white font-weight-bold">{{ item.name.charAt(0) }}</span>
                </v-avatar>
                <div>
                  <div class="text-body-2 font-weight-medium">{{ item.name }}</div>
                  <div class="text-caption text-grey">{{ item.email }}</div>
                </div>
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
            <template #item.created_at="{ item }">
              <span class="text-caption text-grey">{{ item.created_at }}</span>
            </template>
            <template #no-data>
              <div class="text-center py-8 text-grey text-body-2">
                {{ employeeSearch ? '找不到符合的員工' : '此公司尚無員工' }}
              </div>
            </template>
          </v-data-table>
        </v-card-text>

        <!-- Footer stats -->
        <v-divider />
        <div class="px-5 py-3 d-flex align-center gap-4" style="flex-shrink:0">
          <span class="text-caption text-grey">
            共 <strong>{{ employees.length }}</strong> 名員工
          </span>
          <v-chip size="x-small" color="primary" variant="tonal">
            Manager {{ employees.filter(e => e.role === 'manager').length }}
          </v-chip>
          <v-chip size="x-small" color="default" variant="tonal">
            成員 {{ employees.filter(e => e.role === 'member').length }}
          </v-chip>
        </div>
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
import { ref, computed, onMounted } from 'vue'
import { useCompanyStore, type Company, type CompanyFeature } from '@/stores/company'
import { useToast } from '@/composables/useToast'
import UserModal from '@/components/UserModal.vue'
import api from '@/lib/axios'

const store = useCompanyStore()
const toast = useToast()
const search = ref('')
const showTrashed = ref(false)

const filteredCompanies = computed(() =>
  store.list.filter(c => showTrashed.value ? !!c.deleted_at : !c.deleted_at)
)

const showCreate = ref(false)
const newName = ref('')
const creating = ref(false)

const showDeleteConfirm = ref(false)
const deleteTarget = ref<Company | null>(null)
const deleting = ref(false)

const showFeatures = ref(false)
const featureCompany = ref<Company | null>(null)
const featureList = ref<CompanyFeature[]>([])
const featuresLoading = ref(false)

const showEmployees = ref(false)
const employeeCompany = ref<Company | null>(null)
const employees = ref<any[]>([])
const employeesLoading = ref(false)
const employeeSearch = ref('')
const showUserModal = ref(false)

const roleLabel: Record<string, string> = { admin: '管理員', manager: '經理', member: '成員' }

const companyHeaders = [
  { title: '公司名稱', key: 'name',           sortable: true },
  { title: '狀態',    key: 'status',          sortable: true },
  { title: 'Manager', key: 'managers_count',  sortable: true },
  { title: '成員數',   key: 'members_count',   sortable: true },
  { title: '建立日期', key: 'created_at',      sortable: true },
  { title: '',       key: 'actions',          sortable: false, width: '120px' },
]

const employeeHeaders = [
  { title: '姓名 / Email', key: 'name',       sortable: true },
  { title: '角色',         key: 'role',       sortable: true },
  { title: '狀態',         key: 'status',     sortable: true },
  { title: '加入日期',     key: 'created_at', sortable: true },
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

onMounted(() => store.fetchList(true))

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

function confirmDelete(company: Company) {
  deleteTarget.value = company
  showDeleteConfirm.value = true
}

async function handleDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await store.deleteCompany(deleteTarget.value.id)
    toast.success('公司已刪除')
    showDeleteConfirm.value = false
  } catch {
    toast.error('刪除失敗')
  } finally {
    deleting.value = false
  }
}

async function handleRestore(company: Company) {
  try {
    await store.restoreCompany(company.id)
    toast.success('公司已還原')
  } catch {
    toast.error('還原失敗')
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
  employeeSearch.value = ''
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
    await store.fetchList(true)
  }
}
</script>
