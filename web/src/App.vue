<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import Navbar from './components/common/Navbar.vue'
import AppFooter from './components/common/AppFooter.vue'
import ErrorDisplay from './components/common/ErrorDisplay.vue'
import CookieBanner from './components/common/CookieBanner.vue'
import { readSession, clearSession } from './auth/session'
import { useMotionPreference } from './composables/useMotion'
import api from './api/api.js'

const FALLBACK_AVATAR = '/placeholder-avatar.svg'

const route = useRoute()
const reduced = useMotionPreference()

const loggedIn = ref(false)
const photo = ref(FALLBACK_AVATAR)

// Les écrans d'authentification s'affichent en plein cadre, sans navbar ni pied de page
const showChrome = computed(() => !route.meta.hideChrome)

const resolvePhoto = (value) =>
    value ? `${import.meta.env.VITE_API_BASE_URL}${value}` : FALLBACK_AVATAR

onMounted(async () => {
    const session = readSession()
    loggedIn.value = session.valid

    if (!session.valid) {
        clearSession()
        return
    }

    const cached = localStorage.getItem('userPhoto')
    if (cached) {
        photo.value = cached
        return
    }

    try {
        const { data } = await api.get(import.meta.env.VITE_API_URL_USER)
        photo.value = resolvePhoto(data.photo)
        localStorage.setItem('userPhoto', photo.value)
    } catch {
        // L'intercepteur gère déjà 401/500 ; ici l'avatar par défaut suffit
        photo.value = FALLBACK_AVATAR
    }
})

const handleLogin = (newPhoto) => {
    loggedIn.value = true
    photo.value = newPhoto || FALLBACK_AVATAR
}

const handleLogout = () => {
    loggedIn.value = false
    clearSession()
    photo.value = FALLBACK_AVATAR
}
</script>

<template>
    <a class="skip-link" href="#contenu">Aller au contenu principal</a>


    <ErrorDisplay />

    <header v-if="showChrome">
        <Navbar :logged-in="loggedIn" :photo="photo" @logout="handleLogout" />
    </header>

    <main id="contenu" class="relative">
        <!--
          Pas de <Transition> autour de la vue de route.

          Vue fait avancer les classes de transition dans des
          `requestAnimationFrame`, or un onglet en arrière-plan les gèle : avec
          `mode="out-in"`, une navigation déclenchée à ce moment-là démonte
          l'ancienne vue sans jamais monter la nouvelle, et l'utilisateur
          retrouve une page vide. Le fondu inter-pages ne vaut pas ce risque —
          d'autant que chaque vue anime déjà son propre contenu à l'entrée, en
          CSS, ce qui dégrade sans casser.
        -->
        <router-view v-slot="{ Component, route: current }">
            <component
                :is="Component"
                :key="current.path"
                @login-success="handleLogin"
            />
        </router-view>
    </main>

    <AppFooter v-if="showChrome" />

    <CookieBanner />
</template>
