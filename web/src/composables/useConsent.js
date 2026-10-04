import { computed, ref } from 'vue'

const STORAGE_KEY = 'cineaste.consent.v1'
// 6 mois = 184 jours maximum, plus une marge d'un jour
const MAX_DURATION_MS = 185 * 24 * 3600 * 1000

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
        if (!raw) return null
        const choice = JSON.parse(raw)
        const now = Date.now()
        // Choix sans date, date future/invalide, ou validité excédant 6 mois (+ marge) : invalidé
        if (
            !['granted', 'denied'].includes(choice?.value) ||
            !Number.isFinite(choice.decidedAt) ||
            !Number.isFinite(choice.expiresAt) ||
            choice.decidedAt > now ||
            choice.expiresAt <= now ||
            choice.expiresAt - choice.decidedAt > MAX_DURATION_MS
        ) {
            localStorage.removeItem(STORAGE_KEY)
            return null
        }
        return choice.value
    } catch {
        // Stockage indisponible (navigation privée verrouillée) : on redemande
        return null
    }
}

const state = ref(read())

const persist = (value) => {
    state.value = value
    try {
        const decided = new Date()
        const expires = new Date(decided)
        expires.setMonth(expires.getMonth() + 6)
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ value, decidedAt: decided.getTime(), expiresAt: expires.getTime() }))
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
