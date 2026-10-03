import { defineStore } from 'pinia';
import api from '../api/api';
import { logger } from '../utils/logger';

/**
 * Taille de page demandée pour les collections chargées « en entier »
 * (acteurs, réalisateurs, catégories : filtrés et paginés côté client).
 *
 * Cette valeur est plafonnée côté serveur par
 * `api_platform.defaults.pagination_maximum_items_per_page`. Le code demandait
 * auparavant 1000 : le serveur en renvoyait 24 sans le signaler, et les vues
 * paginaient sur ces 24 éléments en croyant tenir tout le catalogue. Les deux
 * valeurs doivent rester cohérentes.
 */
const PAGE_SIZE_ALL = 200;

export const useDataStore = defineStore('data', {
  state: () => ({
    movies: [],
    actors: [],
    directors: [],
    categories: [],
    user: null,
    /**
     * Totaux annoncés par l'API, distincts de la longueur des tableaux :
     * les collections sont plafonnées à PAGE_SIZE_ALL, or le catalogue de
     * films dépasse ce plafond. Afficher `movies.length` mentirait.
     */
    totals: { movies: 0, actors: 0, directors: 0, categories: 0 },
    isFetchingMovies: false,
    isFetchingActors: false,
    isFetchingDirectors: false,
    isFetchingCategories: false,
    isFetchingUser: false,
  }),
  actions: {
    async fetchMovies(force = false) {
      if (!force && (this.movies.length > 0 || this.isFetchingMovies)) return;
      this.isFetchingMovies = true;
      try {
        const response = await api.get('/movies', {
          params: {
            itemsPerPage: PAGE_SIZE_ALL,
            'groups[]': ['movie:read', 'movie:categories']
          }
        });
        this.movies = response.data['hydra:member'] || response.data.member || [];
        this.totals.movies =
          response.data['hydra:totalItems'] ?? response.data.totalItems ?? this.movies.length;
      } catch (error) {
        logger.error('store:movies', error);
        throw error;
      } finally {
        this.isFetchingMovies = false;
      }
    },
    async fetchActors(force = false) {
      if (!force && (this.actors.length > 0 || this.isFetchingActors)) return;
      this.isFetchingActors = true;
      try {
        const response = await api.get('/actors', {
          params: {
            itemsPerPage: PAGE_SIZE_ALL,
            'groups[]': 'actor:read'
          }
        });
        this.actors = response.data['hydra:member'] || response.data.member || [];
        this.totals.actors =
          response.data['hydra:totalItems'] ?? response.data.totalItems ?? this.actors.length;
      } catch (error) {
        logger.error('store:actors', error);
        throw error;
      } finally {
        this.isFetchingActors = false;
      }
    },
    async fetchDirectors(force = false) {
      if (!force && (this.directors.length > 0 || this.isFetchingDirectors)) return;
      this.isFetchingDirectors = true;
      try {
        const response = await api.get('/directors', {
          params: { itemsPerPage: PAGE_SIZE_ALL }
        });
        this.directors = response.data['hydra:member'] || response.data.member || [];
        this.totals.directors =
          response.data['hydra:totalItems'] ?? response.data.totalItems ?? this.directors.length;
      } catch (error) {
        logger.error('store:directors', error);
        throw error;
      } finally {
        this.isFetchingDirectors = false;
      }
    },
    async fetchCategories(force = false) {
      if (!force && (this.categories.length > 0 || this.isFetchingCategories)) return;
      this.isFetchingCategories = true;
      try {
        const response = await api.get('/categories', {
          params: { itemsPerPage: PAGE_SIZE_ALL }
        });
        this.categories = response.data['hydra:member'] || response.data.member || [];
        this.totals.categories =
          response.data['hydra:totalItems'] ?? response.data.totalItems ?? this.categories.length;
      } catch (error) {
        logger.error('store:categories', error);
        throw error;
      } finally {
        this.isFetchingCategories = false;
      }
    },
    async fetchUser(force = false) {
      if (!force && (this.user || this.isFetchingUser)) return;
      this.isFetchingUser = true;
      try {
        const response = await api.get(import.meta.env.VITE_API_URL_USER);
        this.user = response.data;
      } catch (error) {
        logger.error('store:user', error);
        this.user = null;
        throw error;
      } finally {
        this.isFetchingUser = false;
      }
    },
    removeMovieById(movieId) {
      const index = this.movies.findIndex(m => m.id === movieId);
      if (index !== -1) this.movies.splice(index, 1);
    },
    removeActorById(actorId) {
      const index = this.actors.findIndex(a => a.id === actorId);
      if (index !== -1) this.actors.splice(index, 1);
    },
    removeDirectorById(directorId) {
      const index = this.directors.findIndex(d => d.id === directorId);
      if (index !== -1) this.directors.splice(index, 1);
    },
    removeCategoryById(categoryId) {
      const index = this.categories.findIndex(c => c.id === categoryId);
      if (index !== -1) this.categories.splice(index, 1);
    },
  },
});
