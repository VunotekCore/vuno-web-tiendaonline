<script setup lang="ts">
import { ref, onMounted } from 'vue'

const step = ref<'password' | 'totp'>('password')
const email = ref('')
const password = ref('')
const totpCode = ref('')
const errorMsg = ref('')
const loading = ref(false)
const totpLoading = ref(false)

onMounted(async () => {
  try {
    const res = await fetch('/api/admin/verify.php')
    const data = await res.json()
    if (data.totpPending) {
      email.value = data.email || ''
      step.value = 'totp'
    }
  } catch {}
})

async function handlePasswordLogin() {
  errorMsg.value = ''
  loading.value = true
  try {
    const res = await fetch('/api/admin/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: email.value, password: password.value }),
    })
    const data = await res.json()
    if (!res.ok) throw new Error(data.error || 'Login failed')

    if (data.totpRequired) {
      step.value = 'totp'
      totpCode.value = ''
    } else {
      window.location.href = '/admin/productos'
    }
  } catch (err: any) {
    errorMsg.value = err.message || 'Invalid credentials'
  } finally {
    loading.value = false
  }
}

async function handleTotpVerify() {
  errorMsg.value = ''
  totpLoading.value = true
  try {
    const res = await fetch('/api/admin/2fa/verify.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code: totpCode.value.trim() }),
    })
    const data = await res.json()
    if (!res.ok) throw new Error(data.error || 'Verification failed')
    window.location.href = '/admin/productos'
  } catch (err: any) {
    errorMsg.value = err.message || 'Invalid code'
    totpCode.value = ''
  } finally {
    totpLoading.value = false
  }
}

async function backToLogin() {
  await fetch('/api/admin/logout.php')
  step.value = 'password'
  password.value = ''
}
</script>

<template>
  <div class="w-full max-w-sm">
    <div class="text-center mb-12">
      <h1 class="font-headline text-headline-md text-[#dae2fd] mb-2">
        <span>Ram;Lop</span>
      </h1>
      <p class="font-label-caps text-label-caps text-[#94a3b8]">ADMIN PANEL</p>
    </div>

    <!-- Password form -->
    <form v-if="step === 'password'" class="flex flex-col gap-6" @submit.prevent="handlePasswordLogin">
      <div
        v-if="errorMsg"
        class="font-body text-body-md text-[#DC2626] bg-[#DC2626]/10 p-3 text-center"
        role="alert"
      >{{ errorMsg }}</div>

      <div>
        <label for="login-email" class="font-label-caps text-label-caps text-[#94a3b8] block mb-2">EMAIL</label>
        <input
          id="login-email"
          v-model="email"
          type="email"
          required
          class="w-full bg-[#1e293b] border border-[#1e293b] px-4 py-3 text-[#dae2fd] focus:border-[#42b883] focus:outline-none focus:ring-1 focus:ring-[#42b883] transition-colors rounded-sm"
          :aria-invalid="errorMsg ? 'true' : undefined"
        />
      </div>
      <div>
        <label for="login-password" class="font-label-caps text-label-caps text-[#94a3b8] block mb-2">PASSWORD</label>
        <input
          id="login-password"
          v-model="password"
          type="password"
          required
          class="w-full bg-[#1e293b] border border-[#1e293b] px-4 py-3 text-[#dae2fd] focus:border-[#42b883] focus:outline-none focus:ring-1 focus:ring-[#42b883] transition-colors rounded-sm"
          :aria-invalid="errorMsg ? 'true' : undefined"
        />
      </div>
      <button
        type="submit"
        class="w-full font-label-caps text-label-caps bg-[#42b883] text-white rounded-sm px-6 h-11 hover:bg-[#42b883]/90 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="loading"
      >
        {{ loading ? 'SIGNING IN...' : 'SIGN IN' }}
      </button>
    </form>

    <!-- TOTP form -->
    <form v-else class="flex flex-col gap-6" @submit.prevent="handleTotpVerify">
      <div
        v-if="errorMsg"
        class="font-body text-body-md text-[#DC2626] bg-[#DC2626]/10 p-3 text-center"
        role="alert"
      >{{ errorMsg }}</div>

      <p class="font-body text-body-md text-[#94a3b8] text-center">
        Enter the code from your authenticator app or a backup code.
      </p>
      <div>
        <label for="totp-code" class="font-label-caps text-label-caps text-[#94a3b8] block mb-2">VERIFICATION CODE</label>
        <input
          id="totp-code"
          v-model="totpCode"
          type="text"
          inputmode="numeric"
          autocomplete="one-time-code"
          required
          class="w-full bg-[#1e293b] border border-[#1e293b] px-4 py-3 text-[#dae2fd] focus:border-[#42b883] focus:outline-none focus:ring-1 focus:ring-[#42b883] transition-colors rounded-sm text-center text-2xl tracking-[0.25em]"
          placeholder="000000"
          maxlength="8"
          :aria-invalid="errorMsg ? 'true' : undefined"
        />
      </div>
      <button
        type="submit"
        class="w-full font-label-caps text-label-caps bg-[#42b883] text-white rounded-sm px-6 h-11 hover:bg-[#42b883]/90 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="totpLoading"
      >
        {{ totpLoading ? 'VERIFYING...' : 'VERIFY' }}
      </button>
      <button
        type="button"
        class="font-body text-body-md text-[#42b883] hover:text-[#dae2fd] transition-colors underline underline-offset-2 text-center"
        @click="backToLogin"
      >
        Back to login
      </button>
    </form>
  </div>
</template>
