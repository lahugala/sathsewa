<template>
  <DashboardLayout>
    <div class="members-container animate-fade-in">
      <div class="header-bar mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>Society Members</h2>
        <button class="btn btn-primary" @click="openAddModal">+ Add New Member</button>
      </div>

      <div v-if="successMessage" class="alert alert-success mb-4">
        {{ successMessage }}
      </div>
      <div v-if="errorMessage" class="alert alert-error mb-4">
        {{ errorMessage }}
      </div>

      <div class="glass-panel" style="overflow: hidden;">
        <div class="search-bar">
          <input type="search" v-model="searchQuery" class="form-control" placeholder="Search by name, NIC or city...">
        </div>
        <div class="table-responsive" style="padding: 0 1rem 1rem 1rem;">
          <table class="data-table" v-if="filteredMembers.length > 0">
            <thead>
              <tr>
                <th>Name</th>
                <th>NIC</th>
                <th>City</th>
                <th>Contact</th>
                <th>Date Added</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="member in filteredMembers" :key="member.id">
                <td>{{ member.name }}</td>
                <td>{{ member.nic }}</td>
                <td>{{ member.city }}</td>
                <td>{{ member.contact_number }}</td>
                <td>{{ member.date_added }}</td>
                <td>
                  <button class="btn btn-sm btn-outline" @click="openEditModal(member.id)" style="margin-right: 0.5rem;">Edit</button>
                  <button class="btn btn-sm btn-danger" @click="deleteMember(member.id)">Delete</button>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-else-if="loading" class="text-center py-4">Loading members...</p>
          <p v-else class="text-muted text-center py-4">No members found.</p>
        </div>
      </div>
    </div>

    <!-- Application Modal -->
    <ApplicationModal 
      :show="isModalOpen" 
      :editId="editingId"
      @close="closeModal" 
      @success="handleSuccess"
    />
  </DashboardLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import DashboardLayout from '../components/DashboardLayout.vue'
import ApplicationModal from '../components/ApplicationModal.vue'

const members = ref([])
const loading = ref(true)
const isModalOpen = ref(false)
const editingId = ref(null)
const successMessage = ref('')
const errorMessage = ref('')
const searchQuery = ref('')

const fetchMembers = async () => {
  loading.value = true
  try {
    const res = await fetch('/api/get_members.php')
    const data = await res.json()
    if (data.success) {
      members.value = data.members
    }
  } catch (error) {
    console.error('Error fetching members:', error)
  } finally {
    loading.value = false
  }
}

const filteredMembers = computed(() => {
  if (!searchQuery.value) return members.value
  const query = searchQuery.value.toLowerCase()
  return members.value.filter(m => 
    m.name.toLowerCase().includes(query) ||
    m.nic.toLowerCase().includes(query) ||
    m.city.toLowerCase().includes(query)
  )
})

const openAddModal = () => {
  editingId.value = null
  isModalOpen.value = true
}

const openEditModal = (id) => {
  editingId.value = id
  isModalOpen.value = true
}

const closeModal = () => {
  isModalOpen.value = false
  editingId.value = null
}

const handleSuccess = (message) => {
  successMessage.value = message
  fetchMembers() // Refresh list
  setTimeout(() => {
    successMessage.value = ''
  }, 5000)
}

const deleteMember = async (id) => {
  if (confirm('Are you sure you want to delete this member?')) {
    errorMessage.value = ''
    try {
      const res = await fetch('/api/delete_member.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
      })
      const data = await res.json()
      if (data.success) {
        successMessage.value = 'Member deleted successfully'
        fetchMembers()
        setTimeout(() => successMessage.value = '', 5000)
      } else {
        errorMessage.value = data.message || 'Failed to delete member'
      }
    } catch (e) {
      errorMessage.value = 'Network error deleting member'
    }
  }
}

onMounted(() => {
  fetchMembers()
})
</script>

<style scoped>
.mb-4 { margin-bottom: 1.5rem; }
.py-4 { padding-top: 1.5rem; padding-bottom: 1.5rem; }
.text-center { text-align: center; }

.search-bar {
  padding: 1rem;
  border-bottom: 1px solid rgba(0,0,0,0.05);
}

.table-responsive {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th, .data-table td {
  padding: 1rem;
  text-align: left;
  border-bottom: 1px solid rgba(0,0,0,0.05);
}

.data-table th {
  font-weight: 600;
  color: var(--primary-color);
  background: rgba(128, 0, 0, 0.03);
}

.data-table tbody tr:hover {
  background: rgba(0,0,0,0.02);
}

.alert {
  padding: 1rem;
  border-radius: 8px;
  font-weight: 500;
}

.alert-success {
  background-color: rgba(25, 135, 84, 0.1);
  color: var(--success);
  border: 1px solid rgba(25, 135, 84, 0.2);
}

.alert-error {
  background-color: rgba(220, 53, 69, 0.1);
  color: var(--error);
  border: 1px solid rgba(220, 53, 69, 0.2);
}

.btn-sm {
  padding: 0.4rem 0.8rem;
  font-size: 0.875rem;
}
</style>
