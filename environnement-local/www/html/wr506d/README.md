# WR506D - Movie API

lien back-end : [https://wr506.theo-birost.fr/]

## Description
API REST et GraphQL pour la gestion de films, acteurs, catégories et utilisateurs.
Projet réalisé dans le cadre du module WR506D.

## Fonctionnalités
- **API REST & GraphQL** : Gestion complète des ressources (CRUD)
- **Authentification** : JWT (JSON Web Token)
- **Sécurité** : Gestion des rôles (ROLE_USER, ROLE_ADMIN)
- **Upload de fichiers** : Gestion des médias (images)
- **Rate Limiting** : Protection contre les abus
- **API Key** : Authentification par clé API
- **Qualité de code** : PHPCS, PHPStan, PHPMD, PHPUnit

## Installation

### Prérequis
- PHP 8.4+
- Composer
- Symfony CLI
- Base de données (SQLite par défaut, configurable)

### Étapes
1. Cloner le dépôt :
   ```bash
   git clone https://github.com/votre-username/wr506d.git
   cd wr506d
   ```

2. Installer les dépendances :
   ```bash
   composer install
   ```

3. Configurer l'environnement :
   Copier le fichier `.env` en `.env.local` et adapter si nécessaire.

4. Générer les clés JWT :
   ```bash
   php bin/console lexik:jwt:generate-keypair
   ```

5. Créer la base de données et les tables :
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

6. (Optionnel) Charger les fixtures :
   ```bash
   php bin/console doctrine:fixtures:load
   ```

7. Lancer le serveur :
   ```bash
   symfony server:start
   ```

## Endpoints Principaux

### Authentification
- `POST /auth` : connexion. Pour un navigateur, le JWT est posé dans un cookie
  `BEARER` HttpOnly/Secure/SameSite (jamais renvoyé dans le corps) ; le corps
  contient un résumé `session` (rôles, expiration). Si la 2FA est active, un
  jeton intermédiaire de 5 minutes est renvoyé, utilisable uniquement sur
  `POST /api/2fa/login/verify`.
- `POST /api/logout` : bloque le jeton présenté jusqu'à son expiration et efface le cookie.
- Requêtes non sûres authentifiées par cookie : en-tête `X-Requested-With: XMLHttpRequest` obligatoire (anti-CSRF).
- Clients non navigateur : en-tête `Authorization: Bearer <jwt>` toujours accepté.
- `POST /api/users` : Créer un compte utilisateur (mot de passe ≥ 12 caractères, absent des fuites connues)
- 2FA : `POST /api/2fa/setup` (secret en attente ; code actuel exigé si déjà active), `POST /api/2fa/enable` (bascule après code valide), `POST /api/2fa/disable`, `GET /api/2fa/status`. Quota : 5 échecs / 5 min par compte.

### Ressources (REST)
- `GET /api/movies` : Liste des films
- `GET /api/movies/{id}` : Détails d'un film
- `GET /api/actors` : Liste des acteurs
- `GET /api/categories` : Liste des catégories

### GraphQL
- Endpoint : `/api/graphql`
- Interface GraphiQL disponible en mode dev

## Tests et Qualité
Lancer les tests et analyses :
```bash
# PHPUnit (tests unitaires + intégration HTTP sur SQLite jetable var/test.db,
# clés JWT de test générées dans var/test-jwt ; aucun service externe)
php vendor/bin/phpunit

# PHP CodeSniffer
vendor/bin/phpcs --standard=PSR2 src/

# PHPStan
vendor/bin/phpstan analyze src/ --level=2

# PHPMD
vendor/bin/phpmd src/ text cleancode,codesize,controversial,design
```

## Déploiement
L'application est déployée et accessible à l'adresse : [https://cineaste.theo-birost.fr/profile]

Variables d'environnement : voir `.env.example` (noms seulement ; valeurs dans
l'onglet Environment de Dokploy, jamais dans le dépôt ni l'image).

### Migrations : sauvegarde obligatoire
Le conteneur applique les migrations au démarrage et **s'arrête si l'une
échoue** (plus de `|| true`). Avant tout déploiement contenant une migration :

1. Sauvegarde cohérente de la base, conservée hors du VPS :
   `mysqldump --single-transaction --routines --triggers <base> | gzip > backup-<date>.sql.gz`
   (et des fichiers `public/media/`).
2. Vérifier la restauration sur une base jetable.
3. Déployer ; en cas d'échec, `doctrine:migrations:migrate prev` ou restauration
   de la sauvegarde. `RUN_MIGRATIONS=0` permet de lancer les migrations à part,
   en job contrôlé, avant de rouvrir le trafic.

### Révocation d'urgence des sessions
- Un compte : incrémenter `user.token_version` (fait automatiquement au
  changement de mot de passe, de rôle et à l'activation de la 2FA).
- Tous les comptes : remplacer la paire de clés JWT (`JWT_SECRET_KEY`,
  `JWT_PUBLIC_KEY`, `JWT_PASSPHRASE`) puis redéployer.

## Auteur
Théo