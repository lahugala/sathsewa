<template>
  <Teleport to="body">
    <div class="modal-overlay" v-if="show">
      <div class="modal-container glass-panel animate-fade-in" style="max-width: 1250px;">
        <div class="modal-header">
          <h2>Ledger: {{ member?.name }} ({{ member?.membership_number || 'N/A' }})</h2>
          <button class="btn-close" @click="$emit('close')" type="button" aria-label="Close">
            <X :size="20" />
          </button>
        </div>
        
        <div class="modal-body">
          <div class="ledger-controls" style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
            <div class="form-group" style="display: flex; align-items: center; gap: 1rem; margin: 0;">
              <label>Year:</label>
              <select v-model="selectedYear" class="form-control" @change="fetchPayments" style="width: 120px;">
                <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
          </div>

          <div class="table-responsive">
            <table class="data-table ledger-table responsive-card-table">
              <thead>
                <tr>
                  <th>Month</th>
                  <th>Paid Date</th>
                  <th>Member Fee</th>
                  <th>Share Capital</th>
                  <th>Special</th>
                  <th>Due</th>
                  <th>Remarks</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(m, idx) in monthsList"
                  :key="idx"
                  :class="{ 'month-complete': m.isComplete && !isBeforeMemberDateMonth(idx + 1), 'month-locked': isBeforeMemberDateMonth(idx + 1) }"
                >
                  <td data-label="Month">
                    <div class="month-cell">
                      <span>{{ m.name }}</span>
                      <span v-if="isBeforeMemberDateMonth(idx + 1)" class="locked-badge">Before member date</span>
                      <span v-if="m.isComplete && !isBeforeMemberDateMonth(idx + 1)" class="complete-badge">Paid</span>
                    </div>
                  </td>
                  <td data-label="Paid Date">
                    <VueDatePicker
                      v-model="m.data.paid_date"
                      model-type="yyyy-MM-dd"
                      auto-apply
                      :clearable="false"
                      :time-config="dateOnlyPickerConfig"
                      :min-date="memberMinDate"
                      :disabled="isBeforeMemberDateMonth(idx + 1)"
                      teleport
                      class="ledger-date-picker"
                    />
                  </td>
                  <td data-label="Member Fee"><input type="number" v-model="m.data.member_fee" class="form-control form-control-sm" :disabled="isBeforeMemberDateMonth(idx + 1)"></td>
                  <td data-label="Share Capital"><input type="number" v-model="m.data.share_capital" class="form-control form-control-sm" :disabled="isBeforeMemberDateMonth(idx + 1)"></td>
                  <td data-label="Special">
                    <input type="number" v-model="m.data.special_charges" class="form-control form-control-sm" :disabled="isBeforeMemberDateMonth(idx + 1)">
                    <div v-if="m.expected_special_charges > 0" class="field-note">
                      Required: {{ formatCurrency(m.expected_special_charges) }}
                    </div>
                  </td>
                  <td data-label="Due">
                    <div class="due-cell">
                      <strong>{{ formatCurrency(expectedTotal(m)) }}</strong>
                      <span v-if="outstandingBalance(m) > 0">{{ formatCurrency(outstandingBalance(m)) }} due</span>
                      <span v-else>Paid</span>
                    </div>
                  </td>
                  <td data-label="Remarks"><input type="text" v-model="m.data.remarks" class="form-control form-control-sm" :disabled="isBeforeMemberDateMonth(idx + 1)"></td>
                  <td data-label="Action">
                    <button class="btn btn-sm btn-primary" @click="savePayment(idx + 1, m.data)" :disabled="m.saving || isBeforeMemberDateMonth(idx + 1)" style="padding: 0.4rem;" title="Save">
                      <Save size="16" v-if="!m.saving" />
                      <span v-else>...</span>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Save, X } from 'lucide-vue-next'
import { alertError, alertSuccess, alertWarning } from '../utils/alerts'
import { apiFetch } from '../utils/api'

const props = defineProps({
  show: Boolean,
  member: Object
})
const emit = defineEmits(['close'])

const currentYear = new Date().getFullYear()
const selectedYear = ref(currentYear)
const availableYears = Array.from({length: 15}, (_, i) => currentYear - 10 + i)
const dateOnlyPickerConfig = { enableTimePicker: false }
const monthlyMemberFee = 100

const monthsNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"]
const monthsList = ref([])

const formatCurrency = (value) => {
  return Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

const chargesByMonth = (charges = []) => {
  return charges.reduce((acc, charge) => {
    acc[Number(charge.charge_month)] = Number(charge.amount || 0)
    return acc
  }, {})
}

const initMonths = (predefinedCharges = {}) => {
  monthsList.value = monthsNames.map((name, idx) => ({
    name,
    monthNum: idx + 1,
    saving: false,
    isComplete: false,
    expected_special_charges: predefinedCharges[idx + 1] || 0,
    data: {
      paid_date: '',
      member_fee: monthlyMemberFee,
      share_capital: 0,
      special_charges: predefinedCharges[idx + 1] || 0,
      remarks: ''
    }
  }))
}

const parseDate = (value) => {
  if (!value) return null
  const date = new Date(`${value}T00:00:00`)
  return Number.isNaN(date.getTime()) ? null : date
}

const memberMinDate = computed(() => parseDate(props.member?.membership_date))

const isBeforeMemberDateMonth = (monthNum) => {
  const memberDate = parseDate(props.member?.membership_date)
  if (!memberDate) return false

  const paymentMonth = new Date(Number(selectedYear.value), monthNum - 1, 1)
  const memberMonth = new Date(memberDate.getFullYear(), memberDate.getMonth(), 1)
  return paymentMonth < memberMonth
}

const isPaidDateBeforeMemberDate = (paidDateValue) => {
  const memberDate = parseDate(props.member?.membership_date)
  const paidDate = parseDate(paidDateValue)
  if (!memberDate || !paidDate) return false
  return paidDate < memberDate
}

const expectedTotal = (month) => {
  return monthlyMemberFee + Number(month.expected_special_charges || 0)
}

const outstandingBalance = (month) => {
  const memberFeeBalance = Math.max(0, monthlyMemberFee - Number(month.data.member_fee || 0))
  const specialBalance = Math.max(0, Number(month.expected_special_charges || 0) - Number(month.data.special_charges || 0))
  return memberFeeBalance + specialBalance
}

const isPaymentComplete = (month) => {
  return outstandingBalance(month) <= 0
}

const fetchPayments = async () => {
  if (!props.member?.id) return
  try {
    const res = await apiFetch(`/api/get_payments.php?member_id=${props.member.id}&year=${selectedYear.value}`)
    const data = await res.json()
    if (data.success && data.payments) {
      initMonths(chargesByMonth(data.predefined_special_charges || []))
      data.payments.forEach(p => {
        const idx = p.payment_month - 1
        if (monthsList.value[idx]) {
          monthsList.value[idx].data = {
            paid_date: p.paid_date,
            member_fee: parseFloat(p.member_fee),
            share_capital: parseFloat(p.share_capital),
            special_charges: parseFloat(p.special_charges),
            remarks: p.remarks || ''
          }
          monthsList.value[idx].isComplete = isPaymentComplete(monthsList.value[idx])
        }
      })
    } else {
      initMonths()
    }
  } catch (e) {
    console.error('Failed to load ledger', e)
    initMonths()
  }
}

watch(() => props.show, (newVal) => {
  if (newVal) {
    fetchPayments()
  }
})

const savePayment = async (monthNum, data) => {
  if (isBeforeMemberDateMonth(monthNum)) {
    alertWarning('Before member date', 'Cannot save payment for a month before the member date.')
    return
  }

  if (!data.paid_date) {
    alertWarning('Paid date required', `Paid Date is required for ${monthsNames[monthNum-1]}.`)
    return
  }

  if (isPaidDateBeforeMemberDate(data.paid_date)) {
    alertWarning('Invalid paid date', 'Paid date cannot be before the member date.')
    return
  }
  
  monthsList.value[monthNum-1].saving = true
  
  const payload = {
    member_id: props.member.id,
    payment_year: selectedYear.value,
    payment_month: monthNum,
    paid_date: data.paid_date,
    member_fee: data.member_fee,
    share_capital: data.share_capital,
    special_charges: data.special_charges,
    remarks: data.remarks
  }

  try {
    const res = await apiFetch('/api/save_payment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    const resData = await res.json()
    if (resData.success) {
      monthsList.value[monthNum-1].isComplete = isPaymentComplete(monthsList.value[monthNum-1])
      alertSuccess('Payment saved', `Saved ${monthsNames[monthNum-1]} successfully!`)
    } else {
      alertError('Save failed', resData.message || 'Failed to save payment.')
    }
  } catch (e) {
    alertError('Save failed', 'Failed to save payment.')
  } finally {
    monthsList.value[monthNum-1].saving = false
  }
}
</script>

<style scoped>
.modal-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(4px); display: flex; justify-content: center; align-items: center; z-index: 1000; padding: 1rem; }
.modal-container { width: 100%; max-height: 90vh; overflow-y: auto; padding: 0; display: flex; flex-direction: column; background: #fff; }
.modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: inherit; z-index: 10; }
.modal-header h2 { margin: 0; font-size: 1.5rem; color: var(--primary-color); }
.btn-close { background: none; border: none; cursor: pointer; color: var(--text-muted); display: inline-flex; align-items: center; justify-content: center; }
.btn-close:hover { color: var(--error); }
.btn-close:focus-visible { outline: none; box-shadow: var(--focus-ring); border-radius: 6px; }
.modal-body { padding: 2rem; overflow: hidden; display: flex; flex-direction: column; min-height: 0; }
.table-responsive { max-height: 62vh; overflow: auto; border: 1px solid rgba(0,0,0,0.06); border-radius: 8px; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid rgba(0,0,0,0.05); }
.data-table th { font-weight: 600; color: var(--primary-color); background: #eefaf7; }
.ledger-table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  box-shadow: 0 1px 0 rgba(0, 0, 0, 0.08);
}
.ledger-table tbody tr.month-complete {
  background: rgba(25, 135, 84, 0.08);
}
.ledger-table tbody tr.month-complete td {
  border-bottom-color: rgba(25, 135, 84, 0.18);
}
.ledger-table tbody tr.month-complete:hover {
  background: rgba(25, 135, 84, 0.13);
}
.ledger-table tbody tr.month-locked {
  background: rgba(108, 117, 125, 0.08);
  color: #6c757d;
}
.ledger-table tbody tr.month-locked:hover {
  background: rgba(108, 117, 125, 0.12);
}
.month-cell {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  min-width: 120px;
  flex-wrap: wrap;
}
.complete-badge,
.locked-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.18rem 0.45rem;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
}
.complete-badge {
  background: rgba(25, 135, 84, 0.16);
  color: #126742;
}
.locked-badge {
  background: rgba(108, 117, 125, 0.15);
  color: #495057;
}
.ledger-date-picker {
  min-width: 148px;
}
.ledger-date-picker :deep(.dp__input) {
  min-height: 36px;
  padding: 0.4rem 2rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.875rem;
  font-family: inherit;
}
.ledger-date-picker :deep(.dp__input_icon),
.ledger-date-picker :deep(.dp__clear_icon) {
  top: 50%;
  transform: translateY(-50%);
}
.ledger-date-picker :deep(.dp__input:focus) {
  border-color: var(--primary-light);
  box-shadow: var(--focus-ring);
}
.form-control-sm { padding: 0.4rem; font-size: 0.875rem; width: 100%; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px;}
.field-note {
  margin-top: 0.25rem;
  color: var(--text-muted);
  font-size: 0.72rem;
  white-space: nowrap;
}
.due-cell {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 96px;
  font-size: 0.84rem;
}
.due-cell strong {
  color: var(--text-main);
}
.due-cell span {
  color: var(--text-muted);
  font-size: 0.75rem;
}
</style>


