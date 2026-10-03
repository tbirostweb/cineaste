import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './assets/css/main.css'
import { logger } from './utils/logger'
import { reveal } from './motion/reveal'

const app = createApp(App)

app.directive('reveal', reveal)

app.use(createPinia())
app.use(router)

// Dernier filet : une erreur de rendu ne doit pas laisser une page blanche muette.
// `logger` ne parle qu'en développement, la garde d'environnement est chez lui.
app.config.errorHandler = (error, _instance, info) => {
    logger.error(`Vue:${info}`, error)
}

app.mount('#app')
