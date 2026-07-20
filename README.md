# Suivi stagiaire

Application web de suivi des stagiaires (fiches, évaluations par compétences,
badges, notation globale) pour un usage interne, réservé aux personnes
disposant d'un compte.

> Correction 3.18 : ce README remplace l'ancien, qui décrivait un dossier
> `config/` inexistant, un fichier `dashboard.php` jamais livré, parlait de
> `formulaire_stagiaire.php` (au singulier) et affirmait l'absence de comptes
> utilisateurs alors que l'application en gère. Le contenu ci-dessous décrit
> uniquement ce qui est réellement présent dans le dépôt.

## Arborescence réelle

```
.
├── config.php                      Connexion PDO, session, CSRF, auth, journal, anti brute-force
├── login.php                       Page de connexion
├── logout.php                      Déconnexion (POST + CSRF uniquement)
├── index.php                       Liste des stagiaires, filtres, menu, popups (sécurité/affichage)
├── formulaire_stagiaires.php       Création / modification d'un stage + saisie d'une évaluation
├── stagiaire_detail_fragment.php   Fragment HTML (chargé en fetch par index.php) : fiche détaillée
├── delete_stagiaire.php            Suppression d'un stage (POST + CSRF)
├── suivi_modifications.php         Journal des modifications (recherche, groupement, pagination)
├── securite_compte.php             Endpoint AJAX : changement de mot de passe
├── preference_affichage.php        Endpoint AJAX : mode sombre
├── database/
│   ├── schema.sql                  Structure complète de la base (installable en une fois)
│   ├── seed.sql                    Données de référence + compte admin initial
│   └── migration_3.13_3.14_3.16_3.17.sql   Migration pour une base déjà installée
├── index.css, style.css, parametres.css, login.css   Feuilles de style
└── README.md
```

> Remarque : `historique.php` et `parametres.php`, s'ils sont encore présents
> dans votre copie du projet, sont des versions antérieures remplacées
> respectivement par `suivi_modifications.php` et par les popups AJAX
> `securite_compte.php` / `preference_affichage.php` intégrées à `index.php`.
> Ils ne sont référencés par aucune page active et peuvent être supprimés ;
> vérifiez simplement qu'aucun lien externe (favoris, script externe) n'y
> pointe encore avant de les retirer.

## Prérequis

- PHP 8.x avec l'extension PDO MySQL
- MySQL / MariaDB
- Un serveur local type XAMPP/WAMP/MAMP, ou `php -S localhost:8000`

## Installation

1. **Base de données** — le port MySQL utilisé par l'application est le
   **3307** (valeur de `DB_PORT` dans `config.php`, typique d'une installation
   XAMPP où le port par défaut 3306 est déjà pris). Adaptez `DB_HOST`,
   `DB_PORT`, `DB_USER`, `DB_PASS` dans `config.php` à votre environnement si
   besoin.

   ```bash
   mysql -u root -p < database/schema.sql
   mysql -u root -p staginf < database/seed.sql
   ```

   `schema.sql` crée la base `staginf` elle-même (`CREATE DATABASE IF NOT EXISTS`),
   toutes les tables, et les contraintes de clés étrangères. `seed.sql` ajoute
   les classes, établissements, compétences, badges et le premier compte.

2. **Fichiers de l'application** — placez l'ensemble des fichiers PHP/CSS à la
   racine servie par votre serveur web (ex. `htdocs/suivi-stagiaire/` sous
   XAMPP).

3. **Première connexion** — un compte administrateur est créé par `seed.sql` :

   | Identifiant | Mot de passe   |
   |-------------|----------------|
   | `admin`     | `ChangeMoi123!`|

   Connectez-vous puis changez immédiatement ce mot de passe depuis le menu
   **☰ → Sécurité du compte**. Il n'existe pas d'écran d'inscription
   (choix assumé, cf. section Sécurité) : pour créer un compte supplémentaire,
   il faut l'insérer directement en base avec un hash généré par
   `password_hash()` (voir le commentaire dans `seed.sql`).

## Pages principales

| Page | Rôle |
|---|---|
| `login.php` | Connexion (verrouillage après échecs répétés, par compte **et** par IP) |
| `index.php` | Liste des stages, recherche, filtres (classe, établissement, période), accès au menu |
| `formulaire_stagiaires.php` | Créer un stagiaire/stage, ou enregistrer une nouvelle séance d'évaluation pour un stage existant |
| `stagiaire_detail_fragment.php` | Fiche détaillée (notes, compétences, badges, historique des évaluations) affichée dans la modale d'`index.php` |
| `suivi_modifications.php` | Historique des créations/modifications/suppressions, avec recherche et regroupement |

## Choix de sécurité

- Mots de passe hachés avec `password_hash()` / vérifiés avec `password_verify()`.
- Protection CSRF (jeton de session) sur tous les formulaires et endpoints qui modifient l'état.
- Anti brute-force stocké en base (table `login_attempts`), par identifiant **et** par adresse IP.
- Session : identifiant régénéré à la connexion, expiration réelle après 30 minutes d'inactivité, et **actif = 0** invalide immédiatement une session existante.
- Rôles (`admin` / `utilisateur`) présents en base ; aucun écran d'administration des comptes n'est livré pour l'instant (voir Limites connues).
- Le détail des erreurs SQL n'est jamais renvoyé au navigateur ; il part dans les logs serveur (`error_log`).

## Limites connues / hors périmètre de cette version

Pour éviter l'écart entre documentation et code déploré dans un audit
précédent, voici ce que l'application **ne fait pas**, explicitement :

- Pas de suivi des activités réalisées ni des objectifs de stage (seules les
  compétences, badges et une note globale sont suivis).
- Pas d'écran d'administration des comptes utilisateurs, catalogues de
  compétences/badges, classes ou établissements : toute modification de ces
  référentiels se fait directement en base pour l'instant.
- Pas d'inscription libre : la création d'un compte est une opération manuelle
  réservée à un administrateur système.

## Base de données

Voir `database/schema.sql` pour le détail des tables. Points clés du modèle :

- Une **personne** (`stagiaire`) peut avoir plusieurs **stages** (`stage`),
  chacun rattaché à une classe et un établissement.
- Chaque **séance d'évaluation** (`evaluation`) est datée et indépendante des
  précédentes : rien n'est jamais écrasé, ce qui permet de suivre la
  progression d'un stagiaire dans le temps.
- Les suppressions en cascade sont gérées par la base (`ON DELETE CASCADE`)
  de bout en bout sur la chaîne stage → evaluation → notes de compétences.