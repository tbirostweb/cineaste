<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useDataStore } from '../../stores/useDataStore'
import { applyJsonLd, SITE_URL } from '../../composables/useSeo'
import { revealDelay } from '../../motion/reveal'
import IndexRow from '../../components/common/IndexRow.vue'

const router = useRouter()
const dataStore = useDataStore()
const loading = ref(true)

const programme = computed(() => dataStore.movies.slice(0, 7))
const troupe = computed(() => dataStore.actors.slice(0, 18))
const totals = computed(() => dataStore.totals)

const figures = computed(() => [
    { label: 'Films', value: totals.value.movies || '—' },
    { label: 'Interprètes', value: totals.value.actors || '—' },
    { label: 'Réalisateurs', value: totals.value.directors || '—' },
    { label: 'Genres', value: totals.value.categories || '—' },
])

const year = new Date().getFullYear()

const yearOf = (date) => {
    const d = new Date(date)
    return date && !isNaN(d) ? d.getFullYear() : '——'
}

/** Genres d'un film, résolus depuis les IRI que renvoie l'API. */
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

const open = (id) => router.push(`/movies/${id}`)

onMounted(async () => {
    applyJsonLd({
        '@context': 'https://schema.org',
        '@type': 'WebSite',
        name: 'Générique',
        url: SITE_URL,
        inLanguage: 'fr-FR',
        potentialAction: {
            '@type': 'SearchAction',
            target: `${SITE_URL}/movies?search={search_term_string}`,
            'query-input': 'required name=search_term_string',
        },
    })

    try {
        await Promise.all([
            dataStore.fetchMovies(),
            dataStore.fetchActors(),
            dataStore.fetchCategories(),
            dataStore.fetchDirectors(),
        ])
    } catch {
        // L'intercepteur signale déjà l'erreur ; on retire seulement l'attente
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <div class="mx-auto max-w-[82rem] px-5 md:px-10">
        <!-- Une -->
        <header class="grid gap-10 pt-14 md:grid-cols-[1.5fr_1fr] md:items-end md:pt-24">
            <div>
                <p class="eyebrow mb-6">Index du cinéma — édition {{ year }}</p>
                <h1>Générique</h1>
            </div>

            <p class="max-w-[34ch] text-lg leading-relaxed text-[var(--color-ink-soft)] md:pb-3">
                Un index de films, d'interprètes et de réalisateurs. Pas d'affiches,
                pas de bandes-annonces : des titres, des années, des durées et des
                distributions.
            </p>
        </header>

        <hr class="rule-strong mt-10" />

        <!-- Chiffres du catalogue : ils remplacent l'image d'en-tête absente,
             et disent quelque chose de vrai sur ce que contient le site. -->
        <dl class="figures grid grid-cols-2 gap-px bg-[var(--color-rule)] md:grid-cols-4">
            <div v-for="f in figures" :key="f.label" class="bg-[var(--color-paper)] py-8">
                <dt class="data mb-2">{{ f.label }}</dt>
                <dd class="font-[family-name:var(--font-display)] text-5xl font-extrabold leading-none tabular-nums md:text-6xl">
                    {{ f.value }}
                </dd>
            </div>
        </dl>

        <hr class="rule" />

        <!-- Au programme -->
        <section class="mt-20" aria-labelledby="programme">
            <div class="mb-6 flex items-end justify-between gap-6">
                <div>
                    <p class="eyebrow mb-3">Sélection</p>
                    <h2 id="programme">Au programme</h2>
                </div>
                <router-link to="/movies" class="btn btn-quiet shrink-0">Tout l'index</router-link>
            </div>

            <hr class="rule-strong" />

            <div v-if="loading" class="space-y-px pt-px">
                <div v-for="n in 7" :key="n" class="skeleton h-[5.5rem]"></div>
            </div>

            <p v-else-if="!programme.length" class="py-12 text-[var(--color-ink-soft)]">
                Le catalogue est vide pour le moment.
            </p>

            <div v-else>
                <IndexRow
                    v-for="(movie, i) in programme"
                    :key="movie.id"
                    v-reveal="revealDelay(i)"
                    :lead="yearOf(movie.releaseDate)"
                    :title="movie.name"
                    :meta="genresOf(movie)"
                    :trail="trailOf(movie)"
                    @click="open(movie.id)"
                    @keydown.enter="open(movie.id)"
                />
            </div>
        </section>

        <!-- Interprètes -->
        <section class="mt-24" aria-labelledby="troupe">
            <div class="mb-6 flex items-end justify-between gap-6">
                <div>
                    <p class="eyebrow mb-3">Distribution</p>
                    <h2 id="troupe">Interprètes</h2>
                </div>
                <router-link to="/actors" class="btn btn-quiet shrink-0">Tous les noms</router-link>
            </div>

            <hr class="rule-strong mb-6" />

            <ul v-if="!loading && troupe.length" class="grid gap-x-10 gap-y-1 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="(actor, i) in troupe" :key="actor.id" v-reveal="revealDelay(i, 0.02)">
                    <router-link :to="`/actors/${actor.id}`" class="name-link">
                        <span class="name-link__last">{{ actor.lastname }}</span>
                        <span class="name-link__first">{{ actor.firstname }}</span>
                    </router-link>
                </li>
            </ul>

            <div v-else-if="loading" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="n in 12" :key="n" class="skeleton h-8"></div>
            </div>
        </section>
    </div>
</template>

<style scoped>
/* Chaque filet vertical doit respirer des deux côtés : sans marge à gauche,
   il vient toucher le libellé de la colonne suivante. Les cellules en tête
   de rangée gardent le bord franc pour rester alignées sur la marge de page
   et sur le titre au-dessus. */
.figures > * {
    padding-inline: 1.75rem;
}
.figures > *:nth-child(odd) { padding-inline-start: 0; }
.figures > *:nth-child(even) { padding-inline-end: 0; }

@media (min-width: 768px) {
    .figures > * { padding-inline: 1.75rem; }
    .figures > *:first-child { padding-inline-start: 0; }
    .figures > *:last-child { padding-inline-end: 0; }
}

/*
 * Nom d'interprète : patronyme en display, prénom en labeur, sur la même ligne
 * de base. C'est la convention d'un générique, et cela donne une colonne qui se
 * parcourt du regard sans effort.
 */
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
    font-size: 1.25rem;
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
