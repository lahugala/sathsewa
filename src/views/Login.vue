<template>
  <div class="login-wrapper">
    <main class="login-shell animate-fade-in">
      <section class="login-identity" aria-label="Sathsewa Welfare Society">
        <img src="/logo.png" alt="Sathsewa Welfare Society logo" class="identity-logo">
        <div class="identity-copy">
          <span class="identity-eyebrow">Secure portal</span>
          <h1>Sathsewa Welfare Society</h1>
          <p>Lahugala</p>
        </div>
      </section>

      <div class="login-card">
        <div class="login-header">
          <img src="/logo.png" alt="Sathsewa Welfare Society logo" class="login-logo">
          <span class="login-kicker">Welcome back</span>
          <h2>Sign in to continue</h2>
          <p>Enter your account details below.</p>
        </div>

        <form @submit.prevent="handleLogin" class="login-form">
          <div class="form-group">
            <label class="form-label" for="username">Username</label>
            <div class="input-shell">
              <UserRound :size="18" aria-hidden="true" />
              <input
                id="username"
                type="text"
                v-model="username"
                class="login-input"
                required
                autocomplete="username"
                placeholder="Enter your username"
              >
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <div class="input-shell">
              <LockKeyhole :size="18" aria-hidden="true" />
              <input
                id="password"
                :type="showPassword ? 'text' : 'password'"
                v-model="password"
                class="login-input"
                required
                autocomplete="current-password"
                placeholder="Enter your password"
              >
              <button
                type="button"
                class="password-toggle"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                @click="showPassword = !showPassword"
              >
                <EyeOff v-if="showPassword" :size="18" aria-hidden="true" />
                <Eye v-else :size="18" aria-hidden="true" />
              </button>
            </div>
          </div>

          <button type="submit" class="btn login-submit" :disabled="loading">
            <LoaderCircle v-if="loading" :size="18" class="spin" aria-hidden="true" />
            <LogIn v-else :size="18" aria-hidden="true" />
            {{ loading ? 'Logging in...' : 'Login' }}
          </button>
        </form>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { Eye, EyeOff, LoaderCircle, LockKeyhole, LogIn, UserRound } from 'lucide-vue-next'
import { alertError } from '../utils/alerts'

const router = useRouter()
const username = ref('')
const password = ref('')
const loading = ref(false)
const showPassword = ref(false)

const handleLogin = async () => {
  loading.value = true
  
  try {
    const response = await fetch('/api/login.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username: username.value, password: password.value })
    })
    
    const data = await response.json()
    if (data.success) {
      router.push('/dashboard')
    } else {
      alertError('Login failed', data.message || 'Please check your username and password.')
    }
  } catch (err) {
    alertError('Connection error', 'An error occurred connecting to the server.')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-wrapper {
  flex: 1;
  display: grid;
  place-items: center;
  min-height: 100vh;
  padding: clamp(1rem, 4vw, 3rem);
  background:
    linear-gradient(125deg, rgba(7, 31, 23, 0.96) 0%, rgba(15, 23, 42, 0.96) 52%, rgba(20, 83, 45, 0.94) 100%),
    url('/logo.png') center / min(72vw, 780px) no-repeat;
  position: relative;
  overflow: hidden;
}

.login-wrapper::before,
.login-wrapper::after {
  content: "";
  position: absolute;
  inset: auto;
  pointer-events: none;
}

.login-wrapper::before {
  width: 62vw;
  height: 62vw;
  max-width: 760px;
  max-height: 760px;
  right: -24vw;
  top: -34vw;
  border: 1px solid rgba(250, 204, 21, 0.32);
  transform: rotate(28deg);
}

.login-wrapper::after {
  width: 52vw;
  height: 18vw;
  left: -12vw;
  bottom: 8vh;
  border-top: 1px solid rgba(255, 255, 255, 0.12);
  border-bottom: 1px solid rgba(250, 204, 21, 0.18);
  transform: rotate(-12deg);
}

.login-shell {
  width: min(100%, 1160px);
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(360px, 430px);
  align-items: center;
  gap: clamp(2rem, 5vw, 5rem);
  position: relative;
  z-index: 1;
}

.login-identity {
  color: #ffffff;
  display: grid;
  gap: 1.75rem;
  justify-items: start;
}

.identity-logo {
  width: min(300px, 36vw);
  height: auto;
  filter: drop-shadow(0 22px 36px rgba(0, 0, 0, 0.35));
}

.identity-copy {
  max-width: 560px;
}

.identity-eyebrow,
.login-kicker {
  display: inline-flex;
  align-items: center;
  width: fit-content;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0;
}

.identity-eyebrow {
  padding: 0.35rem 0.75rem;
  color: #facc15;
  background: rgba(250, 204, 21, 0.1);
  border: 1px solid rgba(250, 204, 21, 0.24);
  margin-bottom: 1rem;
}

.identity-copy h1 {
  margin: 0;
  color: #ffffff;
  font-size: 3.8rem;
  font-weight: 700;
  line-height: 0.98;
  text-wrap: balance;
}

.identity-copy p {
  margin-top: 1rem;
  color: rgba(255, 255, 255, 0.72);
  font-size: 1.2rem;
  font-weight: 500;
}

.login-card {
  width: 100%;
  padding: clamp(1.5rem, 4vw, 2.5rem);
  background: rgba(255, 255, 255, 0.94);
  border: 1px solid rgba(255, 255, 255, 0.58);
  border-radius: 8px;
  box-shadow: 0 24px 70px rgba(0, 0, 0, 0.28);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
}

.login-form {
  display: grid;
  gap: 1.15rem;
}

.login-form .form-group {
  margin-bottom: 0;
}

.login-form .form-label {
  color: #1f2937;
  font-weight: 650;
}

.input-shell {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 0.65rem;
  min-height: 48px;
  padding: 0 0.9rem;
  border: 1px solid #d7dde5;
  border-radius: 8px;
  background: #ffffff;
  color: #64748b;
  transition: var(--transition);
}

.input-shell:focus-within {
  border-color: #16a34a;
  box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.14);
  color: #14532d;
}

.login-input {
  width: 100%;
  min-width: 0;
  border: 0;
  outline: 0;
  background: transparent;
  color: #111827;
  font: inherit;
  padding: 0.75rem 0;
}

.login-input::placeholder {
  color: #9aa4b2;
}

.password-toggle {
  width: 34px;
  height: 34px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #64748b;
  cursor: pointer;
  transition: var(--transition);
}

.password-toggle:hover,
.password-toggle:focus-visible {
  background: rgba(20, 83, 45, 0.08);
  color: #14532d;
  outline: none;
}

.login-submit {
  width: 100%;
  margin-top: 0.25rem;
  min-height: 48px;
  background: linear-gradient(135deg, #14532d, #0f766e);
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 0 10px 24px rgba(20, 83, 45, 0.22);
}

.login-submit:hover {
  transform: translateY(-1px);
  box-shadow: 0 14px 28px rgba(20, 83, 45, 0.28);
}

.login-submit:disabled {
  opacity: 0.7;
  cursor: not-allowed;
  transform: none;
}

.spin {
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.login-header {
  text-align: left;
  margin-bottom: 2rem;
}

.login-logo {
  width: 96px;
  height: auto;
  margin: 0 0 1.25rem;
  display: none;
}

.login-kicker {
  padding: 0.3rem 0.65rem;
  color: #14532d;
  background: rgba(20, 83, 45, 0.08);
  margin-bottom: 0.85rem;
}

.login-header h2 {
  margin: 0;
  color: #0f172a;
  font-size: 2.15rem;
}

.login-header p {
  margin-top: 0.65rem;
  color: #64748b;
}

@media (max-width: 860px) {
  .login-wrapper {
    place-items: start center;
    padding: 1.25rem;
    background-size: 92vw;
  }

  .login-shell {
    grid-template-columns: 1fr;
    gap: 1.5rem;
  }

  .login-identity {
    display: none;
  }

  .login-card {
    margin-top: clamp(1rem, 8vh, 3rem);
  }

  .login-header {
    text-align: center;
  }

  .login-logo {
    display: block;
    margin-left: auto;
    margin-right: auto;
  }

  .login-kicker {
    margin-left: auto;
    margin-right: auto;
  }

  .login-header h2 {
    font-size: 1.85rem;
  }
}

@media (max-width: 1020px) and (min-width: 861px) {
  .identity-copy h1 {
    font-size: 3rem;
  }
}

@media (max-width: 420px) {
  .login-wrapper {
    padding: 0.75rem;
  }

  .login-card {
    padding: 1.25rem;
  }

  .login-header h2 {
    font-size: 1.7rem;
  }
}
</style>
