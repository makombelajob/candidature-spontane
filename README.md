# CandySponta

CandySponta est une application web Symfony pensée pour permettre aux candidats de déposer des candidatures spontanées auprès d’entreprises. L’objectif principal du projet est de simplifier la mise en relation entre les utilisateurs et les entreprises, en leur offrant un parcours clair : création de compte, connexion, recherche d’entreprises, et dépôt d’une candidature spontanée.

## Fonctionnalités principales

- Objectif principal : permettre aux users de lancer des candidatures spontanées
  - recherche d’entreprises pertinentes
  - accès à une base d’entreprises déjà importée
  - préparation du parcours de candidature spontanée

- Page d’accueil et pages informatives
  - accueil du site
  - page À propos
  - page Contact

- Authentification et inscription
  - création de compte utilisateur
  - connexion sécurisée
  - vérification d’email
  - validation de l’email via un mécanisme intégré

- Espace profil utilisateur
  - accès réservé aux utilisateurs connectés
  - consultation d’une liste d’entreprises provenant d’une base SQLite locale
  - recherche par mot-clé dans les colonnes principales
  - pagination des résultats
  - préparation du parcours vers la candidature spontanée

- Enrichissement des entreprises
  - la base SQLite contient les entreprises de base sans coordonnées de contact complètes
  - un crawler sera chargé de générer des données de contact supplémentaires
  - ces données enrichies seront ensuite stockées dans la base ORM réelle du projet
  - la base SQLite est actuellement utilisée comme source de départ, en mode lecture seule, pour alimenter le processus

- Administration
  - accès réservé aux administrateurs
  - espace dédié à la gestion avancée de l’application

- Environnement Docker prêt à l’emploi
  - PHP/Apache
  - MySQL
  - phpMyAdmin
  - Mailhog
  - interface SQLite Web

## Accès en ligne

L’application est actuellement disponible en ligne sur le serveur à l’adresse suivante :

- https://candys.jobmakombela.fr

## Prérequis

Avant de lancer le projet localement, assurez-vous d’avoir installé :

- Docker
- Docker Compose
- Git

## Démarrage rapide avec Docker Compose

Depuis la racine du projet :

```bash
docker compose up --build -d
```

Cette commande va construire et démarrer les services suivants :

- PHP/Apache sur le port 8080
- MySQL sur le port 3306 (interne au réseau Docker)
- phpMyAdmin sur le port 8081
- Mailhog sur les ports 1025 et 8025
- SQLite Web sur le port 8082

### Vérifier les conteneurs

```bash
docker compose ps
```

### Voir les logs

```bash
docker compose logs -f
```

## Accéder à l’application localement

Une fois les conteneurs lancés, ouvrez dans votre navigateur :

- http://localhost:8080/

### Services complémentaires

- phpMyAdmin : http://localhost:8081/
- Mailhog : http://localhost:8025/
- SQLite Web : http://localhost:8082/

## Structure du projet

```text
.
├── app/                  # application Symfony
├── apache/               # configuration Apache
├── docker-compose.yaml   # orchestration Docker
├── php/                  # image PHP personnalisée
├── mysql/                # données MySQL persistantes
└── README.md             # documentation du projet
```

## Développement local

### Installer les dépendances PHP

Depuis le dossier app :

```bash
cd app
composer install
```

### Exécuter les migrations (si nécessaire)

```bash
cd app
php bin/console doctrine:migrations:migrate
```

### Nettoyer le cache

```bash
cd app
php bin/console cache:clear
```


## Base de données

Le projet utilise :

- MySQL comme base de données principale de l’application Symfony via Doctrine ORM
- SQLite comme base de départ pour les entreprises importées, utilisée actuellement en lecture seule

Le fichier SQLite est généralement accessible via le volume Docker dans :

```text
app/var/data/data.db
```

## Utilisation de l’application

1. Ouvrez l’application dans votre navigateur.
2. Créez un compte sur la page d’inscription.
3. Vérifiez votre adresse email.
4. Connectez-vous avec vos identifiants.
5. Accédez à l’espace profil pour consulter les entreprises disponibles.
6. Utilisez la recherche pour filtrer les entreprises par SIREN, SIRET, nom, ville ou adresse.
7. Sélectionnez une entreprise et lancez votre candidature spontanée depuis le parcours prévu par l’application.

L’objectif est de permettre à chaque utilisateur de découvrir des entreprises, de les consulter facilement, puis de pouvoir initier une candidature spontanée de manière simple et fluide.

### Évolution prévue de la base de données

Aujourd’hui, la base SQLite contient des entreprises de base sans contacts suffisants. À terme, un crawler enrichira ces fiches avec des données de contact, puis les enregistrera dans la vraie base Doctrine ORM, qui deviendra la source de vérité du projet.

## Dépannage courant

### Les conteneurs ne démarrent pas

Vérifiez les logs Docker :

```bash
docker compose logs php
```

### Les dépendances PHP ne sont pas installées

```bash
cd app
composer install
```

### La base SQLite n’existe pas encore

Vérifiez que le fichier de base est bien présent dans :

```text
app/var/data/data.db
```

Si nécessaire, relancez les services et vérifiez les volumes Docker.

## Contribution

Les contributions sont les bienvenues. Pour proposer une amélioration :

1. créer une branche
2. apporter vos modifications
3. ouvrir une pull request

## Auteur

Projet CandySponta.
