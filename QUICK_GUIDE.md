# 🚀 GUIDE RAPIDE - ECO-ride

## ⏱️ 5 Minutes Pour Comprendre

### 1️⃣ Démarrer le Serveur (1 minute)

```bash
cd /Users/kevinpautut/Documents/ECO-ride
php -S localhost:8000 -t frontend/
```

Puis ouvrez http://localhost:8000

### 2️⃣ Se Connecter (1 minute)

Cliquez "Connexion" et utilisez :
- **Email** : `passager@ecoride.fr`
- **Password** : `Test@2025!`

### 3️⃣ Réserver un Trajet (1 minute)

1. Vous êtes sur la page des trajets
2. Cliquez sur un trajet
3. Cliquez "Réserver"
4. Choisissez le nombre de passagers
5. Confirmez

✅ Réservation faite !

### 4️⃣ Voir les Commentaires du Code (2 minutes)

Ouvrez `/frontend/js/booking-manager.js` et regardez les commentaires en français :

```javascript
/**
 * Confirmer la réservation
 * Valide les informations et envoie la requête au serveur
 */
async confirmReservation(rideId) {
    // ÉTAPE 1 : Récupérer les données du modal
    // ÉTAPE 2 : Vérifier que les conditions sont acceptées
    // ÉTAPE 3 : Envoyer la requête...
    // etc.
}
```

---

## 📚 Où Trouver Quoi

| Question | Réponse |
|----------|---------|
| Comment ça marche ? | Lire [INDEX.md](./INDEX.md) |
| Expliquer le PHP | Lire [PHP_DOCUMENTATION.md](./PHP_DOCUMENTATION.md) |
| Expliquer le JavaScript | Lire [JAVASCRIPT_DOCUMENTATION.md](./JAVASCRIPT_DOCUMENTATION.md) |
| Comprendre l'architecture | Lire [CODE_COMMENTS.md](./CODE_COMMENTS.md) |
| Voir un exemple complet | Voir section ci-dessous |

---

## 👤 Tous les Comptes de Test

```
1. Passager
   Email: passager@ecoride.fr
   Password: Test@2025!
   Type: Passager (réserve des trajets)

2. Chauffeur
   Email: driver@ecoride.fr
   Password: Test@2025!
   Type: Chauffeur (crée des trajets)

3. Admin
   Email: admin@ecoride.fr
   Password: Test@2025!
   Type: Admin (gère l'application)

4. Employé
   Email: employee@ecoride.fr
   Password: Test@2025!
   Type: Employé

... (voir TEST_CREDENTIALS.md pour 14 comptes)
```

---

## 🔍 Flux Complet : Me Connecter et Réserver

### Dans le Navigateur

```
1. Ouvrir http://localhost:8000
   ↓
2. Cliquer "Connexion"
   ↓
3. Entrer email + password
   ↓
4. Cliquer "Se connecter"
   ↓
5. ✅ Redirigé vers les trajets
   ↓
6. Cliquer "Réserver" sur un trajet
   ↓
7. Modal s'affiche
   ↓
8. Entrer le nombre de passagers
   ↓
9. Cocher "J'accepte les conditions"
   ↓
10. Cliquer "Confirmer"
   ↓
11. ✅ Réservation faite !
```

### Dans les Fichiers

#### 1. auth.js - Traite le login
```javascript
class AuthManager {
    async handleLogin(e) {
        // Envoyer email + password au serveur
        const response = await fetch('/backend/api/auth?action=login', {...});
        // Recevoir le token
        localStorage.setItem('ecoride_token', token);
        // Rediriger vers rides.html
        window.location.href = '/rides.html';
    }
}
```

#### 2. API - Authentifier l'utilisateur
```
POST /backend/api/auth?action=login
{email: "...", password: "..."}
   ↓
/frontend/api.php (routeur)
   → Voir que c'est /backend/api/auth
   → Inclure /backend/api/auth.php
   ↓
/backend/api/auth.php
   → Chercher l'utilisateur dans users.json
   → Vérifier le password
   → Générer un token JWT
   ↓
Répondre avec JSON
{success: true, token: "...", user: {...}}
```

#### 3. rides.js - Afficher les trajets
```javascript
class RidesManager {
    async loadAvailableRides() {
        // Charger les trajets depuis JSON
        const response = await fetch('/data/rides.json');
        this.allRides = response.rides;
        
        // Afficher chaque trajet comme une carte
        this.allRides.forEach(ride => {
            const card = this.renderRide(ride);
            this.ridesList.appendChild(card);
        });
    }
}
```

#### 4. booking-manager.js - Créer une réservation
```javascript
class BookingManager {
    async confirmReservation(rideId) {
        // Envoyer la réservation au serveur
        const response = await fetch('/backend/api/bookings', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`  // ← Prouver qu'on est connecté
            },
            body: JSON.stringify({
                ride_id: rideId,
                passengers_count: 2
            })
        });
        
        // Serveur déduit les crédits et crée la réservation
        // Répondre avec succès
        // Mettre à jour l'affichage des crédits
    }
}
```

#### 5. API Réservation - Traiter la demande
```
POST /backend/api/bookings
Authorization: Bearer <token>
{ride_id: 5, passengers_count: 2}
   ↓
/backend/api/bookings.php
   1. Valider le token JWT
   2. Vérifier les crédits disponibles
   3. Créer la réservation dans bookings.json
   4. Déduire les crédits de l'utilisateur
   5. Réduire les places disponibles
   ↓
Répondre avec JSON
{success: true, booking: {...}, passenger_credits_remaining: 10}
```

#### 6. booking-manager.js - Mise à jour
```javascript
if (data.success) {
    // Mettre à jour les crédits locaux
    this.currentUser.credits = data.passenger_credits_remaining;
    localStorage.setItem('ecoride_current_user', ...);
    
    // Fermer le modal
    modal.remove();
    
    // Montrer un message de succès
    alert('✅ Réservation confirmée !');
}
```

---

## 📁 Fichiers à Modifier Pour Ajouter une Fonctionnalité

### Exemple : Ajouter un bouton "Partager le trajet"

#### 1. Créer l'API PHP (`/backend/api/share.php`)
```php
<?php
// Traiter la requête de partage
// Envoyer l'email, etc.
echo json_encode(['success' => true]);
?>
```

#### 2. Créer la classe JavaScript (`/frontend/js/share-manager.js`)
```javascript
class ShareManager {
    shareRide(rideId) {
        // Envoyer la requête
        fetch('/backend/api/share', {method: 'POST', ...});
    }
}
```

#### 3. Ajouter un bouton dans `rides.js`
```javascript
div.innerHTML = `
    ...
    <button onclick="shareManager.shareRide(${ride.id})">Partager</button>
    ...
`;
```

---

## 🐛 Déboguer - Les Étapes

### Erreur : "Non connecté"
```
1. Ouvrir DevTools (F12)
2. Aller à Console
3. Taper : localStorage.getItem('ecoride_token')
4. Si vide → Pas connecté
5. Si avoir un token → Connecté
```

### Erreur : "Trajet ne s'affiche pas"
```
1. DevTools → Network
2. Chercher requête GET /data/rides.json
3. Vérifier le status (200 OK ou 404?)
4. Regarder la réponse (valide JSON?)
5. Vérifier le fichier existe : /frontend/data/rides.json
```

### Erreur : "Réservation échoue"
```
1. DevTools → Network
2. POST /backend/api/bookings
3. Regarder la réponse
4. Error message ? Crédits insuffisants ? Places ?
5. Voir les logs PHP du serveur
```

---

## 💡 Tips & Tricks

### Voir toutes les requêtes API
```
DevTools → Network Tab → Faire une action
```

### Vérifier les données JSON
```bash
cat frontend/data/users.json | jq .
cat frontend/data/rides.json | jq .
cat frontend/data/bookings.json | jq .
```

### Modifier les trajets
```bash
# Éditer le fichier
nano frontend/data/rides.json

# Ou ajouter un nouveau trajet
jq '.rides += [{"id":11, "departure_city":"Nice", ...}]' frontend/data/rides.json > tmp && mv tmp frontend/data/rides.json
```

### Voir les erreurs PHP
```bash
php -S localhost:8000 -t frontend/ 2>&1 | tee server.log

# Puis regarder server.log pour les erreurs
```

---

## ✅ Checklist - Ce Qui Marche

- ✅ Connexion / Inscription
- ✅ Affichage des trajets
- ✅ Recherche et filtrage
- ✅ Réservation de trajets
- ✅ Système de crédits
- ✅ Autocomplete des villes
- ✅ Protection des pages
- ✅ Avatars des chauffeurs

---

## 🎯 Prochaines Actions

### Pour Apprendre
1. Lire [INDEX.md](./INDEX.md) pour vue d'ensemble
2. Lire [JAVASCRIPT_DOCUMENTATION.md](./JAVASCRIPT_DOCUMENTATION.md) pour JS
3. Lire [PHP_DOCUMENTATION.md](./PHP_DOCUMENTATION.md) pour PHP
4. Modifier le code et tester

### Pour Améliorer
1. Ajouter validations supplémentaires
2. Améliorer le design
3. Ajouter des tests
4. Déployer sur un serveur
5. Ajouter une vraie BDD

---

## 📞 Aide Rapide

| Problème | Solution |
|----------|----------|
| Serveur ne démarre pas | Vérifier que le port 8000 est libre |
| Page blanche | Vérifier la console F12 pour les erreurs |
| Pas de données | Vérifier que les fichiers JSON existent |
| Erreur PHP | Voir les logs du serveur |
| Token invalide | Se reconnecter |
| Crédits insuffisants | Créer un compte avec plus de crédits |

---

## 🚀 Vous êtes Prêt !

1. Lancez le serveur
2. Connectez-vous
3. Réservez un trajet
4. Lisez les commentaires du code
5. Modifiez le code
6. Créez vos propres fonctionnalités

**Bon code ! 🎉**

---

## 📖 Documents Complets

- **[INDEX.md](./INDEX.md)** - Navigation complète
- **[CODE_COMMENTS.md](./CODE_COMMENTS.md)** - Vue d'ensemble architecture
- **[JAVASCRIPT_DOCUMENTATION.md](./JAVASCRIPT_DOCUMENTATION.md)** - Explication JS
- **[PHP_DOCUMENTATION.md](./PHP_DOCUMENTATION.md)** - Explication PHP
- **[Ce fichier](./QUICK_GUIDE.md)** - Guide rapide

🌱 **ECO-ride : Le covoiturage écologique** 🚗
