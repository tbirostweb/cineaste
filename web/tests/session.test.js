import { beforeEach, describe, it } from 'node:test'
import assert from 'node:assert/strict'
import './setup.js'
import { clearSession, readSession, saveSession, SESSION_KEY } from '../src/auth/session.js'

const inOneHour = () => Math.floor(Date.now() / 1000) + 3600

describe('session navigateur (JWT en cookie HttpOnly)', () => {
    beforeEach(() => localStorage.clear())

    it("ne stocke jamais de jeton, seulement rôles et expiration", () => {
        saveSession({ roles: ['ROLE_USER', 'ROLE_ADMIN'], expiresAt: inOneHour(), token: 'eyJ.secret.jwt' })

        const raw = localStorage.getItem(SESSION_KEY)
        assert.ok(!raw.includes('eyJ'))
        assert.deepEqual(Object.keys(JSON.parse(raw)).sort(), ['expiresAt', 'roles'])
        assert.deepEqual(JSON.parse(raw).roles, ['ROLE_USER', 'ROLE_ADMIN'])
        assert.equal(localStorage.getItem('token'), null)

        const session = readSession()
        assert.equal(session.valid, true)
        assert.equal(session.isAdmin, true)
    })

    it("purge l'ancien jeton localStorage d'une version précédente", () => {
        localStorage.setItem('token', 'ancien.jwt')
        localStorage.setItem('loggedIn', 'true')
        saveSession({ roles: ['ROLE_USER'], expiresAt: inOneHour() })
        assert.equal(localStorage.getItem('token'), null)
        assert.equal(localStorage.getItem('loggedIn'), null)
    })

    it('considère une session expirée ou altérée comme absente', () => {
        saveSession({ roles: ['ROLE_ADMIN'], expiresAt: Math.floor(Date.now() / 1000) - 1 })
        assert.equal(readSession().valid, false)

        localStorage.setItem(SESSION_KEY, '{pas du json')
        assert.equal(readSession().valid, false)
        assert.equal(readSession().isAdmin, false)
    })

    it('la déconnexion efface la session mais conserve le choix de mesure d’audience', () => {
        localStorage.setItem('cineaste.consent.v1', 'denied')
        localStorage.setItem('token', 'ancien.jwt')
        saveSession({ roles: ['ROLE_USER'], expiresAt: inOneHour() })
        localStorage.setItem('userPhoto', '/a.png')

        clearSession()

        assert.equal(readSession().valid, false)
        assert.equal(localStorage.getItem('userPhoto'), null)
        assert.equal(localStorage.getItem('token'), null)
        assert.equal(localStorage.getItem('cineaste.consent.v1'), 'denied')
    })
})
