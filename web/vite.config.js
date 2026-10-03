import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue(), tailwindcss()],

  build: {
    // Les traces de source de production alourdissent le déploiement et
    // exposent le code d'origine : on les laisse désactivées.
    sourcemap: false,
    rollupOptions: {
      output: {
        // Sépare les dépendances stables du code applicatif : une correction
        // de l'app n'invalide plus le cache navigateur de Vue/GSAP/Axios.
        manualChunks: {
          'vendor-vue': ['vue', 'vue-router', 'pinia'],
          'vendor-http': ['axios', 'jwt-decode', 'mitt'],
        },
      },
    },
  },

  esbuild: {
    // Filet de sécurité : aucun console.log/debugger oublié ne part en prod.
    // console.error et console.warn sont conservés pour le diagnostic.
    drop: ['debugger'],
    pure: ['console.log', 'console.info', 'console.debug'],
  },
})
