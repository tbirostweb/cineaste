<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { bus } from '../../bus'
import { readSession, clearSession } from '../../auth/session'

const props = defineProps({
    loggedIn: Boolean,
    photo: String,
})

const emit = defineEmits(['logout'])

const router = useRouter()
const route = useRoute()

const isOpen = ref(false)
const isScrolled = ref(false)
const rateLimit = ref({ remaining: null, limit: null })

const navigation = [
    { name: 'Films', href: '/movies' },
    { name: 'Interprètes', href: '/actors' },
    { name: 'Réalisateurs', href: '/directors' },
    { name: 'Genres', href: '/categories' },
]

// L'entrée Admin suit le rôle porté par le jeton, pas un drapeau localStorage
const isAdmin = computed(() => props.loggedIn && readSession().isAdmin)

const logout = () => {
    emit('logout')
    clearSession()
    router.push('/connexion')
}

const handleScroll = () => {
    isScrolled.value = window.scrollY > 4
}

const updateRateLimit = (data) => {
    rateLimit.value = data
}

watch(() => route.fullPath, () => (isOpen.value = false))

const onKeydown = (event) => {
    if (event.key === 'Escape') isOpen.value = false
}

onMounted(() => {
    window.addEventListener('scroll', handleScroll, { passive: true })
    window.addEventListener('keydown', onKeydown)
    bus.on('rate-limit-update', updateRateLimit)
    handleScroll()
})

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll)
    window.removeEventListener('keydown', onKeydown)
    bus.off('rate-limit-update', updateRateLimit)
})
</script>

<template>
    <nav
        class="masthead"
        :class="{ 'masthead--pinned': isScrolled || isOpen }"
        aria-label="Navigation principale"
    >
        <div class="mx-auto flex h-[var(--nav-height)] max-w-[82rem] items-center gap-8 px-5 md:px-10">
            <router-link
                to="/"
                class="wordmark"
                aria-label="Générique, retour à l'accueil"
            >
                Générique
            </router-link>

            <!-- Navigation bureau -->
            <div class="ml-auto hidden items-center gap-7 md:flex">
                <router-link
                    v-for="item in navigation"
                    :key="item.href"
                    :to="item.href"
                    class="nav-link"
                    active-class="is-active"
                    :aria-current="route.path === item.href ? 'page' : undefined"
                >
                    {{ item.name }}
                </router-link>

                <router-link v-if="isAdmin" to="/admin" class="nav-link" active-class="is-active">
                    Admin
                </router-link>
            </div>

            <div class="hidden items-center gap-5 md:flex">
                <span
                    v-if="props.loggedIn && rateLimit.remaining !== null"
                    class="data"
                    title="Requêtes API restantes sur la minute en cours"
                >
                    {{ rateLimit.remaining }}/{{ rateLimit.limit }}
                </span>

                <template v-if="props.loggedIn">
                    <router-link to="/profile" class="nav-link" active-class="is-active">
                        Profil
                    </router-link>
                    <button type="button" class="nav-link" @click="logout">Quitter</button>
                </template>

                <router-link v-else to="/connexion" class="btn btn-primary !px-5 !py-2.5">
                    Connexion
                </router-link>
            </div>

            <!-- Bascule mobile -->
            <button
                type="button"
                class="nav-link ml-auto md:hidden"
                :aria-expanded="isOpen"
                aria-controls="menu-mobile"
                @click="isOpen = !isOpen"
            >
                {{ isOpen ? 'Fermer' : 'Menu' }}
            </button>
        </div>

        <Transition name="drawer">
            <div v-if="isOpen" id="menu-mobile" class="border-t border-[var(--color-rule)] md:hidden">
                <div class="mx-auto max-w-[82rem] px-5 py-4">
                    <router-link
                        v-for="item in navigation"
                        :key="item.href"
                        :to="item.href"
                        class="block border-b border-[var(--color-rule)] py-3 font-[family-name:var(--font-display)] text-2xl uppercase"
                    >
                        {{ item.name }}
                    </router-link>
                    <router-link
                        v-if="isAdmin"
                        to="/admin"
                        class="block border-b border-[var(--color-rule)] py-3 font-[family-name:var(--font-display)] text-2xl uppercase"
                    >
                        Admin
                    </router-link>

                    <div class="flex items-center gap-6 pt-5">
                        <template v-if="props.loggedIn">
                            <router-link to="/profile" class="nav-link">Profil</router-link>
                            <button type="button" class="nav-link" @click="logout">Quitter</button>
                        </template>
                        <router-link v-else to="/connexion" class="btn btn-primary">Connexion</router-link>
                    </div>
                </div>
            </div>
        </Transition>
    </nav>
</template>

<style scoped>
.masthead {
    position: sticky;
    top: 0;
    z-index: 50;
    background-color: var(--color-paper);
    border-bottom: 1px solid transparent;
    transition: border-color var(--duration-fast) linear;
}

/* Le filet n'apparaît qu'une fois la page défilée : au repos, le bandeau se
   fond dans le papier et laisse le titre respirer. */
.masthead--pinned {
    border-bottom-color: var(--color-rule);
}

.wordmark {
    font-family: var(--font-display);
    font-size: 1.75rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    text-transform: uppercase;
    line-height: 1;
    color: var(--color-ink);
}

.nav-link {
    position: relative;
    font-family: var(--font-mono);
    font-size: 0.75rem;
    font-weight: 500;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--color-ink-soft);
    background: none;
    border: 0;
    padding: 0.25rem 0;
    cursor: pointer;
    transition: color var(--duration-fast) linear;
}

.nav-link:hover { color: var(--color-ink); }

/* Soulignement qui se déploie depuis la gauche */
.nav-link::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: -2px;
    height: 1px;
    background-color: var(--color-night);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform var(--duration-base) var(--ease-page);
}

.nav-link:hover::after,
.nav-link.is-active::after { transform: scaleX(1); }
.nav-link.is-active { color: var(--color-ink); }

.drawer-enter-active,
.drawer-leave-active {
    transition: opacity var(--duration-fast) linear;
}
.drawer-enter-from,
.drawer-leave-to { opacity: 0; }

@media (prefers-reduced-motion: reduce) {
    .nav-link::after { transition: none; }
}
</style>
