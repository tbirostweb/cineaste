import { createRouter, createWebHistory } from 'vue-router'
import { applySeo } from './composables/useSeo'
import { readSession, clearSession } from './auth/session'

const Connexion = () => import('./views/auth/Connexion.vue')
const Inscription = () => import('./views/auth/Inscription.vue')
const Home = () => import('./views/public/Home.vue')
const Movies = () => import('./views/public/Movies.vue')
const MovieDetails = () => import('./views/details/MovieDetails.vue')
const Actors = () => import('./views/public/Actors.vue')
const ActorDetails = () => import('./views/details/ActorDetails.vue')
const Categories = () => import('./views/public/Categories.vue')
const Directors = () => import('./views/public/Directors.vue')
const DirectorDetails = () => import('./views/details/DirectorDetails.vue')
const Profile = () => import('./views/public/Profile.vue')
const AdminPanel = () => import('./views/admin/Admin.vue')
const LegalNotice = () => import('./views/legal/LegalNotice.vue')
const PrivacyPolicy = () => import('./views/legal/PrivacyPolicy.vue')
const ServerError = () => import('./views/errors/ServerError.vue')
const NotFound = () => import('./views/errors/NotFound.vue')

const routes = [
    {
        path: '/',
        name: 'home',
        component: Home,
        meta: {
            title: null, // titre racine du site
            description:
                "Un index de films, d'interprètes et de réalisateurs : titres, années, durées et distributions.",
        },
    },
    {
        path: '/connexion',
        name: 'login',
        component: Connexion,
        meta: {
            hideChrome: true,
            title: 'Connexion',
            description: 'Connectez-vous à votre compte Générique pour retrouver vos avis et votre profil.',
            noindex: true,
        },
    },
    {
        path: '/inscription',
        name: 'register',
        component: Inscription,
        meta: {
            hideChrome: true,
            title: 'Inscription',
            description: 'Créez un compte Générique pour publier des avis et suivre vos films.',
            noindex: true,
        },
    },
    {
        path: '/movies',
        name: 'movies',
        component: Movies,
        meta: {
            title: 'Films',
            description: 'Index des films : recherche par titre, filtrage par genre, tri par année ou durée.',
        },
    },
    {
        path: '/movies/:id',
        name: 'movie-details',
        component: MovieDetails,
        meta: { title: 'Film', description: 'Fiche détaillée du film : synopsis, casting, genres et avis.' },
    },
    {
        path: '/actors',
        name: 'actors',
        component: Actors,
        meta: {
            title: 'Interprètes',
            description: "Index alphabétique des interprètes du catalogue et de leur filmographie.",
        },
    },
    {
        path: '/actors/:id',
        name: 'actor-details',
        component: ActorDetails,
        meta: { title: 'Interprète', description: "Repères et filmographie de l'interprète." },
    },
    {
        path: '/categories',
        name: 'categories',
        component: Categories,
        meta: {
            title: 'Genres',
            description: 'Tous les genres du catalogue, classés par nombre de films.',
        },
    },
    {
        path: '/directors',
        name: 'directors',
        component: Directors,
        meta: {
            title: 'Réalisateurs',
            description: 'Découvrez les réalisateurs du catalogue et les films qu’ils ont signés.',
        },
    },
    {
        path: '/directors/:id',
        name: 'director-details',
        component: DirectorDetails,
        meta: { title: 'Réalisateur', description: 'Biographie et filmographie du réalisateur.' },
    },
    {
        path: '/profile',
        name: 'profile',
        component: Profile,
        meta: { requiresAuth: true, title: 'Mon profil', noindex: true },
    },
    {
        path: '/admin',
        name: 'admin',
        component: AdminPanel,
        meta: { requiresAuth: true, requiresAdmin: true, title: 'Administration', noindex: true },
    },
    {
        path: '/mentions-legales',
        name: 'legal-notice',
        component: LegalNotice,
        meta: {
            title: 'Mentions légales',
            description: 'Éditeur, hébergeur et conditions d’utilisation du site Générique.',
        },
    },
    {
        path: '/confidentialite',
        name: 'privacy',
        component: PrivacyPolicy,
        meta: {
            title: 'Politique de confidentialité',
            description: 'Données collectées, finalités, durées de conservation et exercice de vos droits (RGPD).',
        },
    },
    {
        path: '/500',
        name: 'server-error',
        component: ServerError,
        meta: { title: 'Erreur serveur', noindex: true },
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: NotFound,
        meta: { title: 'Page introuvable', noindex: true },
    },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) return savedPosition
        if (to.hash) return { el: to.hash, behavior: 'smooth' }
        // `behavior: 'auto'` neutralise le `scroll-behavior: smooth` global :
        // un changement de page doit repartir en haut instantanément, pas
        // dérouler toute la page précédente sous les yeux du visiteur.
        return { top: 0, behavior: 'auto' }
    },
})

router.beforeEach((to) => {
    const session = readSession()

    // Un jeton expiré équivaut à une déconnexion : on nettoie avant d'arbitrer
    if (!session.valid) clearSession()

    if (to.meta.requiresAuth && !session.valid) {
        return { name: 'login', query: { redirect: to.fullPath } }
    }

    if (to.meta.requiresAdmin && !session.isAdmin) {
        return { name: 'home' }
    }

    // Un utilisateur déjà connecté n'a rien à faire sur connexion/inscription
    if (session.valid && (to.name === 'login' || to.name === 'register')) {
        return { name: 'home' }
    }

    return true
})

router.afterEach((to) => {
    applySeo({
        title: to.meta.title,
        description: to.meta.description,
        path: to.path,
        noindex: Boolean(to.meta.noindex),
    })
})

export default router
