# Job Board

Un site d'offres d'emploi : les visiteurs consultent les offres et postulent, et un administrateur gère toutes les données.

- **Back end** : une API REST en PHP, sans framework, avec une base SQLite.
- **Front end** : HTML, CSS et JavaScript, sans framework. Toutes les données affichées viennent de l'API, avec `fetch()`.

## Installation et lancement

Il faut **PHP 8** (testé avec PHP 8.2), avec l'extension `pdo_sqlite`, incluse dans XAMPP. Rien d'autre à installer.

Depuis la racine du dépôt (le dossier qui contient `frontend/` et `backend/`) :

```bash
# 1. Créer et remplir la base uniquement si elle n'existe pas
php scripts/init-db.php

# 2. Lancer le serveur
cd backend
php -S localhost:8000 index.php
```

Puis ouvrir **http://localhost:8000**. Le même serveur envoie le front et répond à l'API : par exemple, http://localhost:8000/offres renvoie les offres en JSON.

Laisser ce terminal ouvert pendant l'utilisation du site ; `Ctrl+C` arrête le serveur. Si le port 8000 est déjà occupé, choisir par exemple 8001 dans la commande et dans l'adresse du navigateur.

`scripts/init-db.php` conserve une base existante. **Attention : `php seed.php`, exécuté dans `backend/`, efface toutes les données avant de recréer les exemples.** Ne l'utiliser que pour réinitialiser volontairement une base de test, après sauvegarde si nécessaire. Une modification de `schema.sql` ne met pas automatiquement à jour une base existante.

## Comptes de test

Créés par `seed.php`. Se connecter sur http://localhost:8000/login.html : l'admin arrive sur l'administration, les autres comptes sur le job board.

| Rôle | E-mail | Mot de passe |
|---|---|---|
| Admin | chams@example.com | admin123 |
| Recruteur (Lorraine Digital) | mathis@example.com | recruteur123 |
| Recruteur (Studio Pixel Est) | karim@example.com | recruteur456 |
| Candidat | alexis@example.com | candidat123 |
| Candidat | john@example.com | candidat1234 |
| Candidat | lina@example.com | candidat456 |

## Organisation du projet

```
backend/
├── schema.sql     les tables de la base
├── seed.php       les données de test
├── database.php   connecter() : la connexion à la base
├── auth.php       la session, et exigerAdmin() : la protection des routes admin
├── index.php      l'API : toutes les requêtes arrivent ici
├── tables.php     la description des 5 tables
├── outils.php     les fonctions de l'API : repondre(), verifier()…
└── front.php      l'envoi des pages du front
frontend/          les pages, le CSS et le JavaScript (voir « Pages »)
scripts/
└── init-db.php    initialise uniquement une base absente
```

`jobboard.db`, la base elle-même, n'est pas dans Git : chacun la crée sur son poste avec les étapes ci-dessus.

## Base de données

Cinq tables, décrites dans `backend/schema.sql`. `*` = obligatoire, `→` = clé étrangère. Le diagramme des tables est dans [docs/BASE_DE_DONNEES.md](docs/BASE_DE_DONNEES.md).

| Table | Colonnes |
|---|---|
| `categories` | id, nom* |
| `entreprises` | id, nom*, ville, description, site_web, logo_url |
| `personnes` | id, prenom*, nom*, email* (unique), telephone, mot_de_passe*, role* (`admin`, `recruteur` ou `candidat`), entreprise_id → entreprises |
| `offres` | id, titre*, description_courte*, description, salaire_min, salaire_max, lieu, temps_de_travail, type_de_contrat, date_de_publication*, categorie_id* → categories, entreprise_id* → entreprises, recruteur_id → personnes |
| `candidatures` | id, offre_id* → offres, personne_id → personnes, nom*, prenom*, email*, telephone, message*, email_envoye*, date_de_candidature* |

Les liens entre les tables :

- une **entreprise** publie plusieurs **offres**, et une **catégorie** en regroupe plusieurs ;
- une **offre** reçoit plusieurs **candidatures** ;
- une **personne** peut être le recruteur d'une offre, travailler pour une entreprise, et envoyer des candidatures.

Quelques choix :

- **Une seule table `personnes`** pour les admins, les recruteurs et les candidats, distingués par `role`.
- **Les coordonnées du candidat sont copiées dans la candidature.** Une personne sans compte peut ainsi postuler : `personne_id` reste alors vide.
- **Le salaire tient en deux nombres**, `salaire_min` et `salaire_max` : identiques pour un salaire fixe, vides s'il n'est pas précisé.
- **Les mots de passe sont hachés** avec `password_hash()`, jamais stockés en clair.
- **Les dates se remplissent toutes seules** à la création.

Ce qui se passe quand on supprime une ligne :

| On supprime… | Conséquence |
|---|---|
| une entreprise | Ses offres et leurs candidatures sont supprimées. Ses recruteurs gardent leur compte, sans entreprise. |
| une offre | Ses candidatures sont supprimées. |
| une personne | Ses offres et ses candidatures restent, sans lien vers elle. |
| une catégorie | Refusé tant que des offres l'utilisent (409). |

## API

L'API démarre dans `backend/index.php`. Les cinq tables se gèrent de la même façon : elles sont **décrites une seule fois**, dans le tableau `$ressources` de `backend/tables.php` (champs obligatoires, champs facultatifs, droit de lecture, tri), et **un seul code** fournit les mêmes cinq routes pour chacune.

Des exemples complets de requêtes et de réponses sont dans [docs/API.md](docs/API.md).

### Les fichiers du back end

| Fichier | Rôle |
|---|---|
| `index.php` | Le point d'entrée : il suit chaque requête, de sa lecture à la réponse |
| `front.php` | Envoie les fichiers de `frontend/` (pages, CSS, JavaScript) |
| `tables.php` | Décrit les 5 tables : `$ressources` et `$liens` |
| `outils.php` | Les fonctions : `repondre()`, `erreur()`, `lireJson()`, `lire()`, `inserer()`, `verifier()`, `reglesPersonne()` |
| `auth.php` | `exigerAdmin()` : 401 si non connecté, 403 si pas admin |
| `database.php` | `connecter()` : la connexion à la base |

`index.php` charge tous les autres avec `require` : il ne fonctionne pas sans eux.

### Le trajet d'une requête

Dans `index.php`, toujours dans le même ordre :

1. **Lecture** de la méthode et du chemin.
2. **Front** : si le chemin désigne un fichier de `frontend/`, il est envoyé et on s'arrête là.
3. **Initialisation** : réponses en JSON, session, connexion à la base, description des tables.
4. **Authentification** : `/connexion`, `/inscription`, `/deconnexion`, `/session`.
5. **Postuler** : la route `POST /offres/{id}/candidatures`.
6. **Les cinq routes** de la table demandée : droits, existence de la ligne, puis l'action.
7. **Sinon**, 404 « Route introuvable ».

### Les cinq routes de chaque table

`{table}` vaut `offres`, `entreprises`, `categories`, `personnes` ou `candidatures`.

| Méthode | Route | Rôle | Succès |
|---|---|---|---|
| GET | `/{table}` | Liste paginée | 200 |
| GET | `/{table}/{id}` | Détail | 200 |
| POST | `/{table}` | Créer | 201 |
| PUT | `/{table}/{id}` | Modifier (envoyer tous les champs) | 200 |
| DELETE | `/{table}/{id}` | Supprimer | 204 |

**Accès.** Tout le monde peut lire les offres, les entreprises et les catégories. Lire les personnes et les candidatures, et toute création, modification ou suppression, est réservé à l'admin. Une exception : un recruteur connecté peut créer une offre (`POST /offres`), toujours pour sa propre entreprise.

**Pagination.** `?page=2` pour la page, `?par_page=20` pour sa taille (10 par défaut, 100 au plus).

**Filtres.** `/offres?entreprise_id=3`, `/offres?categorie_id=2`, `/candidatures?offre_id=1`.

Une liste a toujours cette forme :

```json
{ "offres": [ { "id": 1, "titre": "Développeur web PHP", "entreprise": "Lorraine Digital", "categorie": "Informatique", "…": "…" } ], "page": 1, "total": 26 }
```

Chaque offre porte le nom de son entreprise et de sa catégorie, et chaque candidature le titre de son offre. Le mot de passe d'une personne n'est jamais renvoyé.

### Postuler

| Méthode | Route | Accès | Succès |
|---|---|---|---|
| POST | `/offres/{id}/candidatures` | Public | 201 |

```json
{ "nom": "Martin", "prenom": "Alice", "email": "alice@example.com", "telephone": "06 12 34 56 87", "message": "Votre offre m'intéresse." }
```

Un candidat **connecté** n'envoie que `message` : ses coordonnées sont reprises de son compte.

### Erreurs

Une erreur a toujours la forme `{ "erreur": "Le champ titre est requis" }`.

| Code | Signification |
|---|---|
| 400 | Champ obligatoire manquant ou valeur invalide (nombre, e-mail, `id` inexistant) |
| 401 | Non connecté |
| 403 | Connecté, mais pas admin |
| 404 | Route inconnue, ou `id` inexistant |
| 409 | Conflit : e-mail déjà utilisé, ou élément encore utilisé par d'autres lignes |

## Authentification

La connexion utilise une **session PHP** : le serveur range le compte dans `$_SESSION['utilisateur']`, et le navigateur renvoie automatiquement le cookie de session à chaque requête. Le front et l'API étant servis à la même adresse, aucun réglage supplémentaire n'est nécessaire.

| Méthode | Route | Rôle | Succès |
|---|---|---|---|
| POST | `/connexion` | Se connecter : `{ "email", "password" }` | 200, avec `utilisateur` |
| POST | `/inscription` | Créer un compte **candidat** : `{ "prenom", "nom", "email", "password" }` (8 caractères min.) | 201, déjà connecté |
| GET | `/session` | Le compte connecté (401 si personne) | 200 |
| POST | `/deconnexion` | Se déconnecter | 200 |

Règles de sécurité :

- L'inscription crée toujours un `candidat` : impossible de se déclarer admin. Seul un admin change un rôle, depuis l'administration.
- Un admin ne peut pas supprimer son propre compte (409) : il perdrait l'accès à l'administration. La page d'administration l'empêche aussi de se retirer le rôle admin.
- Les routes admin appellent `exigerAdmin()` (`backend/auth.php`) : 401 si personne n'est connecté, 403 si le compte n'est pas admin. **Cacher une page côté front ne protège rien** : c'est ce contrôle côté serveur qui protège les données.
- Un candidat connecté qui postule n'envoie que son message : le serveur reprend ses coordonnées dans son compte et remplit `personne_id`.

## Pages

### Front end (Alexis)

Le front est réalisé en **HTML, CSS et JavaScript, sans framework**. Le serveur PHP sert aussi les fichiers du dossier `frontend/` : il faut ouvrir le site avec `http://localhost:8000/`, pas directement avec un fichier HTML. Aucun serveur Node séparé n'est nécessaire.

| Fichier | Rôle |
|---|---|
| `index.html` + `app.js` | **Job board** : liste paginée, recherche, détail « En savoir plus » et formulaire « Postuler ». |
| `login.html` + `login.js` | **Connexion**. Redirige l'admin vers `admin.html`, les autres vers le job board. |
| `register.html` + `register.js` | **Créer ou modifier un compte** : inscription si personne n'est connecté, « Mon compte » (formulaire pré-rempli) sinon. La modification attend la route `PUT /session` (voir « Limites connues »). |
| `admin.html` + `admin.js` | **Administration** : les 5 tables, avec liste paginée, ajout, modification et suppression. |
| `session.js` | Partagé par toutes les pages : demande qui est connecté (`GET /session`) et adapte la navigation (Connexion/Inscription, ou Mon compte/Administration/Déconnexion). |
| `style.css` | Style commun, police Space Grotesk avec une police de secours, mise en page adaptée aux petits écrans. |

Chaque page charge `session.js` **avant** son propre script. `session.js` expose la promesse `sessionCourante`, que les autres scripts attendent avec `sessionCourante.then((utilisateur) => …)` (`utilisateur` vaut `null` si personne n'est connecté).

### Liaison avec l'API

| Action dans la page | Requête envoyée par `app.js` |
|---|---|
| Afficher les offres ou changer de page | `GET /offres?page=1&par_page=6` (le numéro de page varie) |
| Cliquer sur « En savoir plus » | `GET /offres/{id}` |
| Envoyer une candidature | `POST /offres/{id}/candidatures` avec un corps JSON |
| Se connecter, s'inscrire, se déconnecter | `POST /connexion`, `POST /inscription`, `POST /deconnexion` |
| Modifier son compte (« Mon compte ») | `PUT /session` — **route à ajouter côté back** |
| Administration | `GET`, `POST`, `PUT`, `DELETE` sur `/{table}` et `/{table}/{id}` |

La liste utilise le tableau `offres` de la réponse, ainsi que `page` et `total` pour la pagination. Les cartes affichent le titre, l'entreprise, le lieu et la description courte. Les textes reçus sont insérés avec `textContent`.

Fonctionnalités reliées :

- Pagination de **six offres par page**, avec boutons Précédent et Suivant.
- Recherche par titre, entreprise ou ville **dans la page chargée uniquement**, pas dans toute la base.
- Détail affiché dans la même page, **sans rechargement ni popup** : entreprise, catégorie, lieu, contrat, temps de travail, salaire, date de publication et description complète. Un texte de remplacement est prévu pour chaque information manquante.
- Candidature sans compte avec prénom, nom, e-mail, message et téléphone facultatif. **Connecté, seul le message est demandé** : les champs d'identité sont cachés et le serveur reprend les coordonnées du compte. L'offre visée est déterminée par l'URL de la requête.
- Confirmation seulement après une réponse de succès de l'API, avec la référence de la candidature. Le formulaire est désactivé pendant l'envoi et les champs sont conservés si l'envoi n'est pas confirmé.
- Messages de chargement et d'erreur, avec un bouton Réessayer pour les offres. Aucune liste fictive ne remplace discrètement une API indisponible.
- Navigation au clavier et styles de focus ; le titre Job Board permet de revenir à la liste.

### L'administration

`admin.js` décrit chaque table une seule fois (colonnes du tableau, champs du formulaire), sur le même principe que `tables.php` côté serveur : le même code construit la liste et le formulaire des 5 tables.

- Un onglet par table, 10 lignes par page, avec Précédent et Suivant.
- **Ajouter** et **Modifier** ouvrent le même formulaire au-dessus de la liste (pré-rempli en modification). Les clés étrangères (`entreprise_id`, `categorie_id`…) se choisissent dans une liste déroulante chargée depuis l'API (100 éléments au plus).
- **Supprimer** demande une confirmation. Une suppression refusée par la base, par exemple une catégorie encore utilisée, affiche le message du serveur.
- Un visiteur non connecté est renvoyé vers la connexion ; un compte non admin voit « Cette page est réservée aux administrateurs ».

### Limites connues

- **Modifier son compte : la route `PUT /session` reste à écrire côté back.** La page « Mon compte » est prête et envoie `{ prenom, nom, email, telephone, password }` (`password` vide = inchangé). La route doit modifier **uniquement** le compte de la session, ignorer `role` et `entreprise_id`, et répondre `{ "utilisateur": { … } }`. En attendant, la page affiche « pas encore disponible côté serveur ».
- **Aucun e-mail n'est réellement envoyé.** La table `candidatures` garde l'information dans `email_envoye` (0 par défaut), que l'admin peut cocher une fois le candidat contacté.
- La recherche du job board porte sur la page chargée, pas sur toute la base.
- Les comptes de test ont des mots de passe connus : ne pas déployer cette base en production.

### Vérification manuelle du job board

1. Lancer le serveur et vérifier que les offres viennent de l'API. Avec une base nouvellement initialisée par le seed actuel, 26 offres sont disponibles.
2. Passer à la page suivante, revenir à la précédente et rechercher un titre présent sur la page affichée.
3. Ouvrir puis fermer un détail et vérifier que la page ne se recharge pas.
4. Envoyer une candidature fictive dans une base de test et vérifier la confirmation avec sa référence.
5. Se connecter avec `alexis@example.com` : la navigation affiche « Mon compte », et « Postuler » ne demande plus que le message.
6. Se déconnecter, puis se connecter avec `chams@example.com` : redirection vers l'administration. Ajouter une entreprise, modifier une offre, tenter de supprimer une catégorie utilisée (refusé).
7. Arrêter le serveur, déclencher un chargement et vérifier le message d'erreur. Relancer le serveur puis cliquer sur Réessayer.
8. Vérifier l'affichage sur un petit écran et parcourir les contrôles avec la touche Tab.
