// Stockage navigateur minimal pour l'environnement Node de Vitest.
class MemoryStorage {
    #data = new Map()
    getItem(key) { return this.#data.has(key) ? this.#data.get(key) : null }
    setItem(key, value) { this.#data.set(key, String(value)) }
    removeItem(key) { this.#data.delete(key) }
    clear() { this.#data.clear() }
    key(i) { return [...this.#data.keys()][i] ?? null }
    get length() { return this.#data.size }
}

globalThis.localStorage = new MemoryStorage()
