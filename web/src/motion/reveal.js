const REDUCED = '(prefers-reduced-motion: reduce)'

const prefersReduced = () =>
    typeof window !== 'undefined' && window.matchMedia(REDUCED).matches

/**
 * Motion est importé à la demande, au premier élément à révéler.
 *
 * C'est une bibliothèque d'agrément : 22 ko gzip n'ont rien à faire dans le
 * chemin critique d'un site dont le contenu est du texte. Le minuteur de
 * sécurité ci-dessous couvre le temps de chargement — si le module tarde ou
 * échoue, l'élément apparaît quand même.
 */
let motionPromise = null
const loadMotion = () => (motionPromise ??= import('motion-v'))

/**
 * Directive `v-reveal` — apparition d'un élément entrant dans le cadre.
 *
 *   <li v-reveal>…</li>          apparition simple
 *   <li v-reveal="0.06">…</li>   décalage en secondes, pour une cascade
 *
 * Deux garde-fous, appris à nos dépens sur ce projet :
 *
 *  - un minuteur révèle l'élément au bout de 1,2 s même si l'observateur ne se
 *    déclenche jamais (conteneur masqué, onglet en arrière-plan, module lent).
 *    Une animation ratée ne doit jamais laisser du contenu invisible ;
 *  - en « mouvement réduit », rien n'est masqué ni animé du tout.
 */
export const reveal = {
    mounted(el, binding) {
        if (prefersReduced()) return

        const delay = Number(binding.value) || 0
        let done = false

        const finish = (animate) => {
            if (done) return
            done = true
            clearTimeout(el._revealTimer)
            el._revealStop?.()

            if (!animate) {
                el.style.opacity = ''
                return
            }
            animate(
                el,
                { opacity: [0, 1], transform: ['translateY(14px)', 'translateY(0px)'] },
                { duration: 0.5, delay, ease: [0.22, 1, 0.36, 1] }
            )
        }

        el.style.opacity = '0'
        el._revealTimer = setTimeout(() => finish(null), 1200)

        loadMotion()
            .then(({ animate, inView }) => {
                if (done) return
                el._revealStop = inView(el, () => finish(animate), {
                    amount: 0.15,
                    margin: '0px 0px -8% 0px',
                })
            })
            .catch(() => finish(null))
    },

    unmounted(el) {
        clearTimeout(el._revealTimer)
        el._revealStop?.()
    },
}

/**
 * Décalage régulier pour une liste, borné : au-delà d'une dizaine d'éléments,
 * l'attente cumulée devient plus pénible que l'effet n'est agréable.
 */
export const revealDelay = (index, step = 0.045, max = 10) =>
    Math.min(index, max) * step

export default reveal
