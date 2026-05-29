<template>
  <div>
    <!-- Page header -->
    <div class="mb-6 d-flex align-center justify-space-between flex-wrap gap-3">
      <div>
        <h2 class="text-h6 font-weight-bold">成員管理</h2>
        <p class="text-body-2 text-medium-emphasis">管理公司成員與待審核申請</p>
      </div>
      <div class="d-flex align-center gap-2">
        <v-btn
          variant="outlined"
          color="warning"
          prepend-icon="mdi-account-clock"
          rounded="lg"
          @click="openPendingDialog"
        >
          <span>待審核申請</span>
          <v-badge
            v-if="pendingCount > 0"
            :content="pendingCount"
            color="error"
            inline
            class="ml-1"
          />
        </v-btn>
        <v-btn color="primary" prepend-icon="mdi-account-plus" rounded="lg" @click="openCreateUser">
          新增成員
        </v-btn>
      </div>
    </div>

    <!-- KPI strip -->
    <v-row dense class="mb-4">
      <v-col cols="12" sm="6" md="3">
        <KPICard
          label="全部成員"
          :value="members.length"
          icon="mdi-account-group"
          icon-color="primary"
          accent="primary"
          sub="公司所有成員"
        />
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <KPICard
          label="經理數"
          :value="managerCount"
          icon="mdi-account-tie"
          icon-color="info"
          accent="info"
          sub="manager 角色"
        />
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <KPICard
          label="啟用中"
          :value="activeCount"
          icon="mdi-account-check"
          icon-color="success"
          accent="success"
          sub="目前可登入"
        />
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <KPICard
          label="待審核"
          :value="pendingCount"
          icon="mdi-account-clock"
          icon-color="warning"
          accent="warning"
          sub="邀請碼申請"
        />
      </v-col>
    </v-row>

    <!-- All members table -->
    <v-card rounded="xl">
      <v-data-table
        :headers="headers"
        :items="filteredMembers"
        :loading="loading"
        :search="search"
        hover
        item-value="id"
        @click:row="onRowClick"
      >
        <template #top>
          <v-toolbar flat rounded="t-xl">
            <div class="d-flex align-center gap-3 pa-3 w-100 flex-wrap">
              <v-text-field
                v-model="search"
                prepend-inner-icon="mdi-magnify"
                placeholder="搜尋成員..."
                variant="outlined"
                density="compact"
                hide-details
                rounded="lg"
                style="max-width:260px"
              />
              <ChipGroup
                v-model="statusFilter"
                :items="chipItems"
              />
            </div>
          </v-toolbar>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <v-avatar
              class="mr-2"
              size="32"
              :color="item.status === 'active' ? 'primary' : item.status === 'pending' ? 'warning' : 'grey'"
            >
              <span class="text-caption text-white font-weight-bold">{{ item.name.charAt(0) }}</span>
            </v-avatar>
            <span class="text-body-2 font-weight-medium">{{ item.name }}</span>
          </div>
        </template>

        <template #item.role="{ item }">
          <v-chip
            size="x-small"
            :color="item.role === 'manager' ? 'primary' : 'default'"
            variant="tonal"
          >
            {{ roleLabel[item.role ?? 'member'] }}
          </v-chip>
        </template>

        <template #item.status="{ item }">
          <v-chip
            :color="item.status === 'active' ? 'success' : item.status === 'pending' ? 'warning' : 'default'"
            size="small"
            variant="tonal"
          >
            {{ statusLabel[item.status] ?? item.status }}
          </v-chip>
        </template>

        <template #item.created_at="{ item }">
          <span class="text-caption text-medium-emphasis">{{ item.created_at }}</span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-1" @click.stop>
            <template v-if="item.status === 'pending'">
              <v-btn icon="mdi-check"  size="small" variant="text" color="success" title="核准" @click="approve(item)" />
              <v-btn icon="mdi-close"  size="small" variant="text" color="error"   title="拒絕" @click="reject(item)" />
            </template>
            <v-btn
              v-if="item.status !== 'pending'"
              :icon="item.status === 'active' ? 'mdi-account-off' : 'mdi-account-check'"
              size="small"
              variant="text"
              :color="item.status === 'active' ? 'warning' : 'success'"
              :title="item.status === 'active' ? '停用' : '啟用'"
              @click="toggleStatus(item)"
            />
            <v-btn icon="mdi-pencil" size="small" variant="text" color="grey"  title="編輯" @click="openEdit(item)" />
            <v-btn icon="mdi-delete" size="small" variant="text" color="error" title="刪除" @click="deleteMember(item)" />
          </div>
        </template>

        <template #no-data>
          <div class="py-6">
            <EmptyState
              :icon="search ? 'mdi-account-search-outline' : 'mdi-account-multiple-outline'"
              :title="emptyTitle"
              :sub="emptySub"
            />
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Pending approvals dialog -->
    <v-dialog v-model="showPending" max-width="540" scrollable>
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-3 d-flex align-center justify-space-between">
          <div>
            <div class="text-body-1 font-weight-semibold">待審核申請</div>
            <div class="text-caption text-medium-emphasis">透過邀請碼申請加入的成員</div>
          </div>
          <v-btn icon="mdi-close" variant="text" size="small" @click="showPending = false" />
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-0">
          <div v-if="pendingLoading" class="d-flex justify-center align-center py-12">
            <v-progress-circular indeterminate color="primary" size="32" />
          </div>

          <div v-else-if="pending.length === 0" class="py-6">
            <EmptyState
              icon="mdi-check-circle-outline"
              title="目前沒有待審核的申請"
              sub="所有申請都已處理完畢。"
            />
          </div>

          <v-list v-else lines="two" class="pa-0">
            <template v-for="(p, idx) in pending" :key="p.id">
              <v-list-item class="px-5 py-3">
                <template #prepend>
                  <v-avatar color="warning" size="36">
                    <span class="text-body-2 font-weight-bold text-white">{{ p.name.charAt(0) }}</span>
                  </v-avatar>
                </template>
                <template #title>
                  <span class="text-body-2 font-weight-medium">{{ p.name }}</span>
                </template>
                <template #subtitle>
                  <span class="text-caption text-medium-emphasis">{{ p.email }} · {{ p.created_at }}</span>
                </template>
                <template #append>
                  <div class="d-flex gap-2">
                    <v-btn size="small" color="success" variant="tonal" rounded="lg" @click="approve(p)">核准</v-btn>
                    <v-btn size="small" color="error"   variant="tonal" rounded="lg" @click="reject(p)">拒絕</v-btn>
                  </div>
                </template>
              </v-list-item>
              <v-divider v-if="idx < pending.length - 1" />
            </template>
          </v-list>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- User Modal — create / edit -->
    <UserModal
      v-if="showUserModal"
      :user="editingUser as unknown as Record<string, unknown> | null"
      :company-id="null"
      @close="showUserModal = false"
      @saved="onUserSaved"
    />

    <!-- Member workload dialog (open on row click) -->
    <MemberWorkloadDialog
      v-if="workloadUserId !== null"
      :user-id="workloadUserId"
      @close="workloadUserId = null"
      @edit="onWorkloadEdit"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/lib/axios'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import MemberWorkloadDialog from '@/components/MemberWorkloadDialog.vue'
import UserModal from '@/components/UserModal.vue'
import KPICard from '@/components/ui/KPICard.vue'
import ChipGroup from '@/components/ui/ChipGroup.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

interface Member {
  id: number
  name: string
  email: string
  role?: 'manager' | 'member'
  status: 'active' | 'pending' | 'inactive'
  company_name?: string | null
  created_at: string
}

interface PendingMember {
  id: number
  name: string
  email: string
  company_name?: string | null
  created_at: string
}

type StatusFilter = 'all' | 'active' | 'pending' | 'inactive'

const auth = useAuthStore()
const toast = useToast()
const search = ref('')
const statusFilter = ref<StatusFilter>('all')

const loading = ref(false)
const members = ref<Member[]>([])

const showPending = ref(false)
const pendingLoading = ref(false)
const pending = ref<PendingMember[]>([])
const pendingCount = computed(() => pending.value.length)

const showUserModal = ref(false)
const editingUser = ref<Member | null>(null)

// Workload dialog state — set userId to open, null to close
const workloadUserId = ref<number | null>(null)

const statusLabel: Record<string, string> = {
  active: '啟用', pending: '待審核', inactive: '停用',
}

const roleLabel: Record<string, string> = {
  admin: '管理員', manager: '經理', member: '成員',
}

const activeCount = computed(() => members.value.filter(m => m.status === 'active').length)
const inactiveCount = computed(() => members.value.filter(m => m.status === 'inactive').length)
const managerCount = computed(() => members.value.filter(m => m.role === 'manager').length)

const filteredMembers = computed(() => {
  if (statusFilter.value === 'all') return members.value
  return members.value.filter(m => m.status === statusFilter.value)
})

const chipItems = computed(() => [
  { value: 'all'      as const, label: '全部',  count: members.value.length },
  { value: 'active'   as const, label: '啟用',  count: activeCount.value },
  { value: 'pending'  as const, label: '待審核', count: pendingCount.value },
  { value: 'inactive' as const, label: '已停用', count: inactiveCount.value },
])

const emptyTitle = computed(() => {
  if (search.value) return '找不到符合的成員'
  if (statusFilter.value === 'pending') return '目前沒有待審核成員'
  if (statusFilter.value === 'inactive') return '沒有已停用的成員'
  return '尚無成員'
})

const emptySub = computed(() => {
  if (search.value) return '試試其他關鍵字'
  if (statusFilter.value === 'all') return '可點擊右上角「新增成員」加入第一位成員'
  return '切換篩選或新增成員以開始管理'
})

// `auth` retained for future use (e.g., extra UI gating); reference to avoid lint warning
void auth

const headers = [
  { title: '姓名',     key: 'name',       sortable: true },
  { title: 'Email',   key: 'email',      sortable: true },
  { title: '角色',     key: 'role',       sortable: true },
  { title: '狀態',     key: 'status',     sortable: true },
  { title: '加入日期', key: 'created_at', sortable: true },
  { title: '',        key: 'actions',    sortable: false, width: '160px' },
]

function onRowClick(_evt: Event, ctx: { item: Member }) {
  workloadUserId.value = ctx.item.id
}

function onWorkloadEdit(userId: number) {
  const m = members.value.find(x => x.id === userId)
  if (m) openEdit(m)
}

async function fetchMembers() {
  loading.value = true
  try {
    const { data } = await api.get<Member[]>('/manager/members')
    members.value = data
  } finally {
    loading.value = false
  }
}

async function fetchPending() {
  pendingLoading.value = true
  try {
    const { data } = await api.get<PendingMember[]>('/manager/members/pending')
    pending.value = data
  } finally {
    pendingLoading.value = false
  }
}

async function openPendingDialog() {
  showPending.value = true
  await fetchPending()
}

async function approve(p: PendingMember | Member) {
  try {
    await api.post(`/manager/members/${p.id}/approve`)
    pending.value = pending.value.filter(m => m.id !== p.id)
    const idx = members.value.findIndex(m => m.id === p.id)
    if (idx !== -1) members.value[idx]!.status = 'active'
    else await fetchMembers()
    toast.success(`已核准 ${p.name}`)
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    toast.error(e?.response?.data?.message ?? '操作失敗')
  }
}

async function reject(p: PendingMember | Member) {
  if (!confirm(`確定要拒絕 ${p.name} 的申請？`)) return
  try {
    await api.post(`/manager/members/${p.id}/reject`)
    pending.value = pending.value.filter(m => m.id !== p.id)
    const idx = members.value.findIndex(m => m.id === p.id)
    if (idx !== -1) members.value[idx]!.status = 'inactive'
    else await fetchMembers()
    toast.success('已拒絕申請')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    toast.error(e?.response?.data?.message ?? '操作失敗')
  }
}

async function toggleStatus(m: Member) {
  const next = m.status === 'active' ? 'inactive' : 'active'
  try {
    await api.put(`/manager/members/${m.id}`, { status: next })
    m.status = next
    toast.success(next === 'active' ? `已啟用 ${m.name}` : `已停用 ${m.name}`)
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    toast.error(e?.response?.data?.message ?? '操作失敗')
  }
}

function openCreateUser() {
  editingUser.value = null
  showUserModal.value = true
}

function openEdit(m: Member) {
  editingUser.value = m
  showUserModal.value = true
}

async function onUserSaved() {
  showUserModal.value = false
  toast.success('成員資料已更新')
  await fetchMembers()
}

async function deleteMember(m: Member) {
  if (!confirm(`確定要刪除「${m.name}」？此操作無法復原。`)) return
  try {
    await api.delete(`/manager/members/${m.id}`)
    members.value = members.value.filter(u => u.id !== m.id)
    toast.success(`已刪除 ${m.name}`)
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    toast.error(e?.response?.data?.message ?? '刪除失敗')
  }
}

onMounted(async () => {
  await Promise.all([fetchMembers(), fetchPending()])
})
</script>
