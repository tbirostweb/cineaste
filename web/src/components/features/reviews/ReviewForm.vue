<template>
  <div class="mt-12 bg-[var(--color-paper-raised)] p-6 md:p-8 rounded-xl border border-[var(--color-rule)]">
    <h2 class="text-2xl md:text-3xl font-bold text-[var(--color-ink)] mb-6">Laisser un avis</h2>

    <div v-if="!isLoggedIn" class="text-center py-8">
      <p class="text-[var(--color-ink-soft)] mb-4">Vous devez être connecté pour laisser un avis.</p>
      <router-link to="/connexion" class="btn btn-primary inline-block">
        Se connecter
      </router-link>
    </div>

    <form v-else @submit.prevent="submitReview" class="space-y-6">
      <!-- Rating -->
      <div>
        <label class="block text-[var(--color-ink-faint)] text-xs uppercase tracking-wider font-bold mb-3">Note</label>
        <div class="flex gap-2">
          <button
            v-for="n in 5"
            :key="n"
            type="button"
            @click="rating = n"
            @mouseenter="hoverRating = n"
            @mouseleave="hoverRating = 0"
            class="text-3xl focus:outline-none transition-transform hover:scale-110"
            :class="(hoverRating || rating) >= n ? 'text-[var(--color-night)]' : 'text-[var(--color-rule)]'"
            :aria-label="n + ' étoiles'"
          >
            ★
          </button>
        </div>
        <p v-if="errors.rating" class="text-[var(--color-danger)] text-xs mt-2">{{ errors.rating }}</p>
      </div>

      <!-- Comment -->
      <div>
        <label for="comment" class="block text-[var(--color-ink-faint)] text-xs uppercase tracking-wider font-bold mb-3">Commentaire</label>
        <textarea
          id="comment"
          v-model="comment"
          class="w-full p-4 rounded-lg bg-[var(--color-paper)] border border-[var(--color-rule)] text-[var(--color-ink)] focus:border-[var(--color-ink)] focus:ring-1 focus:ring-[var(--color-night)] outline-none transition-all resize-none"
          rows="4"
          placeholder="Partagez votre avis sur ce film..."
        ></textarea>
        <p v-if="errors.comment" class="text-[var(--color-danger)] text-xs mt-2">{{ errors.comment }}</p>
      </div>

      <!-- Global Error -->
      <div v-if="submitError" class="p-3 bg-[var(--color-danger)]/10 border border-[var(--color-danger)]/30 rounded text-[var(--color-danger)] text-sm text-center">
        {{ submitError }}
      </div>

      <!-- Submit Button -->
      <button
        type="submit"
        :disabled="isSubmitting"
        class="btn btn-primary w-full"
      >
        <span v-if="isSubmitting" class="w-4 h-4 border-2 border-[var(--color-paper)]/30 border-t-[var(--color-paper)] rounded-full animate-spin"></span>
        {{ isSubmitting ? 'Envoi...' : 'Publier l\'avis' }}
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import api from '/src/api/api.js';
import { useDataStore } from '../../../stores/useDataStore';
import { logger } from '../../../utils/logger'

const props = defineProps({
  movieId: {
    type: [Number, String],
    required: true,
  },
});

const emit = defineEmits(['review-submitted']);
const dataStore = useDataStore();

const rating = ref(0);
const hoverRating = ref(0);
const comment = ref('');
const isSubmitting = ref(false);
const submitError = ref('');
const errors = ref({});

const isLoggedIn = computed(() => localStorage.getItem('loggedIn') === 'true');

const validate = () => {
  errors.value = {};
  let isValid = true;

  if (rating.value === 0) {
    errors.value.rating = "Veuillez sélectionner une note.";
    isValid = false;
  }

  if (!comment.value.trim()) {
    errors.value.comment = "Veuillez écrire un commentaire.";
    isValid = false;
  } else if (comment.value.length < 10) {
    errors.value.comment = "Le commentaire doit faire au moins 10 caractères.";
    isValid = false;
  }

  return isValid;
};

const submitReview = async () => {
  if (!validate()) return;

  isSubmitting.value = true;
  submitError.value = '';

  try {
    // On s'assure d'avoir l'utilisateur courant
    if (!dataStore.user) {
        await dataStore.fetchUser();
    }

    // Si toujours pas d'utilisateur, on ne peut pas poster
    if (!dataStore.user || !dataStore.user.id) {
        submitError.value = "Impossible d'identifier l'utilisateur. Veuillez vous reconnecter.";
        isSubmitting.value = false;
        return;
    }

    const movieIdInt = parseInt(props.movieId);
    const userIri = `/api/users/${dataStore.user.id}`;

    await api.post('/reviews', {
      rating: rating.value,
      comment: comment.value,
      movie: `/api/movies/${movieIdInt}`,
      user: userIri // Envoi explicite de l'utilisateur
    }, {
      headers: {
        'Content-Type': 'application/ld+json'
      }
    });

    emit('review-submitted');

    // Reset form
    rating.value = 0;
    comment.value = '';

  } catch (error) {
    logger.error('Error submitting review', error);
    if (error.response && error.response.status === 401) {
        submitError.value = "Votre session a expiré. Veuillez vous reconnecter.";
    } else if (error.response && error.response.status === 500) {
        // Souvent une contrainte d'unicité ou une erreur SQL
        submitError.value = "Erreur serveur. Avez-vous déjà noté ce film ?";
    } else if (error.response && error.response.data && error.response.data['hydra:description']) {
        submitError.value = error.response.data['hydra:description'];
    } else {
        submitError.value = "Une erreur est survenue lors de l'envoi de l'avis.";
    }
  } finally {
    isSubmitting.value = false;
  }
};
</script>
