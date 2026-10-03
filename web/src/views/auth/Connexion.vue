<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import api from '/src/api/api.js'

const router = useRouter()
const route = useRoute()
const emit = defineEmits(['login-success'])

const email = ref('')
const password = ref('')
const authCode = ref('')
const errorMessage = ref('')
const loading = ref(false)
const twoFactorRequired = ref(false)
const twoFactorToken = ref(null)

const handleLoginSuccess = async (token) => {
  localStorage.setItem('token', token)
  localStorage.setItem('loggedIn', 'true')
  api.defaults.headers.common['Authorization'] = `Bearer ${token}`

  const userRes = await api.get(import.meta.env.VITE_API_URL_USER)
  const user = userRes.data

  const baseUrl = import.meta.env.VITE_API_BASE_URL
  const photo = user.photo ? `${baseUrl}${user.photo}` : '/placeholder-avatar.svg'

  localStorage.setItem('userPhoto', photo)
  if (user.roles && user.roles.length > 0) {
    const isAdmin = user.roles.includes('ROLE_ADMIN')
    localStorage.setItem('role', isAdmin ? 'admin' : 'user')
  } else {
    localStorage.setItem('role', 'user')
  }

  emit('login-success', photo)

  // Le garde de route ajoute ?redirect=… quand une page protégée a été
  // demandée avant connexion : on y retourne au lieu de renvoyer à l'accueil.
  const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/'
  // Un chemin interne uniquement : une valeur commençant par // ou http://
  // permettrait de rediriger la victime vers un site tiers après connexion.
  const safeRedirect = redirect.startsWith('/') && !redirect.startsWith('//') ? redirect : '/'
  await router.push(safeRedirect)
}

const login = async (e) => {
  e.preventDefault()
  errorMessage.value = ''
  loading.value = true

  try {
    // Utilisation de l'URL d'authentification définie dans les variables d'environnement
    const response = await axios.post(import.meta.env.VITE_API_URL_AUTH, {
      email: email.value,
      password: password.value
    }, {
      withCredentials: true
    })

    // Si le 2FA est activé, on reçoit un token temporaire
    if (response.data['2fa_required']) {
      twoFactorToken.value = response.data.token
      twoFactorRequired.value = true
    } else {
      // Sinon, on est connecté directement
      await handleLoginSuccess(response.data.token)
    }
  } catch (err) {
    errorMessage.value = "Email ou mot de passe incorrect."
  } finally {
    loading.value = false
  }
}

const verifyTwoFactor = async (e) => {
  e.preventDefault()
  errorMessage.value = ''
  loading.value = true

  try {
    // On utilise le token temporaire pour s'authentifier sur la route de vérification
    const response = await api.post('/2fa/login/verify', {
      code: authCode.value
    }, {
      headers: { 'Authorization': `Bearer ${twoFactorToken.value}` }
    })

    if (response.data.token) {
      await handleLoginSuccess(response.data.token)
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.error || "Code invalide ou expiré."
  } finally {
    loading.value = false
  }
}

onMounted(() => {
})
</script>

<template>
  <div class="mx-auto flex min-h-[70vh] max-w-[34rem] flex-col justify-center px-5 py-16 md:px-10">

    <div class="w-full">
      <div class="text-center">
        <h1 class="text-6xl font-bold text-[var(--color-night)]">Générique</h1>
        <h2 class="mt-2 text-2xl font-bold text-[var(--color-ink)]">
          {{ twoFactorRequired ? 'Vérification requise' : 'Connectez-vous' }}
        </h2>
        <p v-if="!twoFactorRequired" class="mt-2 text-sm text-[var(--color-ink-soft)]">
          Pas encore de compte ?
          <router-link to="/inscription" class="font-medium text-[var(--color-night)] hover:text-[var(--color-night-soft)]">
            Inscrivez-vous
          </router-link>
        </p>
      </div>

      <!-- Formulaire de Login -->
      <form v-if="!twoFactorRequired" class="mt-8 space-y-6 bg-[var(--color-paper-raised)] p-8 rounded-lg shadow-2xl border border-[var(--color-rule)]" @submit="login">
        <div class="space-y-4">
          <div>
            <label for="email-address" class="text-[var(--color-ink-soft)] text-sm tracking-wider uppercase">Email</label>
            <input id="email-address" v-model="email" name="email" type="email" autocomplete="email" required
                   class="mt-2 appearance-none rounded-md relative block w-full px-4 py-3 border border-[var(--color-rule)] bg-[var(--color-paper)] placeholder-gray-500 text-[var(--color-ink)] focus:outline-none focus:ring-[var(--color-night)] focus:border-[var(--color-ink)] sm:text-sm transition-all"
                   placeholder="votre@email.com">
          </div>
          <div>
            <label for="password" class="text-[var(--color-ink-soft)] text-sm tracking-wider uppercase">Mot de passe</label>
            <input id="password" v-model="password" name="password" type="password" autocomplete="current-password" required
                   class="mt-2 appearance-none rounded-md relative block w-full px-4 py-3 border border-[var(--color-rule)] bg-[var(--color-paper)] placeholder-gray-500 text-[var(--color-ink)] focus:outline-none focus:ring-[var(--color-night)] focus:border-[var(--color-ink)] sm:text-sm transition-all"
                   placeholder="********">
          </div>
        </div>

        <div v-if="errorMessage" class="error-message text-[var(--color-danger)] text-sm text-center bg-[var(--color-danger)]/10 p-3 rounded-md border border-[var(--color-danger)]/30">
          {{ errorMessage }}
        </div>

        <div>
          <button type="submit" :disabled="loading"
                  class="btn btn-primary w-full">
            <span v-if="loading" class="absolute left-0 inset-y-0 flex items-center pl-3">
              <svg class="h-5 w-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </span>
            {{ loading ? 'Connexion...' : 'Se connecter' }}
          </button>
        </div>
      </form>

      <!-- Formulaire 2FA -->
      <form v-else class="mt-8 space-y-6 bg-[var(--color-paper-raised)] p-8 rounded-lg shadow-2xl border border-[var(--color-rule)]" @submit="verifyTwoFactor">
        <p class="text-center text-[var(--color-ink-soft)]">Ouvrez votre application d'authentification et entrez le code pour vous connecter.</p>
        <div class="space-y-4">
          <div>
            <label for="auth-code" class="text-[var(--color-ink-soft)] text-sm tracking-wider uppercase">Code de vérification</label>
            <input id="auth-code" v-model="authCode" name="code" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" required
                   class="mt-2 appearance-none rounded-md relative block w-full px-4 py-3 border border-[var(--color-rule)] bg-[var(--color-paper)] placeholder-gray-500 text-[var(--color-ink)] focus:outline-none focus:ring-[var(--color-night)] focus:border-[var(--color-ink)] sm:text-sm transition-all text-center text-2xl tracking-[0.2em] font-mono">
          </div>
        </div>

        <div v-if="errorMessage" class="error-message text-[var(--color-danger)] text-sm text-center bg-[var(--color-danger)]/10 p-3 rounded-md border border-[var(--color-danger)]/30">
          {{ errorMessage }}
        </div>

        <div>
          <button type="submit" :disabled="loading"
                  class="btn btn-primary w-full">
            <span v-if="loading" class="absolute left-0 inset-y-0 flex items-center pl-3">
              <svg class="h-5 w-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </span>
            {{ loading ? 'Vérification...' : 'Vérifier' }}
          </button>
        </div>
      </form>

    </div>
  </div>
</template>
