# Job Board - Frontend

HTML, CSS et JavaScript sans framework. Les pages sont servies par le serveur PHP, a la meme adresse que l'API : il faut les ouvrir via http://localhost:8000/, pas directement comme fichiers locaux.

## Lancement

Prerequis : PHP avec PDO et le pilote `pdo_sqlite`.

Depuis la racine du depot (le dossier contenant `frontend` et `backend`) :

```bash
php scripts/init-db.php
cd backend
php -S localhost:8000 index.php
```

Ouvrir http://localhost:8000/ et laisser le terminal du serveur ouvert. Ctrl+C arrete le serveur.

`init-db.php` cree et remplit uniquement une base absente. Ne pas relancer directement `backend/seed.php` sur une base a conserver : ce script efface les donnees avant de recreer ses exemples.

## Les pages

| Page | Script | Role |
| --- | --- | --- |
| `index.html` | `app.js` | Job board : liste paginee, recherche, detail, candidature |
| `login.html` | `login.js` | Connexion ; l'admin est redirige vers `admin.html`, les autres vers le job board |
| `register.html` | `register.js` | Inscription, ou « Mon compte » si l'utilisateur est deja connecte |
| `admin.html` | `admin.js` | Administration des 5 tables (reservee a l'admin) |

Chaque page charge d'abord `session.js`, puis son propre script. `session.js` appelle `GET /session` et met a jour la navigation. Il expose la promesse `sessionCourante` : `sessionCourante.then((utilisateur) => ...)` donne le compte connecte, ou `null`.

## Routes utilisees

| Action | Route |
| --- | --- |
| Liste des offres | `GET /offres?page=1&par_page=6` |
| Detail d'une offre | `GET /offres/{id}` |
| Candidature | `POST /offres/{id}/candidatures` |
| Connexion / deconnexion | `POST /connexion`, `POST /deconnexion` |
| Qui est connecte ? | `GET /session` |
| Inscription | `POST /inscription` |
| Modifier son compte | `PUT /session` (route a ajouter cote back : en attendant, la page affiche un message) |
| Administration | `GET`, `POST`, `PUT`, `DELETE` sur `/{table}` et `/{table}/{id}` |

## Comportement

- Liste issue de SQLite, avec pagination de six offres. La recherche porte sur la page chargee.
- Detail charge a la demande, sans popup ni rechargement : entreprise, categorie, lieu, contrat, temps de travail, salaire, date et description complete.
- Candidature : sans compte, prenom, nom, e-mail et message sont demandes. Connecte, seul le message est demande ; le serveur reprend les coordonnees du compte.
- Confirmation seulement apres une reponse de succes ; champs conserves en cas d'erreur ; formulaire desactive pendant l'envoi.
- Administration : un onglet par table, 10 lignes par page, ajout et modification dans un formulaire au-dessus de la liste, suppression apres confirmation. Les cles etrangeres se choisissent dans des listes deroulantes.
- Tous les textes venant de l'API sont inseres avec `textContent` : aucun HTML de la base n'est interprete.
- Cacher une page ou un bouton ne protege rien : les droits sont verifies par le serveur (`exigerAdmin()` dans `backend/auth.php`).
