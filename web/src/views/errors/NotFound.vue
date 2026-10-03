<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

const suggestions = [
    { label: 'Films', to: '/movies', hint: 'Tout le catalogue' },
    { label: 'Interprètes', to: '/actors', hint: 'Index alphabétique' },
    { label: 'Réalisateurs', to: '/directors', hint: 'Index alphabétique' },
    { label: 'Genres', to: '/categories', hint: 'Par classement' },
]
</script>

<template>
    <div class="mx-auto max-w-[82rem] px-5 md:px-10">
        <header class="pt-20 md:pt-28">
            <p class="eyebrow mb-5">Erreur 404</p>
            <h1>Cette séance n'existe pas</h1>
            <p class="mt-8 text-lg leading-relaxed text-[var(--color-ink-soft)]">
                La page que vous cherchez a été déplacée, supprimée, ou n'a jamais
                été tournée.
            </p>
        </header>

        <div class="mt-10 flex flex-wrap gap-4">
            <router-link to="/" class="btn btn-primary">Retour à l'accueil</router-link>
            <button type="button" class="btn btn-quiet" @click="router.back()">
                Page précédente
            </button>
        </div>

        <hr class="rule-strong mt-16" />

        <nav aria-label="Suggestions de navigation">
            <router-link v-for="s in suggestions" :key="s.to" :to="s.to" class="index-row block">
                <span class="index-row__year">→</span>
                <span>
                    <span class="index-row__title">{{ s.label }}</span>
                    <span class="index-row__meta block">{{ s.hint }}</span>
                </span>
            </router-link>
        </nav>
    </div>
</template>
