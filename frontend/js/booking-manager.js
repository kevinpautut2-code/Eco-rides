/**
 * EcoRide - Gestion des Réservations
 * Créer, annuler, et gérer les réservations
 */

class BookingManager {
    constructor() {
        this.currentUser = null;
        this.token = null;
        this.init();
    }

    init() {
        this.token = localStorage.getItem('ecoride_token');
        const userStr = localStorage.getItem('ecoride_current_user');
        
        if (userStr) {
            try {
                this.currentUser = JSON.parse(userStr);
            } catch (e) {
                console.error('Erreur parsing utilisateur:', e);
            }
        }

        // Écouter les clics sur les boutons de réservation
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-reserve') || e.target.closest('.btn-reserve')) {
                const rideId = e.target.getAttribute('data-ride-id') || e.target.closest('.btn-reserve').getAttribute('data-ride-id');
                this.openReservationModal(rideId);
            }
        });
    }

    /**
     * Ouvrir le modal de réservation
     */
    openReservationModal(rideId) {
        // Vérifier que l'utilisateur est connecté
        if (!this.currentUser) {
            alert('Veuillez vous connecter pour réserver un trajet');
            window.location.href = '/login.html?redirect=' + encodeURIComponent(window.location.href);
            return;
        }

        // Empêcher les chauffeurs de réserver
        if (this.currentUser.user_type === 'driver' || this.currentUser.role === 'driver') {
            alert('Les chauffeurs ne peuvent pas réserver des trajets');
            return;
        }

        // Trouver le trajet
        const ride = this.findRide(rideId);
        if (!ride) {
            alert('Trajet non trouvé');
            return;
        }

        // Créer et afficher le modal
        this.showReservationModal(ride);
    }

    /**
     * Afficher le modal de réservation
     */
    showReservationModal(ride) {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.id = 'reservationModal';

        const departureDate = new Date(ride.departure_datetime);
        const arrivalDate = new Date(ride.arrival_datetime);

        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Réserver un trajet</h2>
                    <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">✕</button>
                </div>

                <div class="modal-body">
                    <!-- Détails du trajet -->
                    <div class="booking-ride-details">
                        <h3>Détails du trajet</h3>
                        <div class="ride-summary">
                            <div class="route-summary">
                                <strong>${ride.departure_city}</strong>
                                <span class="arrow">→</span>
                                <strong>${ride.arrival_city}</strong>
                            </div>
                            <div class="date-summary">
                                ${departureDate.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' })}
                                ${departureDate.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
                            </div>
                            <div class="price-summary">
                                <span class="price-label">Prix par personne</span>
                                <span class="price-value">${ride.price_credits} crédits</span>
                            </div>
                        </div>
                    </div>

                    <!-- Sélection du nombre de passagers -->
                    <div class="booking-form">
                        <label for="passengersCount" class="form-label">Nombre de passagers</label>
                        <div class="passengers-selector">
                            <button class="btn-qty" onclick="this.nextElementSibling.value = Math.max(1, parseInt(this.nextElementSibling.value) - 1); document.getElementById('totalPrice').textContent = parseInt(document.getElementById('passengersCount').value) * ${ride.price_credits};">−</button>
                            <input type="number" id="passengersCount" class="form-control" value="1" min="1" max="${ride.seats_available}" 
                                   onchange="document.getElementById('totalPrice').textContent = parseInt(this.value) * ${ride.price_credits};">
                            <button class="btn-qty" onclick="this.previousElementSibling.value = Math.min(${ride.seats_available}, parseInt(this.previousElementSibling.value) + 1); document.getElementById('totalPrice').textContent = parseInt(document.getElementById('passengersCount').value) * ${ride.price_credits};">+</button>
                        </div>
                        <small>Places disponibles: ${ride.seats_available}</small>
                    </div>

                    <!-- Résumé du coût -->
                    <div class="booking-summary">
                        <div class="summary-row">
                            <span>Prix unitaire</span>
                            <span>${ride.price_credits} crédits</span>
                        </div>
                        <div class="summary-row">
                            <span>Nombre de passagers</span>
                            <span><span id="passengersCountDisplay">1</span></span>
                        </div>
                        <div class="summary-row summary-total">
                            <span>Coût total</span>
                            <span><span id="totalPrice">${ride.price_credits}</span> crédits</span>
                        </div>
                        <div class="summary-row">
                            <span>Vos crédits actuels</span>
                            <span class="${this.currentUser.credits >= ride.price_credits ? 'text-success' : 'text-danger'}">${this.currentUser.credits} crédits</span>
                        </div>
                    </div>

                    <!-- Conditions -->
                    <div class="booking-terms">
                        <label class="checkbox-group">
                            <input type="checkbox" id="acceptTerms" class="checkbox-input">
                            <span class="checkbox-label">J'accepte les conditions de réservation</span>
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button onclick="document.getElementById('reservationModal').remove()" class="btn btn-secondary">Annuler</button>
                    <button onclick="bookingManager.confirmReservation(${ride.id})" class="btn btn-primary">
                        Confirmer la réservation
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Ajouter les styles du modal
        this.addModalStyles();

        // Mettre à jour l'affichage du nombre de passagers
        const input = modal.querySelector('#passengersCount');
        input.addEventListener('change', () => {
            document.getElementById('passengersCountDisplay').textContent = input.value;
        });
    }

    /**
     * Confirmer la réservation
     * Valide les informations et envoie la requête au serveur
     */
    async confirmReservation(rideId) {
        // ÉTAPE 1 : Récupérer les données du modal
        const modal = document.getElementById('reservationModal');
        const passengersCount = parseInt(modal.querySelector('#passengersCount').value);
        const acceptTerms = modal.querySelector('#acceptTerms').checked;

        // ÉTAPE 2 : Vérifier que les conditions sont acceptées
        if (!acceptTerms) {
            alert('Veuillez accepter les conditions de réservation');
            return;
        }

        try {
            // ÉTAPE 3 : Envoyer la requête au serveur pour créer la réservation
            const response = await fetch('/backend/api/bookings', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.token}` // Token JWT pour l'authentification
                },
                body: JSON.stringify({
                    ride_id: rideId,
                    passengers_count: passengersCount
                })
            });

            const data = await response.json();

            // ÉTAPE 4 : Si la réservation est réussie
            if (data.success) {
                // Mettre à jour les crédits restants localement
                this.currentUser.credits = data.passenger_credits_remaining;
                localStorage.setItem('ecoride_current_user', JSON.stringify(this.currentUser));

                // Mettre à jour la barre utilisateur si elle existe
                if (pageProtection) {
                    pageProtection.currentUser.credits = data.passenger_credits_remaining;
                    pageProtection.displayUserBar();
                }

                alert('Réservation confirmée!\nVos crédits restants: ' + data.passenger_credits_remaining);
                modal.remove();

                // Recharger la page après 1 seconde
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                alert('Erreur: ' + data.message);
            }
        } catch (error) {
            console.error('Erreur réservation:', error);
            alert('Erreur lors de la réservation');
        }
    }

    /**
     * Trouver un trajet par ID
     */
    findRide(rideId) {
        // Essayer de chercher dans les trajets globaux
        if (typeof ridesData !== 'undefined' && ridesData.rides) {
            for (let ride of ridesData.rides) {
                if (ride.id == rideId) return ride;
            }
        }

        // Sinon chercher dans le DOM
        const rideCards = document.querySelectorAll('.ride-card');
        for (let card of rideCards) {
            const button = card.querySelector('.btn-reserve');
            if (button && button.getAttribute('data-ride-id') == rideId) {
                // Extraire les données du DOM
                return {
                    id: rideId,
                    departure_city: card.querySelector('.route-location')?.textContent || 'Départ',
                    arrival_city: card.querySelectorAll('.route-location')[1]?.textContent || 'Arrivée',
                    departure_datetime: card.getAttribute('data-departure') || new Date().toISOString(),
                    arrival_datetime: card.getAttribute('data-arrival') || new Date().toISOString(),
                    price_credits: parseInt(card.getAttribute('data-price') || 0),
                    seats_available: parseInt(card.getAttribute('data-seats') || 1)
                };
            }
        }

        return null;
    }

    /**
     * Ajouter les styles du modal
     */
    addModalStyles() {
        if (document.getElementById('bookingModalStyles')) return;

        const style = document.createElement('style');
        style.id = 'bookingModalStyles';
        style.textContent = `
            .modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
                animation: fadeIn 0.3s ease;
            }

            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            .modal-content {
                background: white;
                border-radius: var(--radius-lg);
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
                max-width: 500px;
                width: 90%;
                max-height: 90vh;
                overflow-y: auto;
                animation: slideUp 0.3s ease;
            }

            @keyframes slideUp {
                from { transform: translateY(20px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }

            .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1.5rem;
                border-bottom: 1px solid var(--border-light);
            }

            .modal-header h2 {
                margin: 0;
                font-size: 1.25rem;
            }

            .modal-close {
                background: none;
                border: none;
                font-size: 1.5rem;
                cursor: pointer;
                color: var(--text-secondary);
                transition: color 0.2s;
            }

            .modal-close:hover {
                color: var(--text-primary);
            }

            .modal-body {
                padding: 1.5rem;
            }

            .booking-ride-details {
                margin-bottom: 1.5rem;
            }

            .booking-ride-details h3 {
                margin-top: 0;
                font-size: 0.9rem;
                color: var(--text-secondary);
                text-transform: uppercase;
            }

            .ride-summary {
                background: var(--bg-secondary);
                padding: 1rem;
                border-radius: var(--radius-md);
            }

            .route-summary {
                font-size: 1.1rem;
                margin-bottom: 0.75rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .arrow {
                color: var(--primary-green);
                font-weight: bold;
            }

            .date-summary,
            .price-summary {
                font-size: 0.9rem;
                color: var(--text-secondary);
                margin-bottom: 0.5rem;
            }

            .price-summary {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-top: 1px solid var(--border-light);
                padding-top: 0.75rem;
                margin-top: 0.75rem;
            }

            .price-value {
                font-weight: 600;
                color: var(--primary-green);
            }

            .booking-form {
                margin-bottom: 1.5rem;
            }

            .passengers-selector {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                margin: 0.75rem 0;
            }

            .btn-qty {
                width: 40px;
                height: 40px;
                border: 1px solid var(--border-light);
                background: white;
                border-radius: var(--radius-md);
                cursor: pointer;
                font-size: 1.2rem;
                font-weight: bold;
                color: var(--primary-green);
                transition: all 0.2s;
            }

            .btn-qty:hover {
                background: var(--bg-secondary);
            }

            #passengersCount {
                flex: 1;
                text-align: center;
                font-size: 1rem;
                font-weight: 600;
            }

            .booking-summary {
                background: var(--bg-secondary);
                padding: 1rem;
                border-radius: var(--radius-md);
                margin-bottom: 1.5rem;
            }

            .summary-row {
                display: flex;
                justify-content: space-between;
                padding: 0.5rem 0;
                border-bottom: 1px solid var(--border-light);
                font-size: 0.95rem;
            }

            .summary-row:last-child {
                border-bottom: none;
            }

            .summary-total {
                font-weight: 600;
                font-size: 1.05rem;
                padding-top: 0.75rem;
                border-top: 2px solid var(--border-light);
                margin-top: 0.75rem;
                color: var(--primary-green);
            }

            .text-success {
                color: var(--success);
            }

            .text-danger {
                color: var(--danger);
            }

            .booking-terms {
                margin-bottom: 1.5rem;
            }

            .modal-footer {
                display: flex;
                gap: 1rem;
                padding: 1.5rem;
                border-top: 1px solid var(--border-light);
            }

            .modal-footer .btn {
                flex: 1;
            }

            @media (max-width: 480px) {
                .modal-content {
                    width: 95%;
                }

                .modal-body {
                    padding: 1rem;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

// Initialiser le booking manager
let bookingManager;
document.addEventListener('DOMContentLoaded', () => {
    bookingManager = new BookingManager();
});
