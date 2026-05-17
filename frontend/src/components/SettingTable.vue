<template>
  <v-card rounded="xl">
    <v-data-table
      :headers="tableHeaders"
      :items="items"
      :loading="loading"
      :search="search"
      hover
      item-value="id"
    >
      <template #top>
        <v-toolbar flat rounded="t-xl">
          <v-text-field
            v-model="search"
            prepend-inner-icon="mdi-magnify"
            :placeholder="`搜尋${title}...`"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            class="mx-4 my-2"
            style="max-width:280px"
          />
          <v-spacer />
          <v-btn
            color="primary"
            prepend-icon="mdi-plus"
            rounded="lg"
            class="mr-3"
            @click="$emit('add')"
          >
            新增{{ title }}
          </v-btn>
        </v-toolbar>
      </template>

      <template v-for="field in colorFields" :key="field.key" #[`item.${field.key}`]="{ item }">
        <div class="d-flex align-center gap-2">
          <span
            class="rounded-circle d-inline-block"
            style="width:14px;height:14px;border:1px solid rgba(0,0,0,.12)"
            :style="{ backgroundColor: item[field.key] as string }"
          />
          <span class="text-caption">{{ item[field.key] }}</span>
        </div>
      </template>

      <template v-for="field in boolFields" :key="field.key" #[`item.${field.key}`]="{ item }">
        <v-chip
          :color="item[field.key] ? 'success' : 'default'"
          size="x-small"
          variant="tonal"
        >
          {{ item[field.key] ? '啟用' : '停用' }}
        </v-chip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <v-btn icon="mdi-pencil" size="small" variant="text" color="grey" @click="$emit('edit', item)" />
          <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="$emit('delete', item)" />
        </div>
      </template>

      <template #no-data>
        <div class="text-center py-8 text-grey">尚無{{ title }}資料，點擊新增</div>
      </template>
    </v-data-table>
  </v-card>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

const props = defineProps<{
  title: string
  items: Record<string, unknown>[]
  loading: boolean
  fields: { key: string; label: string; type?: string }[]
}>()

defineEmits<{
  add: []
  edit: [item: Record<string, unknown>]
  delete: [item: Record<string, unknown>]
}>()

const search = ref('')

const colorFields = computed(() => props.fields.filter(f => f.type === 'color'))
const boolFields  = computed(() => props.fields.filter(f => f.type === 'bool'))

const tableHeaders = computed(() => [
  ...props.fields.map(f => ({ title: f.label, key: f.key, sortable: true })),
  { title: '', key: 'actions', sortable: false, width: '80px' },
])
</script>
