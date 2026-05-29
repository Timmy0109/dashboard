<template>
  <v-dialog
    :model-value="true"
    max-width="800"
    persistent
    scrollable
    @update:model-value="emit('close')"
  >
    <v-card rounded="xl" class="pms-modal">
      <!-- Header -->
      <v-card-title class="pa-5 pb-4 d-flex align-center justify-space-between bg-primary rounded-t-xl">
        <div class="d-flex align-center gap-2">
          <v-icon icon="mdi-upload-outline" color="white" size="20" />
          <span class="text-body-1 font-weight-semibold text-white">匯入專案 / 任務</span>
        </div>
        <v-btn
          icon="mdi-close"
          variant="text"
          size="small"
          color="white"
          :disabled="loading"
          @click="emit('close')"
        />
      </v-card-title>

      <!-- Stepper -->
      <div class="pms-stepper">
        <div
          v-for="(s, i) in steps"
          :key="s.key"
          class="pms-step"
          :class="{
            'pms-step--active': step === s.key,
            'pms-step--done': isStepDone(s.key),
          }"
        >
          <div class="pms-step-circle">
            <v-icon v-if="isStepDone(s.key)" icon="mdi-check" size="16" />
            <span v-else>{{ i + 1 }}</span>
          </div>
          <div class="pms-step-label">{{ s.label }}</div>
          <div v-if="i < steps.length - 1" class="pms-step-line" />
        </div>
      </div>

      <v-divider />

      <!-- Step 1: Upload -->
      <template v-if="step === 'upload'">
        <v-card-text class="pa-6">
          <v-alert
            type="info"
            variant="tonal"
            density="compact"
            class="mb-5 text-body-2"
            icon="mdi-information-outline"
          >
            支援 <strong>.xlsx</strong>（專案 + 任務）及 <strong>.csv</strong>（純專案），檔案大小上限 <strong>10 MB</strong>。
          </v-alert>

          <!-- Drop zone -->
          <div
            class="pms-dropzone"
            :class="{ 'pms-dropzone--dragging': dragging, 'pms-dropzone--has-file': !!selectedFile }"
            @click="fileInput?.click()"
            @dragover.prevent="dragging = true"
            @dragleave="dragging = false"
            @drop.prevent="onDrop"
          >
            <v-icon
              :icon="selectedFile ? 'mdi-file-check-outline' : 'mdi-cloud-upload-outline'"
              size="56"
              :color="selectedFile ? 'primary' : (dragging ? 'primary' : 'grey-lighten-1')"
              class="mb-3"
            />
            <div class="text-body-1 font-weight-medium">
              {{ selectedFile ? '已選擇檔案' : '拖曳檔案至此，或點擊選擇' }}
            </div>
            <div class="text-caption text-grey mt-1">支援 .xlsx / .csv · 最大 10 MB</div>

            <div v-if="selectedFile" class="pms-file-card mt-4" @click.stop>
              <v-icon
                :icon="selectedFile.name.endsWith('.csv') ? 'mdi-file-delimited-outline' : 'mdi-microsoft-excel'"
                color="primary"
                size="24"
              />
              <div class="pms-file-meta">
                <div class="text-body-2 font-weight-medium text-truncate">{{ selectedFile.name }}</div>
                <div class="text-caption text-grey">{{ formatBytes(selectedFile.size) }}</div>
              </div>
              <v-btn icon="mdi-close" variant="text" size="x-small" @click.stop="clearFile" />
            </div>
          </div>
          <input
            ref="fileInput"
            type="file"
            accept=".xlsx,.csv"
            style="display:none"
            @change="onFileChange"
          />
        </v-card-text>

        <v-divider />

        <v-card-actions class="px-6 py-4">
          <v-btn
            variant="text"
            color="primary"
            prepend-icon="mdi-download-outline"
            :loading="downloadingTemplate"
            @click="downloadTemplate"
          >
            下載填寫範本
          </v-btn>
          <v-spacer />
          <v-btn variant="text" color="grey-darken-1" :disabled="loading" @click="emit('close')">
            取消
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
        </v-card-actions>
      </template>

      <!-- Step 2: Preview -->
      <template v-else-if="step === 'preview' && previewData">
        <v-card-text class="pa-6">
          <!-- Errors -->
          <template v-if="previewData.errors.length">
            <v-alert type="error" variant="tonal" class="mb-4" icon="mdi-alert-octagon-outline">
              <div class="text-body-2 font-weight-medium mb-2">
                發現 {{ previewData.errors.length }} 個錯誤，請修正後重新上傳：
              </div>
              <ul class="pms-error-list">
                <li v-for="err in previewData.errors" :key="err">{{ err }}</li>
              </ul>
            </v-alert>
          </template>

          <!-- Preview OK -->
          <template v-else>
            <!-- Summary KPI -->
            <div class="pms-kpi-grid mb-5">
              <div class="pms-kpi">
                <v-icon icon="mdi-folder-multiple-outline" color="primary" size="28" />
                <div>
                  <div class="text-h5 font-weight-bold">{{ previewData.project_count }}</div>
                  <div class="text-caption text-grey">即將建立專案</div>
                </div>
              </div>
              <div class="pms-kpi">
                <v-icon icon="mdi-clipboard-list-outline" color="teal" size="28" />
                <div>
                  <div class="text-h5 font-weight-bold">{{ previewData.task_count }}</div>
                  <div class="text-caption text-grey">即將建立任務</div>
                </div>
              </div>
            </div>

            <!-- Project list preview -->
            <div class="pms-section-title">
              <v-icon icon="mdi-folder-outline" size="16" class="mr-1" />專案清單
            </div>
            <v-card variant="outlined" rounded="lg" class="mb-4 pms-preview-list">
              <v-list density="compact" class="pa-0">
                <v-list-item
                  v-for="(p, i) in previewData.projects"
                  :key="i"
                  :title="p.name"
                  :subtitle="`${p.start_date} → ${p.due_date || '不限'}`"
                  class="px-4"
                >
                  <template #prepend>
                    <v-icon icon="mdi-folder-outline" color="primary" size="18" class="mr-2" />
                  </template>
                </v-list-item>
                <v-list-item v-if="!previewData.projects.length" class="text-center text-grey">
                  無預覽項目
                </v-list-item>
              </v-list>
            </v-card>

            <v-alert
              type="warning"
              variant="tonal"
              density="compact"
              class="text-body-2"
              icon="mdi-alert-outline"
            >
              確認後將立即建立，此操作 <strong>無法復原</strong>。
            </v-alert>
          </template>
        </v-card-text>

        <v-divider />

        <v-card-actions class="px-6 py-4">
          <v-btn variant="text" color="grey-darken-1" :disabled="loading" @click="resetToUpload">
            <v-icon start icon="mdi-arrow-left" />重新上傳
          </v-btn>
          <v-spacer />
          <v-btn
            v-if="!previewData.errors.length"
            color="primary"
            rounded="lg"
            :loading="loading"
            @click="doConfirm"
          >
            <v-icon start icon="mdi-check" />確認匯入
          </v-btn>
        </v-card-actions>
      </template>

      <!-- Step 3: Done -->
      <template v-else-if="step === 'done' && result">
        <v-card-text class="pa-8 text-center">
          <div class="pms-success-icon mb-4">
            <v-icon icon="mdi-check-circle" color="success" size="72" />
          </div>
          <div class="text-h6 font-weight-bold mb-2">匯入成功</div>
          <div class="text-body-2 text-grey mb-5">資料已寫入系統，可至專案頁查看。</div>

          <div class="pms-kpi-grid mb-2" style="justify-content:center">
            <div class="pms-kpi">
              <v-icon icon="mdi-folder-multiple-outline" color="primary" size="28" />
              <div>
                <div class="text-h5 font-weight-bold">{{ result.imported_projects }}</div>
                <div class="text-caption text-grey">已建立專案</div>
              </div>
            </div>
            <div class="pms-kpi">
              <v-icon icon="mdi-clipboard-list-outline" color="teal" size="28" />
              <div>
                <div class="text-h5 font-weight-bold">{{ result.imported_tasks }}</div>
                <div class="text-caption text-grey">已建立任務</div>
              </div>
            </div>
          </div>
        </v-card-text>

        <v-divider />

        <v-card-actions class="px-6 py-4">
          <v-spacer />
          <v-btn color="primary" rounded="lg" @click="emit('done')">
            完成
          </v-btn>
        </v-card-actions>
      </template>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
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

type StepKey = 'upload' | 'preview' | 'done'

const steps: { key: StepKey; label: string }[] = [
  { key: 'upload', label: '上傳檔案' },
  { key: 'preview', label: '驗證預覽' },
  { key: 'done', label: '完成' },
]

const step = ref<StepKey>('upload')
const loading = ref(false)
const downloadingTemplate = ref(false)
const dragging = ref(false)
const selectedFile = ref<File | null>(null)
const previewData = ref<PreviewData | null>(null)
const result = ref<ImportResult | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

const stepIndex = computed(() => steps.findIndex(s => s.key === step.value))

function isStepDone(key: StepKey): boolean {
  const idx = steps.findIndex(s => s.key === key)
  return idx < stepIndex.value
}

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

function clearFile() {
  selectedFile.value = null
  if (fileInput.value) fileInput.value.value = ''
}

function resetToUpload() {
  step.value = 'upload'
  selectedFile.value = null
  previewData.value = null
  if (fileInput.value) fileInput.value.value = ''
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(2)} MB`
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
    previewData.value = {
      project_count: 0,
      task_count: 0,
      errors: [e?.response?.data?.message ?? '解析失敗'],
      projects: [],
      tasks: [],
    }
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

<style scoped>
.pms-modal :deep(.v-field) {
  border-radius: 10px;
}

/* Stepper */
.pms-stepper {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 28px;
  background: rgba(0, 0, 0, .02);
}
.pms-step {
  display: flex;
  align-items: center;
  flex: 1;
  position: relative;
  color: rgba(0, 0, 0, .45);
  font-size: 13px;
}
.pms-step-circle {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: rgba(0, 0, 0, .08);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 13px;
  margin-right: 8px;
  flex-shrink: 0;
  transition: background-color .2s, color .2s;
}
.pms-step--active .pms-step-circle {
  background: rgb(var(--v-theme-primary));
  color: #fff;
}
.pms-step--active {
  color: rgba(0, 0, 0, .87);
  font-weight: 600;
}
.pms-step--done .pms-step-circle {
  background: rgb(var(--v-theme-success, 76 175 80));
  color: #fff;
}
.pms-step-line {
  flex: 1;
  height: 2px;
  background: rgba(0, 0, 0, .1);
  margin: 0 12px;
}
.pms-step--done .pms-step-line {
  background: rgb(var(--v-theme-success, 76 175 80));
}
.pms-step-label {
  white-space: nowrap;
}

/* Dropzone */
.pms-dropzone {
  border: 2px dashed rgba(0, 0, 0, .15);
  border-radius: 14px;
  background: rgba(0, 0, 0, .015);
  padding: 36px 24px;
  text-align: center;
  cursor: pointer;
  transition: all .2s ease;
}
.pms-dropzone:hover,
.pms-dropzone--dragging {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), .04);
}
.pms-dropzone--has-file {
  border-style: solid;
  border-color: rgba(var(--v-theme-primary), .35);
}

.pms-file-card {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  background: #fff;
  border: 1px solid rgba(0, 0, 0, .1);
  border-radius: 10px;
  padding: 10px 14px;
  max-width: 100%;
  text-align: left;
}
.pms-file-meta {
  display: flex;
  flex-direction: column;
  min-width: 0;
  max-width: 320px;
}

/* KPI */
.pms-kpi-grid {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}
.pms-kpi {
  flex: 1;
  min-width: 180px;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 18px;
  background: rgba(0, 0, 0, .025);
  border-radius: 12px;
}

.pms-section-title {
  display: flex;
  align-items: center;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: .04em;
  color: rgba(0, 0, 0, .6);
  margin-bottom: 10px;
  text-transform: uppercase;
}
.pms-preview-list {
  max-height: 220px;
  overflow-y: auto;
}

.pms-error-list {
  margin: 0;
  padding-left: 18px;
}
.pms-error-list li {
  font-size: 12.5px;
  line-height: 1.6;
}

.pms-success-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 96px;
  height: 96px;
  border-radius: 50%;
  background: rgba(76, 175, 80, .12);
  margin: 0 auto;
}
</style>
