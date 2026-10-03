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

export function loadAnalytics() {
    if (loaded) return
    const src = import.meta.env.VITE_ANALYTICS_SRC
    const domain = import.meta.env.VITE_ANALYTICS_DOMAIN
    if (!src || !domain) return

    loaded = true
    const script = document.createElement('script')
    script.defer = true
    script.src = src
    script.dataset.domain = domain
    document.head.appendChild(script)
}

/** Retire le script au retrait du consentement (effectif au rechargement). */
export function unloadAnalytics() {
    const src = import.meta.env.VITE_ANALYTICS_SRC
    if (!src) return
    document.head.querySelector(`script[src="${src}"]`)?.remove()
    loaded = false
}

export function isAnalyticsConfigured() {
    return Boolean(import.meta.env.VITE_ANALYTICS_SRC && import.meta.env.VITE_ANALYTICS_DOMAIN)
}
