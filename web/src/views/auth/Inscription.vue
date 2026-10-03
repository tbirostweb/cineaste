<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()

const apiPublic = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  withCredentials: true,
  headers: {
    'Accept': 'application/ld+json'
  }
})

const firstname = ref('')
const lastname = ref('')
const email = ref('')
const password = ref('')
const dob = ref('')
const photoFile = ref(null)
const photoPreview = ref(null)
const errorMessage = ref('')
const successMessage = ref('')
const isLoading = ref(false)

const handleFileChange = (e) => {
  const file = e.target.files[0]
  if (file) {
    if (!file.type.startsWith('image/')) {
      alert('Veuillez sélectionner un fichier image.')
      return
    }
    if (file.size > 5 * 1024 * 1024) {
      alert('La taille de l\'image ne doit pas dépasser 5 Mo.')
      return
    }
    photoFile.value = file
    const reader = new FileReader()
    reader.onload = (e) => photoPreview.value = e.target.result
    reader.readAsDataURL(file)
  }
}

const removePhoto = () => {
  photoFile.value = null
  photoPreview.value = null
  const fileInput = document.getElementById('photo-upload')
  if (fileInput) fileInput.value = ''
}

/**
 * Envoie l'avatar une fois le compte créé et le jeton obtenu.
 *
 * L'ordre compte : l'envoi se faisait auparavant AVANT l'inscription, sur un
 * endpoint ouvert à tout Internet. Le déplacer après l'authentification permet
 * d'exiger un compte côté API, et un avatar qui échoue ne fait plus perdre
 * l'inscription elle-même.
 */
const uploadPhoto = async (file, token) => {
  const formData = new FormData()
  formData.append('file', file)
  const response = await apiPublic.post('/media_objects', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
      Authorization: `Bearer ${token}`,
    },
  })
  return response.data['@id'] || `/api/media_objects/${response.data.id}`
}

const register = async (e) => {
  e.preventDefault()
  errorMessage.value = ''
  successMessage.value = ''
  isLoading.value = true

  try {
    const { data: created } = await apiPublic.post(
      import.meta.env.VITE_API_URL_REGISTER,
      {
        firstname: firstname.value,
        lastname: lastname.value,
        email: email.value,
        plainPassword: password.value,
        dob: dob.value,
      },
      { headers: { 'Content-Type': 'application/ld+json' } }
    )

    if (photoFile.value) {
      try {
        const { data: auth } = await apiPublic.post(
          import.meta.env.VITE_API_URL_AUTH,
          { email: email.value, password: password.value }
        )
        const photoIri = await uploadPhoto(photoFile.value, auth.token)
        await apiPublic.patch(
          `/users/${created.id}`,
          { photo: photoIri },
          {
            headers: {
              'Content-Type': 'application/merge-patch+json',
              Authorization: `Bearer ${auth.token}`,
            },
          }
        )
      } catch {
        // Le compte existe : on n'annule pas l'inscription pour un avatar.
        // Il pourra être ajouté depuis le profil.
        successMessage.value =
          'Compte créé. La photo n’a pas pu être envoyée, vous pourrez l’ajouter depuis votre profil.'
      }
    }

    if (!successMessage.value) {
      successMessage.value = 'Compte créé avec succès ! Redirection...'
    }
    setTimeout(() => router.push('/connexion'), 2000)
  } catch (error) {
    errorMessage.value =
        error.response?.data?.message ||
        error.response?.data?.['hydra:description'] ||
        error.response?.data?.detail ||
        (error.response?.status === 500
            ? "Une erreur est survenue. L'email est peut-être déjà utilisé."
            : error.message) ||
        "Une erreur est survenue lors de l'inscription."
    nextTick(() => {
    })
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
})
</script>

<template>
  <div class="min-h-screen bg-[var(--color-paper)] flex items-center justify-center px-4 sm:px-6 lg:px-8 py-12 relative overflow-hidden">

    <div class="auth-card w-full max-w-lg space-y-8 z-10">
      <div class="text-center">
        <h1 class="text-6xl font-bold text-[var(--color-night)]">Générique</h1>
        <h2 class="mt-2 text-2xl font-bold text-[var(--color-ink)]">
          Créez votre compte
        </h2>
        <p class="mt-2 text-sm text-[var(--color-ink-soft)]">
          Vous avez déjà un compte ?
          <router-link to="/connexion" class="font-medium text-[var(--color-night)] hover:text-[var(--color-night-soft)]">
            Connectez-vous
          </router-link>
        </p>
      </div>

      <form class="mt-8 space-y-6 bg-[var(--color-paper-raised)] p-8 rounded-lg shadow-2xl border border-[var(--color-rule)]" @submit="register">
        <div class="flex flex-col items-center space-y-4">
          <div class="relative">
            <img :src="photoPreview || '/placeholder-avatar.svg'" alt="Aperçu de l'avatar" class="w-24 h-24 rounded-full object-cover border-4 border-[var(--color-rule)]">
            <button v-if="photoPreview" @click="removePhoto" type="button" class="absolute -top-1 -right-1 bg-[var(--color-danger)] text-[var(--color-paper)] rounded-full w-6 h-6 flex items-center justify-center hover:bg-[var(--color-ink)] transition-colors" aria-label="Supprimer la photo">&times;</button>
          </div>
          <label for="photo-upload" class="btn btn-primary">
            Choisir une photo
          </label>
          <input id="photo-upload" name="photo" type="file" class="sr-only" @change="handleFileChange" accept="image/*">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="firstname" class="text-[var(--color-ink-soft)] text-sm tracking-wider uppercase">Prénom</label>
            <input v-model="firstname" id="firstname" name="firstname" type="text" required class="mt-2 appearance-none rounded-md relative block w-full px-4 py-3 border border-[var(--color-rule)] bg-[var(--color-paper)] placeholder-gray-500 text-[var(--color-ink)] focus:outline-none focus:ring-[var(--color-night)] focus:border-[var(--color-ink)] sm:text-sm transition-all" placeholder="Jean">
          </div>
          <div>
            <label for="lastname" class="text-[var(--color-ink-soft)] text-sm tracking-wider uppercase">Nom</label>
            <input v-model="lastname" id="lastname" name="lastname" type="text" required class="mt-2 appearance-none rounded-md relative block w-full px-4 py-3 border border-[var(--color-rule)] bg-[var(--color-paper)] placeholder-gray-500 text-[var(--color-ink)] focus:outline-none focus:ring-[var(--color-night)] focus:border-[var(--color-ink)] sm:text-sm transition-all" placeholder="Dupont">
          </div>
        </div>

        <div>
          <label for="email-address" class="text-[var(--color-ink-soft)] text-sm tracking-wider uppercase">Email</label>
          <input v-model="email" id="email-address" name="email" type="email" autocomplete="email" required class="mt-2 appearance-none rounded-md relative block w-full px-4 py-3 border border-[var(--color-rule)] bg-[var(--color-paper)] placeholder-gray-500 text-[var(--color-ink)] focus:outline-none focus:ring-[var(--color-night)] focus:border-[var(--color-ink)] sm:text-sm transition-all" placeholder="votre@email.com">
        </div>

        <div>
          <label for="password" class="text-[var(--color-ink-soft)] text-sm tracking-wider uppercase">Mot de passe</label>
          <input v-model="password" id="password" name="password" type="password" autocomplete="new-password" required class="mt-2 appearance-none rounded-md relative block w-full px-4 py-3 border border-[var(--color-rule)] bg-[var(--color-paper)] placeholder-gray-500 text-[var(--color-ink)] focus:outline-none focus:ring-[var(--color-night)] focus:border-[var(--color-ink)] sm:text-sm transition-all" placeholder="********">
        </div>

        <div>
          <label for="dob" class="text-[var(--color-ink-soft)] text-sm tracking-wider uppercase">Date de naissance</label>
          <input v-model="dob" id="dob" name="dob" type="date" required class="mt-2 appearance-none rounded-md relative block w-full px-4 py-3 border border-[var(--color-rule)] bg-[var(--color-paper)] placeholder-gray-500 text-[var(--color-ink)] focus:outline-none focus:ring-[var(--color-night)] focus:border-[var(--color-ink)] sm:text-sm">
        </div>

        <div v-if="successMessage" class="text-[var(--color-success)] text-sm text-center bg-[var(--color-success)]/10 p-3 rounded-md border border-[var(--color-success)]/30">{{ successMessage }}</div>
        <div v-if="errorMessage" class="error-message text-[var(--color-danger)] text-sm text-center bg-[var(--color-danger)]/10 p-3 rounded-md border border-[var(--color-danger)]/30">{{ errorMessage }}</div>

        <div>
          <button :disabled="isLoading" type="submit" class="btn btn-primary w-full">
            <span v-if="isLoading" class="absolute left-0 inset-y-0 flex items-center pl-3">
              <svg class="h-5 w-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </span>
            {{ isLoading ? 'Création du compte...' : 'Créer le compte' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
