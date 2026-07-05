/* TABLE USERS */
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `mode_sombre` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
)

/* TABLE stagiaire */
CREATE TABLE `stagiaire` (
  `id_stagiaire` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `prenom` varchar(255) NOT NULL,
  `classe` enum('Seconde','Première','Terminale','Post-bac','Licence 1','Licence 2','Licence 3') NOT NULL,
  `etablissement` enum('Lycée Dick Ukeiwé','Lycée polyvalent du Mont-Dore','Université de la Nouvelle-Calédonie (UNC)') NOT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  PRIMARY KEY (`id_stagiaire`)
)

/* TABLE competence_technique */
CREATE TABLE `competence_technique` (
  `id_competence_technique` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  PRIMARY KEY (`id_competence_technique`)
)

/* TABLE competence_humaine  */
CREATE TABLE `competence_humaine` (
  `id_competence_humaine` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  PRIMARY KEY (`id_competence_humaine`)
)

/* TABLE badge  */
CREATE TABLE `badge` (
  `id_badge` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  PRIMARY KEY (`id_badge`)
)

/* TABLE evaluation_competence_technique  */
CREATE TABLE `evaluation_competence_technique` (
  `id_stagiaire` int(11) NOT NULL,
  `id_competence_technique` int(11) NOT NULL,
  `niveau` tinyint(4) DEFAULT NULL CHECK (`niveau` between 1 and 3),
  PRIMARY KEY (`id_stagiaire`,`id_competence_technique`),
  KEY `id_competence_technique` (`id_competence_technique`),
  CONSTRAINT `evaluation_competence_technique_ibfk_1` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaire` (`id_stagiaire`),
  CONSTRAINT `evaluation_competence_technique_ibfk_2` FOREIGN KEY (`id_competence_technique`) REFERENCES `competence_technique` (`id_competence_technique`)
)

/* TABLE evaluation_competence_humaine */
CREATE TABLE `evaluation_competence_humaine` (
  `id_stagiaire` int(11) NOT NULL,
  `id_competence_humaine` int(11) NOT NULL,
  `niveau` tinyint(4) DEFAULT NULL CHECK (`niveau` between 1 and 5),
  PRIMARY KEY (`id_stagiaire`,`id_competence_humaine`),
  KEY `id_competence_humaine` (`id_competence_humaine`),
  CONSTRAINT `evaluation_competence_humaine_ibfk_1` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaire` (`id_stagiaire`),
  CONSTRAINT `evaluation_competence_humaine_ibfk_2` FOREIGN KEY (`id_competence_humaine`) REFERENCES `competence_humaine` (`id_competence_humaine`)
)

/* TABLE evaluation_badge */
CREATE TABLE `evaluation_badge` (
  `id_stagiaire` int(11) NOT NULL,
  `id_badge` int(11) NOT NULL,
  `niveau` tinyint(4) DEFAULT NULL CHECK (`niveau` between 1 and 3),
  PRIMARY KEY (`id_stagiaire`,`id_badge`),
  KEY `id_badge` (`id_badge`),
  CONSTRAINT `evaluation_badge_ibfk_1` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaire` (`id_stagiaire`),
  CONSTRAINT `evaluation_badge_ibfk_2` FOREIGN KEY (`id_badge`) REFERENCES `badge` (`id_badge`)
)

/* TABLE notation */
CREATE TABLE `notation` (
  `id_stagiaire` int(11) NOT NULL,
  `note` decimal(4,2) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `date_notation` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_stagiaire`),
  CONSTRAINT `fk_notation_stagiaire` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaire` (`id_stagiaire`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_notation_note` CHECK (`note` >= 0 and `note` <= 20)
)

/* TABLE suivi des modification */
CREATE TABLE `journal_modifications` (
  `id_journal` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) DEFAULT NULL,
  `nom_user` varchar(100) NOT NULL,
  `action` enum('creation','modification','suppression') NOT NULL,
  `id_stagiaire` int(11) DEFAULT NULL,
  `nom_stagiaire` varchar(200) NOT NULL,
  `details` varchar(255) DEFAULT NULL,
  `date_action` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_journal`),
  KEY `fk_journal_user` (`id_user`),
  KEY `idx_journal_date` (`date_action`),
  KEY `idx_journal_stagiaire` (`nom_stagiaire`),
  CONSTRAINT `fk_journal_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE SET NULL
)