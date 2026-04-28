<template>
  <Teleport to="body">
    <div class="modal-overlay" v-if="show">
      <div class="modal-container glass-panel animate-fade-in">
        <div class="modal-header">
          <h2>{{ editId ? 'Edit Member' : 'Add New Member' }}</h2>
          <button class="btn-close" @click="closeModal" type="button" aria-label="Close">
            <X :size="20" />
          </button>
        </div>
        
        <div class="modal-body">
          <div v-if="fetching" class="text-center py-4">Loading data...</div>
          <form v-else @submit.prevent="submitForm">
            <h3 class="section-title">Personal Details</h3>
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" v-model="form.name" class="form-control" required placeholder="John Doe">
              </div>
              
              <div class="form-group">
                <label class="form-label">NIC</label>
                <input
                  type="text"
                  v-model="form.nic"
                  class="form-control"
                  required
                  inputmode="text"
                  maxlength="12"
                  placeholder="123456789V or 200012345678"
                  @input="formatNic"
                >
                <p class="field-help">Use 9 digits with optional V/X, or 12 digits.</p>
              </div>
            </div>

            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Membership Number</label>
                <input type="text" v-model="form.membership_number" class="form-control" placeholder="M-001">
              </div>
              
              <div class="form-group">
                <label class="form-label">Membership Date</label>
                <VueDatePicker
                  v-model="form.membership_date"
                  model-type="yyyy-MM-dd"
                  auto-apply
                  :time-config="dateOnlyPickerConfig"
                  placeholder="Select membership date"
                  teleport
                  class="date-picker"
                />
              </div>
            </div>

            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Member Status</label>
                <select v-model="form.status" class="form-control" required>
                  <option value="Active">Active</option>
                  <option value="Inactive">Inactive</option>
                  <option value="Suspended">Suspended</option>
                </select>
              </div>
            </div>

            <div v-if="form.status !== 'Active'" class="form-group">
              <label class="form-label">{{ form.status }} Reason</label>
              <textarea
                v-model="form.status_reason"
                class="form-control"
                rows="2"
                required
                :placeholder="`Enter reason for ${form.status.toLowerCase()} status`"
              ></textarea>
            </div>
            
            <div class="form-group">
              <label class="form-label">Address</label>
              <textarea v-model="form.address" class="form-control" rows="2" required placeholder="123 Main St..."></textarea>
            </div>

            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">City</label>
                <input type="text" v-model="form.city" class="form-control" required placeholder="Colombo">
              </div>
              
              <div class="form-group">
                <label class="form-label">Contact Number</label>
                <input
                  type="tel"
                  v-model="form.contact_number"
                  class="form-control"
                  required
                  inputmode="numeric"
                  maxlength="10"
                  placeholder="0712345678"
                  @input="formatContactNumber"
                >
                <p class="field-help">Enter exactly 10 digits.</p>
              </div>
            </div>

            <hr class="divider">

            <div class="dependents-header">
              <h3 class="section-title" style="margin-bottom: 0;">Dependents</h3>
              <button type="button" @click="addDependent" class="btn btn-outline btn-sm">+ Add</button>
            </div>

            <div v-if="form.dependents.length === 0" class="empty-state">
              No dependents added yet.
            </div>

            <div v-for="(dep, index) in form.dependents" :key="index" class="dependent-card">
              <div class="dependent-header">
                <h4>Dependent #{{ index + 1 }}</h4>
                <button type="button" @click="removeDependent(index)" class="btn-remove">x</button>
              </div>
              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label">Name</label>
                  <input type="text" v-model="dep.name" class="form-control" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Relationship</label>
                  <select v-model="dep.relationship" class="form-control" required>
                    <option value="" disabled>Select relationship</option>
                    <option
                      v-if="dep.relationship && !relationshipOptions.includes(dep.relationship)"
                      :value="dep.relationship"
                    >
                      {{ dep.relationship }}
                    </option>
                    <option v-for="relationship in relationshipOptions" :key="relationship" :value="relationship">
                      {{ relationship }}
                    </option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Birth Year</label>
                  <VueDatePicker v-model="dep.birth_year" year-picker auto-apply :clearable="false"></VueDatePicker>
                </div>
              </div>
            </div>

            <div class="form-actions">
              <button type="button" class="btn btn-outline" @click="closeModal" style="margin-right: 1rem;">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="loading">
                {{ loading ? 'Saving...' : 'Save Member' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, reactive, defineProps, defineEmits, watch } from 'vue'
import { X } from 'lucide-vue-next'
import { alertError, alertWarning } from '../utils/alerts'
import { apiFetch } from '../utils/api'

const props = defineProps({
  show: Boolean,
  editId: { type: Number, default: null }
})

const emit = defineEmits(['close', 'success'])

const loading = ref(false)
const fetching = ref(false)
const dateOnlyPickerConfig = { enableTimePicker: false }
const relationshipOptions = [
  'Father',
  'Mother',
  'Elder Brother',
  'Younger Brother',
  'Elder Sister',
  'Younger Sister',
  'Son',
  'Daughter',
  'Grandfather',
  'Grandmother',
  "Father's Elder Brother",
  "Father's Younger Brother",
  "Father's Sister",
  "Father's Sister's Husband",
  'Husband',
  'Wife',
  'Father-in-law',
  'Mother-in-law',
  'Brother-in-law',
  'Sister-in-law',
  'Son-in-law',
  'Daughter-in-law',
  "Mother's Elder Sister",
  "Mother's Younger Sister",
  "Mother's Brother",
  "Mother's Brother's Wife"
]

const form = reactive({
  name: '',
  membership_number: '',
  membership_date: '',
  nic: '',
  address: '',
  city: '',
  contact_number: '',
  status: 'Active',
  status_reason: '',
  dependents: []
})

const resetForm = () => {
  form.name = ''
  form.membership_number = ''
  form.membership_date = ''
  form.nic = ''
  form.address = ''
  form.city = ''
  form.contact_number = ''
  form.status = 'Active'
  form.status_reason = ''
  form.dependents = []
}

watch(() => props.show, async (newVal) => {
  if (newVal) {
    if (props.editId) {
      fetching.value = true
      try {
        const res = await apiFetch(`/api/get_member.php?id=${props.editId}`)
        const data = await res.json()
        if (data.success) {
          Object.assign(form, data.member)
          // Ensure birth_year is an integer for the datepicker
          form.dependents = form.dependents.map(d => ({
            ...d,
            birth_year: parseInt(d.birth_year)
          }))
        } else {
          alertError('Failed to load member data')
        }
      } catch (err) {
        alertError('Connection error', 'Error connecting to server.')
      } finally {
        fetching.value = false
      }
    } else {
      resetForm()
    }
  }
})

const addDependent = () => {
  form.dependents.push({
    name: '',
    relationship: '',
    birth_year: new Date().getFullYear()
  })
}

const removeDependent = (index) => {
  form.dependents.splice(index, 1)
}

const normalizeNic = (value) => String(value || '').replace(/[\s-]/g, '').toUpperCase()
const normalizeContactNumber = (value) => String(value || '').replace(/\D/g, '').slice(0, 10)

const isValidNic = (value) => {
  const nic = normalizeNic(value)
  return /^\d{9}[VX]?$/.test(nic) || /^\d{12}$/.test(nic)
}

const isValidContactNumber = (value) => /^\d{10}$/.test(normalizeContactNumber(value))

const formatNic = () => {
  form.nic = normalizeNic(form.nic).replace(/[^0-9VX]/g, '').slice(0, 12)
}

const formatContactNumber = () => {
  form.contact_number = normalizeContactNumber(form.contact_number)
}

const closeModal = () => {
  resetForm()
  emit('close')
}

const submitForm = async () => {
  loading.value = true

  if (form.status !== 'Active' && !form.status_reason.trim()) {
    alertWarning('Reason required', `Reason is required for ${form.status} members.`)
    loading.value = false
    return
  }

  formatNic()
  formatContactNumber()

  if (!isValidNic(form.nic)) {
    alertWarning('Invalid NIC', 'NIC must be 9 digits with optional V/X, or 12 digits.')
    loading.value = false
    return
  }

  if (!isValidContactNumber(form.contact_number)) {
    alertWarning('Invalid contact number', 'Contact number must contain exactly 10 digits.')
    loading.value = false
    return
  }
  
  const payload = {
    id: props.editId,
    name: form.name,
    membership_number: form.membership_number,
    membership_date: form.membership_date,
    nic: form.nic,
    address: form.address,
    city: form.city,
    contact_number: form.contact_number,
    status: form.status || 'Active',
    status_reason: form.status === 'Active' ? '' : form.status_reason.trim(),
    dependents: form.dependents.map(d => ({
      name: d.name,
      relationship: d.relationship,
      birth_year: typeof d.birth_year === 'object' ? d.birth_year.getFullYear() : parseInt(d.birth_year)
    }))
  }

  try {
    const response = await apiFetch('/api/submit_application.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    
    const data = await response.json()
    if (data.success) {
      emit('success', data.message)
      closeModal()
    } else {
      alertError('Save failed', data.message || 'Failed to submit')
    }
  } catch (err) {
    alertError('Connection error', 'An error occurred connecting to the server.')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.modal-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(4px); display: flex; justify-content: center; align-items: center; z-index: 1000; padding: 1rem; }
.modal-container { width: 100%; max-width: 1000px; max-height: 90vh; overflow-y: auto; padding: 0; display: flex; flex-direction: column; }
.modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: inherit; z-index: 10; }
.modal-header h2 { margin: 0; font-size: 1.5rem; color: var(--primary-color); }
.btn-close { background: none; border: none; cursor: pointer; color: var(--text-muted); display: inline-flex; align-items: center; justify-content: center; }
.btn-close:hover { color: var(--error); }
.btn-close:focus-visible { outline: none; box-shadow: var(--focus-ring); border-radius: 6px; }
.modal-body { padding: 2rem; }
.section-title { color: var(--primary-dark); font-size: 1.25rem; border-bottom: 2px solid var(--primary-light); display: inline-block; padding-bottom: 0.25rem; margin-bottom: 1.5rem; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
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
.field-help { margin-top: 0.35rem; color: var(--text-muted); font-size: 0.8rem; line-height: 1.25; }
@media (max-width: 600px) { .form-grid { grid-template-columns: 1fr; gap: 0; } }
.divider { border: 0; height: 1px; background: var(--surface-border); margin: 2rem 0; }
.dependents-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.empty-state { text-align: center; padding: 1.5rem; background: rgba(0,0,0,0.02); border-radius: var(--border-radius); border: 1px dashed #ccc; color: var(--text-muted); margin-bottom: 1.5rem; }
.dependent-card { background: rgba(255,255,255,0.5); border: 1px solid #e9ecef; border-radius: var(--border-radius); padding: 1.5rem; margin-bottom: 1.5rem; }
.dependent-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.dependent-header h4 { margin: 0; color: var(--text-main); }
.btn-remove { background: transparent; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer; }
.btn-remove:hover { color: var(--error); }
.form-actions { margin-top: 2rem; display: flex; justify-content: flex-end; }
.text-center { text-align: center; }
.py-4 { padding-top: 1.5rem; padding-bottom: 1.5rem; }
</style>

