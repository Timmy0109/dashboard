<template>
  <div>
    <div class="mb-6 d-flex align-center justify-space-between flex-wrap gap-3">
      <div>
        <h2 class="text-h6 font-weight-bold">成員管理</h2>
        <p class="text-body-2 text-grey">查看公司所有成員</p>
      </div>
      <v-btn
        variant="outlined"
        color="warning"
        prepend-icon="mdi-account-clock"
        rounded="lg"
        @click="openPendingDialog"
      >
        待審核申請
        <v-badge
          v-if="pendingCount > 0"
          :content="pendingCount"
          color="error"
          inline
          class="ml-1"
        />
      </v-btn>
    </div>

    <!-- All members table -->
    <v-card rounded="xl">
      <v-data-table
        :headers="headers"
        :items="members"
        :loading="loading"
        :search="search"
        hover
        item-value="id"
        @click:row="onRowClick"
      >
        <template #top>
          <v-toolbar flat rounded="t-xl">
            <v-text-field
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              placeholder="搜尋成員..."
              variant="outlined"
              density="compact"
              hide-details
              rounded="lg"
              class="mx-4 my-2"
              style="max-width:300px"
            />
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

        <template #item.status="{ item }">
          <v-chip
            :color="item.status === 'active' ? 'success' : item.status === 'pending' ? 'warning' : 'default'"
            size="small"
            variant="tonal"
          >
            {{ statusLabel[item.status] ?? item.status }}
          </v-chip>
        </template>

        <template #item.company_name="{ item }">
          <span class="text-body-2">{{ item.company_name ?? '—' }}</span>
        </template>

        <template #item.created_at="{ item }">
          <span class="text-caption text-grey">{{ item.created_at }}</span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-1" @click.stop>
            <v-btn
              v-if="item.status !== 'pending'"
              :icon="item.status === 'active' ? 'mdi-account-off' : 'mdi-account-check'"
              size="small"
              variant="text"
              :color="item.status === 'active' ? 'warning' : 'success'"
              :title="item.status === 'active' ? '停用' : '啟用'"
              @click="toggleStatus(item)"
            />
            <v-btn icon="mdi-pencil" size="small" variant="text" color="grey" title="編輯" @click="openEdit(item)" />
            <v-btn icon="mdi-delete" size="small" variant="text" color="error" title="刪除" @click="deleteMember(item)" />
          </div>
        </template>

        <template #no-data>
          <div class="text-center py-8 text-grey">此公司尚無成員</div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Edit member dialog -->
    <v-dialog v-model="showEdit" max-width="480" persistent>
      <v-card rounded="xl">
        <!-- Header -->
        <v-card-title class="pa-5 pb-4 d-flex align-center justify-space-between bg-primary rounded-t-xl">
          <span class="text-body-1 font-weight-semibold text-white">編輯成員資料</span>
          <v-btn icon="mdi-close" variant="text" size="small" color="white" @click="showEdit = false" />
        </v-card-title>

        <!-- Member identity strip -->
        <div class="d-flex align-center gap-4 px-6 py-4 bg-grey-lighten-5">
          <v-avatar
            size="52"
            :color="editingMember?.status === 'active' ? 'primary' : editingMember?.status === 'pending' ? 'warning' : 'grey'"
            class="mr-2"
          >
            <span class="text-h6 font-weight-bold text-white">{{ editingMember?.name?.charAt(0)?.toUpperCase() }}</span>
          </v-avatar>
          <div>
            <div class="text-body-1 font-weight-semibold">{{ editingMember?.name }}</div>
            <div class="text-caption text-grey">{{ editingMember?.email }}</div>
            <v-chip
              size="x-small"
              :color="editingMember?.status === 'active' ? 'success' : editingMember?.status === 'pending' ? 'warning' : 'default'"
              variant="tonal"
              class="mt-1"
            >
              {{ statusLabel[editingMember?.status ?? ''] ?? editingMember?.status }}
            </v-chip>
          </div>
        </div>
        <v-divider />

        <v-card-text class="pa-6">
          <v-form @submit.prevent="saveEdit">
            <div class="text-caption text-grey font-weight-bold text-uppercase mb-3">基本資訊</div>

            <v-text-field
              v-model="editForm.name"
              label="顯示名稱"
              prepend-inner-icon="mdi-account"
              variant="outlined"
              density="comfortable"
              required
              class="mb-3"
            />
            <v-text-field
              v-model="editForm.email"
              label="Email"
              type="email"
              prepend-inner-icon="mdi-email-outline"
              variant="outlined"
              density="comfortable"
              required
              class="mb-4"
            />

            <v-divider class="mb-4" />
            <div class="text-caption text-grey font-weight-bold text-uppercase mb-3">帳號設定</div>

            <v-row dense class="mb-1">
              <v-col cols="6">
                <v-select
                  v-model="editForm.role"
                  label="角色"
                  prepend-inner-icon="mdi-shield-account"
                  variant="outlined"
                  density="comfortable"
                  :items="[{ title: '成員', value: 'member' }, { title: '經理', value: 'manager' }]"
                />
              </v-col>
              <v-col cols="6">
                <v-select
                  v-model="editForm.status"
                  label="狀態"
                  prepend-inner-icon="mdi-toggle-switch"
                  variant="outlined"
                  density="comfortable"
                  :items="[{ title: '啟用', value: 'active' }, { title: '停用', value: 'inactive' }]"
                />
              </v-col>
            </v-row>

            <v-text-field
              v-model="editForm.password"
              label="新密碼（留空則不變更）"
              type="password"
              prepend-inner-icon="mdi-lock-outline"
              variant="outlined"
              density="comfortable"
              autocomplete="new-password"
              class="mb-4"
              hint="至少 8 個字元"
              persistent-hint
            />

            <v-alert v-if="editError" type="error" variant="tonal" density="compact" class="mb-4 text-body-2">
              {{ editError }}
            </v-alert>

            <div class="d-flex gap-4">
              <v-btn variant="outlined" color="grey" style="flex:1" @click="showEdit = false">取消</v-btn>
              <v-btn type="submit" color="primary" style="flex:1" :loading="editSaving">儲存變更</v-btn>
            </div>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- Pending approvals dialog -->
    <v-dialog v-model="showPending" max-width="540" scrollable>
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-3 d-flex align-center justify-space-between">
          <div>
            <div class="text-body-1 font-weight-semibold">待審核申請</div>
            <div class="text-caption text-grey">透過邀請碼申請加入的成員</div>
          </div>
          <v-btn icon="mdi-close" variant="text" size="small" @click="showPending = false" />
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-0">
          <div v-if="pendingLoading" class="d-flex justify-center align-center py-12">
            <v-progress-circular indeterminate color="primary" size="32" />
          </div>

          <div v-else-if="pending.length === 0" class="text-center py-12">
            <v-icon icon="mdi-check-circle-outline" size="48" color="grey-lighten-1" class="mb-3" />
            <div class="text-body-2 text-grey">目前沒有待審核的申請</div>
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
                  <v-chip v-if="auth.isAdmin && p.company_name" size="x-small" color="primary" variant="tonal" class="ml-2">
                    {{ p.company_name }}
                  </v-chip>
                </template>
                <template #subtitle>
                  <span class="text-caption text-grey">{{ p.email }} · {{ p.created_at }}</span>
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

    <!-- Workload dialog (open on row click) -->
    <MemberWorkloadDialog
      v-if="workloadUserId !== null"
      :user-id="workloadUserId"
      @close="workloadUserId = null"
      @edit="onWorkloadEdit"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/lib/axios'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import MemberWorkloadDialog from '@/components/MemberWorkloadDialog.vue'

interface Member {
  id: number
  name: string
  email: string
  status: string
  company_name?: string | null
  created_at: string
}

interface PendingMember {
  id: number
  name: string
  email: string
  company_name: string | null
  created_at: string
}

const auth = useAuthStore()
const toast = useToast()
const search = ref('')

const loading = ref(false)
const members = ref<Member[]>([])

const showPending = ref(false)
const pendingLoading = ref(false)
const pending = ref<PendingMember[]>([])
const pendingCount = computed(() => pending.value.length)

const showEdit = ref(false)
const editingMember = ref<Member | null>(null)
const editForm = reactive({ name: '', email: '', password: '', status: 'active', role: 'member' })
const editSaving = ref(false)
const editError = ref('')

// Workload dialog state — set userId to open, null to close
const workloadUserId = ref<number | null>(null)

function onRowClick(_evt: Event, ctx: { item: Member }) {
  workloadUserId.value = ctx.item.id
}

function onWorkloadEdit(userId: number) {
  const m = members.value.find(x => x.id === userId)
  if (m) openEdit(m)
}

const statusLabel: Record<string, string> = {
  active: '啟用', pending: '待審核', inactive: '停用',
}

const headers = computed(() => {
  const base = [
    { title: '姓名',   key: 'name',       sortable: true },
    { title: 'Email', key: 'email',      sortable: true },
    ...(auth.isAdmin ? [{ title: '公司', key: 'company_name', sortable: true }] : []),
    { title: '狀態',   key: 'status',     sortable: true },
    { title: '加入日期', key: 'created_at', sortable: true },
    { title: '',      key: 'actions',    sortable: false, width: '120px' },
  ]
  return base
})

async function fetchMembers() {
  loading.value = true
  try {
    const { data } = await api.get('/manager/members')
    members.value = data
  } finally {
    loading.value = false
  }
}

async function fetchPending() {
  pendingLoading.value = true
  try {
    const { data } = await api.get('/manager/members/pending')
    pending.value = data
  } finally {
    pendingLoading.value = false
  }
}

async function openPendingDialog() {
  showPending.value = true
  await fetchPending()
}

async function approve(p: PendingMember) {
  try {
    await api.post(`/manager/members/${p.id}/approve`)
    pending.value = pending.value.filter(m => m.id !== p.id)
    const idx = members.value.findIndex(m => m.id === p.id)
    if (idx !== -1) members.value[idx]!.status = 'active'
    else await fetchMembers()
    toast.success(`已核准 ${p.name}`)
  } catch (err: any) {
    toast.error(err?.response?.data?.message ?? '操作失敗')
  }
}

async function reject(p: PendingMember) {
  if (!confirm(`確定要拒絕 ${p.name} 的申請？`)) return
  try {
    await api.post(`/manager/members/${p.id}/reject`)
    pending.value = pending.value.filter(m => m.id !== p.id)
    const idx = members.value.findIndex(m => m.id === p.id)
    if (idx !== -1) members.value[idx]!.status = 'inactive'
    else await fetchMembers()
    toast.success('已拒絕申請')
  } catch (err: any) {
    toast.error(err?.response?.data?.message ?? '操作失敗')
  }
}

async function toggleStatus(m: Member) {
  const next = m.status === 'active' ? 'inactive' : 'active'
  try {
    await api.put(`/manager/members/${m.id}`, { status: next })
    m.status = next
    toast.success(next === 'active' ? `已啟用 ${m.name}` : `已停用 ${m.name}`)
  } catch (err: any) {
    toast.error(err?.response?.data?.message ?? '操作失敗')
  }
}

function openEdit(m: Member) {
  editingMember.value = m
  editForm.name = m.name
  editForm.email = m.email
  editForm.password = ''
  editForm.status = m.status === 'pending' ? 'active' : m.status
  editForm.role = (m as any).role ?? 'member'
  editError.value = ''
  showEdit.value = true
}

async function saveEdit() {
  if (!editingMember.value) return
  editSaving.value = true
  editError.value = ''
  try {
    const payload: Record<string, string> = {
      name: editForm.name,
      email: editForm.email,
      status: editForm.status,
      role: editForm.role,
    }
    if (editForm.password) payload.password = editForm.password
    const { data } = await api.put(`/manager/members/${editingMember.value.id}`, payload)
    const idx = members.value.findIndex(m => m.id === data.id)
    if (idx !== -1) Object.assign(members.value[idx]!, data)
    showEdit.value = false
    toast.success('成員資料已更新')
  } catch (err: any) {
    const errors = err?.response?.data?.errors
    editError.value = errors
      ? Object.values(errors).flat().join('、')
      : (err?.response?.data?.message ?? '儲存失敗')
  } finally {
    editSaving.value = false
  }
}

async function deleteMember(m: Member) {
  if (!confirm(`確定要刪除「${m.name}」？此操作無法復原。`)) return
  try {
    await api.delete(`/manager/members/${m.id}`)
    members.value = members.value.filter(u => u.id !== m.id)
    toast.success(`已刪除 ${m.name}`)
  } catch (err: any) {
    toast.error(err?.response?.data?.message ?? '刪除失敗')
  }
}

onMounted(async () => {
  await Promise.all([fetchMembers(), fetchPending()])
})
</script>
