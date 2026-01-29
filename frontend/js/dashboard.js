/**
 * EcoRide - Gestionnaire du Tableau de Bord (DashboardManager)
 * Gère l'affichage des données utilisateurs, trajets, réservations et actions via API.
 */

class DashboardManager {
    constructor() {
        this.user = null;
        this.userId = null;
        this.RIDES_API = '/rides';
        this.BOOKINGS_API = '/bookings';
        this.USER_API = '/users';
        
        this.init();
    }

    async init() {
        // 1. Vérification de l'authentification
        if (!window.authManager || !window.authManager.isAuthenticated()) {
            window.location.href = 'login.html';
            return;
        }

        this.user = window.authManager.getCurrentUser();
        this.userId = this.user.id;

        // 2. Chargement initial du dashboard
        await this.loadDashboard();

        // 3. Configuration des écouteurs d'événements pour les actions globales (annulations, modal)
        this.setupEventListeners();
    }

    async loadDashboard() {
        // Afficher les informations utilisateur
        document.getElementById('userName').textContent = this.user.pseudo;

        // Charger toutes les données en parallèle
        await Promise.all([
            this.loadUserData(), // Charge les crédits, le user_type réel et les stats
            this.loadUpcomingRides() // Charge les trajets proposés et les réservations
        ]);
        
        // Mettre à jour l'affichage des cartes après le chargement des données utilisateur
        this.updateUserTypeCards();
        this.checkDriverStatus();
    }

    // ====================================================================
    // 1. CHARGEMENT DES DONNÉES UTILISATEUR & STATS (US13)
    // ====================================================================

    async loadUserData() {
        try {
            // Requête pour obtenir les données complètes de l'utilisateur (crédits, type, stats)
            const response = await window.apiClient.get(`${this.USER_API}/${this.userId}`); 

            if (response.success && response.user) {
                const userData = response.user;
                
                // Mettre à jour l'objet user dans le gestionnaire d'authentification et localement
                // Note: La fonction `updateUserInStorage` doit être implémentée dans auth.js pour être persistante
                window.authManager.updateUserInStorage(userData);
                this.user = userData;
                
                // Mettre à jour l'affichage
                document.getElementById('userCredits').textContent = userData.credits || '0';
                
                // Mettre à jour les stats
                document.getElementById('totalRidesAsDriver').textContent = userData.stats?.rides_proposed || 0;
                document.getElementById('totalRidesAsPassenger').textContent = userData.stats?.rides_booked || 0;
                document.getElementById('averageRating').textContent = userData.stats?.average_rating?.toFixed(1) || 'N/A';
            }
        } catch (error) {
            console.error("Erreur lors du chargement des données utilisateur:", error);
            document.getElementById('userCredits').textContent = 'Erreur';
        }
    }

    // ====================================================================
    // 2. GESTION DES TRAJETS ET RÉSERVATIONS (US10)
    // ====================================================================

    async loadUpcomingRides() {
        try {
            // A. Trajets Proposés (Driver)
            const driverRidesResponse = await window.apiClient.get(`${this.RIDES_API}?action=driverRides`);
            const driverRides = driverRidesResponse.success ? driverRidesResponse.data : [];

            // B. Réservations Passager
            const passengerBookingsResponse = await window.apiClient.get(this.BOOKINGS_API);
            const passengerBookings = passengerBookingsResponse.success ? passengerBookingsResponse.bookings : [];

            this.displayRides(driverRides, passengerBookings);

        } catch (error) {
            console.error("Erreur lors du chargement des trajets/réservations:", error);
            this.showEmptyState("Erreur lors du chargement des trajets.", 'Erreur API');
        }
    }
    
    displayRides(driverRides, passengerBookings) {
        const container = document.getElementById('upcomingRides');
        container.innerHTML = '';
        
        const upcomingElements = [];

        // 1. Traitement des réservations (Passager)
        passengerBookings.forEach(booking => {
            const departureDate = new Date(booking.departure_datetime);
            // On n'affiche que les réservations confirmées et futures
            if (booking.booking_status === 'confirmed' && departureDate > new Date()) {
                upcomingElements.push({
                    type: 'passenger',
                    date: departureDate,
                    html: this.renderPassengerBooking(booking)
                });
            }
        });

        // 2. Traitement des trajets proposés (Chauffeur)
        driverRides.forEach(ride => {
            const departureDate = new Date(ride.departure_datetime);
            // On n'affiche que les trajets confirmés/publiés et futurs
            if (ride.status === 'published' && departureDate > new Date()) { 
                upcomingElements.push({
                    type: 'driver',
                    date: departureDate,
                    html: this.renderDriverRide(ride)
                });
            }
        });
        
        // Trier par date de départ
        upcomingElements.sort((a, b) => a.date - b.date);

        if (upcomingElements.length === 0) {
            this.showEmptyState("Aucun trajet à venir pour le moment.", "📅");
        } else {
            container.innerHTML = upcomingElements.map(e => e.html).join('');
        }
    }

    showEmptyState(title, icon) {
        document.getElementById('upcomingRides').innerHTML = `
            <div class="empty-state" style="padding: 2rem;">
                <div class="empty-state-icon">${icon}</div>
                <h4 class="empty-state-title">${title}</h4>
                <p class="empty-state-text">Recherchez ou proposez un trajet pour commencer.</p>
                <a href="rides.html" class="btn btn-primary">Rechercher un trajet</a>
            </div>
        `;
    }

    // --- Fonctions de Rendu (Similaires à la proposition précédente) ---

    renderDriverRide(ride) {
        // ... (Logique de rendu pour le chauffeur, incluant le bouton d'annulation)
        return `
            <div class="ride-card driver-ride" style="margin-bottom: 1rem; border-left: 5px solid var(--primary-green);">
                <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                    <div style="flex: 2; min-width: 250px;">
                        <span class="tag tag-primary">Proposé (Chauffeur)</span>
                        <h4 style="margin: 0.5rem 0 0.5rem 0;">
                            ${ride.departure_city} → ${ride.arrival_city}
                        </h4>
                        <p style="font-size: var(--font-size-sm); color: var(--text-secondary);">
                            Départ : <strong>${new Date(ride.departure_datetime).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })}</strong>
                        </p>
                    </div>

                    <div style="flex: 1; min-width: 150px; text-align: right;">
                        <p style="margin: 0;">Places restantes: <strong>${ride.seats_available}</strong></p>
                        <p style="margin: 0;">Prix/Passager: <strong>${ride.price_credits} crédits</strong></p>
                        <button class="btn btn-danger btn-sm mt-md" onclick="dashboardManager.handleCancelRide(${ride.id})">
                                Annuler le trajet
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    renderPassengerBooking(booking) {
        // ... (Logique de rendu pour le passager, incluant le bouton d'annulation)
         return `
            <div class="ride-card passenger-booking" style="margin-bottom: 1rem; border-left: 5px solid var(--primary-blue);">
                <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                    <div style="flex: 2; min-width: 250px;">
                        <span class="tag tag-secondary">Réservé (Passager)</span>
                        <h4 style="margin: 0.5rem 0 0.5rem 0;">
                            ${booking.departure_city} → ${booking.arrival_city}
                        </h4>
                        <p style="font-size: var(--font-size-sm); color: var(--text-secondary);">
                            Départ : <strong>${new Date(booking.departure_datetime).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })}</strong>
                        </p>
                        <p style="font-size: var(--font-size-sm); color: var(--text-secondary);">
                            Chauffeur : ${booking.driver_pseudo}
                        </p>
                    </div>

                    <div style="flex: 1; min-width: 150px; text-align: right;">
                        <p style="margin: 0;">Places: <strong>${booking.seats_booked}</strong></p>
                        <p style="margin: 0;">Payé: <strong>${booking.price_paid} crédits</strong></p>
                        <button class="btn btn-outline-danger btn-sm mt-md" onclick="dashboardManager.handleCancelBooking(${booking.booking_id})">
                                Annuler la réservation
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    // ====================================================================
    // 3. GESTION DES ACTIONS (ANNULATION - US10)
    // ====================================================================
    
    async handleCancelBooking(bookingId) {
        if (!confirm('Êtes-vous sûr de vouloir annuler cette réservation ? Le remboursement sera effectué en crédits. Cette action est irréversible.')) {
            return;
        }

        try {
            // Utilise DELETE /bookings?id={booking_id}
            const response = await window.apiClient.delete(`${this.BOOKINGS_API}?id=${bookingId}`);

            if (response.success) {
                alert('Réservation annulée. Remboursement effectué.');
                await this.loadDashboard(); // Recharger toutes les données
            } else {
                alert(response.message || "Échec de l'annulation de la réservation. Veuillez contacter le support.");
            }
        } catch (error) {
            console.error("Erreur annulation réservation:", error);
            alert("Erreur réseau ou API.");
        }
    }

    async handleCancelRide(rideId) {
        if (!confirm('Êtes-vous sûr de vouloir annuler ce trajet ? Tous les passagers confirmés seront remboursés automatiquement. Cette action est irréversible.')) {
            return;
        }

        try {
            // Utilise DELETE /rides/{ride_id} (à implémenter dans RidesController)
            const response = await window.apiClient.delete(`${this.RIDES_API}/${rideId}`);

            if (response.success) {
                alert('Trajet annulé. Les passagers ont été remboursés.');
                await this.loadDashboard(); // Recharger toutes les données
            } else {
                alert(response.message || "Échec de l'annulation du trajet. Le délai est peut-être dépassé.");
            }
        } catch (error) {
            console.error("Erreur annulation trajet:", error);
            alert("Erreur réseau ou API.");
        }
    }

    // ====================================================================
    // 4. GESTION DU PROFIL ET TYPE D'UTILISATEUR (US13)
    // ====================================================================

    setupEventListeners() {
        // Les fonctions setUserType, showProfileSettings, closeProfileModal, saveProfile
        // sont gérées via les fonctions globales ci-dessous pour l'appel direct dans l'HTML.
    }

    updateUserTypeCards() {
        const cards = {
            passenger: document.getElementById('passengerCard'),
            driver: document.getElementById('driverCard'),
            both: document.getElementById('bothCard')
        };
        
        // Mettre à jour l'état visuel des cartes
        Object.keys(cards).forEach(type => {
            if (cards[type]) {
                if (this.user.user_type === type) {
                    cards[type].style.border = '2px solid var(--primary-green)';
                    cards[type].style.background = 'var(--bg-hover)';
                } else {
                    cards[type].style.border = '2px solid var(--border-light)';
                    cards[type].style.background = '';
                }
            }
        });
    }
    
    checkDriverStatus() {
         const createCard = document.getElementById('createRideCard');
         if (createCard) {
            if (this.user.user_type === 'passenger') {
                createCard.style.opacity = '0.5';
                createCard.onclick = (e) => {
                    e.preventDefault();
                    alert('Vous devez être chauffeur pour créer un trajet. Changez votre type de compte dans les paramètres.');
                };
            } else {
                createCard.style.opacity = '1';
                createCard.onclick = () => {
                    window.location.href = 'create-ride.html';
                };
            }
        }
    }
}

// ====================================================================
// Fonctions Globales pour les appels HTML (US13)
// ====================================================================

window.setUserType = async function(type) {
    const manager = window.dashboardManager;
    if (!manager) return;
    
    try {
        const response = await window.apiClient.put(`${manager.USER_API}/${manager.userId}`, { user_type: type });

        if (response.success) {
            // Mettre à jour localement l'état et recharger les données pour l'affichage immédiat
            window.authManager.updateUserInStorage({ user_type: type });
            manager.user.user_type = type; // Mise à jour locale
            
            // Recharger le dashboard pour mettre à jour les cartes et la carte "Créer un trajet"
            manager.loadDashboard();
            alert(`Type de compte mis à jour : ${type}`);
        } else {
            alert(response.message || "Erreur lors de la mise à jour du type de compte.");
        }
    } catch (error) {
        console.error("Erreur API setUserType:", error);
        alert("Échec de la connexion à l'API.");
    }
};

window.showProfileSettings = function() {
    const modal = document.getElementById('profileModal');
    const user = window.authManager.getCurrentUser();

    // Remplir le modal avec les données actuelles
    document.getElementById('profilePseudo').value = user.pseudo;
    document.getElementById('profileEmail').value = user.email;
    document.getElementById('profileUserType').value = user.user_type;
    document.getElementById('profilePhoto').src = user.photo_url || 'https://i.pravatar.cc/150?img=1';

    modal.style.display = 'flex';
    // Utiliser requestAnimationFrame pour s'assurer que l'élément est dans le DOM avant d'ajouter la classe 'show'
    requestAnimationFrame(() => modal.classList.add('show'));
};

window.closeProfileModal = function() {
    const modal = document.getElementById('profileModal');
    modal.classList.remove('show');
    // Après l'animation (simulée à 300ms), cacher complètement le modal
    setTimeout(() => modal.style.display = 'none', 300);
};

window.saveProfile = async function() {
    const manager = window.dashboardManager;
    const user = window.authManager.getCurrentUser();
    
    const updatedData = {
        pseudo: document.getElementById('profilePseudo').value,
        email: document.getElementById('profileEmail').value,
        user_type: document.getElementById('profileUserType').value,
        // photo_url: user.photo_url, // Laissez la photo de côté pour la simplicité
    };
    
    try {
        // Utilise PUT /users/{id}
        const response = await window.apiClient.put(`${manager.USER_API}/${manager.userId}`, updatedData);

        if (response.success) {
            alert("Profil mis à jour avec succès !");
            window.closeProfileModal();
            
            // Mettre à jour l'objet utilisateur stocké et recharger le dashboard
            window.authManager.updateUserInStorage(updatedData); 
            await manager.loadDashboard();
        } else {
            alert(response.message || "Échec de l'enregistrement du profil.");
        }
    } catch (error) {
        console.error("Erreur lors de la sauvegarde du profil:", error);
        alert("Erreur réseau lors de la sauvegarde.");
    }
};

// Initialiser
const dashboardManager = new DashboardManager();
window.dashboardManager = dashboardManager; // Rendre l'instance accessible globalement