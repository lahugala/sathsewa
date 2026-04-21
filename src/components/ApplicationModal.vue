<template>
  <Teleport to="body">
    <div class="modal-overlay" v-if="show">
      <div class="modal-container glass-panel animate-fade-in">
        <div class="modal-header">
          <h2>{{ editId ? 'Edit Member' : 'Add New Member' }}</h2>
          <button class="btn-close" @click="closeModal">✕</button>
        </div>
        
        <div class="modal-body">
          <div v-if="fetching" class="text-center py-4">Loading data...</div>
          <form v-else @submit.prevent="submitForm">
            <div v-if="errorMsg" class="alert alert-error">{{ errorMsg }}</div>

            <h3 class="section-title">Personal Details</h3>
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" v-model="form.name" class="form-control" required placeholder="John Doe">
              </div>
              
              <div class="form-group">
                <label class="form-label">NIC</label>
                <input type="text" v-model="form.nic" class="form-control" required placeholder="123456789V">
              </div>
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
                <input type="tel" v-model="form.contact_number" class="form-control" required placeholder="0712345678">
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
                <button type="button" @click="removeDependent(index)" class="btn-remove">✕</button>
              </div>
              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label">Name</label>
                  <input type="text" v-model="dep.name" class="form-control" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Relationship</label>
                  <input type="text" v-model="dep.relationship" class="form-control" required>
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

const props = defineProps({
  show: Boolean,
  editId: { type: Number, default: null }
})

const emit = defineEmits(['close', 'success'])

const loading = ref(false)
const fetching = ref(false)
const errorMsg = ref('')

const form = reactive({
  name: '',
  nic: '',
  address: '',
  city: '',
  contact_number: '',
  dependents: []
})

const resetForm = () => {
  form.name = ''
  form.nic = ''
  form.address = ''
  form.city = ''
  form.contact_number = ''
  form.dependents = []
  errorMsg.value = ''
}

watch(() => props.show, async (newVal) => {
  if (newVal) {
    if (props.editId) {
      fetching.value = true
      try {
        const res = await fetch(`/api/get_member.php?id=${props.editId}`)
        const data = await res.json()
        if (data.success) {
          Object.assign(form, data.member)
          // Ensure birth_year is an integer for the datepicker
          form.dependents = form.dependents.map(d => ({
            ...d,
            birth_year: parseInt(d.birth_year)
          }))
        } else {
          errorMsg.value = 'Failed to load member data'
        }
      } catch (err) {
        errorMsg.value = 'Error connecting to server'
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

const closeModal = () => {
  resetForm()
  emit('close')
}

const submitForm = async () => {
  loading.value = true
  errorMsg.value = ''
  
  const payload = {
    id: props.editId,
    name: form.name,
    nic: form.nic,
    address: form.address,
    city: form.city,
    contact_number: form.contact_number,
    dependents: form.dependents.map(d => ({
      name: d.name,
      relationship: d.relationship,
      birth_year: typeof d.birth_year === 'object' ? d.birth_year.getFullYear() : parseInt(d.birth_year)
    }))
  }

  try {
    const response = await fetch('/api/submit_application.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    
    const data = await response.json()
    if (data.success) {
      emit('success', data.message)
      closeModal()
    } else {
      errorMsg.value = data.message || 'Failed to submit'
    }
  } catch (err) {
    errorMsg.value = 'An error occurred connecting to the server.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.modal-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(4px); display: flex; justify-content: center; align-items: center; z-index: 1000; padding: 1rem; }
.modal-container { width: 100%; max-width: 800px; max-height: 90vh; overflow-y: auto; padding: 0; display: flex; flex-direction: column; }
.modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: inherit; z-index: 10; }
.modal-header h2 { margin: 0; font-size: 1.5rem; color: var(--primary-color); }
.btn-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
.btn-close:hover { color: var(--error); }
.modal-body { padding: 2rem; }
.section-title { color: var(--primary-dark); font-size: 1.25rem; border-bottom: 2px solid var(--primary-light); display: inline-block; padding-bottom: 0.25rem; margin-bottom: 1.5rem; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
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
.alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 500; }
.alert-error { background: rgba(220, 53, 69, 0.1); color: var(--error); border: 1px solid rgba(220, 53, 69, 0.2); }
.text-center { text-align: center; }
.py-4 { padding-top: 1.5rem; padding-bottom: 1.5rem; }
</style>
