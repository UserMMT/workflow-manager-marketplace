# workflow-manager-marketplace

Marketplace **cross-instance** de templates/formulaires AWDL pour l'écosystème
Kibish Approbation ([hierarchical-approval-system](https://github.com/) —
`back_autohier`/`back_php`/`front_autohier`). Dépôt volontairement **séparé**
de l'app principale : contrairement au marketplace déjà intégré à
`back_autohier` (`/api/marketplace/templates`, qui ne partage des templates
qu'*entre organisations d'une même installation*), celui-ci est un service
central unique, indépendant de toute organisation ou instance particulière —
n'importe quelle installation Kibish (n'importe quelle entreprise, n'importe
quel `back_autohier`/`back_php`) peut s'y enregistrer, y publier, et y
télécharger.

## Architecture

```
workflow-manager-marketplace/
  backend/        PHP 8.2+ (Slim 4 + Eloquent + SQLite) — même stack que back_php,
                  choix délibéré pour rester cohérent avec le reste de l'écosystème.
                  API du catalogue + enregistrement des instances éditrices +
                  modération (backend/public/admin/, UI statique de revue).
  catalog/        Données de référence (pas du code) — exemples/seed d'items
                  publiables, au format exact attendu par le backend. Voir
                  catalog/README.md.
  public-front/   Page statique (html/js/css, sans build) — UI publique de
                  navigation du catalogue (parcourir, filtrer par catégorie/
                  type, rechercher, paginer) + une page de détail par item
                  (historique des versions, téléchargement, et pour les
                  templates un visualiseur qui simule le parcours
                  step-by-step ; pour les formulaires, un aperçu rempli qui
                  montre les données produites). La publication se fait
                  depuis l'instance éditrice elle-même (clé API), pas depuis
                  ce front.
```

## Concepts

- **Instance éditrice** (`PublisherInstance`) — une installation Kibish
  enregistrée (`POST /api/publisher-instances`, libre-service, retourne une
  clé API affichée une seule fois). Authentifie qui peut publier — pas un
  compte utilisateur individuel, une instance entière.
- **Item de catalogue** (`CatalogEntry`) — un template, un formulaire, ou un
  bundle publié, identifié par un `code` unique globalement.
- **Version** (`CatalogEntryVersion`) — snapshot immuable du contenu réel
  (`content`, la config AWDL/formulaire/manifeste) à un instant donné —
  jamais réécrit, jamais supprimé (même miroir versionnant que
  `FormDefinition`/`FormDefinitionVersion` côté back_autohier/back_php).
  Télécharger un item pointe toujours vers une version précise.
- **Compte** (`User`) — admin, collaborator ou maintainer : un membre de
  l'équipe qui gère le marketplace lui-même (à ne pas confondre avec
  `PublisherInstance`, qui authentifie une installation Kibish entière, pas
  une personne). Habilité à approuver/rejeter les items soumis, via l'UI de
  modération (`backend/public/admin/`) ou directement l'API `/api/admin/*`.

Le marketplace **ne valide jamais la forme du contenu** publié (pas
d'`AwdlValidatorService` ici) — chaque instance qui télécharge un item est
responsable de le revalider avec son propre moteur avant import, exactement
comme un import depuis le marketplace intégré à `back_autohier` le fait déjà
aujourd'hui.

## Modération

Un item soumis (`POST /api/catalog`) entre en statut `pending` — invisible du
catalogue public tant qu'un compte admin/collaborator/maintainer ne l'a pas
approuvé. Publier une nouvelle version d'un item déjà approuvé le repasse en
`pending` (le contenu a changé, il doit être revu à nouveau) : le catalogue
public ne montre jamais un contenu qui n'a pas été explicitement approuvé.

Il n'y a pas d'inscription libre-service pour les comptes admin/collaborator/
maintainer — seul un admin peut en créer (`POST /api/admin/users`). Le tout
premier admin se crée en ligne de commande :

```bash
cd backend
php cli/create_user.php "Votre nom" vous@example.com "un-mot-de-passe-solide" admin
```

Puis connectez-vous sur `http://localhost:3030/admin/` (servi directement par
le backend, aucune installation séparée).

## Démarrage rapide

```bash
# Backend (port 3030) — expose aussi l'UI de modération sur /admin/
cd backend
composer install
cp .env.example .env
php cli/migrate.php   # crée les tables SQLite (idempotent, pas de framework de migrations)
php cli/create_user.php "Votre nom" vous@example.com "un-mot-de-passe-solide" admin
php cli/seed.php      # optionnel — peuple le catalogue depuis catalog/templates/*.json
php -S localhost:3030 -t public public/index.php

# Front public (port 3031) — page statique, aucune installation nécessaire
cd public-front
php -S localhost:3031   # ou npx serve -l 3031 ., ou n'importe quel serveur de fichiers statiques
```

## API (résumé)

| Route | Auth | Rôle |
|---|---|---|
| `POST /api/publisher-instances` | aucune | Enregistrement en libre-service, retourne `apiKey` (affichée une seule fois) |
| `POST /api/publisher-instances/me/revoke` | clé API | Révoque sa propre clé |
| `GET /api/catalog` | aucune | Liste publique paginée — items `approved` uniquement (`?category=`, `?itemType=`, `?search=`, `?page=`, `?perPage=`), réponse `{ items, page, perPage, total, totalPages }` |
| `GET /api/catalog/facets` | aucune | Catégories et types présents dans le catalogue public (alimente les filtres du front) |
| `GET /api/catalog/:id` | aucune | Détail d'un item `approved` |
| `GET /api/catalog/:id/versions` | aucune | Historique des versions (item `approved`) |
| `POST /api/catalog/:id/download` | aucune | Télécharge la dernière version, incrémente le compteur |
| `POST /api/catalog/:id/versions/:versionId/download` | aucune | Télécharge une version précise |
| `POST /api/catalog` | clé API | Publie un nouvel item en statut `pending` (crée aussi sa v1) |
| `POST /api/catalog/:id/versions` | clé API (doit être l'éditeur d'origine) | Publie une nouvelle version, repasse l'item en `pending` |
| `POST /api/auth/login` | aucune | Connexion d'un compte, retourne un jeton de session |
| `POST /api/auth/logout` | jeton de session | Révoque le jeton courant |
| `GET /api/auth/me` | jeton de session | Compte actuellement connecté |
| `GET /api/admin/catalog` | jeton de session | Tous statuts confondus, paginé, pour la modération (`?status=pending\|approved\|rejected`, `?page=`, `?perPage=`) |
| `GET /api/admin/catalog/:id` | jeton de session | Détail + contenu + historique, pour revue |
| `POST /api/admin/catalog/:id/approve` | jeton de session | Approuve l'item (visible publiquement) |
| `POST /api/admin/catalog/:id/reject` | jeton de session | Rejette l'item (`{ "reason": "..." }` optionnel) |
| `GET /api/admin/users` | jeton de session, rôle `admin` | Liste des comptes |
| `POST /api/admin/users` | jeton de session, rôle `admin` | Crée un compte (`admin`\|`collaborator`\|`maintainer`) |

## État actuel

Backend fonctionnel de bout en bout (testé manuellement — enregistrement,
publication, modération, liste publique, téléchargement, rejets 401/403/409),
front public statique fonctionnel (parcourir/rechercher), UI de modération
statique fonctionnelle. Pas encore : recherche avancée (catégories/types en
facettes), pagination, revalidation de la forme du contenu à l'import — tout
ça reste à construire selon les besoins réels une fois le concept validé.
