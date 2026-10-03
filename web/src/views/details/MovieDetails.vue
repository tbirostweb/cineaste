<script setup>
import { computed, ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '/src/api/api.js'
import ReviewList from '../../components/features/reviews/ReviewList.vue'
import ReviewForm from '../../components/features/reviews/ReviewForm.vue'
import { logger } from '../../utils/logger'

const route = useRoute()
const router = useRouter()
const movie = ref(null)
const categories = ref([])
const reviews = ref([])
const loading = ref(true)
const loadingCategories = ref(true)
const loadingReviews = ref(true)

/** Date de sortie en toutes lettres, ou tiret cadratin si absente. */
const releaseLabel = computed(() => {
  const raw = movie.value?.releaseDate
  if (!raw) return '—'
  const d = new Date(raw)
  return isNaN(d)
    ? '—'
    : d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' })
})

const categoryShortNames = {
  'Documentaire': 'Docu',
  'Science Fiction': 'SF',
  'Science-Fiction': 'SF'
}

const getShortCategoryName = (categoryName) => {
  return categoryShortNames[categoryName] || categoryName
}

const loadActorData = async (actorIriOrObject) => {
  if (typeof actorIriOrObject === 'object') return actorIriOrObject

  const id = actorIriOrObject.split('/').pop()
  try {
    const res = await api.get(`/actors/${id}`)
    return res.data
  } catch (e) {
    logger.error('Erreur chargement acteur', id, e)
    return null
  }
}

const fetchCategories = async (categoryUrls) => {
  if (!categoryUrls || categoryUrls.length === 0) {
    loadingCategories.value = false
    return
  }

  try {
    const categoryPromises = categoryUrls.map(async (categoryUrl) => {
      const path = categoryUrl.replace('/api', '')
      const response = await api.get(path)
      return response.data
    })

    categories.value = await Promise.all(categoryPromises)
  } catch (error) {
    logger.error('Erreur lors du chargement des catégories', error)
    categories.value = []
  } finally {
    loadingCategories.value = false
  }
}

const fetchUserDetails = async (userIri) => {
  if (!userIri || typeof userIri !== 'string') return null;
  try {
    const res = await api.get(userIri.replace('/api', ''));
    return res.data;
  } catch (e) {
    logger.error('Erreur chargement utilisateur', e);
    return null;
  }
};

const fetchReviews = async () => {
  loadingReviews.value = true
  try {
    const response = await api.get('/reviews', {
      params: {
        movie: route.params.id,
        'groups[]': ['review:read']
      }
    })

    const data = response.data['hydra:member'] || response.data['member'] || []

    // Enrichir les avis avec les détails de l'utilisateur
    const enrichedReviews = await Promise.all(data.map(async (review) => {
      if (review.user && typeof review.user === 'string') {
        const userDetails = await fetchUserDetails(review.user);
        return { ...review, user: userDetails };
      }
      return review;
    }));

    reviews.value = enrichedReviews

  } catch (error) {
    logger.error('Erreur lors du chargement des avis', error)
    reviews.value = []
  } finally {
    loadingReviews.value = false
  }
}

onMounted(async () => {
  try {
    const res = await api.get(`/movies/${route.params.id}`, {
      params: {
        'groups[]': ['movie:read', 'movie:detail'],
      }
    })
    const movieData = res.data

    if (movieData.actors && movieData.actors.length > 0) {
      const actorsPromises = movieData.actors.map(loadActorData)
      const loadedActors = await Promise.all(actorsPromises)
      movieData.actors = loadedActors.filter(a => a !== null)
    }

    if (movieData.categories && movieData.categories.length > 0) {
      await fetchCategories(movieData.categories)
    } else {
      loadingCategories.value = false
    }

    movie.value = movieData
    await fetchReviews()

  } catch (err) {
    logger.error('Erreur lors du chargement du film', err)
  } finally {
    loading.value = false
  }
})
</script>

<template>
    <div class="mx-auto max-w-[82rem] px-5 md:px-10">
        <div v-if="loading" class="pt-20">
            <div class="skeleton h-6 w-40"></div>
            <div class="skeleton mt-6 h-24 w-full max-w-3xl"></div>
            <div class="skeleton mt-10 h-64 w-full"></div>
        </div>

        <template v-else-if="movie">
            <button type="button" class="nav-back mt-10" @click="router.back()">← Retour</button>

            <header class="mt-8 border-b-2 border-[var(--color-rule-strong)] pb-10">
                <p class="eyebrow mb-5">Fiche film</p>
                <h1>{{ movie.name }}</h1>
            </header>

            <div class="grid gap-14 pt-10 lg:grid-cols-[1fr_20rem] lg:gap-20">
                <!-- Colonne principale -->
                <div>
                    <p
                        v-if="movie.description"
                        class="text-lg leading-relaxed text-[var(--color-ink-soft)]"
                    >
                        {{ movie.description }}
                    </p>
                    <p v-else class="text-[var(--color-ink-faint)]">
                        Aucun synopsis n'est renseigné pour ce film.
                    </p>

                    <!-- Distribution -->
                    <section v-if="movie.actors?.length" class="mt-14">
                        <div class="flex items-baseline gap-5">
                            <h2 class="display-l shrink-0">Distribution</h2>
                            <hr class="rule flex-1" />
                            <span class="data shrink-0">{{ movie.actors.length }}</span>
                        </div>

                        <ul class="mt-4 grid gap-x-10 gap-y-0.5 sm:grid-cols-2">
                            <li v-for="actor in movie.actors" :key="actor.id">
                                <router-link :to="`/actors/${actor.id}`" class="name-link">
                                    <span class="name-link__last">{{ actor.lastname }}</span>
                                    <span class="name-link__first">{{ actor.firstname }}</span>
                                </router-link>
                            </li>
                        </ul>
                    </section>

                    <!-- Avis -->
                    <section class="mt-16">
                        <div class="flex items-baseline gap-5">
                            <h2 class="display-l shrink-0">Avis</h2>
                            <hr class="rule flex-1" />
                            <span class="data shrink-0">{{ reviews.length }}</span>
                        </div>

                        <div class="mt-6">
                            <ReviewForm :movie-id="route.params.id" @submitted="fetchReviews" />
                            <ReviewList
                                :reviews="reviews"
                                :loading="loadingReviews"
                                class="mt-8"
                                @refresh="fetchReviews"
                            />
                        </div>
                    </section>
                </div>

                <!-- Colonne technique -->
                <aside class="lg:sticky lg:top-24 lg:self-start">
                    <div class="slab">
                        <p class="data mb-5">Fiche technique</p>

                        <dl class="spec">
                            <dt>Sortie</dt>
                            <dd>{{ releaseLabel }}</dd>

                            <dt>Durée</dt>
                            <dd>{{ movie.duration ? `${movie.duration} min` : '—' }}</dd>

                            <dt>Rôles</dt>
                            <dd>{{ movie.actors?.length ?? movie.actorCount ?? '—' }}</dd>

                            <dt v-if="movie.director">Réalisation</dt>
                            <dd v-if="movie.director">
                                {{ movie.director.firstname }} {{ movie.director.lastname }}
                            </dd>

                            <dt v-if="movie.entries || movie.nbEntries">Entrées</dt>
                            <dd v-if="movie.entries || movie.nbEntries">
                                {{ (movie.entries || movie.nbEntries).toLocaleString('fr-FR') }}
                            </dd>
                        </dl>

                        <div v-if="categories.length" class="mt-6 border-t border-[var(--color-rule)] pt-5">
                            <p class="data mb-3">Genres</p>
                            <div class="flex flex-wrap gap-2">
                                <router-link
                                    v-for="c in categories"
                                    :key="c.id"
                                    :to="`/movies?category=${c.id}`"
                                    class="chip"
                                >
                                    {{ getShortCategoryName(c.name) }}
                                </router-link>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </template>

        <div v-else class="py-24 text-center">
            <h1 class="display-l">Film introuvable</h1>
            <p class="mx-auto mt-4 text-[var(--color-ink-soft)]">
                Cette fiche n'existe pas, ou elle demande d'être connecté.
            </p>
            <router-link to="/movies" class="btn btn-quiet mt-8">Retour à l'index</router-link>
        </div>
    </div>
</template>

<style scoped>
.nav-back {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--color-ink-soft);
    background: none;
    border: 0;
    cursor: pointer;
    transition: color var(--duration-fast) linear;
}
.nav-back:hover { color: var(--color-ink); }

.name-link {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    padding: 0.45rem 0;
    border-bottom: 1px solid transparent;
    transition: border-color var(--duration-fast) linear;
}
.name-link:hover { border-bottom-color: var(--color-ink); }
.name-link__last {
    font-family: var(--font-display);
    font-size: 1.125rem;
    font-weight: 700;
    text-transform: uppercase;
    line-height: 1;
}
.name-link__first {
    font-family: var(--font-body);
    font-size: 0.9375rem;
    color: var(--color-ink-soft);
}
</style>
