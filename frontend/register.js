const formulaire = document.querySelector("#register-form");
const message = document.querySelector("#register-message");

formulaire.addEventListener("submit", async (event) => {
  event.preventDefault();
  const donnees = new FormData(formulaire);
  const bouton = formulaire.querySelector('button[type="submit"]');
  if (donnees.get("password") !== donnees.get("confirmPassword")) {
    message.textContent = "Les mots de passe doivent correspondre.";
    formulaire.elements.confirmPassword.focus();
    return;
  }
  if (String(donnees.get("password")).length < 8) {
    message.textContent = "Le mot de passe doit contenir au moins 8 caractères.";
    return;
  }
  bouton.disabled = true;
  message.textContent = "Création du compte en cours…";
  try {
    const reponse = await fetch("/inscription", {
      method: "POST", headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify({ prenom: donnees.get("prenom"), nom: donnees.get("nom"),
        email: donnees.get("email"), password: donnees.get("password") })
    });
    const resultat = await reponse.json();
    if (!reponse.ok) throw new Error(resultat.erreur || "Impossible de créer le compte.");
    message.textContent = "Compte créé ! Redirection…";
    window.location.href = "/index.html";
  } catch (erreur) {
    message.textContent = erreur.message || "Erreur réseau. Réessaie.";
  } finally {
    bouton.disabled = false;
  }
});
