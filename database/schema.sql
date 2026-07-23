-- =====================================================================
-- schema.sql — Suivi stagiaire
-- Corrige les points 3.1, 3.2, 3.4 et 3.9 du rapport d'audit :
--   - script installable en une fois (points-virgules, CREATE DATABASE)
--   - séparation personne / stage / évaluation datée
--   - tables de référence pour les classes et établissements (plus d'ENUM)
-- Utilisation :
--   mysql -u root -p < database/schema.sql
--   mysql -u root -p staginf < database/seed.sql
-- Port MySQL utilisé par l'application : voir DB_PORT dans config.php (3307 sous XAMPP).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `staginf` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `staginf`;

-- ── Comptes utilisateurs de l'application ──
-- Correction 3.13 : l'identifiant était déjà UNIQUE, mais aucun rôle ni statut
-- actif n'existait. On ajoute :
--   - `role` pour distinguer les droits (admin vs utilisateur simple) ;
--   - `actif` pour pouvoir désactiver un compte sans le supprimer (et sans
--     casser l'historique du journal, qui référence users.id).
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` enum('admin','utilisateur') NOT NULL DEFAULT 'utilisateur',
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `mode_sombre` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tables de référence (remplacent les anciens ENUM, cf. 3.9) ──
CREATE TABLE `classe_ref` (
  `id_classe` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `ordre` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_classe`),
  UNIQUE KEY `uniq_classe_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `etablissement_ref` (
  `id_etablissement` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  PRIMARY KEY (`id_etablissement`),
  UNIQUE KEY `uniq_etablissement_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Personne (identité, indépendante du/des stage(s) qu'elle effectue) ──
CREATE TABLE `stagiaire` (
  `id_stagiaire` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `prenom` varchar(255) NOT NULL,
  PRIMARY KEY (`id_stagiaire`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Un stage : une période, une classe, un établissement, rattachés à une personne ──
-- Une même personne peut avoir plusieurs stages (cf. 3.4).
CREATE TABLE `stage` (
  `id_stage` int(11) NOT NULL AUTO_INCREMENT,
  `id_stagiaire` int(11) NOT NULL,
  `id_classe` int(11) NOT NULL,
  `id_etablissement` int(11) NOT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  -- ──`archive` TINYINT(1) NOT NULL DEFAULT 0; ── "enlever les lignes 186 et 186, si première fois" ──
  -- ──`archive_manuel` TINYINT(1) NOT NULL DEFAULT 0; ──
  PRIMARY KEY (`id_stage`),
  KEY `idx_stage_stagiaire` (`id_stagiaire`),
  CONSTRAINT `fk_stage_stagiaire` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaire` (`id_stagiaire`) ON DELETE CASCADE,
  CONSTRAINT `fk_stage_classe` FOREIGN KEY (`id_classe`) REFERENCES `classe_ref` (`id_classe`),
  CONSTRAINT `fk_stage_etablissement` FOREIGN KEY (`id_etablissement`) REFERENCES `etablissement_ref` (`id_etablissement`),
  CONSTRAINT `chk_stage_dates` CHECK (`date_debut` IS NULL OR `date_fin` IS NULL OR `date_fin` >= `date_debut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Catalogues de compétences / badges (inchangés dans leur principe) ──
CREATE TABLE `competence_technique` (
  `id_competence_technique` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  PRIMARY KEY (`id_competence_technique`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `competence_humaine` (
  `id_competence_humaine` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  PRIMARY KEY (`id_competence_humaine`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `badge` (
  `id_badge` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  PRIMARY KEY (`id_badge`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Correction 3.17 (déjà en place ici) : les trois tables de notes ci-dessous et la
-- table `evaluation` elle-même utilisent ON DELETE CASCADE de bout en bout. La
-- suppression d'un stage entraîne donc la suppression de ses évaluations ET des
-- notes de compétences par la base seule ; delete_stagiaire.php n'a plus qu'à
-- supprimer la ligne `stage`, sans dépendre d'un ordre de suppression applicatif.
-- ── Séance d'évaluation datée (cœur de la correction 3.1) ──
-- Chaque enregistrement représente UNE séance d'évaluation pour UN stage,
-- réalisée par UN évaluateur, à UNE date. On n'écrase plus jamais la précédente :
-- chaque soumission du formulaire (dès qu'au moins une note est saisie) crée une
-- nouvelle ligne, ce qui permet de comparer le début, le milieu et la fin du stage.
CREATE TABLE `evaluation` (
  `id_evaluation` int(11) NOT NULL AUTO_INCREMENT,
  `id_stage` int(11) NOT NULL,
  `id_evaluateur` int(11) DEFAULT NULL,
  `date_evaluation` datetime NOT NULL DEFAULT current_timestamp(),
  `note` decimal(4,2) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  PRIMARY KEY (`id_evaluation`),
  KEY `idx_evaluation_stage` (`id_stage`, `date_evaluation`),
  CONSTRAINT `fk_evaluation_stage` FOREIGN KEY (`id_stage`) REFERENCES `stage` (`id_stage`) ON DELETE CASCADE,
  CONSTRAINT `fk_evaluation_evaluateur` FOREIGN KEY (`id_evaluateur`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_evaluation_note` CHECK (`note` IS NULL OR (`note` >= 0 AND `note` <= 20))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Notes de compétences techniques, rattachées à une évaluation datée ──
CREATE TABLE `evaluation_competence_technique` (
  `id_evaluation` int(11) NOT NULL,
  `id_competence_technique` int(11) NOT NULL,
  `niveau` tinyint(4) NOT NULL CHECK (`niveau` between 1 and 3),
  PRIMARY KEY (`id_evaluation`, `id_competence_technique`),
  KEY `idx_ect_competence` (`id_competence_technique`),
  CONSTRAINT `fk_ect_evaluation` FOREIGN KEY (`id_evaluation`) REFERENCES `evaluation` (`id_evaluation`) ON DELETE CASCADE,
  CONSTRAINT `fk_ect_competence` FOREIGN KEY (`id_competence_technique`) REFERENCES `competence_technique` (`id_competence_technique`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `evaluation_competence_humaine` (
  `id_evaluation` int(11) NOT NULL,
  `id_competence_humaine` int(11) NOT NULL,
  `niveau` tinyint(4) NOT NULL CHECK (`niveau` between 1 and 5),
  PRIMARY KEY (`id_evaluation`, `id_competence_humaine`),
  KEY `idx_ech_competence` (`id_competence_humaine`),
  CONSTRAINT `fk_ech_evaluation` FOREIGN KEY (`id_evaluation`) REFERENCES `evaluation` (`id_evaluation`) ON DELETE CASCADE,
  CONSTRAINT `fk_ech_competence` FOREIGN KEY (`id_competence_humaine`) REFERENCES `competence_humaine` (`id_competence_humaine`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `evaluation_badge` (
  `id_evaluation` int(11) NOT NULL,
  `id_badge` int(11) NOT NULL,
  `niveau` tinyint(4) NOT NULL CHECK (`niveau` between 1 and 3),
  PRIMARY KEY (`id_evaluation`, `id_badge`),
  KEY `idx_eb_badge` (`id_badge`),
  CONSTRAINT `fk_eb_evaluation` FOREIGN KEY (`id_evaluation`) REFERENCES `evaluation` (`id_evaluation`) ON DELETE CASCADE,
  CONSTRAINT `fk_eb_badge` FOREIGN KEY (`id_badge`) REFERENCES `badge` (`id_badge`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Journal des modifications (suivi d'audit) ──
-- Rattaché au stage (la « fiche » manipulée dans l'interface) plutôt qu'à la
-- seule personne, puisqu'une personne peut avoir plusieurs stages.
-- Correction 3.16 : `details` était limité à VARCHAR(255), ce qui pouvait tronquer
-- une modification touchant de nombreux critères (ou provoquer une erreur en mode
-- SQL strict). Passage en TEXT ; construireDetailsModification() (formulaire_stagiaires.php)
-- inclut désormais les anciennes ET nouvelles valeurs pour les champs importants,
-- pas seulement leurs noms.
CREATE TABLE `journal_modifications` (
  `id_journal` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) DEFAULT NULL,
  `nom_user` varchar(100) NOT NULL,
  `action` enum('creation','modification','suppression') NOT NULL,
  `id_stage` int(11) DEFAULT NULL,
  `nom_stagiaire` varchar(200) NOT NULL,
  `details` text DEFAULT NULL,
  `date_action` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_journal`),
  KEY `fk_journal_user` (`id_user`),
  KEY `idx_journal_date` (`date_action`),
  KEY `idx_journal_stagiaire` (`nom_stagiaire`),
  CONSTRAINT `fk_journal_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_journal_stage` FOREIGN KEY (`id_stage`) REFERENCES `stage` (`id_stage`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Protection anti brute-force (indépendante de la session PHP) ──
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifiant` varchar(100) NOT NULL,
  `adresse_ip` varchar(45) NOT NULL,
  `date_tentative` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_identifiant_date` (`identifiant`, `date_tentative`),
  KEY `idx_ip_date` (`adresse_ip`, `date_tentative`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE stage ADD COLUMN archive TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE stage ADD COLUMN archive_manuel TINYINT(1) NOT NULL DEFAULT 0;