const SITE_NAME = 'Générique'
const SITE_URL = 'https://generique.theo-birost.fr'
const DEFAULT_IMAGE = `${SITE_URL}/og-image.jpg`
const DEFAULT_DESCRIPTION =
    "Un index de films, d'interprètes et de réalisateurs : titres, années, durées et distributions."

/** Crée la balise si elle manque, sinon met à jour celle déjà présente. */
const setMeta = (attr, key, content) => {
    if (typeof document === 'undefined') return
    let tag = document.head.querySelector(`meta[${attr}="${key}"]`)
    if (!tag) {
        tag = document.createElement('meta')
        tag.setAttribute(attr, key)
        document.head.appendChild(tag)
    }
    tag.setAttribute('content', content)
}

const setLink = (rel, href) => {
    if (typeof document === 'undefined') return
    let tag = document.head.querySelector(`link[rel="${rel}"]`)
    if (!tag) {
        tag = document.createElement('link')
        tag.setAttribute('rel', rel)
        document.head.appendChild(tag)
    }
    tag.setAttribute('href', href)
}

/**
 * Applique le référencement d'une page : <title> unique, meta description,
 * canonique et cartes Open Graph / Twitter.
 *
 * @param {{ title?: string, description?: string, path?: string,
 *           image?: string, type?: string, noindex?: boolean }} seo
 */
export function applySeo(seo = {}) {
    const {
        title,
        description = DEFAULT_DESCRIPTION,
        path = typeof window !== 'undefined' ? window.location.pathname : '/',
        image = DEFAULT_IMAGE,
        type = 'website',
        noindex = false,
    } = seo

    const fullTitle = title ? `${title} — ${SITE_NAME}` : `${SITE_NAME} — l'index du cinéma`
    const url = `${SITE_URL}${path}`

    document.title = fullTitle
    setMeta('name', 'description', description)
    setLink('canonical', url)

    setMeta('property', 'og:title', fullTitle)
    setMeta('property', 'og:description', description)
    setMeta('property', 'og:url', url)
    setMeta('property', 'og:image', image)
    setMeta('property', 'og:type', type)

    setMeta('name', 'twitter:title', fullTitle)
    setMeta('name', 'twitter:description', description)
    setMeta('name', 'twitter:image', image)

    // Les pages privées (profil, admin) ne doivent pas finir dans l'index
    setMeta('name', 'robots', noindex ? 'noindex, nofollow' : 'index, follow')
}

/**
 * Injecte un bloc JSON-LD identifié, remplaçable à chaque navigation.
 * @param {object | null} data schema.org, ou null pour retirer le bloc
 */
export function applyJsonLd(data) {
    if (typeof document === 'undefined') return
    const id = 'app-json-ld'
    document.getElementById(id)?.remove()
    if (!data) return

    const script = document.createElement('script')
    script.id = id
    script.type = 'application/ld+json'
    script.textContent = JSON.stringify(data)
    document.head.appendChild(script)
}

export { SITE_NAME, SITE_URL, DEFAULT_IMAGE, DEFAULT_DESCRIPTION }
