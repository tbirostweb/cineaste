<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '../../api/api.js'
import { logger } from '../../utils/logger'
import PersonDetail from '../../components/common/PersonDetail.vue'

const route = useRoute()
const person = ref(null)
const loading = ref(true)

/** Les films arrivent sous forme d'IRI : on les résout pour l'index. */
const loadMovie = async (movieOrIri) => {
    if (typeof movieOrIri === 'object') return movieOrIri
    const id = String(movieOrIri).split('/').pop()
    try {
        const { data } = await api.get(`/movies/${id}`, {
            params: { 'groups[]': ['movie:read', 'movie:categories'] },
        })
        return data
    } catch (error) {
        logger.error('ActorDetails:movie', id, error)
        return null
    }
}

onMounted(async () => {
    try {
        const { data } = await api.get(`/actors/${route.params.id}`, {
            params: { 'groups[]': ['actor:read', 'actor:detail'] },
        })

        if (data.movies?.length) {
            const loaded = await Promise.all(data.movies.map(loadMovie))
            data.movies = loaded.filter(Boolean)
        }

        person.value = data
    } catch (error) {
        logger.error('ActorDetails', error)
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <PersonDetail
        :person="person"
        :loading="loading"
        role="Interprète"
        works-label="Filmographie"
        back-to="/actors"
    />
</template>
