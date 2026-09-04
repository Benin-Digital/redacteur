#  Guide de déploiement — RédacteurAI

**Agent IA de rédaction web**

> Documentation rédigée par **Abdoul-Wakilou TIGA**

> **BENIN DIGITAL** · 2026

---

##  Table des matières

1. [Architecture cible](#1-architecture-cible)
2. [Prérequis serveur](#2-prérequis-serveur)
3. [Installation des services](#3-installation-des-services)
4. [Structure des dossiers](#4-structure-des-dossiers)
5. [Configuration de la base de données](#5-configuration-de-la-base-de-données)
6. [Configuration du backend (Laravel)](#6-configuration-du-backend-laravel)
7. [Configuration du frontend (Angular)](#7-configuration-du-frontend-angular)
8. [Configuration Nginx](#8-configuration-nginx)
9. [Configuration SSL avec Certbot](#9-configuration-ssl-avec-certbot)
10. [Vérification et tests](#10-vérification-et-tests)
11. [Checklist de déploiement](#11-checklist-de-déploiement)
12. [Dépannage rapide](#12-dépannage-rapide)
13. [Notes supplémentaires](#13-notes-supplémentaires)

---

## 1. Architecture cible

RédacteurAI est composé de deux parties principales, servies sur un **sous-domaine unique** (`redacteur.benin-digital.app`) :

| Composant | Technologie | Rôle |
|---|---|---|
| **Backend** | Laravel | API REST (préfixe `/api`), gestion des utilisateurs, authentification, appels LLM, génération de contenu |
| **Frontend** | Angular | Interface utilisateur, dashboard, création/édition d'articles, publication |

---

## 2. Prérequis serveur

| Composant | Minimum | Recommandé |
|---|---|---|
| CPU | 2 vCores | 4 vCores |
| RAM | 4 Go | 8 Go |
| Stockage | 20 Go SSD | 40 Go SSD |
| OS | Ubuntu 22.04 LTS | Ubuntu 22.04 LTS |

### 2.1 Connexion au serveur

```bash
ssh root@<IP_DU_SERVEUR>
```

### 2.2 Mise à jour du système

```bash
apt update && apt upgrade -y
apt install -y software-properties-common curl wget git unzip zip
```

### 2.3 Créer un utilisateur non-root

```bash
# Créer un utilisateur de déploiement
adduser deployer
usermod -aG sudo deployer

# Copier la clé SSH depuis votre machine locale
ssh-copy-id deployer@<IP_DU_SERVEUR>

# Tester la connexion
ssh deployer@<IP_DU_SERVEUR>

# Verrouiller l'accès root
sudo passwd -l root
```

### 2.4 Sécuriser SSH

```bash
sudo nano /etc/ssh/sshd_config
```

Modifier les lignes suivantes :

```ini
Port 2222                          # Changer le port SSH
PermitRootLogin no                 # Interdire root
PasswordAuthentication no          # Désactiver mots de passe
PubkeyAuthentication yes           # Activer clés SSH
MaxAuthTries 3                     # 3 tentatives max
ClientAliveInterval 300            # Timeout 5 min
ClientAliveCountMax 2              # 2 tentatives keepalive
```

Appliquer les changements :

```bash
sudo systemctl restart sshd
```

### 2.5 Installer Fail2ban (protection brute force)

```bash
sudo apt install -y fail2ban

# Créer la configuration
sudo nano /etc/fail2ban/jail.local
```

Contenu du fichier :

```ini
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true
port = 2222
logpath = %(sshd_log)s
maxretry = 3

[nginx-http-auth]
enabled = true
logpath = /var/log/nginx/error.log
maxretry = 5
```

Démarrer Fail2ban :

```bash
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
sudo fail2ban-client status
```

---

## 3. Installation des services

### 3.1 Installer Nginx

```bash
apt install -y nginx
systemctl enable nginx
systemctl start nginx
```

### 3.2 Installer PHP 8.3

```bash
add-apt-repository -y ppa:ondrej/php
apt update
apt install -y php8.3 php8.3-fpm php8.3-mysql php8.3-xml php8.3-curl \
    php8.3-mbstring php8.3-zip php8.3-intl php8.3-bcmath php8.3-common \
    php8.3-dom php8.3-tokenizer
```

### 3.3 Installer MySQL 8

```bash
apt install -y mysql-server
mysql_secure_installation
systemctl enable mysql
systemctl start mysql
```

### 3.4 Installer Node.js 20 (pour Angular)

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
```

### 3.5 Installer Composer (pour Laravel)

```bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
```

### 3.6 Installer Certbot (SSL)

```bash
apt install -y certbot python3-certbot-nginx
```

---

## 4. Structure des dossiers

```bash
mkdir -p /var/www/redacteur
cd /var/www/redacteur

# Cloner le dépôt unique
git clone https://ghp_VOTRE_TOKEN@github.com/Benin-Digital/redacteur.git .
```

Structure finale :

```
/var/www/redacteur/
├── backend/          (Laravel)
└── frontend/         (Angular)
```

---

## 5. Configuration de la base de données

### 5.1 Créer la base de données et l'utilisateur

```sql
mysql -u root -p

CREATE DATABASE redacteur CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'redacteur_user'@'localhost' IDENTIFIED BY 'M0tBeninDeDigitalPasseRedacteurFort!';
GRANT ALL PRIVILEGES ON redacteur.* TO 'redacteur_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

>  **Sécurité** : remplacez ce mot de passe par un secret généré aléatoirement avant tout déploiement réel, et ne le committez jamais dans un dépôt Git.

---

## 6. Configuration du backend (Laravel)

### 6.1 Installation des dépendances

```bash
cd /var/www/redacteur/backend
cp .env.example .env
composer install --no-dev --optimize-autoloader
```

### 6.2 Configuration du fichier `.env`

```bash
nano .env
```

```env
APP_NAME=RedacteurAI
APP_KEY=
APP_ENV=production
APP_DEBUG=false
APP_URL=https://redacteur.benin-digital.app/api
APP_FRONTEND_URL=https://redacteur.benin-digital.app

# Sécurité des sessions
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict

# CORS sécurisé
CORS_ALLOWED_ORIGINS=https://redacteur.benin-digital.app
CORS_ALLOWED_METHODS=GET,POST,PUT,DELETE
CORS_ALLOWED_HEADERS=Authorization,Content-Type,X-Requested-With

# Fuseau horaire
APP_TIMEZONE=Africa/Porto-Novo

APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
APP_FAKER_LOCALE=fr_FR

# Configuration des logs
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

# Base de données
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=redacteur
DB_USERNAME=redacteur_user
DB_PASSWORD=M0tBeninDeDigitalPasseRedacteurFort!

# Mail (configuration selon votre fournisseur)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=votre-email@gmail.com
MAIL_PASSWORD="mot de passe"
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=votre-email@gmail.com
MAIL_FROM_NAME="RedacteurAI"

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=.benin-digital.app

# Sanctum
SANCTUM_STATEFUL_DOMAINS=redacteur.benin-digital.app,localhost,localhost:4200,127.0.0.1,127.0.0.1:4200

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database
```

>  **Sécurité** : les identifiants ci-dessus (mot de passe DB, mot de passe applicatif Gmail) sont des secrets. Régénérez-les et stockez-les dans un gestionnaire de secrets (Vault, variables d'environnement CI/CD, etc.) plutôt que dans un `.env` versionné.

### 6.3 Génération des clés et migrations

```bash
php artisan key:generate
php artisan storage:link
php artisan migrate
```

### 6.4 Compte administrateur (AdminSeeder)

Le fichier `database/seeders/AdminSeeder.php` crée le compte administrateur principal. C'est ce compte qui sert à se connecter à l'interface (avec 2FA) et à gérer les rédacteurs (créer/administrer les comptes éditeurs).

>  **Important** : ne codez jamais un e-mail ou un mot de passe réel en dur dans le seeder versionné. Utilisez des valeurs génériques par défaut, puis changez immédiatement le mot de passe après la première connexion en production.

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'votreemail@gmail.com'],
            [
                'name' => 'Prénom',
                'firstname' => 'Nom',
                'email' => 'votreemail@gmail.com',
                'password' => Hash::make('MotDePasseParDefaut!'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );
    }
}
```

Exécuter le seeder :

```bash
php artisan db:seed --class=Database\\Seeders\\AdminSeeder
```

>  **Sécurité** : remplacez `votreemail@gmail.com` et `MotDePasseParDefaut!` par les vraies valeurs directement sur le serveur de production (ou via une variable d'environnement/prompt interactif), jamais dans le code source versionné. Après le premier déploiement, connectez-vous et changez le mot de passe depuis l'interface admin.

### 6.5 Optimisations Laravel

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### 6.6 Permissions

```bash
chown -R www-data:www-data /var/www/redacteur/backend
chmod -R 775 /var/www/redacteur/backend/storage
chmod -R 775 /var/www/redacteur/backend/bootstrap/cache
```

---

## 7. Configuration du frontend (Angular)

### 7.1 Installation des dépendances

```bash
cd /var/www/redacteur/frontend
npm install
```

### 7.2 Configuration de l'environnement de production

```bash
nano src/environments/environment.prod.ts
```

```typescript
export const environment = {
    production: true,
    apiUrl: 'https://redacteur.benin-digital.app/api'
};
```

### 7.3 Build de l'application

```bash
npm run build -- --configuration=production
```

Les fichiers générés se trouvent dans `/var/www/redacteur/frontend/dist/redacteur-frontend/browser/`.

---

## 8. Configuration Nginx

>  **Note** : contrairement à ScribeAI (deux domaines distincts), RédacteurAI utilise **un seul sous-domaine** pour le frontend et le backend. Un seul bloc `server` est donc utilisé, avec un bloc `location /api` qui route vers Laravel/PHP-FPM et un bloc `location /` qui sert le build Angular.

```bash
nano /etc/nginx/sites-available/redacteur
```

```nginx
server {
    server_name redacteur.benin-digital.app;

    root /var/www/redacteur/frontend/dist/redacteur-frontend/browser;
    index index.html;

    client_max_body_size 100M;
    client_body_timeout 300;
    client_header_timeout 300;
    keepalive_timeout 300;
    send_timeout 300;

    # Compression Gzip
    gzip on;
    gzip_types text/css application/javascript application/json;
    gzip_min_length 1000;

    # Cache des assets statiques
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # API Laravel
    location /api {
        alias /var/www/redacteur/backend/public;
        try_files $uri $uri/ @backend;

        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/run/php/php8.3-fpm.sock;
            fastcgi_param SCRIPT_FILENAME $document_root/index.php;
            include fastcgi_params;
            fastcgi_read_timeout 600;
        }
    }

    location @backend {
        rewrite /api/(.*)$ /api/index.php?/$1 last;
    }

    # Frontend Angular (SPA)
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Bloquer l'accès aux fichiers sensibles
    location ~ /\.ht {
        deny all;
    }

    location ~* \.(sql|log|sh|bash|env|ini|conf)$ {
        deny all;
    }

    error_page 500 502 503 504 /50x.html;
    location = /50x.html {
        root /usr/share/nginx/html;
    }

    listen 443 ssl; # managed by Certbot
    ssl_certificate /etc/letsencrypt/live/redacteur.benin-digital.app/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/redacteur.benin-digital.app/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
}

server {
    listen 80;
    server_name redacteur.benin-digital.app;

    location /.well-known/acme-challenge/ {
        root /var/www/html;
    }

    return 301 https://redacteur.benin-digital.app$request_uri;
}
```

### 8.1 Activer le site

```bash
ln -s /etc/nginx/sites-available/redacteur /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

---

## 9. Configuration SSL avec Certbot

```bash
# Obtenir le certificat
certbot --nginx -d redacteur.benin-digital.app

# Renouvellement automatique (test)
certbot renew --dry-run
```

---

## 10. Vérification et tests

### 10.1 Vérifier les services

```bash
systemctl status nginx
systemctl status php8.3-fpm
systemctl status mysql
supervisorctl status
```

### 10.2 Tester l'application

Ouvrir [https://redacteur.benin-digital.app](https://redacteur.benin-digital.app) dans un navigateur, puis se connecter avec le compte admin créé via l'`AdminSeeder`.

---

## 11. Checklist de déploiement

- [ ] Serveur provisionné (RAM, CPU, stockage)
- [ ] Nginx installé et configuré
- [ ] PHP 8.3 + extensions installées
- [ ] MySQL installé, base et utilisateur créés
- [ ] Node.js installé
- [ ] Composer installé
- [ ] Certbot installé
- [ ] Backend cloné, dépendances installées, migrations exécutées
- [ ] Compte admin créé via `AdminSeeder` avec des identifiants réels (non versionnés)
- [ ] Frontend cloné, build production réalisé
- [ ] SSL configuré pour le sous-domaine
- [ ] Firewall configuré
- [ ] Tests de fonctionnement réalisés

---

## 12. Dépannage rapide

| Problème | Solution |
|---|---|
| Erreur 502 Bad Gateway | Vérifier PHP-FPM : `systemctl restart php8.3-fpm` |
| Erreur 403 Forbidden | Vérifier les permissions : `chown -R www-data:www-data /var/www/redacteur` |
| Base de données inaccessible | Vérifier MySQL : `systemctl restart mysql` |
| SSL expiré | Renouveler : `certbot renew` |
| API ne répond pas | Vérifier les logs : `tail -f /var/www/redacteur/backend/storage/logs/laravel.log` |
| Frontend ne se charge pas | Vérifier que le build Angular est bien dans `frontend/dist/...` et que Nginx pointe vers le bon dossier |
| Erreur de CORS | Vérifier `CORS_ALLOWED_ORIGINS` dans le `.env` Laravel |
| Appel LLM échoue | Vérifier les clés API dans la configuration des LLM via l'interface admin |
| Route `/api/...` retourne du HTML au lieu de JSON | Vérifier le bloc `location /api` et le rewrite `@backend` dans la config Nginx |
| Impossible de se connecter en admin | Vérifier que `AdminSeeder` a bien été exécuté avec les bons identifiants |

---

## 13. Notes supplémentaires

- Les clés API des fournisseurs LLM sont gérées via l'interface administrateur de l'application. Aucune modification du code n'est nécessaire pour ajouter ou modifier une clé.
- Les articles générés sont stockés dans `storage/app/articles/` (ou équivalent selon la config de stockage retenue).
- Le compte admin créé par `AdminSeeder` gère l'ajout et l'administration des comptes rédacteurs.

###  Sécurité

- Les mots de passe sont hashés avec **Bcrypt**.
- L'authentification à 2 facteurs est disponible pour l'admin.
- Les clés API sont chiffrées en base de données.
- L'authentification est gérée par **Laravel Sanctum**.
- Les sessions sont sécurisées avec des cookies **HTTP-only**.

---

<p align="center"><sub>RédacteurAI · BENIN DIGITAL · 2026</sub></p>