import { computed, ref } from 'vue'

const STORAGE_KEY = 'cineaste.consent.v1'

/**
 * Consentement cookies/traceurs.
 *
 * Trois états : `null` (pas encore choisi → on affiche le bandeau),
 * `'granted'`, `'denied'`. Tant que l'état n'est pas `'granted'`, AUCUN
 * script de mesure n'est injecté : c'est la contrainte RGPD/CNIL
 * (consentement préalable, refus aussi simple que l'acceptation).
 */
const read = () => {
    try {
        const raw = localStorage.getItem(STORAGE_KEY)
        return raw === 'granted' || raw === 'denied' ? raw : null
    } catch {
        // Stockage indisponible (navigation privée verrouillée) : on redemande
        return null
    }
}

const state = ref(read())

const persist = (value) => {
    state.value = value
    try {
        localStorage.setItem(STORAGE_KEY, value)
    } catch {
        /* le choix vaudra pour la session en cours uniquement */
    }
}

export function useConsent() {
    return {
        status: computed(() => state.value),
        needsChoice: computed(() => state.value === null),
        hasConsented: computed(() => state.value === 'granted'),
        accept: () => persist('granted'),
        decline: () => persist('denied'),
        /** Permet de rouvrir le bandeau depuis la politique de confidentialité. */
        reset: () => {
            state.value = null
            try {
                localStorage.removeItem(STORAGE_KEY)
            } catch {
                /* rien à nettoyer */
            }
        },
    }
}
