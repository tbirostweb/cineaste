<script setup>
import { computed, onMounted, ref } from 'vue'
import { useDataStore } from '../../stores/useDataStore'
import PeopleIndex from '../../components/common/PeopleIndex.vue'

const dataStore = useDataStore()
const loading = ref(true)
const errorMessage = ref('')

const people = computed(() => dataStore.actors)

onMounted(async () => {
    try {
        await dataStore.fetchActors()
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
            <p class="eyebrow mb-5">Distribution</p>
            <h1>Interprètes</h1>
        </header>

        <p v-if="errorMessage" class="mt-8 text-[var(--color-danger)]">{{ errorMessage }}</p>

        <PeopleIndex
            :people="people"
            base-path="/actors"
            :loading="loading"
            search-label="Rechercher un interprète…"
        />
    </div>
</template>
