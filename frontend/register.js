// Deux modes pour la même page :
//   personne n'est connecté → inscription : POST /inscription
//   déjà connecté      → « Mon compte » : formulaire pré-rempli, PUT /session
const formulaire = document.querySelector("#register-form");
const message = document.querySelector("#register-message");
const bouton = formulaire.querySelector('button[type="submit"]');
let modeModification = false;

function passerEnModeModification(utilisateur) {
  modeModification = true;
  document.title = "Mon compte - Job Board";
  document.querySelector("#account-title").textContent = "Mon compte";
  document.querySelector("#account-notice").textContent = "Modifie tes informations. Laisse le mot de passe vide pour garder l'actuel.";
  document.querySelector("#password-label").textContent = "Nouveau mot de passe (facultatif)";
  bouton.textContent = "Enregistrer les modifications";
  document.querySelector("#telephone-field").hidden = false;

  for (const champ of ["prenom", "nom", "email", "telephone"]) {
    formulaire.elements[champ].value = utilisateur[champ] ?? "";
  }
  formulaire.elements.password.required = false;
  formulaire.elements.confirmPassword.required = false;
}

sessionCourante.then((utilisateur) => {
  if (utilisateur) passerEnModeModification(utilisateur);
});

formulaire.addEventListener("submit", async (event) => {
  event.preventDefault();
  const donnees = new FormData(formulaire);
  const motDePasse = String(donnees.get("password"));

  // En modification, un mot de passe vide veut dire « ne pas le changer »
  if (motDePasse !== "" || !modeModification) {
    if (motDePasse !== donnees.get("confirmPassword")) {
      message.textContent = "Les mots de passe doivent correspondre.";
      formulaire.elements.confirmPassword.focus();
      return;
    }
    if (motDePasse.length < 8) {
      message.textContent = "Le mot de passe doit contenir au moins 8 caractères.";
      return;
    }
  }

  bouton.disabled = true;
  message.textContent = modeModification ? "Enregistrement…" : "Création du compte en cours…";
  try {
    const reponse = await fetch(modeModification ? "/session" : "/inscription", {
      method: modeModification ? "PUT" : "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify({ prenom: donnees.get("prenom"), nom: donnees.get("nom"),
        email: donnees.get("email"), telephone: donnees.get("telephone"), password: motDePasse })
    });
    // 404 en modification : le serveur n'a pas (encore) la route PUT /session
    if (modeModification && reponse.status === 404) {
      throw new Error("La modification du compte n'est pas encore disponible côté serveur (route PUT /session manquante).");
    }
    const resultat = await reponse.json();
    if (!reponse.ok) throw new Error(resultat.erreur || "Impossible d'enregistrer le compte.");

    if (modeModification) {
      message.textContent = "Compte mis à jour.";
      formulaire.elements.password.value = "";
      formulaire.elements.confirmPassword.value = "";
    } else {
      message.textContent = "Compte créé ! Redirection…";
      window.location.href = "/index.html";
    }
  } catch (erreur) {
    message.textContent = erreur.message || "Erreur réseau. Réessaie.";
  } finally {
    bouton.disabled = false;
  }
});
