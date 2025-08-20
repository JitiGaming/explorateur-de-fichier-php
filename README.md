# Explorateur de fichiers PHP

![Explorateur de fichiers PHP](https://raw.githubusercontent.com/JitiGaming/explorateur-de-fichier-php/refs/heads/main/explorateur-de-fichier-php.jpg)

Un simple **explorateur de fichiers PHP** permettant de naviguer dans les répertoires et de visualiser le contenu d’un dossier directement depuis un navigateur web.

## Fonctionnalités

- Navigation dans l’arborescence des répertoires  
- Affichage des fichiers et dossiers avec leurs tailles  
- Conversion automatique des tailles en unités lisibles (_Ko_, _Mo_, _Go_, etc.)  
- Protection basique avec normalisation des chemins  
- Compatible avec **PHP 7.4+** et **PHP 8+**

## Installation

```bash
git clone https://github.com/JitiGaming/explorateur-de-fichier-php.git
```

1. Placez `index.php` à la racine du domaine ou sous-domaine que vous souhaitez explorer  
2. Rendez-vous dans votre navigateur à :
   ```
   http://localhost/index.php
   ```

## Sécurité

> **Important** : Ce script n’inclut **aucune** authentification.  
> Il est fortement recommandé de :
> - Protéger l’accès avec un mot de passe (via `.htaccess` ou authentification PHP)  
> - Limiter l’accès à un réseau privé

## Licence

Ce projet est distribué sous licence **Copyleft**.
