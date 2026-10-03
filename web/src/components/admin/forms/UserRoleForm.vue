<script setup>
import { ref, watch, onMounted } from "vue"
import api from "/src/api/api.js"

const props = defineProps({
  user: { type: Object, required: true }
})
const emit = defineEmits(['updated', 'cancel'])

const selectedRole = ref(props.user.roles?.[0] || 'ROLE_USER')
const loading = ref(false)
const errorMessage = ref("")
const successMessage = ref("")

const availableRoles = [
  { value: 'ROLE_USER', label: 'Utilisateur' },
  { value: 'ROLE_ADMIN', label: 'Administrateur' }
]

watch(() => props.user, (newUser) => {
  selectedRole.value = newUser.roles?.[0] || 'ROLE_USER'
  errorMessage.value = ""
  successMessage.value = ""
}, { immediate: true })

onMounted(() => {
})

async function updateRole() {
  const currentRole = props.user.roles?.[0] || 'ROLE_USER'
  if (selectedRole.value === currentRole) {
    errorMessage.value = "Le rôle n'a pas changé"
    return
  }
  loading.value = true
  errorMessage.value = ""
  successMessage.value = ""
  try {
    await api.put(`users/${props.user.id}/role`, { role: selectedRole.value })
    successMessage.value = "Rôle mis à jour avec succès"
    setTimeout(() => emit('updated'), 1000)
  } catch (err) {
    if (err.response) {
      errorMessage.value = `Erreur ${err.response.status} : ${err.response.data.message || err.response.data['hydra:description'] || "Non spécifié"}`
    } else if (err.request) {
      errorMessage.value = "Impossible de mettre à jour le rôle (pas de réponse du serveur)"
    } else {
      errorMessage.value = err.message
    }
  } finally { loading.value = false }
}

function cancel() { emit('cancel') }
</script>

<template>
  <div class="fixed inset-0 bg-[var(--color-ink)]/70 backdrop-blur-sm flex justify-center items-center z-50 p-4" @click.self="cancel">
    <div class="form-container bg-[var(--color-paper-raised)] border border-[var(--color-rule)] rounded-lg w-full max-w-md shadow-2xl">
      <header class="p-6 border-b border-[var(--color-rule)]">
        <h2 class="text-2xl font-bold text-[var(--color-ink)]">
          Modifier le rôle de {{ user.firstname }} {{ user.lastname }}
        </h2>
      </header>

      <main class="p-6 space-y-4">
        <div class="p-4 bg-[var(--color-paper)] border border-[var(--color-rule)] rounded-lg">
          <p class="text-sm text-[var(--color-ink-faint)]">Email: <span class="font-medium text-[var(--color-ink)]">{{ user.email }}</span></p>
          <p class="text-sm text-[var(--color-ink-faint)] mt-2">
            Rôle actuel: <span class="font-medium text-[var(--color-ink)]">{{ user.roles?.[0] === 'ROLE_ADMIN' ? 'Administrateur' : 'Utilisateur' }}</span>
          </p>
        </div>

        <div>
          <label for="role-select" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Nouveau rôle</label>
          <select id="role-select" v-model="selectedRole" :disabled="loading" class="w-full px-4 py-2 bg-[var(--color-paper)] border border-[var(--color-rule)] rounded-md text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-night)]">
            <option v-for="role in availableRoles" :key="role.value" :value="role.value">
              {{ role.label }}
            </option>
          </select>
        </div>

        <div v-if="errorMessage" class="bg-[var(--color-danger)]/10 border border-[var(--color-danger)]/30 text-[var(--color-danger)] p-3 rounded-md text-sm">
          {{ errorMessage }}
        </div>
        <div v-if="successMessage" class="bg-[var(--color-success)]/10 border border-[var(--color-success)]/30 text-[var(--color-success)] p-3 rounded-md text-sm">
          {{ successMessage }}
        </div>
      </main>

      <footer class="p-6 flex justify-end gap-4 border-t border-[var(--color-rule)]">
        <button @click="cancel" :disabled="loading" class="btn btn-quiet">
          Annuler
        </button>
        <button @click="updateRole" :disabled="loading" class="btn btn-primary">
          <svg v-if="loading" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
          {{ loading ? 'Mise à jour...' : 'Confirmer' }}
        </button>
      </footer>
    </div>
  </div>
</template>
