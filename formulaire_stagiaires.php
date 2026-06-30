<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Formulaire Stagiaire</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      background-color: #2a1a5e;
      font-family: 'Segoe UI', Arial, sans-serif;
      display: flex;
      justify-content: center;
      padding: 30px 16px;
      min-height: 100vh;
    }

    .card {
      background-color: #d9d9d9;
      border-radius: 6px;
      width: 100%;
      max-width: 460px;
      padding: 24px 20px 32px;
    }

    /* ── Titre ── */
    h1 {
      text-align: center;
      font-size: 1.35rem;
      font-weight: 700;
      color: #111;
      margin-bottom: 20px;
    }

    /* ── Sections ── */
    .section-title {
      font-size: 0.95rem;
      font-weight: 700;
      color: #111;
      margin-bottom: 10px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .section-title::after {
      content: '';
      flex: 1;
      height: 1px;
      background: #888;
    }

    /* ── Informations générales ── */
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px 16px;
      margin-bottom: 22px;
    }

    .field label {
      display: block;
      font-size: 0.78rem;
      font-weight: 600;
      color: #111;
      margin-bottom: 5px;
    }

    .field label::before {
      content: '• ';
    }

    .field input {
      width: 100%;
      height: 26px;
      background-color: #b8a8c8;
      border: none;
      border-radius: 3px;
      outline: none;
      padding: 0 6px;
      font-size: 0.8rem;
    }

    /* ── Grille compétences ── */
    .skills-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px 8px;
      margin-bottom: 22px;
    }

    .skill-item {
      background-color: #d9d9d9;
      text-align: center;
    }

    .skill-item .skill-name {
      font-size: 0.7rem;
      font-weight: 600;
      color: #111;
      margin-bottom: 4px;
      min-height: 2.2em;
      display: flex;
      align-items: center;
      justify-content: center;
      line-height: 1.2;
    }

    /* ── Stars ── */
    .stars {
      display: flex;
      justify-content: center;
      gap: 3px;
      padding-bottom: 6px;
      border-bottom: 1px solid #888;
    }

    .star {
      font-size: 1.1rem;
      color: #333;
      cursor: pointer;
      transition: color 0.15s;
      -webkit-text-stroke: 1px #555;
    }

    .star.filled {
      color: #555;
    }

    .star:hover,
    .star:hover ~ .star {
      color: #aaa;
    }

    /* Stars container — hover effect from left */
    .stars:hover .star {
      color: #aaa;
    }

    .stars .star:hover ~ .star {
      color: #333;
    }

    /* ── Responsive: 2 colonnes pour les grandes compétences soft skills ── */
    .skills-grid-soft {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px 8px;
      margin-bottom: 8px;
    }

    .skill-item-soft {
      background-color: #d9d9d9;
      text-align: center;
    }

    .skill-item-soft .skill-name {
      font-size: 0.7rem;
      font-weight: 600;
      color: #111;
      margin-bottom: 4px;
      min-height: 2.2em;
      display: flex;
      align-items: center;
      justify-content: center;
      line-height: 1.2;
    }

    .stars-5 {
      display: flex;
      justify-content: center;
      gap: 2px;
      padding-bottom: 6px;
      border-bottom: 1px solid #888;
    }

    .stars-5 .star {
      font-size: 0.95rem;
    }
  </style>
</head>
<body>
  <div class="card">
    <h1>Formulaire stagiaire</h1>

    <!-- Informations générales -->
    <div class="section-title">Informations générales</div>
    <div class="info-grid">
      <div class="field">
        <label>Nom</label>
        <input type="text">
      </div>
      <div class="field">
        <label>Prénom</label>
        <input type="text">
      </div>
      <div class="field">
        <label>Classe</label>
        <input type="text">
      </div>
      <div class="field">
        <label>Etablissement</label>
        <input type="text">
      </div>
    </div>

    <!-- Compétences techniques -->
    <div class="section-title">Compétences technique</div>
    <div class="skills-grid">

      <div class="skill-item">
        <div class="skill-name">Inventaire matériel</div>
        <div class="stars" data-max="3">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item">
        <div class="skill-name">Diagnostic PC</div>
        <div class="stars" data-max="3">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item">
        <div class="skill-name">Préparation PC</div>
        <div class="stars" data-max="3">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item">
        <div class="skill-name">Installation OS</div>
        <div class="stars" data-max="3">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item">
        <div class="skill-name">GLPI : Réponse tickets</div>
        <div class="stars" data-max="3">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item">
        <div class="skill-name">GLPI : Mis à jour données</div>
        <div class="stars" data-max="3">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item">
        <div class="skill-name">Documentation</div>
        <div class="stars" data-max="3">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item">
        <div class="skill-name">Support utilisateur</div>
        <div class="stars" data-max="3">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item">
        <div class="skill-name">Communication IT</div>
        <div class="stars" data-max="3">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item">
        <div class="skill-name">Organisation</div>
        <div class="stars" data-max="3">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item">
        <div class="skill-name">Intervention réseau</div>
        <div class="stars" data-max="3">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item">
        <div class="skill-name">Déploiement réseau : FOG</div>
        <div class="stars" data-max="3">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item">
        <div class="skill-name">Rédaction technique</div>
        <div class="stars" data-max="3">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item">
        <div class="skill-name">Clavier Expert</div>
        <div class="stars" data-max="3">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item">
        <div class="skill-name">Projet</div>
        <div class="stars" data-max="3">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

    </div>

    <!-- Compétences comportementales / soft skills -->
    <div class="section-title">Compétences technique</div>
    <div class="skills-grid-soft">

      <div class="skill-item-soft">
        <div class="skill-name">Travail en équipe</div>
        <div class="stars-5">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item-soft">
        <div class="skill-name">Communication professionnel</div>
        <div class="stars-5">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item-soft">
        <div class="skill-name">Ecoute active</div>
        <div class="stars-5">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item-soft">
        <div class="skill-name">Téléphone : prise notes & transmission claire</div>
        <div class="stars-5">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item-soft">
        <div class="skill-name">Gestion du temps</div>
        <div class="stars-5">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item-soft">
        <div class="skill-name">Autonomie progressive</div>
        <div class="stars-5">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item-soft">
        <div class="skill-name">Ponctualité</div>
        <div class="stars-5">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item-soft">
        <div class="skill-name">Politesse</div>
        <div class="stars-5">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item-soft">
        <div class="skill-name">Capacité d'apprentissage rapide</div>
        <div class="stars-5">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

      <div class="skill-item-soft">
        <div class="skill-name">Curiosité professionnelle</div>
        <div class="stars-5">
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
          <span class="star">★</span>
        </div>
      </div>

    </div>
  </div>

  <script>
    // Star rating interactivity
    document.querySelectorAll('.stars, .stars-5').forEach(container => {
      const stars = container.querySelectorAll('.star');
      let currentRating = 0;

      stars.forEach((star, index) => {
        // Hover: highlight up to hovered star
        star.addEventListener('mouseenter', () => {
          stars.forEach((s, i) => {
            s.style.color = i <= index ? '#111' : '#aaa';
          });
        });

        // Click: set rating
        star.addEventListener('click', () => {
          currentRating = index + 1;
          updateStars();
        });
      });

      // Mouse leave: revert to current rating
      container.addEventListener('mouseleave', () => {
        updateStars();
      });

      function updateStars() {
        stars.forEach((s, i) => {
          s.style.color = i < currentRating ? '#111' : '#aaa';
        });
      }

      // Init: all grey (unrated)
      updateStars();
    });
  </script>
</body>
</html>
