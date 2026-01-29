/**
 * ========================================
 * GESTION DE LA RECHERCHE AVEC AUTOCOMPLETE
 * ========================================
 * Classe pour gérer les listes déroulantes intelligentes
 * Affiche les suggestions de villes au fur et à mesure de la saisie
 */

class RideSearchManager {
    constructor() {
        this.rides = [];               // Tous les trajets disponibles
        this.uniqueCities = [];        // Liste des villes uniques extraites
        this.init();
    }

    async init() {
        // ÉTAPE 1 : Charger la liste de tous les trajets
        await this.loadRides();
        
        // ÉTAPE 2 : Configurer l'autocomplete sur les champs de recherche
        this.setupAutocomplete();
    }

    async loadRides() {
        try {
            // Charger les trajets depuis le fichier JSON
            const response = await fetch('/data/rides.json');
            const data = await response.json();
            this.rides = data.rides || [];
            
            // Extraire les villes uniques pour l'autocomplete
            this.extractUniqueCities();
        } catch (error) {
            console.error('Erreur lors du chargement des trajets:', error);
        }
    }

    /**
     * Extraire toutes les villes uniques des trajets
     * Crée un Set pour éviter les doublons
     */
    extractUniqueCities() {
        const cities = new Set();
        // Parcourir tous les trajets et ajouter les villes uniques
        this.rides.forEach(ride => {
            if (ride.departure_city) cities.add(ride.departure_city);
            if (ride.arrival_city) cities.add(ride.arrival_city);
        });
        // Convertir en tableau et trier alphabétiquement
        this.uniqueCities = Array.from(cities).sort();
    }

    /**
     * Configurer l'autocomplete sur les deux champs (départ/arrivée)
     */
    setupAutocomplete() {
        const departureInput = document.getElementById('departure');
        const arrivalInput = document.getElementById('arrival');

        // Configurer l'autocomplete pour le champ "Départ"
        if (departureInput) {
            this.setupInputAutocomplete(departureInput, 'departure');
        }

        // Configurer l'autocomplete pour le champ "Arrivée"
        if (arrivalInput) {
            this.setupInputAutocomplete(arrivalInput, 'arrival');
        }
    }

    /**
     * Configurer l'autocomplete pour un champ spécifique
     * @param {Element} input - L'élément input HTML
     * @param {String} type - Le type de champ (departure ou arrival)
     */
    setupInputAutocomplete(input, type) {
        // ÉTAPE 1 : Créer le conteneur pour les suggestions
        const container = input.parentElement;
        const suggestionsList = document.createElement('ul');
        suggestionsList.className = 'autocomplete-suggestions';
        suggestionsList.style.display = 'none';
        container.appendChild(suggestionsList);

        // ÉTAPE 2 : Ajouter l'écouteur d'événement sur chaque caractère saisi
        input.addEventListener('input', (e) => {
            const value = e.target.value.trim().toLowerCase();
            suggestionsList.innerHTML = ''; // Effacer les suggestions précédentes

            // Si rien n'est saisi, masquer les suggestions
            if (value.length === 0) {
                suggestionsList.style.display = 'none';
                return;
            }

            // ÉTAPE 3 : Filtrer les villes qui correspondent à la saisie
            const filtered = this.uniqueCities.filter(city => 
                city.toLowerCase().includes(value)
            );

            // Si aucune ville ne correspond, masquer la liste
            if (filtered.length === 0) {
                suggestionsList.style.display = 'none';
                return;
            }

            // Afficher les suggestions
            filtered.forEach(city => {
                const li = document.createElement('li');
                li.className = 'autocomplete-item';
                li.textContent = city;
                li.addEventListener('click', () => {
                    input.value = city;
                    suggestionsList.style.display = 'none';
                });
                suggestionsList.appendChild(li);
            });

            suggestionsList.style.display = 'block';
        });

        // Fermer la liste quand on clique ailleurs
        document.addEventListener('click', (e) => {
            if (e.target !== input) {
                suggestionsList.style.display = 'none';
            }
        });
    }

    /**
     * Obtenir les trajets filtrés
     */
    getFilteredRides(filters) {
        return this.rides.filter(ride => {
            // Filtre ville départ
            if (filters.departure && ride.departure_city.toLowerCase() !== filters.departure.toLowerCase()) {
                return false;
            }

            // Filtre ville arrivée
            if (filters.arrival && ride.arrival_city.toLowerCase() !== filters.arrival.toLowerCase()) {
                return false;
            }

            // Filtre date
            if (filters.date) {
                const rideDate = new Date(ride.departure_datetime).toISOString().split('T')[0];
                if (rideDate !== filters.date) {
                    return false;
                }
            }

            return true;
        });
    }
}

// Initialiser le gestionnaire de recherche
document.addEventListener('DOMContentLoaded', () => {
    window.rideSearchManager = new RideSearchManager();
});
