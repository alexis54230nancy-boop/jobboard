// Partagé par toutes les pages, et chargé AVANT le script propre à la page.
// 1. Demande au serveur qui est connecté (GET /session).
// 2. Adapte la barre de navigation : Connexion/Inscription, ou Mon compte/Administration/Déconnexion.
//
// « sessionCourante » est une promesse : les autres scripts font
//   sessionCourante.then((utilisateur) => { … })
// et reçoivent l'utilisateur connecté, ou null si personne ne l'est.

async function recupererSession() {
  try {
    const reponse = await fetch("/session", { credentials: "same-origin" });
    if (!reponse.ok) return null;   // 401 : personne n'est connecté
    const donnees = await reponse.json();
    return donnees.utilisateur;
  } catch (erreur) {
    return null;                    // serveur injoignable : on fait comme si personne n'était connecté
  }
}

const sessionCourante = recupererSession();

function creerLien(adresse, texte) {
  const lien = document.createElement("a");
  lien.href = adresse;
  lien.textContent = texte;
  // Le lien de la page ouverte est signalé (style + lecteurs d'écran)
  if (window.location.pathname.endsWith("/" + adresse)) {
    lien.setAttribute("aria-current", "page");
  }
  return lien;
}

async function deconnecter() {
  await fetch("/deconnexion", { method: "POST", credentials: "same-origin" });
  window.location.href = "/index.html";
}

sessionCourante.then((utilisateur) => {
  const nav = document.querySelector(".site-nav");
  if (!nav) return;

  const elements = [creerLien("index.html", "Offres")];
  if (!utilisateur) {
    elements.push(creerLien("login.html", "Connexion"), creerLien("register.html", "Inscription"));
  } else {
    if (utilisateur.role === "admin") {
      elements.push(creerLien("admin.html", "Administration"));
    }
    elements.push(creerLien("register.html", "Mon compte"));

    const bouton = document.createElement("button");
    bouton.type = "button";
    bouton.textContent = "Déconnexion (" + utilisateur.prenom + ")";
    bouton.addEventListener("click", deconnecter);
    elements.push(bouton);
  }
  nav.replaceChildren(...elements);
});
