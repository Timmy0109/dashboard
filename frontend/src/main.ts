import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'

import App from './App.vue'
import router from './router'

const vuetify = createVuetify({
  components,
  directives,
  icons: { defaultSet: 'mdi' },
  theme: {
    defaultTheme: 'light',
    themes: {
      light: {
        dark: false,
        colors: {
          primary:    '#00897B',
          'primary-darken-1': '#00695C',
          secondary:  '#546E7A',
          accent:     '#80CBC4',
          error:      '#E53935',
          warning:    '#F57C00',
          info:       '#0288D1',
          success:    '#43A047',
          background: '#F5F5F5',
          surface:    '#FFFFFF',
        },
      },
      dark: {
        dark: true,
        colors: {
          // 維持品牌主色，亮度稍微提升以保持在深色底上可讀
          primary:    '#26A69A',
          'primary-darken-1': '#00897B',
          secondary:  '#90A4AE',
          accent:     '#80CBC4',
          error:      '#EF5350',
          warning:    '#FFA726',
          info:       '#29B6F6',
          success:    '#66BB6A',
          background: '#121212',     // Material dark baseline
          surface:    '#1E1E1E',     // 卡片背景（比 background 高一階）
          'surface-bright': '#2C2C2C',
          'surface-light': '#2A2A2A',
          'surface-variant': '#3A3A3A',
          'on-surface': '#E0E0E0',
          'on-background': '#E0E0E0',
        },
      },
    },
  },
  defaults: {
    VBtn: { variant: 'flat', rounded: 'lg' },
    VCard: { rounded: 'lg', elevation: 1 },
    VTextField: { variant: 'outlined', density: 'comfortable', rounded: 'lg' },
    VSelect: { variant: 'outlined', density: 'comfortable', rounded: 'lg' },
    VTextarea: { variant: 'outlined', density: 'comfortable', rounded: 'lg' },
    VDataTable: { hover: true },
  },
})

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(vuetify)

app.mount('#app')
