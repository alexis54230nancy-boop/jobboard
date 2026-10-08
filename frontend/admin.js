console.log("admin.js is running");

const applications = [
    {
      offre: "Développeur Web",
      candidat: "Alice Dupont",
      date: "2024-06-01"
    },
    {
    offre: "Analyste cybersécurité",
    candidat: "Bob Martin",
    date: "2024-06-02"
  },
  {
    offre: "Ingénieur DevOps",
    candidat: "Charlie Durand",
    date: "2024-06-03"
  },
  {
    offre: "DevSecOps",
    candidat: "Diana Leroy",
    date: "2024-06-04"
  }         






]


const applicationsList = document.querySelector("#applications-list");

for (const application of applications) {
  applicationsList.innerHTML += `
    <tr>
      <td>${application.offre}</td>
      <td>${application.candidat}</td>
      <td>${application.date}</td>
    </tr>
  `;
}

for (const application of applications) {
  console.log(application.offre + " - " + application.candidat + " - " + application.date);
}   
