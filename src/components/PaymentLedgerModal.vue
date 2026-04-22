<template>
  <Teleport to="body">
    <div class="modal-overlay" v-if="show">
      <div class="modal-container glass-panel animate-fade-in" style="max-width: 1000px;">
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
            <div v-if="saveMessage" class="text-success">{{ saveMessage }}</div>
            <div v-if="errorMsg" class="text-danger">{{ errorMsg }}</div>
          </div>

          <div class="table-responsive">
            <table class="data-table ledger-table">
              <thead>
                <tr>
                  <th>Month</th>
                  <th>Paid Date</th>
                  <th>Member Fee</th>
                  <th>Share Capital</th>
                  <th>Special</th>
                  <th>Remarks</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(m, idx) in monthsList" :key="idx">
                  <td>{{ m.name }}</td>
                  <td><input type="date" v-model="m.data.paid_date" class="form-control form-control-sm"></td>
                  <td><input type="number" v-model="m.data.member_fee" class="form-control form-control-sm"></td>
                  <td><input type="number" v-model="m.data.share_capital" class="form-control form-control-sm"></td>
                  <td><input type="number" v-model="m.data.special_charges" class="form-control form-control-sm"></td>
                  <td><input type="text" v-model="m.data.remarks" class="form-control form-control-sm"></td>
                  <td>
                    <button class="btn btn-sm btn-primary" @click="savePayment(idx + 1, m.data)" :disabled="m.saving" style="padding: 0.4rem;" title="Save">
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
import { ref, watch } from 'vue'
import { Save, X } from 'lucide-vue-next'

const props = defineProps({
  show: Boolean,
  member: Object
})
const emit = defineEmits(['close'])

const currentYear = new Date().getFullYear()
const selectedYear = ref(currentYear)
const availableYears = Array.from({length: 15}, (_, i) => currentYear - 10 + i)

const saveMessage = ref('')
const errorMsg = ref('')

const monthsNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"]
const monthsList = ref([])

const initMonths = () => {
  monthsList.value = monthsNames.map((name, idx) => ({
    name,
    monthNum: idx + 1,
    saving: false,
    data: {
      paid_date: '',
      member_fee: 100, // Default as per doc
      share_capital: 0,
      special_charges: 0,
      remarks: ''
    }
  }))
}

const fetchPayments = async () => {
  if (!props.member?.id) return
  initMonths() // Reset grid
  try {
    const res = await fetch(`/api/get_payments.php?member_id=${props.member.id}&year=${selectedYear.value}`)
    const data = await res.json()
    if (data.success && data.payments) {
      data.payments.forEach(p => {
        const idx = p.payment_month - 1
        monthsList.value[idx].data = {
          paid_date: p.paid_date,
          member_fee: parseFloat(p.member_fee),
          share_capital: parseFloat(p.share_capital),
          special_charges: parseFloat(p.special_charges),
          remarks: p.remarks || ''
        }
      })
    }
  } catch (e) {
    console.error('Failed to load ledger', e)
  }
}

watch(() => props.show, (newVal) => {
  if (newVal) {
    saveMessage.value = ''
    errorMsg.value = ''
    fetchPayments()
  }
})

const savePayment = async (monthNum, data) => {
  if (!data.paid_date) {
    errorMsg.value = `Paid Date is required for ${monthsNames[monthNum-1]}`
    setTimeout(() => errorMsg.value = '', 3000)
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
    const res = await fetch('/api/save_payment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    const resData = await res.json()
    if (resData.success) {
      saveMessage.value = `Saved ${monthsNames[monthNum-1]} successfully!`
      setTimeout(() => saveMessage.value = '', 3000)
    } else {
      errorMsg.value = resData.message
    }
  } catch (e) {
    errorMsg.value = "Failed to save payment"
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
.modal-body { padding: 2rem; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid rgba(0,0,0,0.05); }
.data-table th { font-weight: 600; color: var(--primary-color); background: rgba(37, 99, 235, 0.03); }
.form-control-sm { padding: 0.4rem; font-size: 0.875rem; width: 100%; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px;}
.text-success { color: var(--success); font-weight: 500; }
.text-danger { color: var(--error); font-weight: 500; }
</style>


