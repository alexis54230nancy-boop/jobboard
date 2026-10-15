const offersList = document.querySelector("#offers-list");
const recherche = document.querySelector("#search");
const resultat = document.querySelector("#offers-message");
const detail = document.querySelector("#offer-detail");
const panneauCandidature = document.querySelector("#apply-panel");
const formulaire = document.querySelector("#apply-form");
const messageCandidature = document.querySelector("#apply-message");
const boutonReessayer = document.querySelector("#retry-offers");
const boutonPrecedent = document.querySelector("#previous-page");
const boutonSuivant = document.querySelector("#next-page");
const etatPagination = document.querySelector("#page-status");
const champsCandidature = document.querySelector("#apply-fields");
const offresParPage = 6;
let offres = [];
let pageCourante = 1;
let totalOffres = 0;
let chargementEnCours = false;
let envoiEnCours = false;
let numeroDemandeDetail = 0;
let offreSelectionnee = null;
let boutonOrigineDetail = null;
let boutonOrigineCandidature = null;
let utilisateur = null;   // le compte connecté (voir session.js), ou null

// L'API renvoie { offres: [...], page: 1, total: 4 }, pas directement un tableau.
async function recupererOffres(page) {
  const reponse = await fetch(`/offres?page=${page}&par_page=${offresParPage}`);
  const donnees = await reponse.json();
  if (!reponse.ok) {
    throw new Error(donnees.erreur || "Impossible de charger les offres.");
  }
  if (!Array.isArray(donnees.offres)) {
    throw new Error("L'API ne renvoie pas une liste d'offres.");
  }
  return donnees;
}

function afficherOffres(liste) {
  offersList.replaceChildren();
  resultat.textContent = liste.length === 0
    ? "Aucune offre trouvée sur cette page."
    : liste.length + " offre(s) sur cette page · " + totalOffres + " au total.";

  for (const offre of liste) {
    const carte = document.createElement("article");
    const titre = document.createElement("h2");
    titre.textContent = offre.titre;
    const informations = document.createElement("p");
    informations.textContent = offre.entreprise + " · " + (offre.lieu || "Lieu non précisé");
    const resume = document.createElement("p");
    resume.textContent = offre.description_courte;

    const actions = document.createElement("div");
    actions.className = "actions";
    const boutonDetail = document.createElement("button");
    boutonDetail.type = "button";
    boutonDetail.textContent = "En savoir plus";
    boutonDetail.addEventListener("click", () => {
      boutonOrigineDetail = boutonDetail;
      afficherDetail(offre.id);
    });

    const boutonPostuler = document.createElement("button");
    boutonPostuler.type = "button";
    boutonPostuler.textContent = "Postuler";
    boutonPostuler.addEventListener("click", () => {
      boutonOrigineCandidature = boutonPostuler;
      ouvrirCandidature(offre.id);
    });

    actions.append(boutonDetail, boutonPostuler);
    carte.append(titre, informations, resume, actions);
    offersList.append(carte);
  }
}

function filtrerOffres() {
  const texte = recherche.value.trim().toLowerCase();
  const selection = offres.filter((offre) => {
    const contenu = offre.titre + " " + offre.entreprise + " " + (offre.lieu || "");
    return contenu.toLowerCase().includes(texte);
  });
  afficherOffres(selection);
}

// [["Lieu", "Nancy"], …] → une liste <dl> : <dt>Lieu</dt><dd>Nancy</dd>…
function afficherInfos(lignes) {
  const liste = document.querySelector("#detail-infos");
  liste.replaceChildren();
  for (const [libelle, valeur] of lignes) {
    const terme = document.createElement("dt");
    terme.textContent = libelle;
    const definition = document.createElement("dd");
    definition.textContent = valeur;
    liste.append(terme, definition);
  }
}

// Le salaire est rangé en deux nombres (brut annuel) : min et max, égaux pour un salaire fixe
function formaterSalaire(min, max) {
  const euros = (n) => n.toLocaleString("fr-FR") + " €";
  if (min === null && max === null) return "Selon profil";
  if (max === null || min === max) return euros(min ?? max) + " brut / an";
  if (min === null) return "Jusqu'à " + euros(max) + " brut / an";
  return euros(min) + " – " + euros(max) + " brut / an";
}

async function afficherDetail(id) {
  // Seule la derniere demande peut modifier le panneau apres des clics rapides.
  const numero = ++numeroDemandeDetail;
  detail.hidden = false;
  document.querySelector("#detail-title").textContent = "Chargement de l'offre…";
  document.querySelector("#detail-infos").replaceChildren();
  document.querySelector("#detail-description").textContent = "";
  document.querySelector("#detail-title").focus();
  try {
    const reponse = await fetch(`/offres/${id}`);
    const offre = await reponse.json();
    if (!reponse.ok) {
      throw new Error(offre.erreur || "Impossible de charger cette offre.");
    }
    if (numero !== numeroDemandeDetail) return;
    document.querySelector("#detail-title").textContent = offre.titre;
    afficherInfos([
      ["Entreprise", offre.entreprise],
      ["Catégorie", offre.categorie],
      ["Lieu", offre.lieu || "Lieu non précisé"],
      ["Contrat", offre.type_de_contrat || "Non précisé"],
      ["Temps de travail", offre.temps_de_travail || "Non précisé"],
      ["Salaire", formaterSalaire(offre.salaire_min, offre.salaire_max)],
      ["Publiée le", offre.date_de_publication.split("-").reverse().join("/")]   // « 2026-10-15 » → « 15/10/2026 »
    ]);
    document.querySelector("#detail-description").textContent = offre.description || offre.description_courte;
  } catch (erreur) {
    if (numero !== numeroDemandeDetail) return;
    document.querySelector("#detail-title").textContent = "Offre indisponible";
    document.querySelector("#detail-description").textContent = erreur.message;
  }
}

function ouvrirCandidature(id) {
  if (envoiEnCours) return;
  offreSelectionnee = offres.find((offre) => offre.id === id);
  if (!offreSelectionnee) return;

  document.querySelector("#apply-offer").textContent = offreSelectionnee.titre + " · " + offreSelectionnee.entreprise;
  formulaire.elements.offre_id.value = offreSelectionnee.id;
  messageCandidature.textContent = "";
  panneauCandidature.hidden = false;
  (utilisateur ? formulaire.elements.message : formulaire.elements.prenom).focus();
}

// Utilisateur connecté → ses coordonnées sont déjà connues : on cache les champs d'identité.
function adapterFormulaire() {
  const champsIdentite = document.querySelector("#identity-fields");
  const identite = document.querySelector("#apply-identity");
  champsIdentite.hidden = utilisateur !== null;
  identite.hidden = utilisateur === null;
  for (const champ of ["prenom", "nom", "email"]) {
    formulaire.elements[champ].required = utilisateur === null;   // un champ caché ne peut pas être requis
  }
  if (utilisateur) {
    identite.textContent = "Tu postules en tant que " + utilisateur.prenom + " " + utilisateur.nom
      + " (" + utilisateur.email + ") : tes coordonnées sont reprises de ton compte.";
  }
}

formulaire.addEventListener("submit", async (event) => {
  event.preventDefault();
  if (!offreSelectionnee || envoiEnCours) return;

  const donnees = new FormData(formulaire);
  // Connecté : seul le message est demandé, le serveur reprend le reste du compte
  const champsObligatoires = utilisateur ? ["message"] : ["prenom", "nom", "email", "message"];
  for (const champ of champsObligatoires) {
    if (!donnees.get(champ).trim()) {
      messageCandidature.textContent = "Complète les champs obligatoires avec autre chose que des espaces.";
      formulaire.elements[champ].focus();
      return;
    }
  }
  const candidature = utilisateur
    ? { message: donnees.get("message") }
    : Object.fromEntries(donnees.entries());
  const offre = offreSelectionnee;
  envoiEnCours = true;
  champsCandidature.disabled = true;
  messageCandidature.textContent = "Envoi de la candidature…";
  try {
    const reponse = await fetch(`/offres/${offre.id}/candidatures`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(candidature)
    });
    const resultat = await reponse.json();
    if (!reponse.ok) {
      throw new Error(resultat.erreur || "La candidature n'a pas pu être enregistrée.");
    }
    messageCandidature.textContent = "Candidature enregistrée pour « " + offre.titre + " » (référence " + resultat.id + ").";
    formulaire.reset();
    formulaire.elements.offre_id.value = offre.id;
  } catch (erreur) {
    messageCandidature.textContent = "Envoi non confirmé : " + erreur.message + " Les champs ont été conservés.";
  } finally {
    envoiEnCours = false;
    champsCandidature.disabled = false;
  }
});

document.querySelector("#close-detail").addEventListener("click", () => {
  numeroDemandeDetail++;
  detail.hidden = true;
  if (boutonOrigineDetail && boutonOrigineDetail.isConnected) {
    boutonOrigineDetail.focus();
  } else {
    recherche.focus();
  }
});

document.querySelector("#close-apply").addEventListener("click", () => {
  panneauCandidature.hidden = true;
  if (boutonOrigineCandidature && boutonOrigineCandidature.isConnected) {
    boutonOrigineCandidature.focus();
  } else {
    recherche.focus();
  }
});

async function chargerPage(page) {
  if (chargementEnCours) return;
  chargementEnCours = true;
  recherche.disabled = true;
  boutonPrecedent.disabled = true;
  boutonSuivant.disabled = true;
  boutonReessayer.hidden = true;
  resultat.textContent = "Chargement des offres…";
  try {
    const donnees = await recupererOffres(page);
    offres = donnees.offres;
    pageCourante = donnees.page;
    totalOffres = donnees.total;
    numeroDemandeDetail++;
    detail.hidden = true;
    recherche.disabled = false;
    filtrerOffres();
  } catch (erreur) {
    offres = [];
    totalOffres = 0;
    offersList.replaceChildren();
    detail.hidden = true;
    numeroDemandeDetail++;
    resultat.textContent = "Impossible de charger les offres. Vérifie que le serveur PHP est lancé, puis réessaie.";
    boutonReessayer.hidden = false;
    console.error(erreur);
  } finally {
    chargementEnCours = false;
    const nombrePages = Math.max(1, Math.ceil(totalOffres / offresParPage));
    etatPagination.textContent = totalOffres === 0 ? "" : `Page ${pageCourante} sur ${nombrePages}`;
    boutonPrecedent.disabled = pageCourante <= 1 || totalOffres === 0;
    boutonSuivant.disabled = pageCourante >= nombrePages || totalOffres === 0;
  }
}

recherche.addEventListener("input", filtrerOffres);
boutonPrecedent.addEventListener("click", () => chargerPage(pageCourante - 1));
boutonSuivant.addEventListener("click", () => chargerPage(pageCourante + 1));
boutonReessayer.addEventListener("click", () => chargerPage(pageCourante));
sessionCourante.then((compte) => {
  utilisateur = compte;
  adapterFormulaire();
});
chargerPage(1);
