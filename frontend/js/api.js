/**
 * EcoRide - Module API
 * Gestion des appels à l'API backend
 */

// Base URL - Pointe vers le serveur PHP en développement
const API_BASE_URL = 'http://localhost:8000/backend/api'; 

class APIClient {
    constructor() {
        this.baseURL = API_BASE_URL;
        this.token = localStorage.getItem('ecoride_token') || null;
    }

    /**
     * Effectuer une requête HTTP
     * @param {string} endpoint - Doit être une route RESTful simple (ex: /rides)
     * @param {object} options
     * @returns {Promise}
     */
    async request(endpoint, options = {}) {
        // L'URL complète est maintenant http://localhost:8888/rides
        const url = `${this.baseURL}${endpoint}`; 

        const config = {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                // ... (Auth headers)
                ...(this.token && { 'Authorization': `Bearer ${this.token}` }),
                ...options.headers,
            },
        };

        if (options.body && typeof options.body !== 'string') {
            config.body = JSON.stringify(options.body);
        }

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            if (!response.ok) {
                // Utiliser le message d'erreur du backend s'il existe
                throw new Error(data.error || data.message || `Erreur API: ${response.status}`);
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            // Gérer les erreurs de connexion (ex: CORS, Serveur non disponible)
            throw new Error(`Erreur de communication avec l'API: ${error.message}`);
        }
    }

    // === AUTH ===

    /**
     * Connexion utilisateur
     */
    async login(email, password) {
        // CORRECTION 2: Utilisation de la route RESTful /auth/login
        const data = await this.request('/auth/login', { 
            method: 'POST',
            body: { email, password }
        });

        if (data.success && data.token) {
            this.token = data.token;
            localStorage.setItem('ecoride_token', data.token);
        }

        return data;
    }

    /**
     * Inscription utilisateur
     */
    async register(userData) {
        // CORRECTION 3: Utilisation de la route RESTful /auth/register
        const data = await this.request('/auth/register', { 
            method: 'POST',
            body: userData
        });

        return data;
    }

    // Déconnexion... (pas de changement)

    // === RIDES ===

    /**
     * Rechercher des trajets
     * @param {object} filters - Filtres de recherche
     */
    async searchRides(filters = {}) {
        const params = new URLSearchParams();

        if (filters.departure_city) params.append('departure_city', filters.departure_city); // Correction du nom du param
        if (filters.arrival_city) params.append('arrival_city', filters.arrival_city); // Correction du nom du param
        if (filters.date) params.append('date', filters.date);

        // ... (autres filtres)

        const query = params.toString() ? `?${params.toString()}` : '';

        // CORRECTION 4: L'endpoint est simple et RESTful (/rides)
        const endpoint = `/rides${query}`; 
        
        const data = await this.request(endpoint);

        return data;
    }

    /**
     * Obtenir les détails d'un trajet
     * @param {number} rideId
     */
    async getRideDetails(rideId) {
        // CORRECTION 5: L'endpoint est simple et RESTful (/rides/{id})
        const data = await this.request(`/rides/${rideId}`);
        return data;
    }

    /**
     * Créer un nouveau trajet
     * @param {object} rideData
     */
    async createRide(rideData) {
        // CORRECTION 6: L'endpoint est simple et RESTful (/rides)
        const data = await this.request('/rides', {
            method: 'POST',
            body: rideData
        });

        return data;
    }
    
    // ... (Autres fonctions de l'API sont uniformisées ci-dessous)

    // === USERS ===

    /**
     * Obtenir le profil d'un utilisateur
     * @param {number} userId
     */
    async getUserProfile(userId) {
        // CORRECTION 7: L'endpoint est simple et RESTful (/users/{id})
        const data = await this.request(`/users/${userId}`); 
        return data;
    }

    /**
     * Obtenir les trajets créés par l'utilisateur
     */
    async getUserRides(userId) {
        // CORRECTION 8: L'endpoint est simple et RESTful (/users/{id}/rides)
        const data = await this.request(`/users/${userId}/rides`);
        return data;
    }

    /**
     * Obtenir les réservations de l'utilisateur
     */
    async getUserBookings(userId) {
        // CORRECTION 9: L'endpoint est simple et RESTful (/users/{id}/bookings)
        const data = await this.request(`/users/${userId}/bookings`);
        return data;
    }
    
    // === VEHICLES ===

    /**
     * Obtenir la liste des véhicules
     * @param {number} userId - Optionnel, pour filtrer par utilisateur
     */
    async getVehicles(userId = null) {
        const query = userId ? `?user_id=${userId}` : '';
        // CORRECTION 10: L'endpoint est simple et RESTful (/vehicles)
        const data = await this.request(`/vehicles${query}`);
        return data;
    }

    // === BOOKINGS & ACTIONS ===
    
    async bookRide(rideId, userId) {
        // CORRECTION 11: L'endpoint est simple et RESTful (/rides/{id}/book)
        const data = await this.request(`/rides/${rideId}/book`, {
            method: 'POST',
            body: { user_id: userId }
        });
        return data;
    }

    async cancelRide(rideId) {
        // CORRECTION 12: L'endpoint est simple et RESTful (DELETE /rides/{id})
        const data = await this.request(`/rides/${rideId}`, {
            method: 'DELETE'
        });
        return data;
    }

    async cancelBooking(bookingId) {
        // CORRECTION 13: L'endpoint est simple et RESTful (DELETE /bookings/{id})
        const data = await this.request(`/bookings/${bookingId}`, {
            method: 'DELETE'
        });
        return data;
    }
}

// Créer une instance globale
window.apiClient = new APIClient();