<template>
  <v-dialog :model-value="true" max-width="560" persistent @update:model-value="emit('close')">
    <v-card rounded="xl">
      <!-- Header -->
      <v-card-title class="pa-5 pb-4 d-flex align-center justify-space-between bg-primary rounded-t-xl">
        <div class="d-flex align-center gap-2">
          <v-icon icon="mdi-upload" color="white" size="20" />
          <span class="text-body-1 font-weight-semibold text-white">匯入專案 / 任務</span>
        </div>
        <v-btn icon="mdi-close" variant="text" size="small" color="white" :disabled="loading" @click="emit('close')" />
      </v-card-title>

      <!-- Step 1: Upload -->
      <template v-if="step === 'upload'">
        <v-card-text class="pa-6">
          <!-- Tips -->
          <v-alert type="info" variant="tonal" density="compact" class="mb-5 text-body-2">
            <div>支援 <strong>.xlsx</strong>（專案 + 任務）及 <strong>.csv</strong>（純專案）格式，檔案上限 10MB</div>
          </v-alert>

          <!-- Drop zone -->
          <div
            class="rounded-xl pa-8 text-center mb-4"
            :class="dragging ? 'bg-primary-lighten-5 border-primary' : 'bg-grey-lighten-5'"
            style="border: 2px dashed; border-color: inherit; cursor:pointer; transition: all .2s"
            @click="fileInput?.click()"
            @dragover.prevent="dragging = true"
            @dragleave="dragging = false"
            @drop.prevent="onDrop"
          >
            <v-icon icon="mdi-cloud-upload-outline" size="48" :color="dragging ? 'primary' : 'grey-lighten-1'" class="mb-3" />
            <div class="text-body-2 font-weight-medium">拖曳檔案至此，或點擊選擇</div>
            <div class="text-caption text-grey mt-1">.xlsx / .csv，上限 10MB</div>
            <v-chip v-if="selectedFile" color="primary" variant="tonal" size="small" class="mt-3" prepend-icon="mdi-file-excel">
              {{ selectedFile.name }}
            </v-chip>
          </div>
          <input ref="fileInput" type="file" accept=".xlsx,.csv" style="display:none" @change="onFileChange" />

          <div class="d-flex justify-space-between align-center">
            <v-btn
              variant="text"
              color="primary"
              prepend-icon="mdi-download"
              size="small"
              :loading="downloadingTemplate"
              @click="downloadTemplate"
            >
              下載填寫範本
            </v-btn>
            <v-btn
              color="primary"
              rounded="lg"
              :disabled="!selectedFile"
              :loading="loading"
              @click="doPreview"
            >
              驗證並預覽
            </v-btn>
          </div>
        </v-card-text>
      </template>

      <!-- Step 2: Preview -->
      <template v-else-if="step === 'preview' && previewData">
        <v-card-text class="pa-6">
          <!-- Errors -->
          <template v-if="previewData.errors.length">
            <v-alert type="error" variant="tonal" class="mb-4">
              <div class="text-body-2 font-weight-medium mb-2">發現 {{ previewData.errors.length }} 個錯誤，請修正後重新上傳：</div>
              <div v-for="err in previewData.errors" :key="err" class="text-caption mb-1">• {{ err }}</div>
            </v-alert>
            <v-btn variant="outlined" color="primary" rounded="lg" class="w-100" @click="step = 'upload'; selectedFile = null">
              重新上傳
            </v-btn>
          </template>

          <!-- Preview OK -->
          <template v-else>
            <!-- Summary chips -->
            <div class="d-flex gap-3 mb-5">
              <v-chip color="primary" variant="tonal" prepend-icon="mdi-folder-multiple">
                {{ previewData.project_count }} 個專案
              </v-chip>
              <v-chip color="teal" variant="tonal" prepend-icon="mdi-clipboard-list">
                {{ previewData.task_count }} 個任務
              </v-chip>
            </div>

            <!-- Project list preview -->
            <div class="text-caption text-grey font-weight-bold text-uppercase mb-2">即將匯入的專案</div>
            <v-card variant="outlined" rounded="lg" class="mb-4" style="max-height:200px;overflow-y:auto">
              <v-list density="compact" class="pa-0">
                <v-list-item
                  v-for="(p, i) in previewData.projects"
                  :key="i"
                  :title="p.name"
                  :subtitle="`${p.start_date} → ${p.due_date || '不限'}`"
                  class="px-4"
                >
                  <template #prepend>
                    <v-icon icon="mdi-folder" color="primary" size="18" class="mr-2" />
                  </template>
                </v-list-item>
              </v-list>
            </v-card>

            <v-alert type="warning" variant="tonal" density="compact" class="mb-5 text-body-2">
              確認後將立即建立，此操作<strong>無法復原</strong>。
            </v-alert>

            <div class="d-flex gap-3">
              <v-btn variant="outlined" color="grey" style="flex:1" :disabled="loading" @click="step = 'upload'; selectedFile = null">
                重新上傳
              </v-btn>
              <v-btn color="primary" style="flex:1" :loading="loading" @click="doConfirm">
                確認匯入
              </v-btn>
            </div>
          </template>
        </v-card-text>
      </template>

      <!-- Step 3: Done -->
      <template v-else-if="step === 'done' && result">
        <v-card-text class="pa-6 text-center">
          <v-icon icon="mdi-check-circle" color="success" size="64" class="mb-4" />
          <div class="text-h6 font-weight-bold mb-2">匯入成功！</div>
          <div class="d-flex justify-center gap-4 mb-6">
            <v-chip color="primary" variant="tonal" prepend-icon="mdi-folder-multiple">
              {{ result.imported_projects }} 個專案
            </v-chip>
            <v-chip color="teal" variant="tonal" prepend-icon="mdi-clipboard-list">
              {{ result.imported_tasks }} 個任務
            </v-chip>
          </div>
          <v-btn color="primary" rounded="lg" class="w-100" @click="emit('done')">完成</v-btn>
        </v-card-text>
      </template>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import api from '@/lib/axios'

const emit = defineEmits<{ close: []; done: [] }>()

interface PreviewData {
  project_count: number
  task_count: number
  errors: string[]
  projects: { name: string; start_date: string; due_date: string | null }[]
  tasks: { name: string; project_name: string }[]
}

interface ImportResult {
  imported_projects: number
  imported_tasks: number
}

const step = ref<'upload' | 'preview' | 'done'>('upload')
const loading = ref(false)
const downloadingTemplate = ref(false)
const dragging = ref(false)
const selectedFile = ref<File | null>(null)
const previewData = ref<PreviewData | null>(null)
const result = ref<ImportResult | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  selectedFile.value = input.files?.[0] ?? null
}

function onDrop(e: DragEvent) {
  dragging.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file && (file.name.endsWith('.xlsx') || file.name.endsWith('.csv'))) {
    selectedFile.value = file
  }
}

async function downloadTemplate() {
  downloadingTemplate.value = true
  try {
    const res = await api.get('/projects/import/template', { responseType: 'blob' })
    const url = URL.createObjectURL(res.data)
    const a = document.createElement('a')
    a.href = url
    a.download = '匯入範本.xlsx'
    a.click()
    URL.revokeObjectURL(url)
  } finally {
    downloadingTemplate.value = false
  }
}

async function doPreview() {
  if (!selectedFile.value) return
  loading.value = true
  try {
    const form = new FormData()
    form.append('file', selectedFile.value)
    const { data } = await api.post('/projects/import/preview', form)
    previewData.value = data
    step.value = 'preview'
  } catch (e: any) {
    previewData.value = { project_count: 0, task_count: 0, errors: [e?.response?.data?.message ?? '解析失敗'], projects: [], tasks: [] }
    step.value = 'preview'
  } finally {
    loading.value = false
  }
}

async function doConfirm() {
  if (!selectedFile.value) return
  loading.value = true
  try {
    const form = new FormData()
    form.append('file', selectedFile.value)
    const { data } = await api.post('/projects/import/confirm', form)
    result.value = data
    step.value = 'done'
  } catch (e: any) {
    previewData.value = {
      ...previewData.value!,
      errors: [e?.response?.data?.message ?? '匯入失敗'],
    }
  } finally {
    loading.value = false
  }
}
</script>
