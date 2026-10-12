const formulaire = document.querySelector("#login-form");
const message = document.querySelector("#login-message");

formulaire.addEventListener("submit", async (event) => {
  event.preventDefault();
  const bouton = formulaire.querySelector('button[type="submit"]');
  bouton.disabled = true;
  message.textContent = "Connexion en cours…";
  try {
    const donnees = new FormData(formulaire);
    const reponse = await fetch("/connexion", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify({ email: donnees.get("email"), password: donnees.get("password") })
    });
    const resultat = await reponse.json();
    if (!reponse.ok) throw new Error(resultat.erreur || "Impossible de se connecter.");
    message.textContent = `Bienvenue ${resultat.utilisateur.prenom} !`;
    window.location.href = resultat.utilisateur.role === "admin" ? "/admin.html" : "/index.html";
  } catch (erreur) {
    message.textContent = erreur.message || "Erreur réseau. Réessaie.";
  } finally {
    bouton.disabled = false;
  }
});
