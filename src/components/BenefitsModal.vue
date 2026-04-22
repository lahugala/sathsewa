<template>
  <Teleport to="body">
    <div class="modal-overlay" v-if="show">
      <div class="modal-container glass-panel animate-fade-in" style="max-width: 900px;">
        <div class="modal-header">
          <h2>Benefits & Payouts: {{ member?.name }} ({{ member?.membership_number || 'N/A' }})</h2>
          <button class="btn-close" @click="$emit('close')" type="button" aria-label="Close">
            <X :size="20" />
          </button>
        </div>

        <div class="modal-body" style="display: flex; gap: 2rem;">
          <div class="form-section" style="flex: 1;">
            <h3 class="section-title">Record New Benefit</h3>
            <div v-if="saveMessage" class="text-success mb-4">{{ saveMessage }}</div>
            <div v-if="errorMsg" class="text-danger mb-4">{{ errorMsg }}</div>

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
                <input type="date" v-model="form.paid_date" class="form-control" required>
              </div>

              <template v-if="form.benefit_type === 'death_gratuity'">
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
              <table class="data-table">
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
                    <td>{{ b.paid_date }}</td>
                    <td>
                      <span class="badge" :class="b.benefit_type === 'death_gratuity' ? 'badge-dark' : 'badge-info'">
                        {{ b.benefit_type === 'death_gratuity' ? 'Death Gratuity' : 'Special Donation' }}
                      </span>
                    </td>
                    <td>
                      <div v-if="b.benefit_type === 'death_gratuity'">
                        <strong>{{ b.dependent_name }}</strong> ({{ b.relationship }})
                      </div>
                      <div v-else>
                        {{ b.aid_nature }}
                      </div>
                    </td>
                    <td style="font-weight: 600;">{{ formatAmount(b.amount) }}</td>
                    <td>
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
import Swal from 'sweetalert2'
import 'sweetalert2/dist/sweetalert2.min.css'
import { Trash2, X } from 'lucide-vue-next'

const props = defineProps({
  show: Boolean,
  member: Object
})

defineEmits(['close'])

const benefits = ref([])
const saving = ref(false)
const saveMessage = ref('')
const errorMsg = ref('')

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
}

const formatAmount = (value) => {
  const amount = Number(value)
  return Number.isFinite(amount) ? amount.toFixed(2) : '0.00'
}

const fetchBenefits = async () => {
  if (!props.member?.id) return
  try {
    const res = await fetch(`/api/get_benefits.php?member_id=${props.member.id}`)
    const data = await res.json()
    if (data.success) {
      benefits.value = data.benefits
    }
  } catch (e) {
    console.error('Failed to load benefits', e)
  }
}

watch(() => props.show, (newVal) => {
  if (newVal) {
    saveMessage.value = ''
    errorMsg.value = ''
    resetForm()
    fetchBenefits()
  }
})

watch(
  () => props.member?.id,
  (id) => {
    if (props.show && id) {
      fetchBenefits()
    }
  }
)

const saveBenefit = async () => {
  if (!props.member?.id) {
    errorMsg.value = 'Member is not selected.'
    return
  }

  if (form.benefit_type === 'death_gratuity' && (!form.dependent_name || !form.relationship)) {
    errorMsg.value = 'Dependent name and relationship are required.'
    return
  }

  if (form.benefit_type === 'special_donation' && !form.aid_nature) {
    errorMsg.value = 'Nature of aid is required.'
    return
  }

  saving.value = true
  errorMsg.value = ''
  saveMessage.value = ''

  const payload = {
    member_id: props.member.id,
    ...form
  }

  try {
    const res = await fetch('/api/save_benefit.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    const data = await res.json()
    if (data.success) {
      saveMessage.value = data.message
      resetForm()
      fetchBenefits()
      setTimeout(() => {
        saveMessage.value = ''
      }, 3000)
    } else {
      errorMsg.value = data.message
    }
  } catch (e) {
    errorMsg.value = 'Network error'
  } finally {
    saving.value = false
  }
}

const deleteBenefit = async (id) => {
  const result = await Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this record!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, delete it!'
  })

  if (result.isConfirmed) {
    try {
      const res = await fetch('/api/delete_benefit.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
      })
      const data = await res.json()
      if (data.success) {
        Swal.fire('Deleted!', 'Record deleted successfully.', 'success')
        fetchBenefits()
      } else {
        Swal.fire('Error!', data.message, 'error')
      }
    } catch (e) {
      Swal.fire('Error!', 'Network error deleting record', 'error')
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
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid rgba(0,0,0,0.05); }
.data-table th { font-weight: 600; color: var(--primary-color); background: rgba(37, 99, 235, 0.03); }
.text-success { color: var(--success); font-weight: 500; }
.text-danger { color: var(--error); font-weight: 500; }
.badge { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
.badge-dark { background: #343a40; color: white; }
.badge-info { background: #17a2b8; color: white; }
.btn-remove { background: none; border: none; color: var(--error); font-size: 1.25rem; cursor: pointer; opacity: 0.7; }
.btn-remove:hover { opacity: 1; }
@media (max-width: 768px) {
  .modal-body { flex-direction: column; gap: 1rem; }
}
</style>
