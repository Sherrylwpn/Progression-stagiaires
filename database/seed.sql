-- =====================================================================
-- seed.sql — Données minimales pour démarrer l'application
-- À exécuter après schema.sql :
--   mysql -u root -p staginf < database/seed.sql
-- =====================================================================
USE `staginf`;

-- ── Classes (reprend les valeurs de l'ancien ENUM, désormais éditables) ──
INSERT INTO `classe_ref` (`nom`, `ordre`) VALUES
  ('Seconde', 1),
  ('Première', 2),
  ('Terminale', 3),
  ('Post-bac', 4),
  ('Licence 1', 5),
  ('Licence 2', 6),
  ('Licence 3', 7);

-- ── Établissements ──
INSERT INTO `etablissement_ref` (`nom`) VALUES
  ('Lycée Dick Ukeiwé'),
  ('Lycée polyvalent du Mont-Dore'),
  ('Université de la Nouvelle-Calédonie (UNC)');

-- ── Compétences techniques ──
INSERT INTO `competence_technique` (`nom`) VALUES
  ('Inventaire matériel'),
  ('Diagnostic PC'),
  ('Préparation PC'),
  ('Installation OS'),
  ('GLPI: Mise à jour données'),
  ('GLPI: Réponse tickets'),
  ('Documentation'),
  ('Communication IT'),
  ('Organisataion'),
  ('Invention réseau'),
  ('Déploiement réseau : FOG'),
  ('Rédaction technique'),
  ('Clavier Expert'),
  ('Support utilisateur')
  ('Projet');

-- ── Compétences humaines ──
INSERT INTO `competence_humaine` (`nom`) VALUES
  ('Autonomie progressive'),
  ('Communication professionnel'),
  ('Ponctualité'),
  ('Travail en équipe'),
  ('Ecoute active'),
  ('Curiosité professionnelle');
  ('Téléphone : prise note & transmission claire'),
  ('Gestion du temps'),
  ('Politesse'),
  ('Capacité apprentissage rapide');

-- ── Badges ──
INSERT INTO `badge` (`nom`) VALUES
  ('Gestion'),
  ('Maintenance'),
  ('Technicien')
  ('Clavier'),
  ('Support & GLPI'),
  ('Réseau & Infrastructure'),
  ('Projet');

-- ── Compte administrateur initial .
INSERT INTO `users` (`nom`, `mot_de_passe`, `role`, `actif`, `mode_sombre`) VALUES
  ('Romain', '$2y$10$79AjECvTuxkXq60Hvt5SHuuIWpf9CfTto5PJNRmcla9eKlcwCzA9i', 'admin', 1, 0);
  ('Valérie', '$2y$10$79AjECvTuxkXq60Hvt5SHuuIWpf9CfTto5PJNRmcla9eKlcwCzA9i', 'admin', 1, 0);

ALTER TABLE stage ADD COLUMN archive TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE stage ADD COLUMN archive_manuel TINYINT(1) NOT NULL DEFAULT 0;