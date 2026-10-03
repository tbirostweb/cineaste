<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDataStore } from '../../stores/useDataStore'
import { applySeo } from '../../composables/useSeo'
import { revealDelay } from '../../motion/reveal'
import api from '../../api/api.js'
import IndexRow from '../../components/common/IndexRow.vue'

const PER_PAGE = 25
const SEARCH_DEBOUNCE_MS = 400

const router = useRouter()
const route = useRoute()
const dataStore = useDataStore()

const search = ref(typeof route.query.search === 'string' ? route.query.search : '')
const page = ref(Number(route.query.page) || 1)
const sort = ref(typeof route.query.sort === 'string' ? route.query.sort : 'name')
const loading = ref(true)
const movies = ref([])
const totalItems = ref(0)

const SORTS = [
    { key: 'name', label: 'Titre', param: { 'order[name]': 'asc' } },
    { key: 'recent', label: 'Plus récents', param: { 'order[releaseDate]': 'desc' } },
    { key: 'old', label: 'Plus anciens', param: { 'order[releaseDate]': 'asc' } },
    { key: 'long', label: 'Durée', param: { 'order[duration]': 'desc' } },
]

const totalPages = computed(() => Math.max(1, Math.ceil(totalItems.value / PER_PAGE)))
const activeGenre = computed(() =>
    dataStore.categories.find((c) => String(c.id) === String(route.query.category))
)

/**
 * Une requête par frappe saturait le limiteur de l'API (5 requêtes par minute
 * pour un visiteur anonyme). On attend une pause de saisie et on annule la
 * requête précédente.
 */
let debounceId = null
let controller = null

const fetchMovies = async () => {
    controller?.abort()
    controller = new AbortController()
    loading.value = true

    try {
        const params = {
            page: page.value,
            itemsPerPage: PER_PAGE,
            'groups[]': ['movie:read', 'movie:categories'],
            ...(SORTS.find((s) => s.key === sort.value)?.param ?? {}),
        }
        if (route.query.category) params['categories.id'] = route.query.category
        if (search.value.trim()) params.name = search.value.trim()

        const { data } = await api.get('/movies', { params, signal: controller.signal })
        movies.value = data['hydra:member'] ?? data.member ?? []
        totalItems.value = data['hydra:totalItems'] ?? data.totalItems ?? movies.value.length
    } catch (error) {
        if (error.name === 'CanceledError' || error.code === 'ERR_CANCELED') return
        movies.value = []
        totalItems.value = 0
    } finally {
        if (!controller?.signal.aborted) loading.value = false
    }
}

const yearOf = (date) => {
    const d = new Date(date)
    return date && !isNaN(d) ? d.getFullYear() : '——'
}

const genresOf = (movie) => {
    if (!movie.categories?.length) return ''
    return movie.categories
        .map((iri) => dataStore.categories.find((c) => `/api/categories/${c.id}` === iri))
        .filter(Boolean)
        .slice(0, 2)
        .map((c) => c.name)
        .join(' · ')
}

const trailOf = (movie) => {
    const trail = []
    if (movie.duration) trail.push(`${movie.duration} min`)
    if (movie.actorCount) trail.push(`${movie.actorCount} rôles`)
    return trail
}

/** L'état de la vue vit dans l'URL : la recherche devient partageable. */
const syncUrl = (extra = {}) => {
    const query = { ...route.query, ...extra }
    if (search.value.trim()) query.search = search.value.trim()
    else delete query.search
    if (page.value > 1) query.page = String(page.value)
    else delete query.page
    if (sort.value !== 'name') query.sort = sort.value
    else delete query.sort
    router.replace({ query })
}

const setGenre = (id) => {
    page.value = 1
    const query = { ...route.query }
    if (id && String(id) !== String(route.query.category)) query.category = String(id)
    else delete query.category
    delete query.page
    router.replace({ query })
}

const setSort = (key) => {
    sort.value = key
    page.value = 1
    syncUrl()
    fetchMovies()
}

const goToPage = (next) => {
    page.value = Math.min(totalPages.value, Math.max(1, next))
    syncUrl()
    fetchMovies()
    window.scrollTo({ top: 0, behavior: 'auto' })
}

watch(search, () => {
    page.value = 1
    clearTimeout(debounceId)
    debounceId = setTimeout(() => {
        syncUrl()
        fetchMovies()
    }, SEARCH_DEBOUNCE_MS)
})

watch(
    () => route.query.category,
    () => {
        page.value = 1
        applySeo({
            title: activeGenre.value ? `Films — ${activeGenre.value.name}` : 'Films',
            path: route.path,
        })
        fetchMovies()
    }
)

onMounted(async () => {
    dataStore.fetchCategories().catch(() => {})
    await fetchMovies()
})

onUnmounted(() => {
    clearTimeout(debounceId)
    controller?.abort()
})
</script>

<template>
    <div class="mx-auto max-w-[82rem] px-5 md:px-10">
        <header class="pt-14 md:pt-20">
            <p class="eyebrow mb-5">Catalogue</p>
            <h1>{{ activeGenre ? activeGenre.name : 'Films' }}</h1>
        </header>

        <!-- Recherche : un seul champ, à la taille d'un titre -->
        <div class="mt-10">
            <label for="movie-search" class="sr-only">Rechercher un film</label>
            <input
                id="movie-search"
                v-model="search"
                type="search"
                class="field"
                placeholder="Rechercher un titre…"
                autocomplete="off"
            />
        </div>

        <!-- Filtres et tri -->
        <div class="mt-8 flex flex-col gap-5 border-b border-[var(--color-rule)] pb-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                <span class="data mr-2">Genre</span>
                <button
                    type="button"
                    class="chip"
                    :class="{ 'is-active': !route.query.category }"
                    @click="setGenre(null)"
                >
                    Tous
                </button>
                <button
                    v-for="c in dataStore.categories"
                    :key="c.id"
                    type="button"
                    class="chip"
                    :class="{ 'is-active': String(route.query.category) === String(c.id) }"
                    @click="setGenre(c.id)"
                >
                    {{ c.name }}
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2 lg:shrink-0">
                <span class="data mr-2">Trier</span>
                <button
                    v-for="s in SORTS"
                    :key="s.key"
                    type="button"
                    class="chip"
                    :class="{ 'is-active': sort === s.key }"
                    @click="setSort(s.key)"
                >
                    {{ s.label }}
                </button>
            </div>
        </div>

        <p class="data mt-5" role="status" aria-live="polite">
            {{ loading ? 'Recherche…' : `${totalItems} film${totalItems > 1 ? 's' : ''}` }}
        </p>

        <!-- Index -->
        <div class="mt-4">
            <hr class="rule-strong" />

            <div v-if="loading" class="space-y-px pt-px">
                <div v-for="n in 10" :key="n" class="skeleton h-[5.5rem]"></div>
            </div>

            <div v-else-if="movies.length">
                <IndexRow
                    v-for="(movie, i) in movies"
                    :key="movie.id"
                    v-reveal="revealDelay(i, 0.02)"
                    :lead="yearOf(movie.releaseDate)"
                    :title="movie.name"
                    :meta="genresOf(movie)"
                    :trail="trailOf(movie)"
                    @click="router.push(`/movies/${movie.id}`)"
                    @keydown.enter="router.push(`/movies/${movie.id}`)"
                />
            </div>

            <div v-else class="py-20 text-center">
                <p class="mx-auto text-lg text-[var(--color-ink-soft)]">
                    Aucun film ne correspond à cette recherche.
                </p>
                <button
                    v-if="search || route.query.category"
                    type="button"
                    class="btn btn-quiet mt-6"
                    @click="search = ''; setGenre(null)"
                >
                    Réinitialiser
                </button>
            </div>
        </div>

        <!-- Pagination -->
        <nav
            v-if="totalPages > 1 && !loading"
            class="mt-12 flex items-center justify-between gap-6"
            aria-label="Pagination"
        >
            <button
                type="button"
                class="btn btn-quiet"
                :disabled="page === 1"
                @click="goToPage(page - 1)"
            >
                ← Précédent
            </button>

            <p class="data" role="status" aria-live="polite">
                Page {{ page }} / {{ totalPages }}
            </p>

            <button
                type="button"
                class="btn btn-quiet"
                :disabled="page === totalPages"
                @click="goToPage(page + 1)"
            >
                Suivant →
            </button>
        </nav>
    </div>
</template>
