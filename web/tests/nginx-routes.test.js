import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { describe, it } from 'node:test'
import assert from 'node:assert/strict'

const read = (path) => readFileSync(fileURLToPath(new URL(path, import.meta.url)), 'utf8')

// Les routes de src/router.js, hors attrape-tout.
const routerPaths = [...read('../src/router.js').matchAll(/path:\s*'([^']+)'/g)]
    .map((m) => m[1])
    .filter((p) => !p.includes('pathMatch'))

// Les expressions des blocs location de nginx.conf qui servent index.html en 200.
const nginx = read('../nginx.conf')
const spaLocations = [...nginx.matchAll(/location ~ (\^[^ ]+) \{[^}]*try_files \/index\.html =404;/g)]
    .map((m) => new RegExp(m[1]))
const servedAsApp = (path) => path === '/' || spaLocations.some((re) => re.test(path))

describe('vrais statuts 404 côté nginx', () => {
    it('sert chaque route du routeur Vue (synchronisation nginx ↔ router.js)', () => {
        assert.ok(routerPaths.length > 10)
        for (const path of routerPaths) {
            assert.equal(servedAsApp(path.replace(/:[a-z]+/gi, '42')), true, path)
        }
    })

    it('ne sert pas les URL inconnues en 200', () => {
        for (const path of ['/inconnue', '/movies/1/extra', '/wp-admin', '/.env']) {
            assert.equal(servedAsApp(path), false, path)
        }
        assert.match(nginx, /location \/ \{\s*try_files \$uri =404;\s*\}/)
        assert.ok(nginx.includes('error_page 404 /index.html;'))
    })

    it('la CSP est complétée au build par les origines API/mesure', () => {
        const headers = read('../nginx/security-headers.conf')
        assert.ok(headers.includes("connect-src 'self' __API_ORIGIN__ __ANALYTICS_ORIGIN__"))
        assert.ok(read('../Dockerfile').includes('__API_ORIGIN__'))
    })
})
