import { readonly, ref } from 'vue'

const QUERY = '(prefers-reduced-motion: reduce)'

/**
 * Préférence système de mouvement réduit, partagée par toute l'application
 * (une seule MediaQueryList). Source de vérité commune au CSS et au JavaScript.
 */
const reduced = ref(
    typeof window !== 'undefined' && window.matchMedia(QUERY).matches
)

if (typeof window !== 'undefined') {
    window.matchMedia(QUERY).addEventListener('change', (event) => {
        reduced.value = event.matches
    })
}

export function useMotionPreference() {
    return readonly(reduced)
}

export function prefersReducedMotion() {
    return reduced.value
}
