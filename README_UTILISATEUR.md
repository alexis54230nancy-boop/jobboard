# Job Board

Un site d'offres d'emploi. On y parcourt les offres, on lit leur détail et on postule en quelques clics, avec ou sans compte. Un administrateur gère toutes les données depuis une page dédiée.

## Ce qu'il vous faut

- **PHP 8**, avec l'extension SQLite (déjà incluse dans [XAMPP](https://www.apachefriends.org/fr/) sous Windows).
- Un navigateur web.

Rien d'autre : pas de serveur de base de données, pas de Node.js. Pour vérifier que PHP est installé, tapez `php -v` dans un terminal.

## Installer et lancer

1. Récupérez le projet : `git clone https://github.com/alexis54230nancy-boop/jobboard.git`, ou le bouton **Code › Download ZIP** sur GitHub.
2. Ouvrez un terminal dans le dossier `jobboard`, puis créez la base (une seule fois) :

   ```bash
   php scripts/init-db.php
   ```

3. Lancez le site :

   ```bash
   cd backend
   php -S localhost:8000 index.php
   ```

4. Ouvrez **http://localhost:8000** dans votre navigateur.

Laissez le terminal ouvert tant que vous utilisez le site. `Ctrl + C` l'arrête.

## Les comptes de test

| Rôle | E-mail | Mot de passe |
|---|---|---|
| Administrateur | chams@example.com | admin123 |
| Candidat | alexis@example.com | candidat123 |

## Ce que vous pouvez faire

**Sans compte**

- Parcourir les offres, 6 par page, et chercher un titre, une entreprise ou une ville dans la page affichée.
- Cliquer sur **En savoir plus** : le détail s'affiche sans quitter la page (salaire, contrat, lieu, description…).
- Cliquer sur **Postuler** : prénom, nom, e-mail et message suffisent.

**Avec un compte candidat**

- Créez votre compte avec **Inscription** : vous êtes connecté tout de suite.
- Pour postuler, seul le message est demandé : vos coordonnées sont reprises de votre compte.

**En tant qu'administrateur**

- Après la connexion, vous arrivez sur la page **Administration**.
- Un onglet par type de données : offres, entreprises, catégories, personnes et candidatures.
- Ajoutez, modifiez ou supprimez une ligne. Certaines suppressions sont refusées pour protéger les données, par exemple une catégorie encore utilisée par des offres.

## En cas de problème

| Problème | Solution |
|---|---|
| `php` n'est pas reconnu | Installez PHP, ou utilisez son chemin complet (avec XAMPP : `C:\xampp\php\php.exe`). |
| Le port 8000 est déjà utilisé | Remplacez `8000` par `8001` dans la commande et dans l'adresse. |
| « Impossible de charger les offres » | Le serveur est arrêté : relancez l'étape 3, puis cliquez sur **Réessayer**. |
| La page s'affiche mal | Ouvrez le site par http://localhost:8000, pas en double-cliquant sur un fichier HTML. |
| Revenir aux données de départ | Dans `backend/`, lancez `php seed.php`. **Attention : tout ce que vous avez ajouté est effacé.** |

## Limites actuelles

- Aucun e-mail n'est réellement envoyé au candidat.

## Pour les développeurs

- [`README.md`](README.md) : la documentation complète de l'équipe.
- [`backend/README.md`](backend/README.md) : la base de données et l'API.
- [`docs/API.md`](docs/API.md) et [`docs/BASE_DE_DONNEES.md`](docs/BASE_DE_DONNEES.md) : les routes avec exemples, et le schéma de la base.
- [`frontend/README.md`](frontend/README.md) : les pages du site.

Projet Epitech réalisé à trois : base de données et API, authentification, et interface.
