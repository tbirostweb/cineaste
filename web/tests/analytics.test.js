import { beforeEach, describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { loadAnalytics, unloadAnalytics, isAnalyticsConfigured } from '../src/analytics/index.js'

function fakeDocument() {
    const scripts = []
    return {
        scripts,
        createElement: () => {
            const el = { dataset: {}, remove: () => scripts.splice(scripts.indexOf(el), 1) }
            return el
        },
        head: {
            appendChild: (el) => scripts.push(el),
            querySelector: () => scripts[0] ?? null,
        },
    }
}

const ENV = { VITE_ANALYTICS_ENABLED: 'true', VITE_ANALYTICS_SRC: 'https://stats.example.test/js/script.js', VITE_ANALYTICS_DOMAIN: 'generique.example.test' }

describe("mesure d'audience soumise au consentement", () => {
    beforeEach(() => {
        globalThis.document = fakeDocument()
    })

    it("n'injecte rien sans configuration", () => {
        loadAnalytics({})
        assert.equal(document.scripts.length, 0)
        assert.equal(isAnalyticsConfigured({}), false)
    })

    it('reste désactivée quand seules les anciennes variables sont configurées', () => {
        const { VITE_ANALYTICS_ENABLED, ...legacy } = ENV
        loadAnalytics(legacy)
        assert.equal(document.scripts.length, 0)
        assert.equal(isAnalyticsConfigured(legacy), false)
    })

    it('retire le script et recharge la page au retrait du consentement', () => {
        loadAnalytics(ENV)
        assert.equal(document.scripts.length, 1)

        let reloads = 0
        unloadAnalytics(() => reloads++, ENV)
        assert.equal(document.scripts.length, 0)
        assert.equal(reloads, 1)

        // Aucun rechargement si la mesure n'était pas active.
        unloadAnalytics(() => reloads++, ENV)
        assert.equal(reloads, 1)
    })
})
