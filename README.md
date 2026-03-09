## Chun Tian – Démo locale

Petit projet de démo pour une boutique de fruits, légumes et herbes, avec front en HTML/CSS/JS et backend PHP + SQLite.

### Prérequis

- **PHP 8+** (avec l’extension SQLite activée)
- **Composer** (pour installer Stripe PHP, déjà référencé dans `public_html/composer.json`)

### Installation rapide

1. **Cloner le repo**

```bash
git clone <URL_DU_REPO>
cd chuntian
```

2. **Installer les dépendances PHP**

```bash
cd public_html
composer install
```

3. **Configurer l’environnement**

- Un fichier `.env` est déjà prévu dans `public_html/.env`.  
- Pour une **démo locale sans paiement réel**, tu peux laisser les clés Stripe commentées (ou utiliser des clés de test).

### Lancer le projet en local

Depuis le dossier `public_html`, lance le serveur PHP intégré :

```bash
cd public_html
php -S localhost:8000
```

Ensuite ouvre ton navigateur sur `http://localhost:8000`.

- Page d’accueil : `index.html`
- Produits : `fruits.html`, `legumes.html`, `herbes.html`
- Panier / paiement : `panier.html`, `checkout.html`
- Espace gérant (admin) : `admin.html` (requiert un compte admin, voir `public_html/api` pour la logique d’auth).

### Remarques

- La base SQLite est stockée dans `public_html/storage/db.sqlite` (chemin configuré dans `public_html/.env`).
- En mode démo (sans clé Stripe secrète), la création de commande redirige directement vers `merci.html` sans passer par un vrai paiement.

