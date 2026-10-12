# Job Board - Frontend

Le front est maintenant relie a l'API PHP de la branche `feature/api-offres` (commit `6051db8`). Il doit etre ouvert via le serveur PHP, plus directement comme un fichier local.

## Lancement

Prerequis : PHP avec PDO et le pilote `pdo_sqlite`.

Depuis la racine du depot (le dossier contenant `frontend` et `backend`) :

```bash
php scripts/init-db.php
cd backend
php -S localhost:8000 index.php
```

Ouvrir http://localhost:8000/ et laisser le terminal du serveur ouvert. Ctrl+C arrete le serveur. Si le port est deja utilise, choisir par exemple 8001 dans la commande et l'adresse.

`init-db.php` cree et remplit uniquement une base absente. Une base existante est conservee. Ne pas relancer directement `backend/seed.php` sur une base a conserver : ce script efface les donnees avant de recreer ses exemples.

## Routes utilisees

| Action | Route | Reponse |
| --- | --- | --- |
| Liste | `GET /offres?page=1&par_page=6` | `{ offres: [...], page: 1, total: 4 }` |
| Detail | `GET /offres/{id}` | Un objet offre |
| Candidature | `POST /offres/{id}/candidatures` | `201` et l'objet enregistre |

Champs de candidature : `prenom`, `nom`, `email`, `message`, `telephone` facultatif. L'offre est determinee par l'URL. Le serveur valide les donnees et enregistre dans `backend/jobboard.db`.

Le front et l'API sont servis a la meme adresse. Aucun serveur Node ni reglage CORS n'est necessaire pour ce lancement local.

## Comportement

- Liste issue de SQLite, avec pagination de six offres.
- Recherche locale par titre, entreprise ou ville dans la page chargee. L'API ne fournit pas de recherche textuelle globale dans cette version.
- Detail charge a la demande, sans popup ni rechargement. Les descriptions et lieux absents ont une valeur de remplacement.
- Candidature publique en JSON, confirmation seulement apres une reponse de succes. Champs conserves en cas d'erreur ; formulaire desactive pendant l'envoi.
- Une candidature enregistree ne signifie pas qu'un email a ete envoye : cette API laisse `email_envoye` a zero.
- Les erreurs de chargement permettent de reessayer ; aucun tableau fictif ne masque une panne de l'API.

## Encore en attente

`backend/auth.php` contient `exigerAdmin()` et la session, mais aucune route publique de connexion, inscription ou deconnexion n'est presente dans la branche recuperee.

Les pages de compte restent donc des interfaces non connectees. La page admin conserve son apercu fictif clairement indique et n'est pas encore branchee aux operations protegees. Ne pas utiliser `POST /personnes` comme inscription publique : cette route exige un administrateur.

L'integration de l'authentification, l'administration complete, l'envoi d'email et la verification finale du sujet restent a faire avec l'equipe. Ne pas deployer les comptes et donnees de test comme un service de production.
