<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '../../../api/api'
import { logger } from '../../../utils/logger'

const props = defineProps({
  userId: {
    type: [Number, String],
    required: true
  }
})

const reviews = ref([])
const loading = ref(true)
const editingReview = ref(null)
const editComment = ref('')
const editRating = ref(0)

// Pagination
const page = ref(1)
const totalItems = ref(0)
const itemsPerPage = 10
const totalPages = computed(() => Math.ceil(totalItems.value / itemsPerPage))

const fetchUserReviews = async () => {
  loading.value = true
  try {
    // Correction : Utilisation de l'IRI complet pour le filtre utilisateur
    const userIri = `/api/users/${props.userId}`;
    const response = await api.get('/reviews', {
      params: {
        user: userIri,
        page: page.value,
        itemsPerPage: itemsPerPage,
        'groups[]': ['review:read']
      }
    })

    const data = response.data
    let items = data['hydra:member'] || data['member'] || []

    // Enrichir avec les noms de films si nécessaire (comme dans l'admin)
    const enrichedItems = await Promise.all(items.map(async (review) => {
        if (review.movie && typeof review.movie === 'string') {
            try {
                const id = review.movie.split('/').pop();
                const res = await api.get(`/movies/${id}`);
                return { ...review, movie: res.data };
            } catch {
                return review;
            }
        }
        return review;
    }));

    reviews.value = enrichedItems
    totalItems.value = data['hydra:totalItems'] || data['totalItems'] || reviews.value.length

  } catch (err) {
    logger.error('Erreur chargement avis utilisateur', err)
  } finally {
    loading.value = false
  }
}

const deleteReview = async (reviewId) => {
  if (!confirm("Êtes-vous sûr de vouloir supprimer cet avis ?")) return
  try {
    await api.delete(`/reviews/${reviewId}`)
    fetchUserReviews()
  } catch (err) {
    logger.error('Erreur suppression avis', err)
    alert("Impossible de supprimer l'avis.")
  }
}

const startEdit = (review) => {
  editingReview.value = review.id
  editComment.value = review.comment
  editRating.value = review.rating
}

const cancelEdit = () => {
  editingReview.value = null
  editComment.value = ''
  editRating.value = 0
}

const saveEdit = async (reviewId) => {
  try {
    await api.patch(`/reviews/${reviewId}`, {
      comment: editComment.value,
      rating: editRating.value
    }, {
      headers: { 'Content-Type': 'application/merge-patch+json' }
    })
    editingReview.value = null
    fetchUserReviews()
  } catch (err) {
    logger.error('Erreur modification avis', err)
    alert("Impossible de modifier l'avis.")
  }
}

const changePage = (newPage) => {
  if (newPage >= 1 && newPage <= totalPages.value) {
    page.value = newPage
    fetchUserReviews()
  }
}

onMounted(() => {
  if (props.userId) {
    fetchUserReviews()
  }
})
</script>

<template>
  <div class="bg-[var(--color-paper-raised)] border border-[var(--color-rule)] rounded-lg p-8 space-y-6">
    <div class="flex justify-between items-center border-b border-[var(--color-rule)] pb-4 mb-4">
      <h2 class="text-[var(--color-ink)] font-bold text-2xl ">Mes Avis</h2>
      <span class="text-sm text-[var(--color-ink-faint)]">Total: <span class="text-[var(--color-night)] font-bold">{{ totalItems }}</span></span>
    </div>

    <div v-if="loading" class="text-center py-8 text-[var(--color-ink-faint)]">
      Chargement de vos avis...
    </div>

    <div v-else-if="reviews.length > 0" class="space-y-6">
      <div v-for="review in reviews" :key="review.id" class="bg-[var(--color-paper)] p-4 rounded-lg border border-[var(--color-rule)]">

        <!-- Mode Lecture -->
        <div v-if="editingReview !== review.id">
          <div class="flex justify-between items-start mb-2">
            <h3 class="text-[var(--color-ink)] font-bold text-lg">
              <router-link v-if="review.movie?.id" :to="`/movies/${review.movie.id}`" class="hover:text-[var(--color-night)] transition-colors">
                {{ review.movie?.name || 'Film inconnu' }}
              </router-link>
              <span v-else>Film inconnu</span>
            </h3>
            <div class="flex text-[var(--color-night)] text-sm">
              <span v-for="n in 5" :key="n">{{ n <= review.rating ? '★' : '☆' }}</span>
            </div>
          </div>
          <p class="text-[var(--color-ink-soft)] text-sm mb-4">{{ review.comment }}</p>
          <div class="flex gap-3 justify-end">
            <button @click="startEdit(review)" class="text-xs font-bold text-[var(--color-night)] hover:text-[var(--color-ink)] uppercase tracking-wider transition-colors">Modifier</button>
            <button @click="deleteReview(review.id)" class="text-xs font-bold text-[var(--color-danger)] hover:text-[var(--color-danger)] uppercase tracking-wider transition-colors">Supprimer</button>
          </div>
        </div>

        <!-- Mode Édition -->
        <div v-else class="space-y-4">
          <div class="flex justify-between items-center">
            <h3 class="text-[var(--color-ink)] font-bold">{{ review.movie?.name }}</h3>
            <div class="flex gap-1">
              <button
                v-for="n in 5"
                :key="n"
                type="button"
                @click="editRating = n"
                class="text-xl focus:outline-none transition-transform hover:scale-110"
                :class="editRating >= n ? 'text-[var(--color-night)]' : 'text-[var(--color-rule)]'"
              >★</button>
            </div>
          </div>
          <textarea
            v-model="editComment"
            class="w-full p-3 rounded bg-[var(--color-paper-raised)] border border-[var(--color-rule)] text-[var(--color-ink)] focus:border-[var(--color-ink)] outline-none text-sm"
            rows="3"
          ></textarea>
          <div class="flex gap-3 justify-end">
            <button @click="cancelEdit" class="px-4 py-2 text-xs font-bold text-[var(--color-ink-soft)] border border-[var(--color-rule)] rounded hover:bg-[var(--color-paper-sunk)] transition-colors">Annuler</button>
            <button @click="saveEdit(review.id)" class="btn btn-primary">Enregistrer</button>
          </div>
        </div>

      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex justify-center items-center gap-4 pt-4">
        <button
          @click="changePage(page - 1)"
          :disabled="page === 1"
          class="px-3 py-1.5 bg-[var(--color-paper-raised)] border border-[var(--color-rule)] rounded text-[var(--color-ink)] hover:border-[var(--color-ink)] disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-xs"
        >
          Précédent
        </button>
        <span class="text-[var(--color-ink-faint)] text-xs">Page {{ page }} / {{ totalPages }}</span>
        <button
          @click="changePage(page + 1)"
          :disabled="page === totalPages"
          class="px-3 py-1.5 bg-[var(--color-paper-raised)] border border-[var(--color-rule)] rounded text-[var(--color-ink)] hover:border-[var(--color-ink)] disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-xs"
        >
          Suivant
        </button>
      </div>
    </div>

    <div v-else class="text-center py-8 text-[var(--color-ink-faint)]">
      Vous n'avez pas encore posté d'avis.
    </div>
  </div>
</template>
