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

const routes = [
  { path: '/', component: Login },
  { path: '/dashboard', component: Dashboard },
  { path: '/members', component: Members },
  { path: '/reports/members', component: MembersReport },
  { path: '/reports/financial', component: FinancialReport }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

const app = createApp(App)
app.component('VueDatePicker', VueDatePicker);
app.use(router)
app.mount('#app')
