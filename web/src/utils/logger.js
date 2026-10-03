/**
 * Journalisation applicative.
 *
 * En développement, on veut la pile d'erreur complète dans la console. En
 * production, la console d'un visiteur n'est pas un journal : elle expose les
 * URL internes, les identifiants d'objets et la forme des réponses. Les appels
 * sont donc neutralisés au build.
 *
 * Le jour où un service de suivi d'erreurs est branché (Sentry, GlitchTip),
 * c'est ici — et nulle part ailleurs — qu'il se connecte.
 *
 * Signature variadique volontaire : les appelants passent souvent un
 * identifiant en plus de l'erreur, comme ils le faisaient avec `console.error`.
 */
const isDev = import.meta.env.DEV

export const logger = {
    /**
     * @param {string} context étiquette courte et stable (ex. `store:movies`)
     * @param {...unknown} details erreur et éléments de contexte
     */
    error(context, ...details) {
        if (isDev) console.error(`[${context}]`, ...details)
    },

    /**
     * @param {string} context
     * @param {...unknown} details
     */
    warn(context, ...details) {
        if (isDev) console.warn(`[${context}]`, ...details)
    },
}

export default logger
