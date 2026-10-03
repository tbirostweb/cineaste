<script setup>
import { ref, onMounted, computed } from "vue"
import { useRouter } from 'vue-router'
import { useDataStore } from '../../stores/useDataStore'
import api from "/src/api/api.js"
import ConfirmDeleteUser from "../../components/admin/modals/ConfirmDeleteUser.vue"
import UserForm from '../../components/admin/forms/UserForm.vue'
import TwoFactorSetup from '../../components/features/auth/TwoFactorSetup.vue'
import UserReviews from '../../components/features/profile/UserReviews.vue'
import { logger } from '../../utils/logger'

// User Profile State
const dataStore = useDataStore()
const user = computed(() => dataStore.user)
const userId = computed(() => user.value?.id)
const firstname = computed(() => user.value?.firstname || "")
const lastname = computed(() => user.value?.lastname || "")
const email = computed(() => user.value?.email || "")
const dob = computed(() => user.value?.dob?.date ? user.value.dob.date.split(' ')[0] : "")
const userRole = computed(() => user.value?.roles ? user.value.roles[0] : "ROLE_USER")
const formattedRole = computed(() => formatRole(userRole.value))
const photo = computed(() => user.value?.photo ? `${import.meta.env.VITE_API_BASE_URL}${user.value.photo}` : "/placeholder-avatar.svg")

const errorMessage = ref("")
const loading = ref(true)
const uploadingPhoto = ref(false)

const router = useRouter()
const showConfirm = ref(false)
const userToDelete = ref(null)
const showForm = ref(false)
const selectedUser = ref(null)

const fileInput = ref(null)
const selectedFile = ref(null)

// API Key State
const apiKeyInfo = ref(null)
const newlyGeneratedApiKey = ref("")
const apiKeyLoading = ref(true)
const apiKeyError = ref("")

// 2FA State
const twoFactorStatus = ref({ enabled: false })
const twoFactorLoading = ref(true)
const twoFactorError = ref("")
const showTwoFactorSetup = ref(false)

// --- User Profile Methods ---

const confirmDelete = (user) => {
  userToDelete.value = user
  showConfirm.value = true
}

const editUser = (user) => {
  selectedUser.value = { ...user }
  showForm.value = true
}

const deleteUser = async () => {
  try {
    await api.delete(`/users/${userToDelete.value.id}`)
    showConfirm.value = false
    userToDelete.value = null
    localStorage.removeItem('token')
    localStorage.removeItem('role')
    localStorage.removeItem('userPhoto')
    router.push('/inscription')
  } catch (err) {
    logger.error('Erreur suppression', err)
    errorMessage.value = "Erreur lors de la suppression"
  }
}

function formatDate(dateString) {
  if (!dateString) return "Non spécifié"
  const date = new Date(dateString)
  if (isNaN(date)) return "Date invalide"
  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = date.getFullYear()
  return `${day}-${month}-${year}`
}

function formatRole(roleStr) {
  switch (roleStr) {
    case "ROLE_ADMIN": return "Administrateur"
    case "ROLE_USER": return "Utilisateur"
    default: return roleStr || "Aucun rôle"
  }
}

async function fetchUser() {
  loading.value = true
  errorMessage.value = ""
  try {
    await dataStore.fetchUser()
    if (user.value) {
      localStorage.setItem("role", userRole.value === "ROLE_ADMIN" ? "admin" : "user")
      localStorage.setItem("userPhoto", photo.value)
    }
  } catch (err) {
    logger.error('Erreur récupération profil', err)
    errorMessage.value = err.response?.data?.message || err.message || "Impossible de récupérer les informations"
  } finally {
    loading.value = false
  }
}

const openFilePicker = () => {
  fileInput.value?.click()
}

const handleFileChange = (event) => {
  const file = event.target.files?.[0]
  if (file) {
    if (!file.type.startsWith('image/')) {
      alert('Veuillez sélectionner un fichier image.')
      return
    }
    if (file.size > 5 * 1024 * 1024) {
      alert('La taille de l\'image ne doit pas dépasser 5 Mo.')
      return
    }
    selectedFile.value = file
    uploadPhoto()
  }
}

const uploadPhoto = async () => {
  if (!selectedFile.value || !userId.value) return
  uploadingPhoto.value = true

  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)

    const uploadResponse = await api.post('/media_objects', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    const photoIri = uploadResponse.data['@id'] || `/api/media_objects/${uploadResponse.data.id}`

    await api.patch(`/users/${userId.value}`, { photo: photoIri }, {
      headers: { 'Content-Type': 'application/merge-patch+json' }
    })

    await fetchUser()
    selectedFile.value = null
    if (fileInput.value) fileInput.value.value = ''
    alert('Photo mise à jour avec succès !')
  } catch (error) {
    logger.error('Profile:uploadPhoto', error)
    alert("Erreur lors de l'envoi de la photo.")
  } finally {
    uploadingPhoto.value = false
  }
}

// --- API Key Methods ---

async function fetchApiKeyStatus() {
  apiKeyLoading.value = true
  apiKeyError.value = ""
  try {
    const res = await api.get('/me/api-key')
    apiKeyInfo.value = res.data
  } catch (err) {
    if (err.response && err.response.status === 404) {
      apiKeyInfo.value = null
    } else {
      logger.error('Erreur récupération statut clé API', err)
      apiKeyError.value = "Impossible de récupérer le statut de la clé API."
    }
  } finally {
    apiKeyLoading.value = false
  }
}

async function generateApiKey() {
  try {
    const res = await api.post('/me/api-key')
    newlyGeneratedApiKey.value = res.data.apiKey
    await fetchApiKeyStatus()
  } catch (err) {
    logger.error('Erreur génération clé API', err)
    apiKeyError.value = "Une erreur est survenue lors de la génération de la clé."
  }
}

async function toggleApiKeyStatus() {
  if (!apiKeyInfo.value) return
  const originalStatus = apiKeyInfo.value.enabled
  try {
    const newStatus = !originalStatus
    apiKeyInfo.value.enabled = newStatus // Optimistic update
    await api.patch('/me/api-key', { enabled: newStatus })
  } catch (err) {
    logger.error('Erreur changement statut clé API', err)
    apiKeyError.value = "Impossible de modifier le statut de la clé."
    if (apiKeyInfo.value) apiKeyInfo.value.enabled = originalStatus // Revert on failure
  }
}

async function revokeApiKey() {
    if (!confirm("Êtes-vous sûr de vouloir révoquer cette clé API ? Cette action est irréversible et prendra effet immédiatement.")) {
        return
    }
  try {
    await api.delete('/me/api-key')
    apiKeyInfo.value = null
    newlyGeneratedApiKey.value = ""
  } catch (err) {
    logger.error('Erreur révocation clé API', err)
    apiKeyError.value = "Impossible de révoquer la clé API."
  }
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(() => {
    alert("Copié dans le presse-papiers !")
  }).catch(err => {
    logger.error('Could not copy text', err);
    alert("Impossible de copier. Veuillez copier manuellement.");
  });
}

function closeApiKeyDisplay() {
    newlyGeneratedApiKey.value = ""
}

function formatApiKeyDate(dateString) {
    if (!dateString) return "Jamais"
    const date = new Date(dateString)
    return date.toLocaleString('fr-FR')
}

// --- 2FA Methods ---

async function fetchTwoFactorStatus() {
  twoFactorLoading.value = true
  try {
    const res = await api.get('/2fa/status')
    twoFactorStatus.value = res.data
  } catch (err) {
    logger.error('Erreur récupération statut 2FA', err)
    twoFactorError.value = "Impossible de récupérer le statut 2FA."
  } finally {
    twoFactorLoading.value = false
  }
}

async function disableTwoFactor() {
  const code = prompt("Pour désactiver l'authentification à deux facteurs, veuillez entrer un code de votre application d'authentification.")
  if (!code) return

  try {
    await api.post('/2fa/disable', { code })
    alert("L'authentification à deux facteurs a été désactivée.")
    await fetchTwoFactorStatus()
  } catch (err) {
    logger.error('Erreur désactivation 2FA', err)
    alert(err.response?.data?.error || "Impossible de désactiver le 2FA. Le code est peut-être invalide.")
  }
}

// --- Lifecycle Hooks ---

onMounted(() => {
  fetchUser()
  fetchApiKeyStatus()
  fetchTwoFactorStatus()
  // Correction ici : on attend que le DOM soit prêt et on cible les éléments avec la classe .profile-card
  // qui doit être ajoutée aux divs correspondantes dans le template
})
</script>

<template>
  <div class="min-h-screen bg-[var(--color-paper)]">
    <div class="max-w-4xl mx-auto px-6 py-20">
      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center min-h-[60vh]" aria-label="Chargement en cours">
        <div class="flex gap-2">
          <div class="w-3 h-3 bg-[var(--color-ink)] rounded-full animate-bounce"></div>
          <div class="w-3 h-3 bg-[var(--color-ink)] rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
          <div class="w-3 h-3 bg-[var(--color-ink)] rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
        </div>
      </div>

      <!-- Error -->
      <div v-else-if="errorMessage" class="text-center py-20">
        <div class="inline-block p-6 bg-[var(--color-paper-raised)] rounded-full mb-6">
          <svg class="w-12 h-12 text-[var(--color-night)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <p class="text-[var(--color-ink-soft)] text-lg mb-6">{{ errorMessage }}</p>
        <router-link to="/connexion" class="btn btn-primary">
          SE RECONNECTER
        </router-link>
      </div>

      <!-- Profile Content -->
      <div v-else class="space-y-12">
        <div class="profile-card flex flex-col md:flex-row items-center gap-8">
          <div class="relative group">
            <img :src="photo" alt="Photo de profil" class="w-32 h-32 rounded-full object-cover border-4 border-[var(--color-ink)] shadow-lg">
            <button @click="openFilePicker" :disabled="uploadingPhoto" class="absolute inset-0 bg-[var(--color-ink)]/70 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity" aria-label="Changer la photo">
              <svg v-if="!uploadingPhoto" class="w-8 h-8 text-[var(--color-ink)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
              <svg v-else class="animate-spin h-8 w-8 text-[var(--color-ink)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </button>
            <input type="file" ref="fileInput" @change="handleFileChange" accept="image/*" class="hidden">
          </div>
          <div class="text-center md:text-left">
            <h1 class="text-5xl font-bold text-[var(--color-ink)]">{{ firstname }} {{ lastname }}</h1>
            <p class="text-[var(--color-night)] tracking-[0.2em] text-sm">{{ formattedRole }}</p>
          </div>
        </div>

        <div class="profile-card bg-[var(--color-paper-raised)] border border-[var(--color-rule)] rounded-lg p-8 space-y-6">
          <h2 class="text-[var(--color-ink)] font-bold text-2xl border-b border-[var(--color-rule)] pb-4 mb-4">Informations personnelles</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-lg">
            <div>
              <span class="text-[var(--color-ink-faint)] text-sm uppercase tracking-wider">Prénom</span>
              <p class="text-[var(--color-ink)] font-semibold mt-1">{{ firstname }}</p>
            </div>
            <div>
              <span class="text-[var(--color-ink-faint)] text-sm uppercase tracking-wider">Nom</span>
              <p class="text-[var(--color-ink)] font-semibold mt-1">{{ lastname }}</p>
            </div>
            <div>
              <span class="text-[var(--color-ink-faint)] text-sm uppercase tracking-wider">Email</span>
              <p class="text-[var(--color-ink)] font-semibold mt-1">{{ email }}</p>
            </div>
            <div>
              <span class="text-[var(--color-ink-faint)] text-sm uppercase tracking-wider">Date de naissance</span>
              <p class="text-[var(--color-ink)] font-semibold mt-1">{{ formatDate(dob) }}</p>
            </div>
          </div>
        </div>

        <!-- Mes Avis -->
        <div class="profile-card">
          <UserReviews v-if="userId" :user-id="userId" />
        </div>

        <!-- 2FA Management -->
        <div class="profile-card bg-[var(--color-paper-raised)] border border-[var(--color-rule)] rounded-lg p-8 space-y-6">
          <h2 class="text-[var(--color-ink)] font-bold text-2xl border-b border-[var(--color-rule)] pb-4 mb-4">Sécurité - Authentification à deux facteurs (2FA)</h2>
          <div v-if="twoFactorLoading" class="text-center text-[var(--color-ink-soft)]">Chargement...</div>
          <div v-else-if="twoFactorError" class="text-center text-[var(--color-danger)] p-4 bg-[var(--color-danger)]/10 rounded-md">{{ twoFactorError }}</div>
          <div v-else-if="twoFactorStatus.enabled" class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <svg class="w-8 h-8 text-[var(--color-success)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
              <p class="text-[var(--color-success)] font-semibold">L'authentification à deux facteurs est activée.</p>
            </div>
            <button @click="disableTwoFactor" class="px-6 py-3 bg-[var(--color-danger)]/10 hover:bg-[var(--color-danger)]/10 border border-[var(--color-danger)]/30 text-[var(--color-danger)] font-bold rounded-lg transition-all text-xs tracking-[0.2em]">DÉSACTIVER</button>
          </div>
          <div v-else class="text-center py-4">
            <p class="text-[var(--color-ink-soft)] mb-6">Protégez votre compte en ajoutant une deuxième couche de sécurité.</p>
            <button @click="showTwoFactorSetup = true" class="btn btn-primary">ACTIVER LE 2FA</button>
          </div>
        </div>

        <!-- API Key Management -->
        <div class="profile-card bg-[var(--color-paper-raised)] border border-[var(--color-rule)] rounded-lg p-8 space-y-6">
          <h2 class="text-[var(--color-ink)] font-bold text-2xl border-b border-[var(--color-rule)] pb-4 mb-4">Gestion de la Clé API</h2>

          <div v-if="apiKeyLoading" class="text-center text-[var(--color-ink-soft)]">Chargement...</div>
          <div v-else-if="apiKeyError" class="text-center text-[var(--color-danger)] p-4 bg-[var(--color-danger)]/10 rounded-md">{{ apiKeyError }}</div>

          <div v-else-if="newlyGeneratedApiKey" class="bg-[var(--color-warning)]/10 border border-[var(--color-warning)]/30 rounded-lg p-6 space-y-4">
              <h3 class="text-lg font-semibold text-[var(--color-warning)]">Votre nouvelle clé API</h3>
              <p class="text-[var(--color-warning)]">Copiez cette clé et conservez-la en lieu sûr. <strong>Vous ne pourrez plus la voir après avoir fermé cet encadré.</strong></p>
              <div class="bg-[var(--color-ink)]/70 rounded-md p-4 flex items-center justify-between gap-4">
                  <code class="text-[var(--color-ink)] font-mono break-all text-sm">{{ newlyGeneratedApiKey }}</code>
                  <button @click="copyToClipboard(newlyGeneratedApiKey)" title="Copier la clé" class="p-2 hover:bg-[var(--color-paper-sunk)] rounded-md transition flex-shrink-0">
                      <svg class="w-6 h-6 text-[var(--color-ink)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                  </button>
              </div>
              <button @click="closeApiKeyDisplay" class="btn btn-primary w-full mt-4">J'AI BIEN COPIÉ MA CLÉ</button>
          </div>

          <div v-else-if="apiKeyInfo && apiKeyInfo.prefix" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
              <div>
                <span class="text-[var(--color-ink-faint)] text-sm uppercase tracking-wider">Préfixe</span>
                <p class="text-[var(--color-ink)] font-semibold mt-1 font-mono">{{ apiKeyInfo.prefix }}...</p>
              </div>
              <div>
                <span class="text-[var(--color-ink-faint)] text-sm uppercase tracking-wider">Statut</span>
                <div class="flex items-center gap-3 mt-2">
                    <button @click="toggleApiKeyStatus" :class="apiKeyInfo.enabled ? 'bg-[var(--color-success)]' : 'bg-[var(--color-rule)]'" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors flex-shrink-0" aria-label="Basculer le statut de la clé API">
                        <span :class="apiKeyInfo.enabled ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-[var(--color-paper-raised)] transition-transform"></span>
                    </button>
                    <span :class="apiKeyInfo.enabled ? 'text-[var(--color-success)]' : 'text-[var(--color-ink-faint)]'" class="font-semibold text-lg">{{ apiKeyInfo.enabled ? 'Activée' : 'Désactivée' }}</span>
                </div>
              </div>
              <div>
                <span class="text-[var(--color-ink-faint)] text-sm uppercase tracking-wider">Créée le</span>
                <p class="text-[var(--color-ink)] font-semibold mt-1">{{ formatApiKeyDate(apiKeyInfo.createdAt) }}</p>
              </div>
              <div>
                <span class="text-[var(--color-ink-faint)] text-sm uppercase tracking-wider">Dernière utilisation</span>
                <p class="text-[var(--color-ink)] font-semibold mt-1">{{ formatApiKeyDate(apiKeyInfo.lastUsedAt) }}</p>
              </div>
            </div>
            <div class="pt-6 border-t border-[var(--color-rule)]">
                <button @click="revokeApiKey" class="w-full px-8 py-3 bg-[var(--color-danger)]/10 hover:bg-[var(--color-danger)]/10 border border-[var(--color-danger)]/30 text-[var(--color-danger)] font-bold rounded-lg transition-all text-xs tracking-[0.2em]">RÉVOQUER LA CLÉ API</button>
            </div>
          </div>

          <div v-else class="text-center py-4">
            <p class="text-[var(--color-ink-soft)] mb-6">Vous n'avez pas de clé API. Générez-en une pour l'utiliser avec des scripts ou des services externes.</p>
            <button @click="generateApiKey" class="btn btn-primary">GÉNÉRER UNE CLÉ API</button>
          </div>
        </div>

        <div class="profile-card flex flex-col sm:flex-row gap-4">
          <button @click="editUser({ id: userId, firstname, lastname, email, dob, roles: [userRole], photo })" class="btn btn-primary flex-1">MODIFIER LE PROFIL</button>
          <router-link v-if="userRole === 'ROLE_ADMIN'" to="/admin" class="btn btn-primary flex-1 text-center">ADMIN</router-link>
        </div>

        <div class="profile-card border-t border-[var(--color-rule)] pt-8 mt-12">
          <h3 class="text-lg font-semibold text-[var(--color-danger)] mb-2">Zone de danger</h3>
          <p class="text-[var(--color-ink-faint)] mb-4">La suppression de votre compte est une action irréversible.</p>
          <button @click="confirmDelete({ id: userId, firstname, lastname, email, dob, roles: [userRole] })" class="w-full px-8 py-4 bg-[var(--color-danger)]/10 hover:bg-[var(--color-danger)]/10 border border-[var(--color-danger)]/30 text-[var(--color-danger)] font-bold rounded-lg transition-all text-xs tracking-[0.2em]">SUPPRIMER MON COMPTE</button>
        </div>
      </div>
    </div>

    <UserForm v-if="showForm" :user="selectedUser" @close="showForm = false" @refresh="fetchUser"/>
    <ConfirmDeleteUser v-if="showConfirm" :user="userToDelete" @cancel="showConfirm = false" @confirm="deleteUser"/>

    <!-- Modal pour le setup 2FA -->
    <TwoFactorSetup v-if="showTwoFactorSetup" @close="showTwoFactorSetup = false" @enabled="fetchTwoFactorStatus" />

  </div>
</template>
