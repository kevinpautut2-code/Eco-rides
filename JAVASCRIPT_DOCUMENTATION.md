# 📖 Documentation JavaScript - Explication Détaillée

## Vue d'ensemble

Les fichiers JavaScript gèrent toute l'interactivité côté navigateur :
- Authentification (login/register)
- Affichage des trajets
- Gestion des réservations
- Protection des pages

---

## Fichier 1 : `auth.js` - Authentification

### Rôle
Gère la connexion et l'inscription des utilisateurs.

### Structure Principale

```javascript
class AuthManager {
    constructor() {
        this.currentUser = null;      // L'utilisateur connecté
        this.init();
    }

    init() {
        // 1. Charger l'utilisateur depuis localStorage
        this.loadCurrentUser();

        // 2. Attacher les formulaires
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => this.handleLogin(e));
        }

        // 3. Vérifier l'accès aux pages protégées
        this.checkProtectedPage();
    }
}
```

### Processus LOGIN - Expliqué Pas à Pas

```javascript
async handleLogin(e) {
    e.preventDefault();  // Empêcher le rechargement de la page

    // ÉTAPE 1 : Récupérer les données du formulaire
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;

    // ÉTAPE 2 : Valider que les champs ne sont pas vides
    if (!email || !password) {
        this.showNotification('Veuillez remplir tous les champs', 'error');
        return;
    }

    // ÉTAPE 3 : Montrer un indicateur de chargement
    const submitButton = document.querySelector('[type="submit"]');
    submitButton.disabled = true;
    submitButton.textContent = 'Connexion en cours...';

    try {
        // ÉTAPE 4 : Envoyer les identifiants au serveur
        const response = await fetch('/backend/api/auth?action=login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ email, password })
        });

        const data = await response.json();

        // ÉTAPE 5 : Vérifier la réponse du serveur
        if (data.success) {
            // ✅ Connexion réussie

            // ÉTAPE 6 : Sauvegarder le token JWT
            localStorage.setItem('ecoride_token', data.token);

            // ÉTAPE 7 : Sauvegarder les infos utilisateur
            localStorage.setItem('ecoride_current_user', JSON.stringify(data.user));

            // ÉTAPE 8 : Actualiser l'objet utilisateur local
            this.currentUser = data.user;

            // ÉTAPE 9 : Afficher un message de succès
            this.showNotification('Connexion réussie !', 'success');

            // ÉTAPE 10 : Attendre 1 seconde, puis rediriger
            setTimeout(() => {
                window.location.href = '/rides.html';
            }, 1000);

        } else {
            // ❌ Connexion échouée
            this.showNotification(data.message || 'Email ou mot de passe incorrect', 'error');
            submitButton.disabled = false;
            submitButton.textContent = 'Se connecter';
        }

    } catch (error) {
        // ❌ Erreur réseau ou serveur
        console.error('Erreur login:', error);
        this.showNotification('Erreur de connexion', 'error');
        submitButton.disabled = false;
        submitButton.textContent = 'Se connecter';
    }
}
```

### Processus REGISTER - Explication

```javascript
async handleRegister(e) {
    e.preventDefault();

    // ÉTAPE 1 : Récupérer les données
    const firstName = document.getElementById('firstName').value;
    const lastName = document.getElementById('lastName').value;
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const userType = document.querySelector('[name="user_type"]:checked').value;

    // ÉTAPE 2 : Valider les données
    if (!firstName || !lastName || !email || !password || !confirmPassword) {
        this.showNotification('Tous les champs sont requis', 'error');
        return;
    }

    // ÉTAPE 3 : Vérifier que les mots de passe correspondent
    if (password !== confirmPassword) {
        this.showNotification('Les mots de passe ne correspondent pas', 'error');
        return;
    }

    // ÉTAPE 4 : Vérifier la force du mot de passe
    if (!this.validatePassword(password)) {
        this.showNotification('Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial', 'error');
        return;
    }

    try {
        // ÉTAPE 5 : Envoyer les données d'inscription au serveur
        const response = await fetch('/backend/api/auth?action=register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                first_name: firstName,
                last_name: lastName,
                email: email,
                password: password,
                user_type: userType
            })
        });

        const data = await response.json();

        if (data.success) {
            // ✅ Inscription réussie

            // ÉTAPE 6 : Sauvegarder automatiquement le token (auto-login)
            localStorage.setItem('ecoride_token', data.token);
            localStorage.setItem('ecoride_current_user', JSON.stringify(data.user));

            // ÉTAPE 7 : Afficher le message
            this.showNotification('Inscription réussie ! Bienvenue ' + firstName, 'success');

            // ÉTAPE 8 : Rediriger
            setTimeout(() => {
                window.location.href = '/rides.html';
            }, 1000);

        } else {
            this.showNotification(data.message || 'Erreur lors de l\'inscription', 'error');
        }

    } catch (error) {
        console.error('Erreur register:', error);
        this.showNotification('Erreur de connexion au serveur', 'error');
    }
}
```

### localStorage - Comment ça marche

Le `localStorage` est une zone de stockage du navigateur qui persiste entre les pages :

```javascript
// Sauvegarder des données
localStorage.setItem('clé', 'valeur');
localStorage.setItem('ecoride_token', 'eyJpZCI6MSwiZW1haWwiOi...');

// Récupérer des données
const token = localStorage.getItem('ecoride_token');

// Supprimer des données
localStorage.removeItem('ecoride_token');

// Supprimer tout
localStorage.clear();

// Vérifier si existe
if (localStorage.getItem('ecoride_token')) {
    // L'utilisateur est connecté
}
```

---

## Fichier 2 : `rides.js` - Affichage et Filtrage

### Rôle
Affiche les covoiturages et permet de les filtrer.

### Architecture

```javascript
class RidesManager {
    constructor() {
        this.allRides = [];       // Tous les trajets chargés
        this.filteredRides = [];  // Trajets après application des filtres
        this.searchParams = {};   // Paramètres de recherche
        
        this.filterEco = ...;     // Checkbox "Électrique"
        this.filterPrice = ...;   // Slider prix
        this.filterDuration = ...; // Slider durée
        
        this.init();
    }

    init() {
        this.loadSearchParams();      // Charger depuis l'URL
        this.setupFilters();          // Attacher les écouteurs
        this.loadAvailableRides();    // Charger les trajets
    }
}
```

### Charger les Trajets

```javascript
async loadAvailableRides() {
    try {
        // ÉTAPE 1 : Récupérer les trajets
        const response = await fetch('/data/rides.json');
        const data = await response.json();
        this.allRides = data.rides || [];

        // ÉTAPE 2 : Afficher un indicateur de chargement
        this.loadingState.style.display = 'block';

        // ÉTAPE 3 : Appliquer les filtres
        this.applyFilters();

        // ÉTAPE 4 : Afficher les résultats
        this.displayRides(this.filteredRides);

        // ÉTAPE 5 : Masquer l'indicateur de chargement
        this.loadingState.style.display = 'none';

    } catch (error) {
        console.error('Erreur:', error);
        this.showNotification('Erreur lors du chargement des trajets', 'error');
    }
}
```

### Afficher un Trajet (Créer une Carte)

```javascript
renderRide(ride) {
    // Créer un élément div pour la carte
    const div = document.createElement('div');
    div.className = 'ride-card';

    // Formater les dates
    const departureDate = new Date(ride.departure_datetime);
    const arrivalDate = new Date(ride.arrival_datetime);

    // Calculer la durée
    const duration = (arrivalDate - departureDate) / (1000 * 60 * 60);
    const isEcological = ride.is_ecological;

    // Créer le HTML de la carte
    div.innerHTML = `
        <div class="ride-card-header">
            <div class="ride-driver">
                <!-- Avatar du chauffeur -->
                <img src="${ride.driver_avatar}" class="driver-avatar">
                <h4>${ride.driver_pseudo}</h4>
                <!-- Note du chauffeur -->
                <span>${parseFloat(ride.driver_rating).toFixed(1)}/5</span>
            </div>
            <!-- Badge écologique -->
            ${isEcological ? '<span class="badge">Électrique</span>' : ''}
        </div>

        <div class="ride-card-body">
            <!-- Itinéraire -->
            <div class="route">
                <strong>${ride.departure_city}</strong> → <strong>${ride.arrival_city}</strong>
            </div>
            
            <!-- Date/heure -->
            <div class="date">
                ${departureDate.toLocaleDateString('fr-FR')}
                ${departureDate.toLocaleTimeString('fr-FR')}
            </div>

            <!-- Info trajets -->
            <div class="info">
                <span>${ride.seats_available} places</span>
                <span>${duration.toFixed(1)}h</span>
                <span>${ride.price_credits} crédits</span>
            </div>
        </div>

        <div class="ride-card-footer">
            <button class="btn-reserve" data-ride-id="${ride.id}">Réserver</button>
            <a href="ride-details.html?id=${ride.id}" class="btn-details">Détails</a>
        </div>
    `;

    return div;
}
```

### Filtrer les Trajets

```javascript
applyFilters() {
    // ÉTAPE 1 : Commencer avec tous les trajets
    this.filteredRides = [...this.allRides];

    // ÉTAPE 2 : Filtrer par ville de départ
    if (this.searchParams.departure) {
        this.filteredRides = this.filteredRides.filter(ride =>
            ride.departure_city.toLowerCase() === this.searchParams.departure.toLowerCase()
        );
    }

    // ÉTAPE 3 : Filtrer par ville d'arrivée
    if (this.searchParams.arrival) {
        this.filteredRides = this.filteredRides.filter(ride =>
            ride.arrival_city.toLowerCase() === this.searchParams.arrival.toLowerCase()
        );
    }

    // ÉTAPE 4 : Filtrer par prix (slider)
    const maxPrice = this.filterPrice.value;
    this.filteredRides = this.filteredRides.filter(ride =>
        ride.price_credits <= maxPrice
    );

    // ÉTAPE 5 : Filtrer par durée (slider)
    const maxDuration = this.filterDuration.value;
    this.filteredRides = this.filteredRides.filter(ride => {
        const duration = (new Date(ride.arrival_datetime) - new Date(ride.departure_datetime)) / (1000 * 60 * 60);
        return duration <= maxDuration;
    });

    // ÉTAPE 6 : Filtrer par note (slider)
    const minRating = this.filterRating.value;
    this.filteredRides = this.filteredRides.filter(ride =>
        ride.driver_rating >= minRating
    );

    // ÉTAPE 7 : Filtrer par écologique
    if (this.filterEco.checked) {
        this.filteredRides = this.filteredRides.filter(ride =>
            ride.is_ecological === true
        );
    }

    // ÉTAPE 8 : Afficher le nombre de résultats
    this.resultsCount.textContent = `${this.filteredRides.length} trajet(s) trouvé(s)`;
}

// Mettre à jour l'affichage quand un filtre change
this.filterPrice.addEventListener('input', () => {
    this.applyFilters();
    this.displayRides(this.filteredRides);
});
```

---

## Fichier 3 : `booking-manager.js` - Gestion des Réservations

### Rôle
Gère l'affichage du modal de réservation et l'envoi de la réservation au serveur.

### Ouvrir le Modal

```javascript
openReservationModal(rideId) {
    // ÉTAPE 1 : Vérifier que l'utilisateur est connecté
    if (!this.currentUser) {
        alert('Veuillez vous connecter pour réserver');
        window.location.href = '/login.html';
        return;
    }

    // ÉTAPE 2 : Empêcher les chauffeurs de réserver
    if (this.currentUser.user_type === 'driver') {
        alert('Les chauffeurs ne peuvent pas réserver');
        return;
    }

    // ÉTAPE 3 : Trouver le trajet dans la liste
    const ride = this.findRide(rideId);
    if (!ride) {
        alert('Trajet non trouvé');
        return;
    }

    // ÉTAPE 4 : Afficher le modal avec les détails
    this.showReservationModal(ride);
}
```

### Créer et Afficher le Modal

```javascript
showReservationModal(ride) {
    // ÉTAPE 1 : Créer l'élément modal
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.id = 'reservationModal';

    // ÉTAPE 2 : Construire le HTML du modal
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>Réserver un trajet</h2>
                <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">✕</button>
            </div>

            <div class="modal-body">
                <!-- Détails du trajet -->
                <div class="booking-ride-details">
                    <h3>${ride.departure_city} → ${ride.arrival_city}</h3>
                    <p>${new Date(ride.departure_datetime).toLocaleDateString('fr-FR')}</p>
                    <p class="price">
                        <strong>${ride.price_credits} crédits par personne</strong>
                    </p>
                </div>

                <!-- Sélecteur du nombre de passagers -->
                <div class="form-group">
                    <label for="passengersCount">Nombre de passagers</label>
                    <input type="number" id="passengersCount" min="1" max="${ride.seats_available}" value="1">
                    <small>Disponible : ${ride.seats_available} place(s)</small>
                </div>

                <!-- Affichage du coût total -->
                <div class="booking-summary">
                    <p>Coût total : <strong id="totalCost">${ride.price_credits}</strong> crédits</p>
                    <p>Vos crédits : <strong>${this.currentUser.credits}</strong> crédits</p>
                </div>

                <!-- Conditions d'acceptation -->
                <div class="form-group">
                    <input type="checkbox" id="acceptTerms">
                    <label>Je déclare accepter les conditions de réservation</label>
                </div>

                <!-- Boutons d'action -->
                <div class="modal-footer">
                    <button class="btn-cancel" onclick="this.closest('.modal-overlay').remove()">Annuler</button>
                    <button class="btn-confirm" onclick="bookingManager.confirmReservation(${ride.id})">Confirmer</button>
                </div>
            </div>
        </div>
    `;

    // ÉTAPE 3 : Ajouter le modal au DOM (le rendre visible)
    document.body.appendChild(modal);

    // ÉTAPE 4 : Mettre à jour le coût total quand le nombre change
    const input = modal.querySelector('#passengersCount');
    input.addEventListener('change', () => {
        const total = ride.price_credits * input.value;
        document.getElementById('totalCost').textContent = total;
    });
}
```

### Confirmer la Réservation

```javascript
async confirmReservation(rideId) {
    // ÉTAPE 1 : Récupérer les données du modal
    const modal = document.getElementById('reservationModal');
    const passengersCount = parseInt(modal.querySelector('#passengersCount').value);
    const acceptTerms = modal.querySelector('#acceptTerms').checked;

    // ÉTAPE 2 : Vérifier que les conditions sont acceptées
    if (!acceptTerms) {
        alert('Veuillez accepter les conditions');
        return;
    }

    try {
        // ÉTAPE 3 : Envoyer la requête au serveur
        const response = await fetch('/backend/api/bookings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.token}`  // Envoyer le token JWT
            },
            body: JSON.stringify({
                ride_id: rideId,
                passengers_count: passengersCount
            })
        });

        const data = await response.json();

        // ÉTAPE 4 : Traiter la réponse
        if (data.success) {
            // ✅ Réservation réussie

            // Mettre à jour les crédits localement
            this.currentUser.credits = data.passenger_credits_remaining;
            localStorage.setItem('ecoride_current_user', JSON.stringify(this.currentUser));

            // Mettre à jour la barre utilisateur
            if (pageProtection) {
                pageProtection.currentUser.credits = data.passenger_credits_remaining;
                pageProtection.displayUserBar();
            }

            // Afficher un message de succès
            alert('Réservation confirmée !');

            // Fermer le modal
            modal.remove();

            // Recharger la page pour mettre à jour les places disponibles
            setTimeout(() => location.reload(), 1000);

        } else {
            // ❌ Réservation échouée
            alert('Erreur : ' + data.message);
        }

    } catch (error) {
        console.error('Erreur:', error);
        alert('Erreur de connexion');
    }
}
```

---

## Fichier 4 : `page-protection.js` - Protection des Pages

### Rôle
Vérifie si l'utilisateur est connecté et redirige si nécessaire.

### Vérifier l'Accès

```javascript
checkPageAccess() {
    const currentPage = window.location.pathname;
    
    // Pages qui nécessitent d'être connecté
    const protectedPages = [
        '/dashboard.html',
        '/admin-dashboard.html',
        '/create-ride.html'
    ];

    // Pages accessibles à tous
    const publicPages = [
        '/',
        '/index.html',
        '/login.html',
        '/rides.html'
    ];

    // ÉTAPE 1 : Vérifier si on est sur une page protégée
    const isProtected = protectedPages.some(page => currentPage.includes(page));

    // ÉTAPE 2 : Si protégée et pas connecté, rediriger
    if (isProtected && !this.currentUser) {
        console.warn('Accès refusé - redirection login');
        window.location.href = '/login.html?redirect=' + encodeURIComponent(currentPage);
        return;
    }

    // ÉTAPE 3 : Si connecté et sur login/register, rediriger vers rides
    if (this.currentUser && (currentPage.includes('/login.html') || currentPage.includes('/register.html'))) {
        window.location.href = '/rides.html';
        return;
    }
}
```

### Afficher la Barre Utilisateur

```javascript
displayUserBar() {
    // ÉTAPE 1 : Créer le HTML de la barre
    const userBar = `
        <div class="user-bar">
            <div class="user-info">
                <span>${this.currentUser.first_name}</span>
                <strong>${this.currentUser.credits} crédits</strong>
            </div>
            <button class="btn-logout" onclick="authManager.handleLogout()">
                Déconnexion
            </button>
        </div>
    `;

    // ÉTAPE 2 : Injecter dans le DOM
    const navbar = document.querySelector('.navbar-actions');
    if (navbar) {
        navbar.innerHTML = userBar + navbar.innerHTML;
    }
}
```

---

## Fichier 5 : `ride-search.js` - Autocomplete

### Rôle
Affiche des suggestions de villes au fur et à mesure de la saisie.

### Comment ça marche

```javascript
setupInputAutocomplete(input, type) {
    // ÉTAPE 1 : Créer la liste de suggestions
    const container = input.parentElement;
    const suggestionsList = document.createElement('ul');
    suggestionsList.className = 'autocomplete-suggestions';
    container.appendChild(suggestionsList);

    // ÉTAPE 2 : Écouteur pour chaque caractère tapé
    input.addEventListener('input', (e) => {
        const value = e.target.value.toLowerCase();
        suggestionsList.innerHTML = '';  // Effacer les anciennes suggestions

        // ÉTAPE 3 : Si rien saisi, masquer la liste
        if (value.length === 0) {
            suggestionsList.style.display = 'none';
            return;
        }

        // ÉTAPE 4 : Filtrer les villes qui contiennent le texte saisi
        const filtered = this.uniqueCities.filter(city =>
            city.toLowerCase().includes(value)
        );

        // ÉTAPE 5 : Si pas de résultat, masquer
        if (filtered.length === 0) {
            suggestionsList.style.display = 'none';
            return;
        }

        // ÉTAPE 6 : Créer une <li> pour chaque suggestion
        filtered.forEach(city => {
            const li = document.createElement('li');
            li.textContent = city;

            // ÉTAPE 7 : Quand on clique sur une suggestion
            li.addEventListener('click', () => {
                input.value = city;  // Remplir le champ
                suggestionsList.innerHTML = '';  // Effacer les suggestions
                suggestionsList.style.display = 'none';
            });

            suggestionsList.appendChild(li);
        });

        // ÉTAPE 8 : Afficher la liste
        suggestionsList.style.display = 'block';
    });
}
```

### Exemple Pas à Pas

```
Utilisateur tape : "P"
↓
Filtrer les villes contenant "P"
↓
Afficher : Paris, Pau, Poitiers, Lyon (contient P)
↓
Utilisateur clique "Paris"
↓
Le champ se remplit avec "Paris"
↓
Les suggestions disparaissent
```

---

## Interactions Entre les Fichiers

### Flux : Utilisateur se connecte puis réserve un trajet

```
1. Utilisateur ouvre login.html
   → auth.js s'initialise

2. Utilisateur se connecte
   → auth.js envoie POST /backend/api/auth?action=login
   → Reçoit le token JWT
   → Stocke dans localStorage
   → Redirige vers rides.html

3. rides.html se charge
   → page-protection.js vérifie le token
   → rides.js affiche les trajets
   → ride-search.js ajoute l'autocomplete

4. Utilisateur réserve un trajet
   → booking-manager.js ouvre le modal
   → Utilisateur confirme
   → booking-manager.js envoie POST /backend/api/bookings
   → Serveur déduit les crédits
   → booking-manager.js met à jour localStorage
   → page-protection.js rafraîchit la barre utilisateur
   → Modal se ferme

5. Page se recharge
   → Affiche les trajets mises à jour
   → Barre utilisateur affiche les nouveaux crédits
```

---

## Console du Navigateur (DevTools)

Pour déboguer, appuyez sur `F12` et regardez la console :

```javascript
// Voir l'utilisateur connecté
console.log(localStorage.getItem('ecoride_current_user'));

// Voir le token
console.log(localStorage.getItem('ecoride_token'));

// Vérifier les erreurs
// Les erreurs apparaissent en rouge dans la console

// Tester une requête API manuellement
fetch('/backend/api/auth?action=login', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({email: 'test@test.com', password: 'pass'})
}).then(r => r.json()).then(d => console.log(d));
```

---

## Points Clés à Comprendre

1. **localStorage** = Stockage persistant dans le navigateur
2. **Token JWT** = Identifiant unique qui prouve qu'on est connecté
3. **Fetch API** = Communiquer avec le serveur
4. **Classes JavaScript** = Organiser le code en modules
5. **Event Listeners** = Réagir aux actions utilisateur (clics, saisie, etc.)

---

Bonne compréhension ! 🚀
