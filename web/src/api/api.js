import axios from 'axios';
import { bus } from '../bus';
import router from '../router';
import { clearSession } from '../auth/session';

// Le JWT voyage dans un cookie HttpOnly posé par l'API : `withCredentials`
// le fait joindre par le navigateur. `X-Requested-With` est exigé par l'API
// sur les requêtes non sûres authentifiées par cookie (protection CSRF).
export const CSRF_HEADERS = { 'X-Requested-With': 'XMLHttpRequest' };

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL,
    withCredentials: true,
    headers: { ...CSRF_HEADERS },
});

let isRateLimited = false;
let retryQueue = [];

const processQueue = () => {
    retryQueue.forEach(promise => promise.resolve());
    retryQueue = [];
};

api.interceptors.request.use(config => {
    if (isRateLimited) {
        return new Promise(resolve => {
            retryQueue.push({ resolve: () => resolve(config) });
        });
    }

    return config;
});

const handleRateLimitHeaders = (headers) => {
    const remaining = headers['x-ratelimit-remaining'];
    const limit = headers['x-ratelimit-limit'];
    const reset = headers['x-ratelimit-reset'];

    if (remaining !== undefined && limit !== undefined) {
        bus.emit('rate-limit-update', { remaining, limit });

        if (Number(remaining) === 0 && reset) {
            isRateLimited = true;
            const retryAfter = (new Date(reset * 1000) - new Date()) + 1000; // Add a 1s buffer
            
            bus.emit('error', `Trop de requêtes. Veuillez réessayer dans ${Math.ceil(retryAfter / 1000)} secondes.`);

            setTimeout(() => {
                isRateLimited = false;
                processQueue();
            }, retryAfter);
        }
    }
};

api.interceptors.response.use(
    response => {
        handleRateLimitHeaders(response.headers);
        return response;
    },
    error => {
        if (error.response) {
            handleRateLimitHeaders(error.response.headers);
            switch (error.response.status) {
                case 401:
                    // L'API a déjà effacé le cookie invalide dans sa réponse.
                    clearSession();
                    router.push('/connexion');
                    bus.emit('error', 'Votre session a expiré. Veuillez vous reconnecter.');
                    break;
                case 429:
                    // This will be handled by the x-ratelimit-remaining header now
                    // but we can keep it as a fallback.
                    if (!isRateLimited) {
                       bus.emit('error', 'Trop de requêtes. Veuillez réessayer dans un instant.');
                    }
                    break;
                case 500:
                    router.push('/500');
                    break;
            }
        }
        return Promise.reject(error);
    }
);

/** Déconnexion : bloque le jeton côté API et efface le cookie, puis l'état local. */
export async function logout() {
    try {
        // Instance nue (sans intercepteurs) : un 401 sur un jeton déjà révoqué
        // ne doit pas rediriger ; la réponse de l'API efface le cookie.
        await axios.post(`${import.meta.env.VITE_API_URL}/logout`, null, {
            withCredentials: true,
            headers: { ...CSRF_HEADERS },
        });
    } catch {
        // Hors ligne ou session déjà expirée : l'état local est nettoyé quand même.
    } finally {
        clearSession();
    }
}

export default api;
