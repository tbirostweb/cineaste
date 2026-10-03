<script setup>
import { ref, onMounted, nextTick, computed } from "vue"
import api from "/src/api/api.js"
import UserRoleForm from "../forms/UserRoleForm.vue"
import { logger } from '../../../utils/logger'

const users = ref([])
const loading = ref(true)
const errorMessage = ref("")
const selectedUser = ref(null)
const showRoleForm = ref(false)

// Pagination
const page = ref(1)
const totalItems = ref(0)
const itemsPerPage = 30 // Valeur par défaut d'API Platform

const totalPages = computed(() => Math.ceil(totalItems.value / itemsPerPage))

function formatDate(dateString) {
  if (!dateString) return "—"
  const date = new Date(dateString)
  if (isNaN(date)) return "—"
  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = date.getFullYear()
  return `${day}-${month}-${year}`
}

function formatRole(roles) {
  if (!roles || !Array.isArray(roles)) return "Aucun rôle"
  if (roles.includes("ROLE_ADMIN")) return "Admin"
  if (roles.includes("ROLE_USER")) return "Utilisateur"
  return roles[0] || "—"
}

async function fetchUsers() {
  loading.value = true
  errorMessage.value = ""
  try {
    const res = await api.get('/users', {
      params: { page: page.value }
    })

    const memberData = res.data['hydra:member'] || res.data['member']
    users.value = Array.isArray(memberData) ? memberData : []
    totalItems.value = res.data['hydra:totalItems'] || res.data['totalItems'] || users.value.length

    await nextTick()

    if (users.value.length > 0) {
    }

  } catch (err) {
    logger.error('Erreur fetchUsers', err)
    if (err.response) {
      errorMessage.value = `Erreur ${err.response.status}: ${err.response.statusText}`
    } else if (err.request) {
      errorMessage.value = "Impossible de joindre le serveur"
    } else {
      errorMessage.value = err.message
    }
  } finally {
    loading.value = false
  }
}

function openRoleForm(user) {
  selectedUser.value = user
  showRoleForm.value = true
}

function closeRoleForm() {
  showRoleForm.value = false
  selectedUser.value = null
}

async function handleRoleUpdated() {
  closeRoleForm()
  await fetchUsers()
}

const changePage = (newPage) => {
  if (newPage >= 1 && newPage <= totalPages.value) {
    page.value = newPage
    fetchUsers()
  }
}

onMounted(() => {
  fetchUsers()
})
</script>

<template>
  <div class="universal-card p-6">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-3xl font-bold text-[var(--color-ink)]">Gestion des Utilisateurs</h2>
      <div class="flex items-center gap-4">
        <span class="text-sm text-[var(--color-ink-soft)]">Total: <span class="text-[var(--color-night)] font-bold">{{ totalItems }}</span></span>
        <button @click="fetchUsers" class="text-sm text-[var(--color-night)] hover:text-[var(--color-ink)] transition-colors">
          Rafraîchir
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-10">
      <div class="flex justify-center gap-2 mb-2">
          <div class="w-2 h-2 bg-[var(--color-ink)] rounded-full animate-bounce"></div>
          <div class="w-2 h-2 bg-[var(--color-ink)] rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
          <div class="w-2 h-2 bg-[var(--color-ink)] rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
      </div>
      <p class="text-[var(--color-ink-soft)] text-sm">Chargement des utilisateurs...</p>
    </div>

    <!-- Error -->
    <div v-else-if="errorMessage" class="text-center py-10 bg-[var(--color-danger)]/10 rounded-lg border border-[var(--color-danger)]/30">
      <p class="text-[var(--color-danger)] font-bold mb-2">Erreur</p>
      <p class="text-[var(--color-danger)] text-sm">{{ errorMessage }}</p>
      <button @click="fetchUsers" class="btn btn-danger mt-4">Réessayer</button>
    </div>

    <!-- User Table -->
    <div v-else-if="users.length > 0" class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead class="border-b border-[var(--color-rule)] bg-[var(--color-paper-sunk)]">
          <tr>
            <th class="p-4 text-xs font-bold text-[var(--color-ink-soft)] uppercase tracking-widest">Utilisateur</th>
            <th class="p-4 text-xs font-bold text-[var(--color-ink-soft)] uppercase tracking-widest">Rôle</th>
            <th class="p-4 text-xs font-bold text-[var(--color-ink-soft)] uppercase tracking-widest">Date de naissance</th>
            <th class="p-4 text-xs font-bold text-[var(--color-ink-soft)] uppercase tracking-widest text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id" class="user-row border-b border-[var(--color-rule)] hover:bg-[var(--color-paper-sunk)] transition-colors">
            <td class="p-4">
              <div class="font-bold text-[var(--color-ink)]">{{ user.firstname }} {{ user.lastname }}</div>
              <div class="text-xs text-[var(--color-ink-soft)] mt-1">{{ user.email }}</div>
            </td>
            <td class="p-4">
              <span :class="user.roles && user.roles.includes('ROLE_ADMIN') ? 'bg-[var(--color-paper-sunk)] text-[var(--color-night)] border-[var(--color-rule)]' : 'bg-[var(--color-paper-sunk)] text-[var(--color-ink-faint)] border-[var(--color-rule)]'" class="px-3 py-1 inline-flex text-xs font-medium rounded-full border">
                {{ formatRole(user.roles) }}
              </span>
            </td>
            <td class="p-4 text-[var(--color-ink-soft)] text-sm">
              {{ user.dob ? formatDate(user.dob) : "—" }}
            </td>
            <td class="p-4 text-right">
              <button @click="openRoleForm(user)" class="text-[var(--color-night)] hover:text-[var(--color-ink)] text-xs font-bold uppercase tracking-wider transition-colors border border-[var(--color-rule)] hover:bg-[var(--color-paper-sunk)] px-3 py-1.5 rounded">
                Modifier Rôle
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex justify-center items-center gap-4 mt-8 pt-4 border-t border-[var(--color-rule)]">
        <button
          @click="changePage(page - 1)"
          :disabled="page === 1"
          class="px-4 py-2 bg-[var(--color-paper-raised)] border border-[var(--color-rule)] rounded text-[var(--color-ink)] hover:border-[var(--color-ink)] disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm"
        >
          Précédent
        </button>
        <span class="text-[var(--color-ink-soft)] text-sm">Page <span class="text-[var(--color-night)] font-bold">{{ page }}</span> sur {{ totalPages }}</span>
        <button
          @click="changePage(page + 1)"
          :disabled="page === totalPages"
          class="px-4 py-2 bg-[var(--color-paper-raised)] border border-[var(--color-rule)] rounded text-[var(--color-ink)] hover:border-[var(--color-ink)] disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm"
        >
          Suivant
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-20 bg-[var(--color-paper-sunk)] rounded-lg border border-[var(--color-rule)]">
      <p class="text-[var(--color-ink-soft)]">Aucun utilisateur trouvé.</p>
    </div>

    <UserRoleForm
      v-if="showRoleForm && selectedUser"
      :user="selectedUser"
      @updated="handleRoleUpdated"
      @cancel="closeRoleForm"
    />
  </div>
</template>
