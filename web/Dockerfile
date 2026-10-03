# syntax=docker/dockerfile:1

# ---- Étape 1 : build ------------------------------------------------------
FROM node:20-alpine AS build

WORKDIR /app

# Les dépendances sont installées avant le code : la couche est réutilisée
# tant que package-lock.json ne change pas.
COPY package.json package-lock.json ./
# `npm ci` installe exactement le verrou, contrairement à `npm install` qui
# peut le réécrire et faire diverger le build de ce qui a été testé.
RUN npm ci

COPY . .

# Vite fige les variables VITE_* au moment du build : elles doivent donc être
# passées en arguments de build (Dokploy → onglet Build Arguments), pas en
# variables d'exécution du conteneur.
ARG VITE_API_BASE_URL
ARG VITE_API_URL
ARG VITE_API_URL_AUTH
ARG VITE_API_URL_USER
ARG VITE_API_URL_REGISTER
ARG VITE_API_URL_MEDIA
ARG VITE_ANALYTICS_SRC
ARG VITE_ANALYTICS_DOMAIN

RUN npm run build

# ---- Étape 2 : service statique -------------------------------------------
FROM nginx:1.27-alpine AS runtime

RUN rm /etc/nginx/conf.d/default.conf && mkdir -p /etc/nginx/snippets
COPY nginx.conf /etc/nginx/conf.d/app.conf
COPY nginx/security-headers.conf /etc/nginx/snippets/security-headers.conf
COPY --from=build /app/dist /usr/share/nginx/html

# Échoue le build plutôt que de livrer une image dont nginx refuse de démarrer
RUN nginx -t

# Le healthcheck interroge la sonde définie dans nginx.conf : Dokploy peut
# ainsi redémarrer le conteneur si nginx ne répond plus.
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD wget -qO- http://127.0.0.1/healthz >/dev/null 2>&1 || exit 1

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
