import { jwtDecode } from 'jwt-decode'

const KEYS = ['token', 'loggedIn', 'role', 'userPhoto']

/**
 * État de session dérivé du JWT lui-même plutôt que d'un drapeau
 * `localStorage.loggedIn`. Le jeton porte son expiration et ses rôles :
 * on évite ainsi d'afficher une interface "connecté" avec un jeton périmé,
 * et de proposer l'onglet Admin à qui a simplement écrit `role=admin`.
 *
 * À garder en tête : c'est du confort d'affichage. L'autorisation réelle est
 * rendue par l'API à chaque requête — jamais par ce fichier.
 */
export function readSession() {
    const token = localStorage.getItem('token')
    if (!token) return { valid: false, isAdmin: false, roles: [], email: null, token: null }

    try {
        const payload = jwtDecode(token)

        // Jeton intermédiaire de la 2FA : il ne vaut pas une session ouverte
        if (payload['2fa_pending'] === true) {
            return { valid: false, isAdmin: false, roles: [], email: null, token: null }
        }

        const expired = typeof payload.exp === 'number' && payload.exp * 1000 <= Date.now()
        if (expired) {
            return { valid: false, isAdmin: false, roles: [], email: null, token: null }
        }

        const roles = Array.isArray(payload.roles) ? payload.roles : []

        return {
            valid: true,
            isAdmin: roles.includes('ROLE_ADMIN'),
            roles,
            email: payload.username ?? payload.email ?? null,
            token,
            expiresAt: payload.exp ? payload.exp * 1000 : null,
        }
    } catch {
        // Jeton illisible (tronqué, altéré) : traité comme absent
        return { valid: false, isAdmin: false, roles: [], email: null, token: null }
    }
}

/** Efface toute trace de session côté navigateur. */
export function clearSession() {
    KEYS.forEach((key) => localStorage.removeItem(key))
}

/** Millisecondes restantes avant expiration (0 si pas de session valide). */
export function timeUntilExpiry() {
    const { valid, expiresAt } = readSession()
    if (!valid || !expiresAt) return 0
    return Math.max(0, expiresAt - Date.now())
}
