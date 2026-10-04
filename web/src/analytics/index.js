/**
 * Chargement différé de la mesure d'audience.
 *
 * Le script n'est injecté qu'après un consentement explicite ET si un domaine
 * de mesure est configuré. Sans variable d'environnement, l'application
 * n'embarque aucun traceur — c'est l'état par défaut.
 *
 * Variables attendues (onglet Environment de Dokploy) :
 *   VITE_ANALYTICS_SRC     ex. https://plausible.example.com/js/script.js
 *   VITE_ANALYTICS_DOMAIN  ex. generique.theo-birost.fr
 */
let loaded = false

// Variables figées par Vite au build ; `?? {}` permet l'exécution hors Vite (tests).
const BUILD_ENV = import.meta.env ?? {}

export function loadAnalytics(env = BUILD_ENV) {
    if (loaded) return
    const src = env.VITE_ANALYTICS_SRC
    const domain = env.VITE_ANALYTICS_DOMAIN
    if (!src || !domain) return

    loaded = true
    const script = document.createElement('script')
    script.defer = true
    script.src = src
    script.dataset.domain = domain
    document.head.appendChild(script)
}

/**
 * Retrait du consentement : retirer la balise ne suffit pas, le script déjà
 * exécuté continuerait de mesurer. Si la mesure était active, la page est
 * rechargée — elle repart alors sans aucun script de mesure.
 */
export function unloadAnalytics(reload = () => window.location.reload(), env = BUILD_ENV) {
    const src = env.VITE_ANALYTICS_SRC
    if (!src) return
    const wasLoaded = loaded
    document.head.querySelector(`script[src="${src}"]`)?.remove()
    loaded = false
    if (wasLoaded) reload()
}

export function isAnalyticsConfigured(env = BUILD_ENV) {
    return Boolean(env.VITE_ANALYTICS_SRC && env.VITE_ANALYTICS_DOMAIN)
}
