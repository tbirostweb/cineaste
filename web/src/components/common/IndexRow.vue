<script setup>
import { computed } from 'vue'

/**
 * Ligne d'index — le composant central de la direction éditoriale.
 *
 * Sans affiche à montrer, ce sont les données qui composent la page : l'année
 * cale la colonne de gauche, le titre occupe la mesure, les informations
 * techniques ferment la ligne. C'est la mise en page d'un programme de salle,
 * et elle rend l'absence d'images non plus subie mais assumée.
 */
defineProps({
    /** Colonne de gauche : année, initiale, rang… */
    lead: { type: [String, Number], default: '' },
    title: { type: String, required: true },
    /** Ligne secondaire sous le titre. */
    meta: { type: String, default: '' },
    /** Informations de fin de ligne (durée, nombre de rôles…). */
    trail: { type: Array, default: () => [] },
    as: { type: String, default: 'div' },
})
</script>

<template>
    <component :is="as" class="index-row" tabindex="0" role="link">
        <span class="index-row__year">{{ lead }}</span>

        <span>
            <span class="index-row__title">{{ title }}</span>
            <span v-if="meta" class="index-row__meta block">{{ meta }}</span>
        </span>

        <span v-if="trail.length" class="index-row__trail">
            <span v-for="item in trail" :key="item">{{ item }}</span>
        </span>
    </component>
</template>
