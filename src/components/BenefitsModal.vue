<template>
  <Teleport to="body">
    <div class="modal-overlay" v-if="show">
      <div class="modal-container glass-panel animate-fade-in" style="max-width: 1125px;">
        <div class="modal-header">
          <h2>Benefits & Payouts: {{ member?.name }} ({{ member?.membership_number || 'N/A' }})</h2>
          <button class="btn-close" @click="$emit('close')" type="button" aria-label="Close">
            <X :size="20" />
          </button>
        </div>

        <div class="modal-body" style="display: flex; gap: 2rem;">
          <div class="form-section" style="flex: 1;">
            <h3 class="section-title">Record New Benefit</h3>

            <form @submit.prevent="saveBenefit">
              <div class="form-group">
                <label class="form-label">Benefit Type</label>
                <select v-model="form.benefit_type" class="form-control" required>
                  <option value="death_gratuity">Death Gratuity</option>
                  <option value="special_donation">Special Donation</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Paid Date</label>
                <VueDatePicker
                  v-model="form.paid_date"
                  model-type="yyyy-MM-dd"
                  auto-apply
                  :clearable="false"
                  :time-config="dateOnlyPickerConfig"
                  placeholder="Select paid date"
                  teleport
                  class="date-picker"
                />
              </div>

              <template v-if="form.benefit_type === 'death_gratuity'">
                <div class="form-group">
                  <label class="form-label">Select Dependent</label>
                  <select v-model="selectedDependentKey" class="form-control" @change="fillDependentFields">
                    <option value="">Choose from dependent list</option>
                    <option v-for="dep in dependents" :key="dep.id || `${dep.name}-${dep.relationship}`" :value="dependentKey(dep)">
                      {{ dep.name }} ({{ dep.relationship }})
                    </option>
                  </select>
                  <p v-if="dependents.length === 0" class="field-help">No dependents saved for this member.</p>
                </div>
                <div class="form-group">
                  <label class="form-label">Dependent Name</label>
                  <input type="text" v-model="form.dependent_name" class="form-control" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Relationship</label>
                  <input type="text" v-model="form.relationship" class="form-control" required>
                </div>
              </template>

              <template v-if="form.benefit_type === 'special_donation'">
                <div class="form-group">
                  <label class="form-label">Nature of Aid (e.g., Sick, Festival)</label>
                  <input type="text" v-model="form.aid_nature" class="form-control" required>
                </div>
              </template>

              <div class="form-group">
                <label class="form-label">Amount</label>
                <input type="number" v-model="form.amount" class="form-control" required min="0" step="0.01">
              </div>

              <div class="form-actions" style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary" style="width: 100%;" :disabled="saving">
                  {{ saving ? 'Saving...' : 'Add Benefit Record' }}
                </button>
              </div>
            </form>
          </div>

          <div class="history-section" style="flex: 2;">
            <h3 class="section-title">Payment History</h3>
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
              <table class="data-table responsive-card-table">
                <thead style="position: sticky; top: 0; background: var(--surface); z-index: 1;">
                  <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Details</th>
                    <th>Amount</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="b in benefits" :key="b.id">
                    <td data-label="Date">{{ b.paid_date }}</td>
                    <td data-label="Type">
                      <span class="badge" :class="b.benefit_type === 'death_gratuity' ? 'badge-dark' : 'badge-info'">
                        {{ b.benefit_type === 'death_gratuity' ? 'Death Gratuity' : 'Special Donation' }}
                      </span>
                    </td>
                    <td data-label="Details">
                      <div v-if="b.benefit_type === 'death_gratuity'">
                        <strong>{{ b.dependent_name }}</strong> ({{ b.relationship }})
                      </div>
                      <div v-else>
                        {{ b.aid_nature }}
                      </div>
                    </td>
                    <td data-label="Amount" style="font-weight: 600;">{{ formatAmount(b.amount) }}</td>
                    <td data-label="Action">
                      <button class="btn-remove" @click="deleteBenefit(b.id)" title="Delete Record" type="button">
                        <Trash2 size="18" />
                      </button>
                    </td>
                  </tr>
                  <tr v-if="benefits.length === 0">
                    <td colspan="5" class="text-center text-muted" style="padding: 2rem;">No benefits recorded yet.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, reactive, watch } from 'vue'
import { Trash2, X } from 'lucide-vue-next'
import { alertError, alertSuccess, alertWarning, confirmWarning } from '../utils/alerts'
import { apiFetch } from '../utils/api'

const props = defineProps({
  show: Boolean,
  member: Object
})

defineEmits(['close'])

const benefits = ref([])
const dependents = ref([])
const saving = ref(false)
const selectedDependentKey = ref('')
const dateOnlyPickerConfig = { enableTimePicker: false }

const form = reactive({
  benefit_type: 'death_gratuity',
  paid_date: new Date().toISOString().split('T')[0],
  dependent_name: '',
  relationship: '',
  aid_nature: '',
  amount: 0
})

const resetForm = () => {
  form.benefit_type = 'death_gratuity'
  form.paid_date = new Date().toISOString().split('T')[0]
  form.dependent_name = ''
  form.relationship = ''
  form.aid_nature = ''
  form.amount = 0
  selectedDependentKey.value = ''
}

const formatAmount = (value) => {
  const amount = Number(value)
  return Number.isFinite(amount) ? amount.toFixed(2) : '0.00'
}

const fetchBenefits = async () => {
  if (!props.member?.id) return
  try {
    const res = await apiFetch(`/api/get_benefits.php?member_id=${props.member.id}`)
    const data = await res.json()
    if (data.success) {
      benefits.value = data.benefits
    }
  } catch (e) {
    console.error('Failed to load benefits', e)
  }
}

const fetchDependents = async () => {
  dependents.value = []
  if (!props.member?.id) return
  try {
    const res = await apiFetch(`/api/get_member.php?id=${props.member.id}`)
    const data = await res.json()
    if (data.success) {
      dependents.value = Array.isArray(data.member?.dependents) ? data.member.dependents : []
    }
  } catch (e) {
    console.error('Failed to load dependents', e)
  }
}

const dependentKey = (dep) => {
  return `${dep.name}|||${dep.relationship}`
}

const fillDependentFields = () => {
  if (!selectedDependentKey.value) return
  const selected = dependents.value.find((dep) => dependentKey(dep) === selectedDependentKey.value)
  if (!selected) return
  form.dependent_name = selected.name
  form.relationship = selected.relationship
}

watch(() => props.show, (newVal) => {
  if (newVal) {
    resetForm()
    fetchBenefits()
    fetchDependents()
  }
})

watch(
  () => props.member?.id,
  (id) => {
    if (props.show && id) {
      fetchBenefits()
      fetchDependents()
    }
  }
)

const saveBenefit = async () => {
  if (!props.member?.id) {
    alertWarning('Member not selected', 'Please select a member before adding a benefit.')
    return
  }

  if (form.benefit_type === 'death_gratuity' && (!form.dependent_name || !form.relationship)) {
    alertWarning('Dependent details required', 'Dependent name and relationship are required.')
    return
  }

  if (form.benefit_type === 'special_donation' && !form.aid_nature) {
    alertWarning('Aid nature required', 'Nature of aid is required.')
    return
  }

  saving.value = true

  const payload = {
    member_id: props.member.id,
    ...form
  }

  try {
    const res = await apiFetch('/api/save_benefit.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    const data = await res.json()
    if (data.success) {
      resetForm()
      fetchBenefits()
      alertSuccess('Benefit saved', data.message || 'Benefit record added successfully.')
    } else {
      alertError('Save failed', data.message || 'Failed to save benefit record.')
    }
  } catch (e) {
    alertError('Network error', 'Network error while saving benefit record.')
  } finally {
    saving.value = false
  }
}

const deleteBenefit = async (id) => {
  const result = await confirmWarning({
    title: 'Are you sure?',
    text: "You won't be able to revert this record!",
    confirmButtonText: 'Yes, delete it!'
  })

  if (result.isConfirmed) {
    try {
      const res = await apiFetch('/api/delete_benefit.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
      })
      const data = await res.json()
      if (data.success) {
        alertSuccess('Deleted!', 'Record deleted successfully.')
        fetchBenefits()
      } else {
        alertError('Error!', data.message || 'Failed to delete record.')
      }
    } catch (e) {
      alertError('Error!', 'Network error deleting record.')
    }
  }
}
</script>

<style scoped>
.modal-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(4px); display: flex; justify-content: center; align-items: center; z-index: 1000; padding: 1rem; }
.modal-container { width: 100%; max-height: 90vh; overflow-y: hidden; padding: 0; display: flex; flex-direction: column; background: #fff; }
.modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; background: inherit; z-index: 10; }
.modal-header h2 { margin: 0; font-size: 1.5rem; color: var(--primary-color); }
.btn-close { background: none; border: none; cursor: pointer; color: var(--text-muted); display: inline-flex; align-items: center; justify-content: center; }
.btn-close:hover { color: var(--error); }
.btn-close:focus-visible { outline: none; box-shadow: var(--focus-ring); border-radius: 6px; }
.modal-body { padding: 2rem; overflow-y: auto; }
.section-title { font-size: 1.1rem; color: var(--primary-dark); border-bottom: 2px solid var(--primary-light); padding-bottom: 0.5rem; margin-bottom: 1rem; display: inline-block; }
.date-picker :deep(.dp__input) {
  min-height: 48px;
  padding-left: 2.5rem;
  padding-right: 2.5rem;
  border: 1px solid #dee2e6;
  border-radius: 8px;
  font-family: inherit;
}
.date-picker :deep(.dp__input_icon),
.date-picker :deep(.dp__clear_icon) {
  top: 50%;
  transform: translateY(-50%);
}
.date-picker :deep(.dp__input:focus) { border-color: var(--primary-light); box-shadow: var(--focus-ring); }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid rgba(0,0,0,0.05); }
.data-table th { font-weight: 600; color: var(--primary-color); background: rgba(20, 184, 166, 0.06); }
.field-help { margin-top: 0.4rem; color: var(--text-muted); font-size: 0.82rem; }
.badge { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
.badge-dark { background: #343a40; color: white; }
.badge-info { background: #17a2b8; color: white; }
.btn-remove { background: none; border: none; color: var(--error); font-size: 1.25rem; cursor: pointer; opacity: 0.7; }
.btn-remove:hover { opacity: 1; }
@media (max-width: 768px) {
  .modal-body { flex-direction: column; gap: 1rem; }
}
</style>
