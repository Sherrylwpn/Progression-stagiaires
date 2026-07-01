/* TABLE USERS */
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci

/* TABLE stagiaire */
CREATE TABLE `stagiaire` (
  `id_stagiaire` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `prenom` varchar(255) NOT NULL,
  `classe` ENUM('Seconde','Première','Terminale','Post-bac','Licence 1','Licence 2','Licence 3') NOT NULL;
  `etablissement` ENUM('Lycée Dick Ukeiwé','Lycée polyvalent du Mont-Dore','Université de la Nouvelle-Calédonie (UNC)') NOT NULL;
  PRIMARY KEY (`id_stagiaire`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_c

/* TABLE competence_technique */
CREATE TABLE `competence_technique` (
  `id_competence_technique` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  PRIMARY KEY (`id_competence_technique`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_c

/* TABLE competence_humaine  */
CREATE TABLE `competence_humaine` (
  `id_competence_humaine` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  PRIMARY KEY (`id_competence_humaine`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_c

/* TABLE badge  */
CREATE TABLE `badge` (
  `id_badge` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  PRIMARY KEY (`id_badge`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_c

/* TABLE evaluation_competence_technique  */
CREATE TABLE `evaluation_competence_technique` (
  `id_stagiaire` int(11) NOT NULL,
  `id_competence_technique` int(11) NOT NULL,
  `niveau` tinyint(4) DEFAULT NULL CHECK (`niveau` between 1 and 3),
  PRIMARY KEY (`id_stagiaire`,`id_competence_technique`),
  KEY `id_competence_technique` (`id_competence_technique`),
  CONSTRAINT `evaluation_competence_technique_ibfk_1` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaire` (`id_stagiaire`),
  CONSTRAINT `evaluation_competence_technique_ibfk_2` FOREIGN KEY (`id_competence_technique`) REFERENCES `competence_technique` (`id_competence_technique`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci

/* TABLE evaluation_competence_humaine */
CREATE TABLE `evaluation_competence_humaine` (
  `id_stagiaire` int(11) NOT NULL,
  `id_competence_humaine` int(11) NOT NULL,
  `niveau` tinyint(4) DEFAULT NULL CHECK (`niveau` between 1 and 5),
  PRIMARY KEY (`id_stagiaire`,`id_competence_humaine`),
  KEY `id_competence_humaine` (`id_competence_humaine`),
  CONSTRAINT `evaluation_competence_humaine_ibfk_1` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaire` (`id_stagiaire`),
  CONSTRAINT `evaluation_competence_humaine_ibfk_2` FOREIGN KEY (`id_competence_humaine`) REFERENCES `competence_humaine` (`id_competence_humaine`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci

/* TABLE evaluation_badge */
CREATE TABLE `evaluation_badge` (
  `id_stagiaire` int(11) NOT NULL,
  `id_badge` int(11) NOT NULL,
  `niveau` tinyint(4) DEFAULT NULL CHECK (`niveau` between 1 and 3),
  PRIMARY KEY (`id_stagiaire`,`id_badge`),
  KEY `id_badge` (`id_badge`),
  CONSTRAINT `evaluation_badge_ibfk_1` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaire` (`id_stagiaire`),
  CONSTRAINT `evaluation_badge_ibfk_2` FOREIGN KEY (`id_badge`) REFERENCES `badge` (`id_badge`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci