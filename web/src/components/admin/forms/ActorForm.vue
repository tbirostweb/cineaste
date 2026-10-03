<script setup>
import { ref, onMounted, watch } from 'vue'
import api from '/src/api/api.js'
import { logger } from '../../../utils/logger'

const emit = defineEmits(['close', 'refresh'])
const props = defineProps({ actor: Object })

const firstname = ref('')
const lastname = ref('')
const dob = ref('')
const dod = ref('')
const bio = ref('')
const selectedMovies = ref([])
const allMovies = ref([])
const loading = ref(false)
const errors = ref(null)

const initFromActor = (a) => {
  if (!a) {
    firstname.value = ''
    lastname.value = ''
    dob.value = ''
    dod.value = ''
    bio.value = ''
    selectedMovies.value = []
    return
  }
  firstname.value = a.firstname || ''
  lastname.value = a.lastname || ''
  dob.value = a.dob ? a.dob.split('T')[0] : ''
  dod.value = a.dod ? a.dod.split('T')[0] : ''
  bio.value = a.bio || ''
  selectedMovies.value = (a.movies || []).map(m => {
    if (typeof m === 'string') {
      const match = m.match(/\/(\d+)$/)
      return match ? Number(match[1]) : null
    }
    if (m.id) return m.id
    if (m['@id']) {
      const match = m['@id'].match(/\/(\d+)$/)
      return match ? Number(match[1]) : null
    }
    return null
  }).filter(id => id !== null)
}

const fetchMovies = async () => {
  try {
    const res = await api.get('/movies', { params: { pagination: false } })
    allMovies.value = res.data.member || res.data['hydra:member'] || []
  } catch (err) {
    logger.error('Erreur chargement films', err)
  }
}

const toggleMovie = (id) => {
  if (selectedMovies.value.includes(id)) {
    selectedMovies.value = selectedMovies.value.filter(m => m !== id)
  } else {
    selectedMovies.value.push(id)
  }
}

onMounted(async () => {
  await fetchMovies()
  initFromActor(props.actor)
})
watch(() => props.actor, (a) => initFromActor(a))

const saveActor = async () => {
  loading.value = true
  errors.value = null

  try {
    const actorData = {
      firstname: firstname.value,
      lastname: lastname.value,
      dob: dob.value,
      dod: dod.value || null,
      bio: bio.value,
      movies: selectedMovies.value.map(id => `/api/movies/${id}`)
    }

    if (props.actor?.id) {
      await api.patch(`/actors/${props.actor.id}`, actorData, { headers: { 'Content-Type': 'application/merge-patch+json' } })
    } else {
      await api.post('/actors', actorData, { headers: { 'Content-Type': 'application/ld+json' } })
    }

    emit('refresh')
    emit('close')
  } catch (err) {
    logger.error('Erreur sauvegarde', err.response || err)
    errors.value = err.response?.data?.['hydra:description'] || err.message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 bg-[var(--color-ink)]/70 backdrop-blur-sm flex justify-center items-center z-50 p-4" @click.self="emit('close')">
    <div class="form-container bg-[var(--color-paper-raised)] border border-[var(--color-rule)] rounded-lg w-full max-w-2xl shadow-2xl max-h-[90vh] flex flex-col">
      <header class="p-6 flex items-center justify-between border-b border-[var(--color-rule)]">
        <h2 class="text-2xl font-bold text-[var(--color-ink)]">
          {{ props.actor ? 'Modifier l\'acteur' : 'Nouvel acteur' }}
        </h2>
        <button @click="emit('close')" class="p-2 rounded-full text-[var(--color-ink-faint)] hover:bg-[var(--color-paper-sunk)]" aria-label="Fermer">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </header>

      <main class="p-6 space-y-6 overflow-y-auto">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label for="actor-firstname" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Prénom</label>
            <input id="actor-firstname" v-model="firstname" type="text" placeholder="ex: Leonardo" class="w-full px-4 py-2 bg-[var(--color-paper)] border border-[var(--color-rule)] rounded-md text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-night)]" />
          </div>
          <div>
            <label for="actor-lastname" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Nom</label>
            <input id="actor-lastname" v-model="lastname" type="text" placeholder="ex: DiCaprio" class="w-full px-4 py-2 bg-[var(--color-paper)] border border-[var(--color-rule)] rounded-md text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-night)]" />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label for="actor-dob" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Date de naissance</label>
            <input id="actor-dob" v-model="dob" type="date" class="w-full px-4 py-2 bg-[var(--color-paper)] border border-[var(--color-rule)] rounded-md text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-night)]" />
          </div>
          <div>
            <label for="actor-dod" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Date de décès (optionnel)</label>
            <input id="actor-dod" v-model="dod" type="date" class="w-full px-4 py-2 bg-[var(--color-paper)] border border-[var(--color-rule)] rounded-md text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-night)]" />
          </div>
        </div>

        <div>
          <label for="actor-bio" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Biographie</label>
          <textarea id="actor-bio" v-model="bio" placeholder="Racontez l'histoire de cet acteur..." rows="4" class="w-full px-4 py-2 bg-[var(--color-paper)] border border-[var(--color-rule)] rounded-md resize-y text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-night)]"></textarea>
        </div>

        <div>
          <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Films ({{ selectedMovies.length }} sélectionné{{ selectedMovies.length > 1 ? 's' : '' }})</label>
          <div class="bg-[var(--color-paper)] border border-[var(--color-rule)] rounded-md p-4 max-h-48 overflow-y-auto space-y-2">
            <label v-for="movie in allMovies" :key="movie.id" class="flex items-center gap-3 p-2 rounded-md hover:bg-[var(--color-paper-sunk)] cursor-pointer">
              <input type="checkbox" :value="movie.id" :checked="selectedMovies.includes(movie.id)" @change="toggleMovie(movie.id)" class="w-4 h-4 rounded border-[var(--color-rule)] bg-[var(--color-paper)] text-[var(--color-night)] focus:ring-[var(--color-night)]" />
              <span class="text-[var(--color-ink)]">{{ movie.name }}</span>
            </label>
          </div>
        </div>

        <div v-if="errors" class="bg-[var(--color-danger)]/10 border border-[var(--color-danger)]/30 text-[var(--color-danger)] p-3 rounded-md text-sm">
          {{ errors }}
        </div>
      </main>

      <footer class="p-6 flex justify-end gap-4 border-t border-[var(--color-rule)]">
        <button @click="emit('close')" class="btn btn-quiet">
          Annuler
        </button>
        <button @click="saveActor" :disabled="loading" class="btn btn-primary">
          {{ loading ? 'Sauvegarde...' : 'Sauvegarder' }}
        </button>
      </footer>
    </div>
  </div>
</template>
