<template>
  <Teleport to="body">
    <div class="modal-overlay" v-if="show">
      <div class="modal-container glass-panel animate-fade-in">
        <div class="modal-header">
          <h2>Loans: {{ member?.name }} ({{ member?.membership_number || 'N/A' }})</h2>
          <button class="btn-close" @click="$emit('close')" type="button" aria-label="Close">
            <X :size="20" />
          </button>
        </div>

        <div class="modal-body">
          <section class="loan-form">
            <h3 class="section-title">Issue New Loan</h3>

            <form @submit.prevent="saveLoan">
              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label">Loan Amount</label>
                  <input type="number" v-model.number="form.principal_amount" class="form-control" min="1" step="0.01" required>
                </div>

                <div class="form-group">
                  <label class="form-label">Fixed Interest Rate (%)</label>
                  <input type="number" v-model.number="form.interest_rate" class="form-control" min="0" step="0.01" required>
                </div>

                <div class="form-group">
                  <label class="form-label">Installments</label>
                  <select v-model.number="form.term_months" class="form-control" required>
                    <option :value="3">3 months</option>
                    <option :value="6">6 months</option>
                    <option :value="12">12 months</option>
                  </select>
                </div>

                <div class="form-group">
                  <label class="form-label">Issued Date</label>
                  <VueDatePicker
                    v-model="form.issued_date"
                    model-type="yyyy-MM-dd"
                    auto-apply
                    :clearable="false"
                    :time-config="dateOnlyPickerConfig"
                    teleport
                    class="date-picker"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">First Due Date</label>
                  <VueDatePicker
                    v-model="form.first_due_date"
                    model-type="yyyy-MM-dd"
                    auto-apply
                    :clearable="false"
                    :time-config="dateOnlyPickerConfig"
                    teleport
                    class="date-picker"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">Remarks</label>
                  <input type="text" v-model="form.remarks" class="form-control" placeholder="Optional">
                </div>
              </div>

              <div class="preview-strip">
                <span>Total interest <strong>{{ formatCurrency(preview.totalInterest) }}</strong></span>
                <span>Total payable <strong>{{ formatCurrency(preview.totalPayable) }}</strong></span>
                <span>Each installment <strong>{{ formatCurrency(preview.installmentAmount) }}</strong></span>
              </div>

              <button type="submit" class="btn btn-primary" :disabled="savingLoan">
                <HandCoins :size="18" />
                {{ savingLoan ? 'Saving...' : 'Issue Loan' }}
              </button>
            </form>
          </section>

          <section class="loan-history">
            <div class="history-heading">
              <h3 class="section-title">Loan Recovery</h3>
              <button class="btn btn-outline btn-sm" type="button" :disabled="loading" @click="fetchLoans">
                <RefreshCw :size="16" />
                Refresh
              </button>
            </div>

            <p v-if="loading" class="empty-state">Loading loan records...</p>
            <p v-else-if="loans.length === 0" class="empty-state">No loans issued for this member yet.</p>

            <template v-else>
              <div class="loan-tabs">
                <button
                  v-for="loan in loans"
                  :key="loan.id"
                  class="loan-tab"
                  :class="{ active: loan.id === activeLoanId }"
                  type="button"
                  @click="activeLoanId = loan.id"
                >
                  <span>{{ formatDate(loan.issued_date) }}</span>
                  <strong>{{ formatCurrency(loan.total_payable) }}</strong>
                  <em :class="statusClass(loan.status)">{{ loan.status }}</em>
                </button>
              </div>

              <div v-if="activeLoan" class="loan-summary">
                <div>
                  <span>Principal</span>
                  <strong>{{ formatCurrency(activeLoan.principal_amount) }}</strong>
                </div>
                <div>
                  <span>Interest</span>
                  <strong>{{ activeLoan.interest_rate }}% / {{ formatCurrency(activeLoan.total_interest) }}</strong>
                </div>
                <div>
                  <span>Recovered</span>
                  <strong>{{ formatCurrency(activeLoan.paid_total) }}</strong>
                </div>
                <div>
                  <span>Balance</span>
                  <strong>{{ formatCurrency(activeLoan.balance) }}</strong>
                </div>
                <button class="btn-remove-loan" type="button" title="Delete Loan" @click="deleteLoan(activeLoan.id)">
                  <Trash2 :size="17" />
                </button>
              </div>

              <div class="table-responsive">
                <table class="data-table responsive-card-table">
                  <thead>
                    <tr>
                      <th>No</th>
                      <th>Due Date</th>
                      <th>Principal</th>
                      <th>Interest</th>
                      <th>Due</th>
                      <th>Paid Date</th>
                      <th>Recovered</th>
                      <th>Status</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="installment in activeLoan.installments" :key="installment.id" :class="{ paid: installment.status === 'Paid' }">
                      <td data-label="No">{{ installment.installment_no }}</td>
                      <td data-label="Due Date">{{ formatDate(installment.due_date) }}</td>
                      <td data-label="Principal">{{ formatCurrency(installment.principal_component) }}</td>
                      <td data-label="Interest">{{ formatCurrency(installment.interest_component) }}</td>
                      <td data-label="Due">{{ formatCurrency(installment.amount_due) }}</td>
                      <td data-label="Paid Date">
                        <VueDatePicker
                          v-model="installment.paid_date"
                          model-type="yyyy-MM-dd"
                          auto-apply
                          :clearable="false"
                          :time-config="dateOnlyPickerConfig"
                          :disabled="installment.status === 'Paid'"
                          teleport
                          class="table-date-picker"
                        />
                      </td>
                      <td data-label="Recovered">
                        <input
                          type="number"
                          v-model.number="installment.recover_amount"
                          class="form-control form-control-sm"
                          min="0"
                          step="0.01"
                          :disabled="installment.status === 'Paid'"
                        >
                      </td>
                      <td data-label="Status">
                        <span class="status-badge" :class="installmentStatusClass(installment.status)">
                          {{ installment.status }}
                        </span>
                      </td>
                      <td data-label="Action">
                        <button class="btn btn-sm btn-primary" type="button" :disabled="installment.saving || installment.status === 'Paid'" @click="saveInstallment(installment)">
                          <Save :size="16" v-if="!installment.saving" />
                          <span v-else>...</span>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>
          </section>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { HandCoins, RefreshCw, Save, Trash2, X } from 'lucide-vue-next'
import { alertError, alertSuccess, alertWarning, confirmWarning } from '../utils/alerts'
import { apiFetch } from '../utils/api'

const props = defineProps({
  show: Boolean,
  member: Object
})

defineEmits(['close'])

const dateOnlyPickerConfig = { enableTimePicker: false }
const loans = ref([])
const activeLoanId = ref(null)
const loading = ref(false)
const savingLoan = ref(false)

const todayIso = () => new Date().toISOString().split('T')[0]

const addMonths = (dateValue, months) => {
  const date = new Date(`${dateValue}T00:00:00`)
  date.setMonth(date.getMonth() + months)
  return date.toISOString().split('T')[0]
}

const form = reactive({
  principal_amount: 0,
  interest_rate: 0,
  term_months: 3,
  issued_date: todayIso(),
  first_due_date: addMonths(todayIso(), 1),
  remarks: ''
})

const resetForm = () => {
  form.principal_amount = 0
  form.interest_rate = 0
  form.term_months = 3
  form.issued_date = todayIso()
  form.first_due_date = addMonths(todayIso(), 1)
  form.remarks = ''
}

const preview = computed(() => {
  const principal = Number(form.principal_amount || 0)
  const rate = Number(form.interest_rate || 0)
  const term = Number(form.term_months || 1)
  const totalInterest = roundMoney(principal * (rate / 100))
  const totalPayable = roundMoney(principal + totalInterest)
  return {
    totalInterest,
    totalPayable,
    installmentAmount: roundMoney(totalPayable / term)
  }
})

const activeLoan = computed(() => loans.value.find((loan) => loan.id === activeLoanId.value) || loans.value[0] || null)

const roundMoney = (value) => Math.round(Number(value || 0) * 100) / 100

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-LK', {
    style: 'currency',
    currency: 'LKR',
    minimumFractionDigits: 2
  }).format(Number(value || 0))
}

const formatDate = (value) => value || '-'

const hydrateLoans = (items) => {
  return items.map((loan) => ({
    ...loan,
    installments: (loan.installments || []).map((installment) => ({
      ...installment,
      recover_amount: Number(installment.amount_paid || installment.amount_due || 0),
      paid_date: installment.paid_date || todayIso(),
      saving: false
    }))
  }))
}

const fetchLoans = async () => {
  if (!props.member?.id) return
  loading.value = true
  try {
    const res = await apiFetch(`/api/get_loans.php?member_id=${props.member.id}`)
    const data = await res.json()
    if (data.success) {
      loans.value = hydrateLoans(data.loans || [])
      if (!loans.value.some((loan) => loan.id === activeLoanId.value)) {
        activeLoanId.value = loans.value[0]?.id || null
      }
    } else {
      alertError('Loans failed to load', data.message || 'Unable to load loan records.')
    }
  } catch (e) {
    alertError('Network error', 'Network error while loading loan records.')
  } finally {
    loading.value = false
  }
}

const saveLoan = async () => {
  if (!props.member?.id) {
    alertWarning('Member not selected', 'Please select a member before issuing a loan.')
    return
  }

  if (Number(form.principal_amount || 0) <= 0) {
    alertWarning('Invalid amount', 'Loan amount must be greater than zero.')
    return
  }

  savingLoan.value = true
  try {
    const res = await apiFetch('/api/save_loan.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ member_id: props.member.id, ...form })
    })
    const data = await res.json()
    if (data.success) {
      resetForm()
      await fetchLoans()
      activeLoanId.value = data.loan_id || activeLoanId.value
      alertSuccess('Loan issued', data.message || 'Loan issued successfully.')
    } else {
      alertError('Save failed', data.message || 'Failed to issue loan.')
    }
  } catch (e) {
    alertError('Network error', 'Network error while issuing loan.')
  } finally {
    savingLoan.value = false
  }
}

const saveInstallment = async (installment) => {
  if (!installment.paid_date) {
    alertWarning('Paid date required', 'Please select a paid date for the recovery.')
    return
  }

  if (Number(installment.recover_amount || 0) <= 0) {
    alertWarning('Invalid amount', 'Recovered amount must be greater than zero.')
    return
  }

  installment.saving = true
  try {
    const res = await apiFetch('/api/save_loan_installment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        installment_id: installment.id,
        paid_date: installment.paid_date,
        amount_paid: installment.recover_amount
      })
    })
    const data = await res.json()
    if (data.success) {
      await fetchLoans()
      alertSuccess('Recovery saved', data.message || 'Installment recovery saved successfully.')
    } else {
      alertError('Save failed', data.message || 'Failed to save installment recovery.')
    }
  } catch (e) {
    alertError('Network error', 'Network error while saving recovery.')
  } finally {
    installment.saving = false
  }
}

const deleteLoan = async (id) => {
  const result = await confirmWarning({
    title: 'Delete loan?',
    text: 'This will delete the loan and all installment records.',
    confirmButtonText: 'Yes, delete loan'
  })

  if (!result.isConfirmed) return

  try {
    const res = await apiFetch('/api/delete_loan.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    })
    const data = await res.json()
    if (data.success) {
      await fetchLoans()
      alertSuccess('Deleted', 'Loan deleted successfully.')
    } else {
      alertError('Delete failed', data.message || 'Failed to delete loan.')
    }
  } catch (e) {
    alertError('Network error', 'Network error while deleting loan.')
  }
}

const statusClass = (status) => ({
  'loan-active': status === 'Active',
  'loan-overdue': status === 'Overdue',
  'loan-settled': status === 'Settled',
  'loan-cancelled': status === 'Cancelled'
})

const installmentStatusClass = (status) => ({
  'status-paid': status === 'Paid',
  'status-partial': status === 'Partially Paid',
  'status-pending': status === 'Pending'
})

watch(() => props.show, (newVal) => {
  if (newVal) {
    resetForm()
    fetchLoans()
  }
})

watch(
  () => props.member?.id,
  (id) => {
    if (props.show && id) {
      fetchLoans()
    }
  }
)
</script>

<style scoped>
.modal-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(4px); display: flex; justify-content: center; align-items: center; z-index: 1000; padding: 1rem; }
.modal-container { width: min(100%, 1320px); max-height: 92vh; overflow: hidden; padding: 0; display: flex; flex-direction: column; background: #fff; }
.modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; background: inherit; z-index: 10; }
.modal-header h2 { margin: 0; font-size: 1.45rem; color: var(--primary-color); }
.btn-close { background: none; border: none; cursor: pointer; color: var(--text-muted); display: inline-flex; align-items: center; justify-content: center; }
.btn-close:hover { color: var(--error); }
.modal-body { display: grid; grid-template-columns: minmax(300px, 0.8fr) minmax(0, 1.6fr); gap: 1.5rem; padding: 1.5rem; overflow-y: auto; }
.loan-form,
.loan-history { min-width: 0; }
.section-title { font-size: 1.05rem; color: var(--primary-dark); border-bottom: 2px solid var(--primary-light); padding-bottom: 0.45rem; margin-bottom: 1rem; display: inline-block; }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 0.85rem; }
.date-picker :deep(.dp__input),
.table-date-picker :deep(.dp__input) {
  min-height: 42px;
  padding-left: 2.3rem;
  padding-right: 2.3rem;
  border: 1px solid rgba(15, 118, 110, 0.18);
  border-radius: 8px;
  font-family: inherit;
}
.date-picker :deep(.dp__input_icon),
.date-picker :deep(.dp__clear_icon),
.table-date-picker :deep(.dp__input_icon),
.table-date-picker :deep(.dp__clear_icon) { top: 50%; transform: translateY(-50%); }
.table-date-picker { min-width: 138px; }
.preview-strip {
  display: grid;
  gap: 0.5rem;
  padding: 0.8rem;
  margin-bottom: 1rem;
  border: 1px solid rgba(217, 119, 6, 0.22);
  border-radius: 8px;
  background: rgba(254, 243, 199, 0.48);
  color: var(--text-muted);
  font-size: 0.86rem;
}
.preview-strip span { display: flex; justify-content: space-between; gap: 0.75rem; }
.preview-strip strong { color: var(--primary-dark); white-space: nowrap; }
.history-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.btn-sm { padding: 0.45rem 0.7rem; min-height: 36px; font-size: 0.86rem; }
.empty-state { padding: 1.25rem; color: var(--text-muted); border: 1px dashed rgba(15, 118, 110, 0.22); border-radius: 8px; background: rgba(20, 184, 166, 0.04); }
.loan-tabs { display: flex; gap: 0.7rem; overflow-x: auto; padding-bottom: 0.75rem; margin-bottom: 0.8rem; }
.loan-tab {
  display: grid;
  gap: 0.18rem;
  min-width: 165px;
  padding: 0.75rem;
  text-align: left;
  border: 1px solid rgba(15, 118, 110, 0.18);
  border-radius: 8px;
  background: #fff;
  cursor: pointer;
}
.loan-tab.active { border-color: var(--primary-color); box-shadow: var(--focus-ring); }
.loan-tab span,
.loan-tab em { color: var(--text-muted); font-size: 0.78rem; font-style: normal; }
.loan-tab strong { color: var(--primary-dark); font-size: 0.95rem; }
.loan-active { color: var(--primary-color) !important; }
.loan-overdue { color: var(--error) !important; }
.loan-settled { color: var(--success) !important; }
.loan-cancelled { color: var(--text-muted) !important; }
.loan-summary {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr)) auto;
  gap: 0.65rem;
  align-items: stretch;
  margin-bottom: 1rem;
}
.loan-summary div {
  padding: 0.75rem;
  border: 1px solid rgba(20, 184, 166, 0.14);
  border-radius: 8px;
  background: rgba(20, 184, 166, 0.04);
}
.loan-summary span { display: block; color: var(--text-muted); font-size: 0.76rem; }
.loan-summary strong { display: block; margin-top: 0.12rem; color: var(--primary-dark); overflow-wrap: anywhere; }
.btn-remove-loan {
  width: 42px;
  border: 1px solid rgba(220, 53, 69, 0.28);
  border-radius: 8px;
  background: rgba(220, 53, 69, 0.06);
  color: var(--error);
  cursor: pointer;
}
.table-responsive { max-height: 430px; overflow: auto; border: 1px solid rgba(15, 118, 110, 0.12); border-radius: 8px; }
.data-table { width: 100%; border-collapse: collapse; background: #fff; }
.data-table th,
.data-table td { padding: 0.65rem; text-align: left; border-bottom: 1px solid rgba(0,0,0,0.05); vertical-align: middle; }
.data-table th { position: sticky; top: 0; z-index: 2; font-weight: 700; color: var(--primary-color); background: #eefaf7; }
.data-table tr.paid { background: rgba(25, 135, 84, 0.07); }
.form-control-sm { padding: 0.45rem; min-width: 112px; font-size: 0.88rem; }
.status-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 92px; padding: 0.25rem 0.5rem; border-radius: 999px; font-size: 0.73rem; font-weight: 750; }
.status-paid { background: rgba(25, 135, 84, 0.13); color: #126742; }
.status-partial { background: rgba(217, 119, 6, 0.14); color: #92400e; }
.status-pending { background: rgba(100, 116, 139, 0.13); color: #475467; }
@media (max-width: 1100px) {
  .modal-body,
  .loan-summary { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .form-grid { grid-template-columns: 1fr; }
  .history-heading { align-items: stretch; flex-direction: column; }
}
</style>
