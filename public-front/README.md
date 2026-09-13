# public-front/

UI publique de navigation du catalogue — page statique (`index.html` +
`app.js` + `style.css`), sans framework ni étape de build : chaque instance
qui veut exposer ce front n'a qu'à servir ce dossier tel quel (n'importe quel
serveur de fichiers statiques convient — Nginx, Apache, `npx serve`, une
page GitHub Pages, etc.).

La publication d'un item se fait depuis l'instance Kibish elle-même (clé API
d'instance éditrice, `POST /api/catalog` — voir le README à la racine du
dépôt) : ce front ne fait que parcourir/rechercher le catalogue, il n'y a pas
de formulaire de publication ici.

## Configuration

Avant de déployer, éditez `config.js` pour pointer vers le backend
marketplace à utiliser :

```js
window.MARKETPLACE_API_URL = 'https://votre-marketplace.example.com/api';
```

## Démarrage local

Servez ce dossier avec n'importe quel serveur de fichiers statiques, par
exemple :

```bash
npx serve -l 3031 .
# ou
php -S localhost:3031
```

Puis ouvrez [http://localhost:3031](http://localhost:3031).
