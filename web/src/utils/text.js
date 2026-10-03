/**
 * Normalise une chaîne pour la comparaison : minuscules, sans accents.
 * Permet de retrouver « Bardém » en tapant « bardem ».
 */
export function normalize(value) {
    return (value ?? '')
        .toString()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
}

/** `true` si `haystack` contient `needle`, accents et casse ignorés. */
export function matches(haystack, needle) {
    return normalize(haystack).includes(normalize(needle))
}
