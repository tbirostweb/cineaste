<script setup>
import { ref, onMounted, watch } from 'vue'
import api from '/src/api/api.js'
import { logger } from '../../../utils/logger'

const emit = defineEmits(['close', 'refresh'])
const props = defineProps({ user: Object })

const userId = ref(null)
const firstname = ref('')
const lastname = ref('')
const email = ref('')
const dob = ref('')
const password = ref('')
const roles = ref('ROLE_USER')
const loading = ref(false)
const errors = ref(null)

const initFromUser = (u) => {
  if (!u) {
    userId.value = null
    firstname.value = ''
    lastname.value = ''
    email.value = ''
    dob.value = ''
    password.value = ''
    roles.value = 'ROLE_USER'
    return
  }

  userId.value = u.id || null
  firstname.value = u.firstname || ''
  lastname.value = u.lastname || ''
  email.value = u.email || ''

  if (u.dob) {
    if (typeof u.dob === 'string') {
      dob.value = u.dob.split(' ')[0]
    } else if (u.dob.date) {
      dob.value = u.dob.date.split(' ')[0]
    }
  } else {
    dob.value = ''
  }

  password.value = ''
  roles.value = (u.roles && u.roles[0]) || 'ROLE_USER'
}

onMounted(() => {
  initFromUser(props.user)
})

watch(() => props.user, (u) => initFromUser(u))

const saveUser = async () => {
  loading.value = true
  errors.value = null

  try {
    const userData = {
      firstname: firstname.value.trim(),
      lastname: lastname.value.trim(),
      email: email.value.trim(),
    }

    if (dob.value) userData.dob = dob.value

    if (userId.value) {
      // Correction ici : Utilisation de PATCH au lieu de PUT et du bon Content-Type
      await api.patch(`/users/${userId.value}`, userData, {
        headers: { 'Content-Type': 'application/merge-patch+json' }
      })
    } else {
      if (!password.value || !password.value.trim()) {
        errors.value = 'Le mot de passe est requis pour créer un utilisateur'
        loading.value = false
        return
      }
      userData.plainPassword = password.value

      // Pour la création, POST avec application/ld+json est standard
      await api.post('/users', userData, {
        headers: { 'Content-Type': 'application/ld+json' }
      })
    }

    await new Promise(resolve => setTimeout(resolve, 500))
    emit('refresh')
    await new Promise(resolve => setTimeout(resolve, 200))
    emit('close')
  } catch (err) {
    logger.error('Erreur saveUser', err)
    if (err.response) {
      errors.value =
          err.response.data?.['hydra:description'] ||
          err.response.data?.message ||
          err.response.data?.error ||
          `Erreur ${err.response.status}: ${err.response.statusText}`
    } else if (err.request) {
      errors.value = "Impossible de contacter le serveur"
    } else {
      errors.value = err.message
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 bg-[var(--color-ink)]/70 backdrop-blur-sm flex justify-center items-center z-50 p-4" @click.self="emit('close')">
    <div class="form-container bg-[var(--color-paper-raised)] border border-[var(--color-rule)] rounded-lg w-full max-w-lg shadow-2xl">
      <header class="p-6 flex items-center justify-between border-b border-[var(--color-rule)]">
        <h2 class="text-2xl font-bold text-[var(--color-ink)]">
          {{ userId ? 'Modifier l\'utilisateur' : 'Ajouter un utilisateur' }}
        </h2>
        <button @click="emit('close')" class="p-2 rounded-full text-[var(--color-ink-faint)] hover:bg-[var(--color-paper-sunk)]" aria-label="Fermer">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </header>

      <main class="p-6 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label for="user-firstname" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Prénom</label>
            <input id="user-firstname" v-model="firstname" type="text" placeholder="Prénom" class="w-full px-4 py-2 bg-[var(--color-paper)] border border-[var(--color-rule)] rounded-md text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-night)]" />
          </div>
          <div>
            <label for="user-lastname" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Nom</label>
            <input id="user-lastname" v-model="lastname" type="text" placeholder="Nom" class="w-full px-4 py-2 bg-[var(--color-paper)] border border-[var(--color-rule)] rounded-md text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-night)]" />
          </div>
        </div>
        <div>
          <label for="user-email" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Email</label>
          <input id="user-email" v-model="email" type="email" placeholder="email@example.com" class="w-full px-4 py-2 bg-[var(--color-paper)] border border-[var(--color-rule)] rounded-md text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-night)]" />
        </div>
        <div>
          <label for="user-dob" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Date de naissance</label>
          <input id="user-dob" v-model="dob" type="date" class="w-full px-4 py-2 bg-[var(--color-paper)] border border-[var(--color-rule)] rounded-md text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-night)]" />
        </div>
        <div v-if="!userId">
          <label for="user-password" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Mot de passe</label>
          <input id="user-password" v-model="password" type="password" placeholder="Mot de passe" class="w-full px-4 py-2 bg-[var(--color-paper)] border border-[var(--color-rule)] rounded-md text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-night)]" />
        </div>

        <div v-if="errors" class="bg-[var(--color-danger)]/10 border border-[var(--color-danger)]/30 text-[var(--color-danger)] p-3 rounded-md text-sm">
          {{ errors }}
        </div>
      </main>

      <footer class="p-6 flex justify-end gap-4 border-t border-[var(--color-rule)]">
        <button @click="emit('close')" class="btn btn-quiet">
          Annuler
        </button>
        <button @click="saveUser" :disabled="loading" class="btn btn-primary">
          {{ loading ? 'Sauvegarde...' : 'Sauvegarder' }}
        </button>
      </footer>
    </div>
  </div>
</template>
