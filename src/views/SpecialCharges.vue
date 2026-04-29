<template>
  <DashboardLayout>
    <div class="charges-page animate-fade-in">
      <div class="header-bar mb-4">
        <h2>Special Charges</h2>
      </div>

      <div class="charges-layout">
        <form class="glass-panel charge-form" @submit.prevent="saveCharge">
          <h3>{{ editing ? 'Edit Monthly Charge' : 'Add Monthly Charge' }}</h3>
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Year</label>
              <input type="number" v-model.number="form.charge_year" class="form-control" min="2000" max="2100" required>
            </div>
            <div class="form-group">
              <label class="form-label">Month</label>
              <select v-model.number="form.charge_month" class="form-control" required>
                <option v-for="(month, index) in months" :key="month" :value="index + 1">{{ month }}</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Special Charge Amount</label>
            <input type="number" v-model.number="form.amount" class="form-control" min="0.01" step="0.01" required placeholder="1500.00">
          </div>

          <div class="form-group">
            <label class="form-label">Description</label>
            <input type="text" v-model.trim="form.description" class="form-control" required placeholder="Reason for this charge">
          </div>

          <div class="form-actions">
            <button type="button" class="btn btn-outline" @click="resetForm">Clear</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              <Save size="17" />
              {{ saving ? 'Saving...' : 'Save Charge' }}
            </button>
          </div>
        </form>

        <div class="glass-panel charges-table-panel">
          <div class="table-toolbar">
            <h3>{{ selectedYear }} Charges</h3>
            <select v-model.number="selectedYear" class="form-control year-filter" @change="fetchCharges">
              <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
            </select>
          </div>

          <div class="table-responsive">
            <table class="data-table compact responsive-card-table" v-if="charges.length > 0">
              <thead>
                <tr>
                  <th>Month</th>
                  <th>Amount</th>
                  <th>Description</th>
                  <th class="actions-col">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="charge in charges" :key="`${charge.charge_year}-${charge.charge_month}`">
                  <td data-label="Month">{{ months[Number(charge.charge_month) - 1] }} {{ charge.charge_year }}</td>
                  <td data-label="Amount">{{ formatCurrency(charge.amount) }}</td>
                  <td data-label="Description">{{ charge.description || '-' }}</td>
                  <td class="actions-col" data-label="Actions">
                    <button class="btn btn-sm btn-outline" type="button" @click="editCharge(charge)" title="Edit">
                      <Pencil size="16" />
                    </button>
                    <button class="btn btn-sm btn-danger" type="button" @click="deleteCharge(charge)" title="Delete">
                      <Trash2 size="16" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-else-if="loading" class="text-center py-4">Loading charges...</p>
            <p v-else class="text-muted text-center py-4">No special charges defined for {{ selectedYear }}.</p>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import DashboardLayout from '../components/DashboardLayout.vue'
import { Pencil, Save, Trash2 } from 'lucide-vue-next'
import { alertError, alertSuccess, alertWarning, confirmWarning } from '../utils/alerts'
import { apiFetch } from '../utils/api'

const currentYear = new Date().getFullYear()
const selectedYear = ref(currentYear)
const availableYears = Array.from({ length: 15 }, (_, i) => currentYear - 5 + i)
const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']

const charges = ref([])
const loading = ref(false)
const saving = ref(false)
const editing = ref(false)

const form = reactive({
  charge_year: currentYear,
  charge_month: new Date().getMonth() + 1,
  amount: 0,
  description: ''
})

const sortedCharges = computed(() => {
  return [...charges.value].sort((a, b) => Number(a.charge_month) - Number(b.charge_month))
})

const formatCurrency = (value) => {
  return Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

const resetForm = () => {
  editing.value = false
  form.charge_year = selectedYear.value
  form.charge_month = new Date().getMonth() + 1
  form.amount = 0
  form.description = ''
}

const fetchCharges = async () => {
  loading.value = true
  try {
    const res = await apiFetch(`/api/get_special_charges.php?year=${selectedYear.value}`)
    const data = await res.json()
    if (data.success) {
      charges.value = Array.isArray(data.charges) ? data.charges : []
      charges.value = sortedCharges.value
      if (!editing.value) {
        form.charge_year = selectedYear.value
      }
    } else {
      alertError('Failed to load charges', data.message || 'Unable to load special charges.')
    }
  } catch (error) {
    alertError('Network error', 'Network error while loading special charges.')
  } finally {
    loading.value = false
  }
}

const saveCharge = async () => {
  const amount = Number(form.amount || 0)
  const description = String(form.description || '').trim()

  if (amount <= 0) {
    alertWarning('Invalid amount', 'Special charge amount must be greater than 0.')
    return
  }

  if (!description) {
    alertWarning('Description required', 'Please enter a description for this special charge.')
    return
  }

  form.amount = amount
  form.description = description
  saving.value = true
  try {
    const res = await apiFetch('/api/save_special_charge.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(form)
    })
    const data = await res.json()
    if (data.success) {
      selectedYear.value = Number(form.charge_year)
      await fetchCharges()
      resetForm()
      alertSuccess('Charge saved', data.message || 'Special charge saved successfully.')
    } else {
      alertError('Save failed', data.message || 'Failed to save special charge.')
    }
  } catch (error) {
    alertError('Network error', 'Network error while saving special charge.')
  } finally {
    saving.value = false
  }
}

const editCharge = (charge) => {
  editing.value = true
  form.charge_year = Number(charge.charge_year)
  form.charge_month = Number(charge.charge_month)
  form.amount = Number(charge.amount || 0)
  form.description = charge.description || ''
}

const deleteCharge = async (charge) => {
  const result = await confirmWarning({
    title: 'Delete charge?',
    text: `${months[Number(charge.charge_month) - 1]} ${charge.charge_year} will no longer require this special charge.`,
    confirmButtonText: 'Yes, delete it'
  })

  if (!result.isConfirmed) return

  try {
      const res = await apiFetch('/api/delete_special_charge.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        charge_year: Number(charge.charge_year),
        charge_month: Number(charge.charge_month)
      })
    })
    const data = await res.json()
    if (data.success) {
      await fetchCharges()
      resetForm()
      alertSuccess('Charge deleted', data.message || 'Special charge deleted successfully.')
    } else {
      alertError('Delete failed', data.message || 'Failed to delete special charge.')
    }
  } catch (error) {
    alertError('Network error', 'Network error while deleting special charge.')
  }
}

onMounted(() => {
  fetchCharges()
})
</script>

<style scoped>
.mb-4 { margin-bottom: 1.5rem; }
.py-4 { padding-top: 1.5rem; padding-bottom: 1.5rem; }
.text-center { text-align: center; }

.charges-layout {
  display: grid;
  grid-template-columns: minmax(280px, 380px) 1fr;
  gap: 1.5rem;
  align-items: start;
}

.charge-form,
.charges-table-panel {
  padding: 1.5rem;
}

.charge-form h3,
.charges-table-panel h3 {
  color: var(--primary-dark);
  margin-bottom: 1rem;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.table-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.table-toolbar h3 {
  margin-bottom: 0;
}

.year-filter {
  width: 130px;
}

.table-responsive {
  overflow-x: auto;
  border: 1px solid rgba(0, 0, 0, 0.06);
  border-radius: 10px;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
}

.data-table th,
.data-table td {
  padding: 0.78rem;
  text-align: left;
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.data-table th {
  color: var(--primary-color);
  background: #eefaf7;
  font-weight: 600;
}

.actions-col {
  width: 120px;
  white-space: nowrap;
}

.actions-col .btn {
  min-height: 34px;
  padding: 0.35rem 0.5rem;
  margin-right: 0.25rem;
}

@media (max-width: 900px) {
  .charges-layout {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 600px) {
  .form-grid,
  .table-toolbar {
    grid-template-columns: 1fr;
  }

  .table-toolbar {
    align-items: stretch;
  }

  .year-filter {
    width: 100%;
  }
}
</style>
