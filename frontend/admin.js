// Page d'administration : lister, créer, modifier et supprimer les lignes des 5 tables.
// Comme tables.php côté serveur, chaque table est DÉCRITE une seule fois ci-dessous,
// et le même code construit la liste et le formulaire pour toutes.
//
// Pour chaque table :
//   titre    : le nom affiché
//   colonnes : les colonnes du tableau, [clé renvoyée par l'API, en-tête]
//   champs   : les champs du formulaire. type : text (défaut), textarea, email, tel, url, number,
//              password, checkbox, choix (liste fixe) ou lien (une autre table, ex. entreprise_id → entreprises)
const tables = {
  offres: {
    titre: "Offres",
    colonnes: [["id", "#"], ["titre", "Titre"], ["entreprise", "Entreprise"], ["categorie", "Catégorie"], ["lieu", "Lieu"], ["date_de_publication", "Publiée le"]],
    champs: [
      { nom: "titre", libelle: "Titre", obligatoire: true },
      { nom: "entreprise_id", libelle: "Entreprise", type: "lien", table: "entreprises", obligatoire: true },
      { nom: "categorie_id", libelle: "Catégorie", type: "lien", table: "categories", obligatoire: true },
      { nom: "recruteur_id", libelle: "Recruteur", type: "lien", table: "personnes" },
      { nom: "lieu", libelle: "Lieu" },
      { nom: "type_de_contrat", libelle: "Type de contrat" },
      { nom: "temps_de_travail", libelle: "Temps de travail" },
      { nom: "salaire_min", libelle: "Salaire min (brut annuel)", type: "number" },
      { nom: "salaire_max", libelle: "Salaire max (brut annuel)", type: "number" },
      { nom: "description_courte", libelle: "Description courte", type: "textarea", obligatoire: true },
      { nom: "description", libelle: "Description complète", type: "textarea" }
    ]
  },
  entreprises: {
    titre: "Entreprises",
    colonnes: [["id", "#"], ["nom", "Nom"], ["ville", "Ville"], ["site_web", "Site web"]],
    champs: [
      { nom: "nom", libelle: "Nom", obligatoire: true },
      { nom: "ville", libelle: "Ville" },
      { nom: "site_web", libelle: "Site web", type: "url" },
      { nom: "logo_url", libelle: "URL du logo", type: "url" },
      { nom: "description", libelle: "Description", type: "textarea" }
    ]
  },
  categories: {
    titre: "Catégories",
    colonnes: [["id", "#"], ["nom", "Nom"]],
    champs: [
      { nom: "nom", libelle: "Nom", obligatoire: true }
    ]
  },
  personnes: {
    titre: "Personnes",
    colonnes: [["id", "#"], ["prenom", "Prénom"], ["nom", "Nom"], ["email", "E-mail"], ["role", "Rôle"]],
    champs: [
      { nom: "prenom", libelle: "Prénom", obligatoire: true },
      { nom: "nom", libelle: "Nom", obligatoire: true },
      { nom: "email", libelle: "E-mail", type: "email", obligatoire: true },
      { nom: "telephone", libelle: "Téléphone", type: "tel" },
      { nom: "role", libelle: "Rôle", type: "choix", valeurs: ["candidat", "recruteur", "admin"], obligatoire: true },
      { nom: "entreprise_id", libelle: "Entreprise (recruteur)", type: "lien", table: "entreprises" },
      // Obligatoire à la création seulement : vide en modification = on garde l'ancien
      { nom: "mot_de_passe", libelle: "Mot de passe", type: "password", obligatoireCreation: true }
    ]
  },
  candidatures: {
    titre: "Candidatures",
    colonnes: [["id", "#"], ["offre", "Offre"], ["prenom", "Prénom"], ["nom", "Nom"], ["email", "E-mail"], ["date_de_candidature", "Date"]],
    champs: [
      { nom: "offre_id", libelle: "Offre", type: "lien", table: "offres", obligatoire: true },
      { nom: "personne_id", libelle: "Compte du candidat", type: "lien", table: "personnes" },
      { nom: "prenom", libelle: "Prénom", obligatoire: true },
      { nom: "nom", libelle: "Nom", obligatoire: true },
      { nom: "email", libelle: "E-mail", type: "email", obligatoire: true },
      { nom: "telephone", libelle: "Téléphone", type: "tel" },
      { nom: "message", libelle: "Message", type: "textarea", obligatoire: true },
      { nom: "email_envoye", libelle: "E-mail envoyé", type: "checkbox" }
    ]
  }
};

// Le texte d'une ligne dans une liste déroulante (ex. pour choisir l'entreprise d'une offre)
const libelles = {
  offres: (ligne) => ligne.titre + " (" + ligne.entreprise + ")",
  entreprises: (ligne) => ligne.nom,
  categories: (ligne) => ligne.nom,
  personnes: (ligne) => ligne.prenom + " " + ligne.nom + " (" + ligne.email + ")"
};

const parPage = 10;
let tableCourante = "offres";
let pageCourante = 1;
let lignes = [];           // les lignes de la page affichée
let ligneEnCours = null;   // la ligne en cours de modification, ou null pour une création
let adminConnecte = null;  // le compte admin connecté (voir session.js)

const messageAdmin = document.querySelector("#admin-message");
const messageTable = document.querySelector("#table-message");
const panneau = document.querySelector("#record-panel");
const formulaire = document.querySelector("#record-form");
const messageFormulaire = document.querySelector("#record-message");
const boutonPrecedent = document.querySelector("#previous-page");
const boutonSuivant = document.querySelector("#next-page");

// Appelle l'API et renvoie le JSON reçu. En cas d'erreur, lance une Error avec le message du serveur.
async function api(methode, adresse, corps) {
  const options = { method: methode, credentials: "same-origin", headers: {} };
  if (corps !== undefined) {
    options.headers["Content-Type"] = "application/json";
    options.body = JSON.stringify(corps);
  }
  const reponse = await fetch(adresse, options);
  const donnees = reponse.status === 204 ? null : await reponse.json();   // 204 : pas de contenu
  if (reponse.status === 401) {
    window.location.href = "/login.html";   // session expirée
  }
  if (!reponse.ok) {
    throw new Error(donnees.erreur || "Erreur " + reponse.status);
  }
  return donnees;
}

// ─── Les onglets ───────────────────────────────────────────

function afficherOnglets() {
  const onglets = document.querySelector("#table-tabs");
  onglets.replaceChildren();
  for (const [nom, description] of Object.entries(tables)) {
    const bouton = document.createElement("button");
    bouton.type = "button";
    bouton.textContent = description.titre;
    bouton.setAttribute("aria-pressed", nom === tableCourante);
    bouton.addEventListener("click", () => {
      tableCourante = nom;
      fermerFormulaire();
      afficherOnglets();
      chargerPage(1);
    });
    onglets.append(bouton);
  }
}

// ─── La liste paginée ──────────────────────────────────────

async function chargerPage(page) {
  const description = tables[tableCourante];
  document.querySelector("#table-title").textContent = description.titre;
  messageTable.textContent = "Chargement…";
  boutonPrecedent.disabled = true;
  boutonSuivant.disabled = true;

  try {
    // Réponse : { "offres": [ … ], "page": 1, "total": 26 }
    const donnees = await api("GET", `/${tableCourante}?page=${page}&par_page=${parPage}`);
    lignes = donnees[tableCourante];
    pageCourante = donnees.page;
    const nombrePages = Math.max(1, Math.ceil(donnees.total / parPage));

    afficherLignes(description);
    messageTable.textContent = donnees.total + " ligne(s) au total.";
    document.querySelector("#page-status").textContent = `Page ${pageCourante} sur ${nombrePages}`;
    boutonPrecedent.disabled = pageCourante <= 1;
    boutonSuivant.disabled = pageCourante >= nombrePages;
  } catch (erreur) {
    lignes = [];
    afficherLignes(description);
    messageTable.textContent = "Impossible de charger la liste : " + erreur.message;
  }
}

function afficherLignes(description) {
  // En-têtes : les colonnes décrites + une colonne pour les boutons
  const entete = document.querySelector("#records-head");
  entete.replaceChildren();
  for (const [, titre] of description.colonnes) {
    const th = document.createElement("th");
    th.scope = "col";
    th.textContent = titre;
    entete.append(th);
  }
  const thActions = document.createElement("th");
  thActions.scope = "col";
  thActions.textContent = "Actions";
  entete.append(thActions);

  // Une ligne <tr> par enregistrement. textContent : aucun HTML venant de la base n'est interprété.
  const corps = document.querySelector("#records-body");
  corps.replaceChildren();
  for (const ligne of lignes) {
    const tr = document.createElement("tr");
    for (const [cle] of description.colonnes) {
      const td = document.createElement("td");
      td.textContent = ligne[cle] ?? "—";
      tr.append(td);
    }

    const tdActions = document.createElement("td");
    tdActions.className = "row-actions";
    const boutonModifier = document.createElement("button");
    boutonModifier.type = "button";
    boutonModifier.textContent = "Modifier";
    boutonModifier.addEventListener("click", () => ouvrirFormulaire(ligne));
    const boutonSupprimer = document.createElement("button");
    boutonSupprimer.type = "button";
    boutonSupprimer.className = "danger";
    boutonSupprimer.textContent = "Supprimer";
    boutonSupprimer.addEventListener("click", () => supprimer(ligne));
    tdActions.append(boutonModifier, boutonSupprimer);
    tr.append(tdActions);

    corps.append(tr);
  }
}

// ─── Supprimer ─────────────────────────────────────────────

async function supprimer(ligne) {
  if (!confirm(`Supprimer définitivement la ligne n°${ligne.id} de « ${tables[tableCourante].titre} » ?`)) {
    return;
  }
  try {
    await api("DELETE", `/${tableCourante}/${ligne.id}`);
    if (ligneEnCours && ligneEnCours.id === ligne.id) fermerFormulaire();
    // Si c'était la dernière ligne de la page, on revient à la page précédente
    await chargerPage(lignes.length === 1 && pageCourante > 1 ? pageCourante - 1 : pageCourante);
    messageTable.textContent = "Ligne n°" + ligne.id + " supprimée. " + messageTable.textContent;
  } catch (erreur) {
    messageTable.textContent = "Suppression refusée : " + erreur.message;
  }
}

// ─── Le formulaire (création et modification) ──────────────

// Remplit une liste déroulante avec les lignes d'une autre table (100 au plus)
async function remplirChoixLien(select, table, valeur) {
  const donnees = await api("GET", `/${table}?par_page=100`);
  for (const ligne of donnees[table]) {
    select.append(new Option(libelles[table](ligne), ligne.id));
  }
  select.value = valeur ?? "";
}

function creerChamp(champ, ligne) {
  const valeur = ligne ? ligne[champ.nom] : null;
  const obligatoire = champ.obligatoire || (champ.obligatoireCreation && !ligne);
  const label = document.createElement("label");
  label.textContent = champ.libelle + (obligatoire ? " *" : "");

  let element;
  if (champ.type === "textarea") {
    element = document.createElement("textarea");
    element.value = valeur ?? "";
  } else if (champ.type === "choix" || champ.type === "lien") {
    element = document.createElement("select");
    if (!obligatoire || champ.type === "lien") {
      element.append(new Option(obligatoire ? "— Choisir —" : "— Aucun —", ""));
    }
    if (champ.type === "choix") {
      for (const choix of champ.valeurs) element.append(new Option(choix, choix));
      element.value = valeur ?? champ.valeurs[0];
    } else {
      remplirChoixLien(element, champ.table, valeur).catch((erreur) => {
        messageFormulaire.textContent = "Liste « " + champ.libelle + " » indisponible : " + erreur.message;
      });
    }
  } else {
    element = document.createElement("input");
    element.type = champ.type || "text";
    if (champ.type === "checkbox") {
      element.checked = Boolean(valeur);
      label.classList.add("checkbox");
    } else if (champ.type === "password") {
      element.autocomplete = "new-password";
      if (ligne) element.placeholder = "Laisser vide pour garder l'actuel";
    } else {
      element.value = valeur ?? "";
      if (champ.type === "number") element.min = 0;
    }
  }

  element.name = champ.nom;
  element.required = obligatoire;
  label.append(element);
  // Les grands textes prennent toute la largeur de la grille
  if (champ.type === "textarea") label.classList.add("full");
  return label;
}

function ouvrirFormulaire(ligne) {
  ligneEnCours = ligne;
  const description = tables[tableCourante];
  document.querySelector("#record-title").textContent = ligne
    ? `Modifier : ${description.titre} n°${ligne.id}`
    : `Ajouter : ${description.titre}`;
  messageFormulaire.textContent = "";

  const champs = document.querySelector("#record-fields");
  champs.replaceChildren(...description.champs.map((champ) => creerChamp(champ, ligne)));
  panneau.hidden = false;
  document.querySelector("#record-title").focus();
}

function fermerFormulaire() {
  panneau.hidden = true;
  ligneEnCours = null;
}

formulaire.addEventListener("submit", async (event) => {
  event.preventDefault();
  // On envoie TOUS les champs : un PUT remplace la ligne entière (un champ absent serait vidé)
  const corps = {};
  for (const champ of tables[tableCourante].champs) {
    const element = formulaire.elements[champ.nom];
    corps[champ.nom] = champ.type === "checkbox" ? (element.checked ? 1 : 0) : element.value;
  }

  // L'admin ne se retire pas son propre rôle : il perdrait l'accès à cette page
  if (tableCourante === "personnes" && ligneEnCours && ligneEnCours.id === adminConnecte.id && corps.role !== "admin") {
    messageFormulaire.textContent = "Tu ne peux pas retirer ton propre rôle admin.";
    return;
  }

  const fieldset = document.querySelector("#record-fieldset");
  fieldset.disabled = true;
  messageFormulaire.textContent = "Enregistrement…";
  const modification = ligneEnCours !== null;
  try {
    const enregistre = modification
      ? await api("PUT", `/${tableCourante}/${ligneEnCours.id}`, corps)
      : await api("POST", `/${tableCourante}`, corps);
    fermerFormulaire();
    await chargerPage(modification ? pageCourante : 1);   // après une création : retour à la page 1
    messageTable.textContent = "Ligne n°" + enregistre.id + " enregistrée. " + messageTable.textContent;
  } catch (erreur) {
    messageFormulaire.textContent = "Non enregistré : " + erreur.message;
  } finally {
    fieldset.disabled = false;
  }
});

document.querySelector("#new-record").addEventListener("click", () => ouvrirFormulaire(null));
document.querySelector("#cancel-record").addEventListener("click", fermerFormulaire);
boutonPrecedent.addEventListener("click", () => chargerPage(pageCourante - 1));
boutonSuivant.addEventListener("click", () => chargerPage(pageCourante + 1));

// ─── Démarrage : la page n'est montrée qu'à un admin ───────
// La vraie protection est côté serveur (exigerAdmin() dans auth.php) :
// même en contournant ce JavaScript, l'API refuse les données à un non-admin.
sessionCourante.then((utilisateur) => {
  if (!utilisateur) {
    window.location.href = "/login.html";
    return;
  }
  if (utilisateur.role !== "admin") {
    messageAdmin.textContent = "Cette page est réservée aux administrateurs.";
    return;
  }
  adminConnecte = utilisateur;
  messageAdmin.textContent = "Connecté en tant que " + utilisateur.prenom + " " + utilisateur.nom + ".";
  document.querySelector("#admin-content").hidden = false;
  afficherOnglets();
  chargerPage(1);
});
