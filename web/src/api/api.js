import axios from 'axios';
import { bus } from '../bus';
import router from '../router';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL,
    withCredentials: true,
});

let isRateLimited = false;
let retryQueue = [];

const processQueue = () => {
    retryQueue.forEach(promise => promise.resolve());
    retryQueue = [];
};

api.interceptors.request.use(config => {
    const token = localStorage.getItem('token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

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
                    localStorage.removeItem('token');
                    localStorage.removeItem('loggedIn');
                    localStorage.removeItem('userPhoto');
                    localStorage.removeItem('role');
                    delete api.defaults.headers.common['Authorization'];
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

export default api;
