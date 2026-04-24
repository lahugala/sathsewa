<template>
  <div class="login-wrapper">
    <div class="login-card glass-panel animate-fade-in">
      <div class="login-header">
        <img src="/logo.png" alt="Sathsewa Society logo" class="login-logo">
        <h1>Sathsewa Society</h1>
        <p class="text-muted">Welcome back! Please login to your account.</p>
      </div>

      <form @submit.prevent="handleLogin">
        <div class="form-group">
          <label class="form-label">Username</label>
          <input type="text" v-model="username" class="form-control" required placeholder="Enter your username">
        </div>

        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" v-model="password" class="form-control" required placeholder="Enter your password">
        </div>

        <div v-if="error" class="text-error mb-4 text-center">{{ error }}</div>

        <button type="submit" class="btn btn-primary" style="width: 100%" :disabled="loading">
          {{ loading ? 'Logging in...' : 'Login' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const username = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

const handleLogin = async () => {
  loading.value = true
  error.value = ''
  
  try {
    const response = await fetch('/api/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username: username.value, password: password.value })
    })
    
    const data = await response.json()
    if (data.success) {
      router.push('/dashboard')
    } else {
      error.value = data.message || 'Login failed'
    }
  } catch (err) {
    error.value = 'An error occurred connecting to the server.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 1;
  padding: 1rem;
}

.login-card {
  width: 100%;
  max-width: 450px;
  padding: 2.5rem;
}

.login-header {
  text-align: center;
  margin-bottom: 2.5rem;
}

.login-logo {
  width: min(150px, 48vw);
  height: auto;
  margin: 0 auto 1.25rem;
  display: block;
}

.login-header h1 {
  font-size: 2rem;
  margin-bottom: 0.5rem;
}
</style>
