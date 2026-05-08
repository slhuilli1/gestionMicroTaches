# 📋 Gestionnaire de Tâches

Application web CRUD simple en PHP pur, sans base de données. Les données sont persistées en XML.

---

## ✨ Fonctionnalités

- Liste des tâches avec recherche en temps réel et tri par colonne
- Ajout, modification et suppression de tâches (accès protégé)
- Authentification par login / mot de passe
- Gestion des utilisateurs avec interface d'administration
- Données stockées en XML (aucune base de données requise)

---

## 🚀 Installation

1. Copier `index.php` à la racine de ton serveur web (Apache, Nginx, WAMP, XAMPP…)
2. S'assurer que PHP a les **droits d'écriture** dans le dossier (pour créer les fichiers XML)
3. Ouvrir la page dans le navigateur

Les fichiers `taches.xml` et `users.xml` sont **créés automatiquement** au premier chargement.

---

## 🔐 Accès par défaut

| Login | Mot de passe |
|-------|-------------|
| `sebastien` | `toto11__` |

> ⚠️ Le mot de passe est stocké hashé (`password_hash`) dans `users.xml`. Il n'est jamais en clair.

---

## 👥 Gestion des utilisateurs

Une fois connecté, un lien **⚙ Utilisateurs** apparaît en haut à droite.  
Il permet d'ajouter ou supprimer des utilisateurs sans toucher au code.

---

## 🔒 Droits d'accès

| Action | Sans connexion | Connecté |
|--------|---------------|---------|
| Voir la liste | ✅ | ✅ |
| Ajouter une tâche | ❌ | ✅ |
| Modifier une tâche | ❌ | ✅ |
| Supprimer une tâche | ❌ | ✅ |
| Gérer les utilisateurs | ❌ | ✅ |

---

## 📁 Structure des fichiers

```
/
├── index.php       ← Application complète (un seul fichier)
├── taches.xml      ← Données des tâches (créé automatiquement)
├── users.xml       ← Utilisateurs hashés (créé automatiquement)
├── .gitignore      ← Exclut les fichiers XML du dépôt
└── README.md
```

---

## 🛠️ Technologies

- PHP 8+ (SimpleXML, DOMDocument, password_hash)
- HTML / CSS / JavaScript vanilla
- Aucune dépendance externe, aucune base de données



Plusieurs options selon ce que tu as sous la main :
Option 1 — Un petit script PHP à lancer une fois (le plus simple)
Crée un fichier hash.php à la racine de ton serveur :
php<?php
echo password_hash('ton_mot_de_passe', PASSWORD_DEFAULT);
Ouvre-le dans le navigateur, copie le résultat, supprime le fichier.
Option 2 — En ligne de commande PHP (si PHP est installé sur ta machine)
bashphp -r "echo password_hash('ton_mot_de_passe', PASSWORD_DEFAULT);"
Option 3 — Via l'interface admin de ton appli
C'est la méthode la plus simple au quotidien : une fois connecté en tant que sebastien, tu vas dans ⚙ Utilisateurs et tu ajoutes directement le nouvel utilisateur avec son mot de passe. Le hash est généré automatiquement, tu n'as rien à faire à la main.

La méthode recommandée pour ton projet c'est clairement l'option 3 — l'interface admin est faite pour ça. Les options 1 et 2 sont utiles si tu veux modifier users.xml manuellement pour une raison particulière.