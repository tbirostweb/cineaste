<script setup>
const reload = () => window.location.reload()
</script>

<template>
    <div class="mx-auto max-w-[82rem] px-5 md:px-10">
        <header class="pt-20 md:pt-28">
            <p class="eyebrow mb-5">Erreur 500</p>
            <h1>La pellicule a cassé</h1>
            <p class="mt-8 text-lg leading-relaxed text-[var(--color-ink-soft)]">
                Une erreur inattendue est survenue de notre côté. L'incident a été
                enregistré ; réessayez dans un instant.
            </p>
        </header>

        <div class="mt-10 flex flex-wrap gap-4">
            <button type="button" class="btn btn-primary" @click="reload">Réessayer</button>
            <router-link to="/" class="btn btn-quiet">Retour à l'accueil</router-link>
        </div>
    </div>
</template>
