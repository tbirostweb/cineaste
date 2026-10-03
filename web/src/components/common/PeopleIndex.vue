<script setup>
import { computed, ref } from 'vue'
import { normalize } from '../../utils/text'
import { revealDelay } from '../../motion/reveal'

/**
 * Index alphabétique de personnes — interprètes ou réalisateurs.
 *
 * Regroupé par initiale plutôt que paginé : sur un catalogue de cette taille,
 * chercher un nom se fait à l'œil, et une pagination n'ajouterait qu'un clic
 * entre le lecteur et ce qu'il vient chercher.
 */
const props = defineProps({
    people: { type: Array, required: true },
    basePath: { type: String, required: true },
    loading: { type: Boolean, default: false },
    searchLabel: { type: String, default: 'Rechercher un nom…' },
})

const search = ref('')

const filtered = computed(() => {
    const needle = normalize(search.value.trim())
    if (!needle) return props.people
    return props.people.filter((p) =>
        `${normalize(p.firstname)} ${normalize(p.lastname)}`.includes(needle)
    )
})

/** Groupes { lettre, personnes }, triés, les noms classés par patronyme. */
const groups = computed(() => {
    const map = new Map()
    for (const person of filtered.value) {
        const letter = (normalize(person.lastname)[0] || '#').toUpperCase()
        if (!map.has(letter)) map.set(letter, [])
        map.get(letter).push(person)
    }
    return [...map.entries()]
        .sort(([a], [b]) => a.localeCompare(b, 'fr'))
        .map(([letter, list]) => ({
            letter,
            people: list.sort((a, b) =>
                String(a.lastname || '').localeCompare(String(b.lastname || ''), 'fr')
            ),
        }))
})

const letters = computed(() => groups.value.map((g) => g.letter))
</script>

<template>
    <div>
        <div class="mt-10">
            <label for="people-search" class="sr-only">{{ searchLabel }}</label>
            <input
                id="people-search"
                v-model="search"
                type="search"
                class="field"
                :placeholder="searchLabel"
                autocomplete="off"
            />
        </div>

        <!-- Sauts d'ancre par initiale -->
        <nav
            v-if="!loading && letters.length > 1"
            class="mt-6 flex flex-wrap gap-x-3 gap-y-1 border-b border-[var(--color-rule)] pb-5"
            aria-label="Aller à une lettre"
        >
            <a v-for="l in letters" :key="l" :href="`#lettre-${l}`" class="letter-jump">{{ l }}</a>
        </nav>

        <p class="data mt-5" role="status" aria-live="polite">
            {{ loading ? 'Chargement…' : `${filtered.length} nom${filtered.length > 1 ? 's' : ''}` }}
        </p>

        <div v-if="loading" class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="n in 18" :key="n" class="skeleton h-9"></div>
        </div>

        <p v-else-if="!filtered.length" class="py-20 text-center text-lg text-[var(--color-ink-soft)]">
            Aucun nom ne correspond à cette recherche.
        </p>

        <section
            v-for="(group, gi) in groups"
            v-else
            :key="group.letter"
            :id="`lettre-${group.letter}`"
            class="mt-12 scroll-mt-28"
        >
            <div class="flex items-baseline gap-5">
                <h2 class="display-l shrink-0">{{ group.letter }}</h2>
                <hr class="rule mt-0 flex-1" />
                <span class="data shrink-0">{{ group.people.length }}</span>
            </div>

            <ul class="mt-4 grid gap-x-10 gap-y-0.5 sm:grid-cols-2 lg:grid-cols-3">
                <li
                    v-for="(person, i) in group.people"
                    :key="person.id"
                    v-reveal="revealDelay(i + gi, 0.015)"
                >
                    <router-link :to="`${basePath}/${person.id}`" class="name-link">
                        <span class="name-link__last">{{ person.lastname }}</span>
                        <span class="name-link__first">{{ person.firstname }}</span>
                    </router-link>
                </li>
            </ul>
        </section>
    </div>
</template>

<style scoped>
.letter-jump {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    font-weight: 500;
    letter-spacing: 0.06em;
    color: var(--color-ink-faint);
    transition: color var(--duration-fast) linear;
}
.letter-jump:hover { color: var(--color-night); }

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
