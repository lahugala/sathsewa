import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import { createRouter, createWebHistory } from 'vue-router'
import Login from './views/Login.vue'
import Members from './views/Members.vue'

import { VueDatePicker } from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'

import Dashboard from './views/Dashboard.vue'
import MembersReport from './views/reports/MembersReport.vue'
import FinancialReport from './views/reports/FinancialReport.vue'
import SpecialCharges from './views/SpecialCharges.vue'
import { checkSession } from './utils/api'

const routes = [
  { path: '/', component: Login, meta: { guestOnly: true } },
  { path: '/dashboard', component: Dashboard, meta: { requiresAuth: true } },
  { path: '/members', component: Members, meta: { requiresAuth: true } },
  { path: '/charges', component: SpecialCharges, meta: { requiresAuth: true } },
  { path: '/reports/members', component: MembersReport, meta: { requiresAuth: true } },
  { path: '/reports/financial', component: FinancialReport, meta: { requiresAuth: true } }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach(async (to) => {
  const isAuthenticated = await checkSession()

  if (to.meta.requiresAuth && !isAuthenticated) {
    return '/'
  }

  if (to.meta.guestOnly && isAuthenticated) {
    return '/dashboard'
  }

  return true
})

const app = createApp(App)
app.component('VueDatePicker', VueDatePicker);
app.use(router)
app.mount('#app')
