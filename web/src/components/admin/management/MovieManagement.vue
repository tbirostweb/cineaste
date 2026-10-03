<script setup>
import { ref, onMounted, computed } from "vue"
import MovieForm from "../forms/MovieForm.vue"
import ConfirmDelete from "../modals/ConfirmDeleteMovie.vue"
import api from '../../../api/api'
import { logger } from '../../../utils/logger'

const loading = ref(false)
const showForm = ref(false)
const showConfirm = ref(false)
const selectedMovie = ref(null)
const movieToDelete = ref(null)
const movies = ref([])

// Pagination
const page = ref(1)
const totalItems = ref(0)
const itemsPerPage = 30

const totalPages = computed(() => Math.ceil(totalItems.value / itemsPerPage))

const fetchMovies = async () => {
  loading.value = true
  try {
    const response = await api.get('/movies', {
      params: {
        page: page.value,
        itemsPerPage: itemsPerPage,
        'groups[]': ['movie:read'] // Optimisation : on ne charge que le nécessaire
      }
    })
    const data = response.data
    movies.value = data['hydra:member'] || data['member'] || []
    totalItems.value = data['hydra:totalItems'] || data['totalItems'] || movies.value.length
  } catch (err) {
    logger.error('Erreur lors du chargement des films', err);
  } finally {
    loading.value = false
  }
}

const editMovie = (movie) => {
  selectedMovie.value = { ...movie }
  showForm.value = true
}

const confirmDelete = (movie) => {
  movieToDelete.value = movie
  showConfirm.value = true
}

const deleteMovie = async () => {
  if (!movieToDelete.value) return;
  try {
    await api.delete(`/movies/${movieToDelete.value.id}`)
    showConfirm.value = false
    movieToDelete.value = null
    fetchMovies() // Rafraîchir la liste
  } catch (err) {
    logger.error('Erreur suppression', err)
  }
}

const onFormSaved = async () => {
  showForm.value = false;
  await fetchMovies();
};

const changePage = (newPage) => {
  if (newPage >= 1 && newPage <= totalPages.value) {
    page.value = newPage
    fetchMovies()
  }
}

onMounted(async () => {
  await fetchMovies()
})
</script>

<template>
  <div class="universal-card p-6">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-3xl font-bold text-[var(--color-ink)]">Gestion des Films</h2>
      <div class="flex items-center gap-4">
        <span class="text-sm text-[var(--color-ink-soft)]">Total: <span class="text-[var(--color-night)] font-bold">{{ totalItems }}</span></span>
        <button
          @click="selectedMovie = null; showForm = true"
          class="btn btn-primary"
        >
          + Ajouter
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-10">
      <div class="flex justify-center gap-2 mb-2">
          <div class="w-2 h-2 bg-[var(--color-ink)] rounded-full animate-bounce"></div>
          <div class="w-2 h-2 bg-[var(--color-ink)] rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
          <div class="w-2 h-2 bg-[var(--color-ink)] rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
      </div>
      <p class="text-[var(--color-ink-soft)] text-sm">Chargement des films...</p>
    </div>

    <div v-else class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead class="border-b border-[var(--color-rule)] bg-[var(--color-paper-sunk)]">
          <tr>
            <th class="p-4 text-xs font-bold text-[var(--color-ink-soft)] uppercase tracking-widest">Titre</th>
            <th class="p-4 text-xs font-bold text-[var(--color-ink-soft)] uppercase tracking-widest">Sortie</th>
            <th class="p-4 text-xs font-bold text-[var(--color-ink-soft)] uppercase tracking-widest">Durée</th>
            <th class="p-4 text-xs font-bold text-[var(--color-ink-soft)] uppercase tracking-widest text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="movie in movies" :key="movie.id" class="border-b border-[var(--color-rule)] hover:bg-[var(--color-paper-sunk)] transition-colors">
            <td class="p-4 text-[var(--color-ink)] font-medium">{{ movie.name }}</td>
            <td class="p-4 text-[var(--color-ink-soft)] text-sm">{{ movie.releaseDate ? new Date(movie.releaseDate).getFullYear() : '-' }}</td>
            <td class="p-4 text-[var(--color-ink-soft)] text-sm">{{ movie.duration }} min</td>
            <td class="p-4 text-right">
              <button @click="editMovie(movie)" class="text-[var(--color-night)] hover:text-[var(--color-ink)] text-xs font-bold uppercase tracking-wider transition-colors border border-[var(--color-rule)] hover:bg-[var(--color-paper-sunk)] px-3 py-1.5 rounded mr-2">
                Modifier
              </button>
              <button @click="confirmDelete(movie)" class="text-[var(--color-danger)] hover:text-[var(--color-danger)] text-xs font-bold uppercase tracking-wider transition-colors border border-[var(--color-danger)]/40 hover:bg-[var(--color-danger)]/10 px-3 py-1.5 rounded">
                Supprimer
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

    <MovieForm
      v-if="showForm"
      :movie="selectedMovie"
      @close="showForm = false"
      @refresh="onFormSaved"
    />
    <ConfirmDelete
      v-if="showConfirm"
      :movie="movieToDelete"
      @cancel="showConfirm = false"
      @confirm="deleteMovie"
    />
  </div>
</template>
