/**
 * État de session côté navigateur.
 *
 * Le JWT n'est plus accessible au JavaScript : l'API le pose dans un cookie
 * HttpOnly, Secure, SameSite que le navigateur joint seul aux appels
 * (`withCredentials`). Un script injecté ne peut donc plus l'exfiltrer.
 *
 * Ce module ne conserve qu'un résumé NON sensible renvoyé par l'API à la
 * connexion (`session` : rôles, expiration) pour piloter l'affichage. C'est du
 * confort : l'autorisation réelle est rendue par l'API à chaque requête.
 */

export const SESSION_KEY = 'session'

// Clés héritées de l'ancien stockage du jeton : purgées à chaque nettoyage.
const LEGACY_KEYS = ['token', 'loggedIn', 'role']
const KEYS = [SESSION_KEY, 'userPhoto', ...LEGACY_KEYS]

const EMPTY = Object.freeze({ valid: false, isAdmin: false, roles: [], expiresAt: null })

function storage() {
    try {
        return typeof localStorage === 'undefined' ? null : localStorage
    } catch {
        return null
    }
}

/** Enregistre le résumé de session renvoyé par l'API (`response.data.session`). */
export function saveSession(summary) {
    const store = storage()
    if (!store || !summary || typeof summary !== 'object') return

    const roles = Array.isArray(summary.roles) ? summary.roles.filter((r) => typeof r === 'string') : []
    const expiresAt = Number(summary.expiresAt)
    if (!Number.isFinite(expiresAt)) return

    // Ancien jeton éventuellement resté d'une version précédente du front.
    LEGACY_KEYS.forEach((key) => store.removeItem(key))
    store.setItem(SESSION_KEY, JSON.stringify({ roles, expiresAt }))
}

export function readSession() {
    const store = storage()
    if (!store) return { ...EMPTY }

    try {
        const raw = store.getItem(SESSION_KEY)
        if (!raw) return { ...EMPTY }

        const { roles, expiresAt } = JSON.parse(raw)
        // expiresAt est exprimé en secondes (horodatage Unix de l'API).
        if (typeof expiresAt !== 'number' || expiresAt * 1000 <= Date.now()) {
            return { ...EMPTY }
        }

        const safeRoles = Array.isArray(roles) ? roles : []

        return {
            valid: true,
            isAdmin: safeRoles.includes('ROLE_ADMIN'),
            roles: safeRoles,
            expiresAt: expiresAt * 1000,
        }
    } catch {
        // Résumé illisible (altéré) : traité comme absent
        return { ...EMPTY }
    }
}

/**
 * Efface toute trace de session côté navigateur. Ne touche pas au choix de
 * mesure d'audience (clé de consentement) : un refus ne doit pas être perdu à
 * la déconnexion.
 */
export function clearSession() {
    const store = storage()
    if (!store) return
    KEYS.forEach((key) => store.removeItem(key))
}

/** Millisecondes restantes avant expiration (0 si pas de session valide). */
export function timeUntilExpiry() {
    const { valid, expiresAt } = readSession()
    if (!valid || !expiresAt) return 0
    return Math.max(0, expiresAt - Date.now())
}
