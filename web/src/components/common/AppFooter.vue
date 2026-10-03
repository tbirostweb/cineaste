<script setup>
const year = new Date().getFullYear()

const columns = [
    {
        title: 'Le catalogue',
        links: [
            { label: 'Films', to: '/movies' },
            { label: 'Interprètes', to: '/actors' },
            { label: 'Réalisateurs', to: '/directors' },
            { label: 'Genres', to: '/categories' },
        ],
    },
    {
        title: 'Votre compte',
        links: [
            { label: 'Connexion', to: '/connexion' },
            { label: 'Créer un compte', to: '/inscription' },
            { label: 'Mon profil', to: '/profile' },
        ],
    },
    {
        title: 'Informations',
        links: [
            { label: 'Mentions légales', to: '/mentions-legales' },
            { label: 'Confidentialité', to: '/confidentialite' },
        ],
    },
]
</script>

<template>
    <footer class="mt-28 border-t border-[var(--color-rule)]">
        <div class="mx-auto max-w-[82rem] px-5 py-16 md:px-10">
            <div class="grid gap-12 md:grid-cols-[1.6fr_repeat(3,1fr)]">
                <div>
                    <p class="font-[family-name:var(--font-display)] text-3xl font-extrabold uppercase leading-none">
                        Générique
                    </p>
                    <p class="mt-4 max-w-[34ch] text-sm leading-relaxed text-[var(--color-ink-soft)]">
                        Un index de films, d'interprètes et de réalisateurs. Les fiches sont
                        des données de démonstration, tenues à jour par sa communauté.
                    </p>
                </div>

                <nav v-for="column in columns" :key="column.title" :aria-label="column.title">
                    <h2 class="data mb-4">{{ column.title }}</h2>
                    <ul class="space-y-2.5">
                        <li v-for="link in column.links" :key="link.to">
                            <router-link :to="link.to" class="footer-link">
                                {{ link.label }}
                            </router-link>
                        </li>
                    </ul>
                </nav>
            </div>

            <hr class="rule my-10" />

            <div class="flex flex-col gap-2 sm:flex-row sm:items-baseline sm:justify-between">
                <p class="data">© {{ year }} Générique — projet étudiant</p>
                <p class="data">Vue 3 · Symfony · API Platform</p>
            </div>
        </div>
    </footer>
</template>

<style scoped>
.footer-link {
    font-family: var(--font-body);
    font-size: 0.9375rem;
    color: var(--color-ink-soft);
    border-bottom: 1px solid transparent;
    transition:
        color var(--duration-fast) linear,
        border-color var(--duration-fast) linear;
}
.footer-link:hover {
    color: var(--color-ink);
    border-bottom-color: var(--color-ink);
}
</style>
