<template>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
      <h3 class="text-sm font-semibold text-gray-700">{{ title }}</h3>
      <button
        @click="$emit('add')"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-lg hover:bg-blue-700 transition-colors"
      >
        <span class="material-icons text-sm leading-none">add</span> 新增
      </button>
    </div>

    <div v-if="loading" class="py-12 text-center text-sm text-gray-400">載入中...</div>

    <table v-else class="w-full">
      <thead>
        <tr class="border-b border-gray-100 bg-gray-50">
          <th v-for="field in fields" :key="(field.key as string)"
            class="px-4 py-2.5 text-left text-xs font-medium text-gray-500">
            {{ field.label }}
          </th>
          <th class="px-4 py-2.5 w-20"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <tr v-for="item in items" :key="item.id as string" class="hover:bg-gray-50 transition-colors">
          <td v-for="field in fields" :key="field.key" class="px-4 py-2.5">
            <template v-if="field.type === 'color'">
              <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded-full inline-block border border-gray-200"
                  :style="{ backgroundColor: item[field.key] as string }"></span>
                <span class="text-xs text-gray-500">{{ item[field.key] }}</span>
              </div>
            </template>
            <template v-else-if="field.type === 'bool'">
              <span class="inline-flex items-center gap-1 text-xs"
                :class="item[field.key] ? 'text-green-600' : 'text-gray-400'">
                <span class="w-1.5 h-1.5 rounded-full inline-block"
                  :class="item[field.key] ? 'bg-green-500' : 'bg-gray-300'"></span>
                {{ item[field.key] ? '啟用' : '停用' }}
              </span>
            </template>
            <template v-else>
              <span class="text-sm text-gray-700">{{ item[field.key] }}</span>
            </template>
          </td>
          <td class="px-4 py-2.5">
            <div class="flex gap-1">
              <button @click="$emit('edit', item)"
                class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors">
                <span class="material-icons text-base leading-none">edit</span>
              </button>
              <button @click="$emit('delete', item)"
                class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors">
                <span class="material-icons text-base leading-none">delete</span>
              </button>
            </div>
          </td>
        </tr>
        <tr v-if="items.length === 0">
          <td :colspan="fields.length + 1" class="px-4 py-10 text-center text-sm text-gray-400">無資料</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
defineProps<{
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
</script>
