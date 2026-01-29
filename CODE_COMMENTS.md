# 📚 Guide des Commentaires du Code ECO-ride

## 🎯 Objectif
Ce document explique l'architecture et les commentaires ajoutés au code ECO-ride pour une meilleure compréhension.

---

## 📁 Structure du Projet

```
ECO-ride/
├── frontend/                    # Tout ce qui s'affiche dans le navigateur
│   ├── index.html              # Page d'accueil
│   ├── login.html              # Page de connexion
│   ├── rides.html              # Page des covoiturages
│   ├── create-ride.html        # Créer un nouveau trajet
│   ├── dashboard.html          # Tableau de bord passager
│   ├── admin-dashboard.html    # Tableau de bord admin
│   ├── employee-dashboard.html # Tableau de bord employé
│   ├── js/                     # Fichiers JavaScript
│   │   ├── auth.js             # Gestion authentification (login/register)
│   │   ├── rides.js            # Affichage et filtrage des trajets
│   │   ├── booking-manager.js  # Gestion des réservations
│   │   ├── page-protection.js  # Protection des pages
│   │   ├── ride-search.js      # Autocomplete des villes
│   │   └── ...
│   ├── css/                    # Feuilles de style
│   ├── data/                   # Données JSON (simule une BDD)
│   │   ├── users.json         # Liste des utilisateurs
│   │   ├── rides.json         # Liste des trajets
│   │   └── bookings.json      # Liste des réservations
│   └── backend/                # API passerelle (wrapper)
│       └── api/
│           ├── auth.php        # Redirection vers /backend/api/auth.php
│           └── bookings.php    # Redirection vers /backend/api/bookings.php
│
├── backend/                     # Logique métier (API réelle)
│   ├── api/
│   │   ├── auth.php           # Authentification (login/register/me)
│   │   ├── bookings.php       # Gestion des réservations
│   │   ├── router.php         # Routeur principal
│   │   └── index.php          # Point d'entrée de l'API
│   ├── controllers/           # Contrôleurs métier
│   ├── models/                # Modèles de données
│   └── middleware/            # Authentification, validation, etc.
│
└── database/                   # Scripts de création de BDD (non utilisé dans cette démo)
```

---

## 🔑 Composants Clés

### 1️⃣ **Authentification (`frontend/js/auth.js`)**

**Qu'est-ce que c'est ?**
- Gère la connexion (login) et l'inscription (register)
- Valide les mots de passe
- Stocke le token JWT dans `localStorage`

**Flux principal :**
```javascript
1. Utilisateur remplit le formulaire de login
2. auth.js envoie email + password au serveur
3. Le serveur génère un token JWT
4. auth.js sauvegarde le token dans localStorage
5. Utilisateur est redirigé vers rides.html
```

**Endroits importants à comprendre :**
- Méthode `handleLogin()` : Connecte l'utilisateur
- Méthode `handleRegister()` : Crée un nouveau compte
- Méthode `validatePassword()` : Affiche les critères de sécurité en temps réel

---

### 2️⃣ **Affichage des Trajets (`frontend/js/rides.js`)**

**Qu'est-ce que c'est ?**
- Affiche la liste des covoiturages disponibles
- Permet de filtrer par prix, durée, note, écologique
- Gère la recherche par ville

**Flux principal :**
```javascript
1. rides.html se charge
2. RidesManager charge les trajets depuis /data/rides.json
3. Les trajets s'affichent sous forme de cartes
4. Utilisateur peut filtrer les résultats
5. Utilisateur clique "Réserver" → booking-manager.js prend le relais
```

**Comment ça marche :**
- `init()` : Initialise tout au démarrage
- `loadAvailableRides()` : Charge les trajets depuis JSON
- `renderRide()` : Crée une carte HTML pour chaque trajet
- `applyFilters()` : Applique les filtres sélectionnés

---

### 3️⃣ **Gestion des Réservations (`frontend/js/booking-manager.js`)**

**Qu'est-ce que c'est ?**
- Affiche un modal pour réserver un trajet
- Valide les crédits disponibles
- Crée une réservation au serveur

**Flux principal :**
```javascript
1. Utilisateur clique le bouton "Réserver"
2. Un modal s'affiche avec les détails du trajet
3. Utilisateur choisit le nombre de passagers
4. Clique "Confirmer"
5. booking-manager.js envoie une requête POST au serveur
6. Le serveur crée la réservation et déduit les crédits
7. Modal se ferme, utilisateur voit les crédits mis à jour
```

**Points clés :**
- `openReservationModal()` : Ouvre le formulaire de réservation
- `confirmReservation()` : Envoie la réservation au serveur
- Vérifie les crédits disponibles avant de réserver

---

### 4️⃣ **Protection des Pages (`frontend/js/page-protection.js`)**

**Qu'est-ce que c'est ?**
- Vérifie si l'utilisateur est connecté
- Redirige vers login si la page est protégée
- Affiche la barre utilisateur avec les crédits

**Pages protégées :**
- `/dashboard.html` → Nécessite connexion
- `/create-ride.html` → Nécessite connexion
- `/rides.html` → Public (accessible à tous)
- `/login.html` → Public, redirige si déjà connecté

---

### 5️⃣ **Autocomplete des Villes (`frontend/js/ride-search.js`)**

**Qu'est-ce que c'est ?**
- Affiche une liste déroulante de suggestions au fur et à mesure de la saisie
- Extrait automatiquement toutes les villes disponibles

**Flux :**
```javascript
1. Utilisateur tape "Par" dans le champ "Départ"
2. ride-search.js filtre les villes contenant "Par"
3. Affiche : Paris, Pau, etc.
4. Utilisateur clique sur "Paris"
5. Le champ se remplit avec "Paris"
```

---

## 🌐 API - Fonctionnement

### Architecture de l'API

L'API suit une architecture en 2 couches :

#### **Couche 1 : Wrapper (Frontend)**
```
/frontend/backend/api/auth.php
         → Passerelle HTTP
         → Reçoit les requêtes du navigateur
         → Redirige vers la couche 2
```

#### **Couche 2 : API Réelle (Backend)**
```
/backend/api/auth.php
 → Logique métier réelle
 → Lire/écrire dans users.json
 → Générer les tokens JWT
 → Envoyer les réponses JSON
```

### Pourquoi 2 couches ?

✅ **Avantages :**
- Le serveur PHP démarre depuis `/frontend`
- Les requêtes AJAX vont vers `/backend/api`
- Évite les problèmes de routing PHP

---

## 📊 Flux d'Authentification Complet

```
┌─────────────────┐
│ Utilisateur     │
└────────┬────────┘
         │ Tape email + password
         ↓
┌────────────────────────┐
│ login.html + auth.js   │
│ Formulaire de connexion│
└────────┬───────────────┘
         │ submit FormData
         ↓
┌────────────────────────────────────────┐
│ POST /backend/api/auth                 │
│ {"email":"...", "password":"..."}      │
└────────┬───────────────────────────────┘
         │ (Passe par wrapper auth.php)
         ↓
┌────────────────────────────────────────┐
│ /backend/api/auth.php (API réelle)     │
│ - Cherche user dans users.json         │
│ - Vérifie le mot de passe              │
│ - Génère un token JWT                  │
└────────┬───────────────────────────────┘
         │ Réponds avec token
         ↓
┌────────────────────────────────────────┐
│ auth.js reçoit le token                │
│ - Sauvegarde dans localStorage         │
│ - Redirige vers rides.html             │
└────────┬───────────────────────────────┘
         │ ✅ Connecté !
         ↓
┌────────────────────────────────────────┐
│ page-protection.js                     │
│ - Affiche la barre utilisateur         │
│ - Montre les crédits restants          │
└────────────────────────────────────────┘
```

---

## 💾 Comment les Données sont Stockées

### Fichiers JSON (Simule une base de données)

**`users.json`**
```json
{
  "users": [
    {
      "id": 1,
      "email": "passager@ecoride.fr",
      "password": "Test@2025!",
      "first_name": "Passager",
      "last_name": "Test",
      "credits": 100,
      "user_type": "passenger"
    }
  ]
}
```

**`rides.json`**
```json
{
  "rides": [
    {
      "id": 1,
      "departure_city": "Paris",
      "arrival_city": "Lyon",
      "departure_datetime": "2025-02-01T10:00:00",
      "driver_pseudo": "Jean_Driver",
      "price_credits": 45,
      "seats_available": 3,
      "is_ecological": true,
      "driver_avatar": "https://api.dicebear.com/..."
    }
  ]
}
```

**`bookings.json`**
```json
{
  "bookings": [
    {
      "id": 1,
      "ride_id": 1,
      "passenger_id": 1,
      "passengers_count": 2,
      "total_credits": 90,
      "status": "confirmed"
    }
  ]
}
```

---

## 🚀 Démarrer le Serveur

```bash
cd /Users/kevinpautut/Documents/ECO-ride

# Démarrer le serveur PHP
php -S localhost:8000 -t frontend/

# Ouvrir dans le navigateur
# http://localhost:8000
```

---

## 🔐 Comptes de Test Disponibles

```
1. Passager
   Email: passager@ecoride.fr
   Password: Test@2025!
   Crédits: 100

2. Chauffeur
   Email: driver@ecoride.fr
   Password: Test@2025!
   Crédits: 50 (pour payer les covoiturages)

3. Admin
   Email: admin@ecoride.fr
   Password: Test@2025!
   Crédits: 500

... (voir TEST_CREDENTIALS.md pour la liste complète)
```

---

## 📝 Fichiers avec Commentaires Améliorés

Les fichiers suivants ont des commentaires détaillés en français :

✅ `/frontend/backend/api/auth.php` - Wrapper authentification
✅ `/frontend/backend/api/bookings.php` - Wrapper réservations  
✅ `/backend/api/auth.php` - API authentification réelle
✅ `/frontend/js/auth.js` - Gestion du login/register
✅ `/frontend/js/rides.js` - Affichage et filtrage
✅ `/frontend/js/booking-manager.js` - Réservations
✅ `/frontend/js/ride-search.js` - Autocomplete
✅ `/frontend/js/page-protection.js` - Protection pages

---

## 🎓 Exemple : Suivre un Réservation de A à Z

### 1. Utilisateur clique "Réserver" sur une carte
```javascript
// Dans rides.html, la carte contient :
<button class="btn-reserve" data-ride-id="5">Réserver</button>
```

### 2. booking-manager.js capte le clic
```javascript
// booking-manager.js ligne ~30
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('btn-reserve')) {
        const rideId = e.target.getAttribute('data-ride-id');
        this.openReservationModal(rideId);
    }
});
```

### 3. Un modal s'affiche
```javascript
// booking-manager.js méthode showReservationModal()
// Crée un élément <div class="modal-overlay">
// Affiche les détails du trajet et un formulaire
```

### 4. Utilisateur confirme
```javascript
// L'utilisateur clique le bouton "Confirmer la réservation"
// booking-manager.js méthode confirmReservation() s'exécute
```

### 5. Requête envoyée au serveur
```javascript
const response = await fetch('/backend/api/bookings', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({
        ride_id: 5,
        passengers_count: 2
    })
});
```

### 6. API traite la demande
```php
// /backend/api/bookings.php
// 1. Valide le token JWT
// 2. Vérifie les crédits disponibles
// 3. Crée une entrée dans bookings.json
// 4. Déduit les crédits de l'utilisateur
// 5. Envoie une réponse JSON
```

### 7. Frontend met à jour l'affichage
```javascript
if (data.success) {
    // Mettre à jour les crédits affichés
    // Fermer le modal
    // Afficher un message de succès
}
```

---

## 🐛 Déboguer - Où Regarder ?

| Problème | Où regarder |
|----------|------------|
| "Non connecté" | Console navigateur → localStorage → `ecoride_token` |
| Trajet ne s'affiche pas | `/data/rides.json` → Vérifie format JSON |
| Réservation échoue | Ouvrir DevTools (F12) → Network → voir la réponse du serveur |
| Erreur PHP | `php -S localhost:8000 -t frontend/` → Voir les logs |
| Token expiré | Nettoyer localStorage et se reconnecter |

---

## 📞 Questions Fréquentes

**Q: Où est la vraie base de données ?**
A: Les données sont stockées en JSON pour cette démo. En production, utiliser PostgreSQL/MySQL.

**Q: Comment sécuriser les mots de passe ?**
A: Actuellement ils sont en clair. En production, utiliser `password_hash()` et `password_verify()`.

**Q: Pourquoi deux fichiers auth.php ?**
A: Le wrapper (`/frontend/backend/api/auth.php`) reçoit les requêtes HTTP.
L'API réelle (`/backend/api/auth.php`) contient la logique métier.

**Q: Comment ajouter une nouvelle fonctionnalité ?**
A: 1. Ajouter la logique dans `/backend/api/`
   2. Créer un wrapper dans `/frontend/backend/api/`
   3. Appeler depuis `frontend/js/`

---

## 🎉 Résumé

Vous disposez maintenant d'une application ECO-ride complète avec :

✅ Authentification JWT
✅ Gestion des réservations
✅ Recherche et filtrage des trajets
✅ Système de crédits
✅ Autocomplete des villes
✅ **Commentaires en français partout**

Bonne compréhension du code ! 🚗🌱
