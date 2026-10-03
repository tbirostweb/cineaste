<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useDataStore } from '../../stores/useDataStore'
import { normalize } from '../../utils/text'
import { revealDelay } from '../../motion/reveal'
import IndexRow from '../../components/common/IndexRow.vue'

const router = useRouter()
const dataStore = useDataStore()
const search = ref('')
const loading = ref(true)
const errorMessage = ref('')

/** Genres classés par volume : l'ossature du catalogue apparaît d'un regard. */
const genres = computed(() => {
    const needle = normalize(search.value.trim())
    return dataStore.categories
        .filter((c) => !needle || normalize(c.name).includes(needle))
        .slice()
        .sort((a, b) => (b.moviesCount || 0) - (a.moviesCount || 0))
})

onMounted(async () => {
    try {
        await dataStore.fetchCategories()
    } catch (error) {
        errorMessage.value = error.response
            ? `Le serveur a répondu ${error.response.status}.`
            : "Impossible de joindre le serveur pour l'instant."
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <div class="mx-auto max-w-[82rem] px-5 md:px-10">
        <header class="pt-14 md:pt-20">
            <p class="eyebrow mb-5">Classement</p>
            <h1>Genres</h1>
        </header>

        <div class="mt-10">
            <label for="genre-search" class="sr-only">Rechercher un genre</label>
            <input
                id="genre-search"
                v-model="search"
                type="search"
                class="field"
                placeholder="Rechercher un genre…"
                autocomplete="off"
            />
        </div>

        <p class="data mt-6" role="status" aria-live="polite">
            {{ loading ? 'Chargement…' : `${genres.length} genre${genres.length > 1 ? 's' : ''}` }}
        </p>

        <p v-if="errorMessage" class="mt-6 text-[var(--color-danger)]">{{ errorMessage }}</p>

        <div class="mt-4">
            <hr class="rule-strong" />

            <div v-if="loading" class="space-y-px pt-px">
                <div v-for="n in 10" :key="n" class="skeleton h-[5.5rem]"></div>
            </div>

            <div v-else-if="genres.length">
                <IndexRow
                    v-for="(genre, i) in genres"
                    :key="genre.id"
                    v-reveal="revealDelay(i, 0.025)"
                    :lead="String(i + 1).padStart(2, '0')"
                    :title="genre.name"
                    :trail="[`${genre.moviesCount || 0} film${(genre.moviesCount || 0) > 1 ? 's' : ''}`]"
                    @click="router.push(`/movies?category=${genre.id}`)"
                    @keydown.enter="router.push(`/movies?category=${genre.id}`)"
                />
            </div>

            <p v-else class="py-20 text-center text-lg text-[var(--color-ink-soft)]">
                Aucun genre ne correspond à cette recherche.
            </p>
        </div>
    </div>
</template>
