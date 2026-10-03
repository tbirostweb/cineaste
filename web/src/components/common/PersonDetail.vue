<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import IndexRow from './IndexRow.vue'

/**
 * Fiche d'une personne — interprète ou réalisateur.
 *
 * Même gabarit pour les deux : un nom en très grande taille, un encart de
 * données à droite, et la filmographie sous forme de lignes d'index. Les deux
 * pages ne différaient que par un libellé ; les factoriser évite de faire
 * diverger leur mise en page à la première retouche.
 */
const props = defineProps({
    person: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    /** 'Interprète' ou 'Réalisateur' */
    role: { type: String, required: true },
    /** Libellé de la section filmographie */
    worksLabel: { type: String, default: 'Filmographie' },
    backTo: { type: String, required: true },
})

const router = useRouter()

const fullName = computed(() =>
    props.person ? `${props.person.firstname ?? ''} ${props.person.lastname ?? ''}`.trim() : ''
)

const works = computed(() =>
    [...(props.person?.movies ?? [])].sort(
        (a, b) => new Date(b.releaseDate ?? 0) - new Date(a.releaseDate ?? 0)
    )
)

const formatDate = (raw) => {
    if (!raw) return '—'
    const d = new Date(raw)
    return isNaN(d)
        ? '—'
        : d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' })
}

const yearOf = (raw) => {
    const d = new Date(raw)
    return raw && !isNaN(d) ? d.getFullYear() : '——'
}

/** Âge, ou âge au décès si la date de mort est renseignée. */
const age = computed(() => {
    const dob = props.person?.dob
    if (!dob) return null
    const birth = new Date(dob)
    if (isNaN(birth)) return null
    const end = props.person.dod ? new Date(props.person.dod) : new Date()
    let years = end.getFullYear() - birth.getFullYear()
    const m = end.getMonth() - birth.getMonth()
    if (m < 0 || (m === 0 && end.getDate() < birth.getDate())) years -= 1
    return years
})
</script>

<template>
    <div class="mx-auto max-w-[82rem] px-5 md:px-10">
        <div v-if="loading" class="pt-20">
            <div class="skeleton h-6 w-40"></div>
            <div class="skeleton mt-6 h-28 w-full max-w-2xl"></div>
            <div class="skeleton mt-10 h-64 w-full"></div>
        </div>

        <template v-else-if="person">
            <button type="button" class="nav-back mt-10" @click="router.back()">← Retour</button>

            <header class="mt-8 border-b-2 border-[var(--color-rule-strong)] pb-10">
                <p class="eyebrow mb-5">{{ role }}</p>
                <h1>{{ fullName }}</h1>
            </header>

            <div class="grid gap-14 pt-10 lg:grid-cols-[1fr_20rem] lg:gap-20">
                <div>
                    <p
                        v-if="person.bio || person.description"
                        class="text-lg leading-relaxed text-[var(--color-ink-soft)]"
                    >
                        {{ person.bio || person.description }}
                    </p>
                    <p v-else class="text-[var(--color-ink-faint)]">
                        Aucune biographie n'est renseignée.
                    </p>

                    <section class="mt-14">
                        <div class="flex items-baseline gap-5">
                            <h2 class="display-l shrink-0">{{ worksLabel }}</h2>
                            <hr class="rule flex-1" />
                            <span class="data shrink-0">{{ works.length }}</span>
                        </div>

                        <hr class="rule-strong mt-4" />

                        <div v-if="works.length">
                            <IndexRow
                                v-for="movie in works"
                                :key="movie.id"
                                :lead="yearOf(movie.releaseDate)"
                                :title="movie.name"
                                :trail="movie.duration ? [`${movie.duration} min`] : []"
                                @click="router.push(`/movies/${movie.id}`)"
                                @keydown.enter="router.push(`/movies/${movie.id}`)"
                            />
                        </div>

                        <p v-else class="py-10 text-[var(--color-ink-faint)]">
                            Aucun film rattaché pour l'instant.
                        </p>
                    </section>
                </div>

                <aside class="lg:sticky lg:top-24 lg:self-start">
                    <div class="slab">
                        <p class="data mb-5">Repères</p>
                        <dl class="spec">
                            <dt>Naissance</dt>
                            <dd>{{ formatDate(person.dob) }}</dd>

                            <template v-if="person.dod">
                                <dt>Décès</dt>
                                <dd>{{ formatDate(person.dod) }}</dd>
                            </template>

                            <template v-if="age !== null">
                                <dt>{{ person.dod ? 'Âge au décès' : 'Âge' }}</dt>
                                <dd>{{ age }} ans</dd>
                            </template>

                            <dt>Films</dt>
                            <dd>{{ works.length }}</dd>

                            <template v-if="person.nationality">
                                <dt>Nationalité</dt>
                                <dd>{{ person.nationality }}</dd>
                            </template>
                        </dl>
                    </div>
                </aside>
            </div>
        </template>

        <div v-else class="py-24 text-center">
            <h1 class="display-l">Fiche introuvable</h1>
            <p class="mx-auto mt-4 text-[var(--color-ink-soft)]">
                Cette fiche n'existe pas, ou elle demande d'être connecté.
            </p>
            <router-link :to="backTo" class="btn btn-quiet mt-8">Retour à l'index</router-link>
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
</style>
