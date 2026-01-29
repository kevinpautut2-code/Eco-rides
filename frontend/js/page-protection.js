/**
 * EcoRide - Protection des pages
 * Vérifier l'authentification et rediriger si nécessaire
 */

class PageProtection {
    constructor() {
        this.currentUser = null;
        this.token = null;
        this.init();
    }

    init() {
        // Charger le token et l'utilisateur
        this.token = localStorage.getItem('ecoride_token');
        const userStr = localStorage.getItem('ecoride_current_user');
        
        if (userStr) {
            try {
                this.currentUser = JSON.parse(userStr);
            } catch (e) {
                console.error('Erreur parsing utilisateur:', e);
                this.currentUser = null;
            }
        }

        // Vérifier si on doit protéger cette page
        this.checkPageAccess();
        
        // Afficher la barre utilisateur si connecté
        if (this.currentUser) {
            this.displayUserBar();
        }
    }

    /**
     * Vérifier l'accès à la page actuelle
     */
    checkPageAccess() {
        const currentPage = window.location.pathname;
        
        // Pages protégées (nécessitent authentification)
        const protectedPages = [
            '/dashboard.html',
            '/admin-dashboard.html',
            '/employee-dashboard.html',
            '/my-rides.html',
            '/my-bookings.html',
            '/create-ride.html',
            '/ride-active.html'
        ];

        // Pages publiques (accessibles à tous)
        const publicPages = [
            '/',
            '/index.html',
            '/login.html',
            '/register.html',
            '/rides.html',
            '/ride-details.html',
            '/about.html',
            '/contact.html',
            '/privacy.html',
            '/terms.html',
            '/cookies.html'
        ];

        // Vérifier si on est sur une page protégée
        const isProtected = protectedPages.some(page => currentPage.includes(page));
        const isPublic = publicPages.some(page => currentPage.includes(page));

        // Si page protégée et pas connecté
        if (isProtected && !this.currentUser) {
            console.warn('Accès refusé - redirection vers login');
            window.location.href = '/login.html?redirect=' + encodeURIComponent(currentPage);
            return false;
        }

        // Si connecté et on essaie d'accéder à login/register
        if (this.currentUser && (currentPage.includes('/login.html') || currentPage.includes('/register.html'))) {
            // Rediriger vers le dashboard approprié
            this.redirectToDashboard();
            return false;
        }

        return true;
    }

    /**
     * Rediriger vers le dashboard selon le rôle
     */
    redirectToDashboard() {
        if (!this.currentUser) return;

        const role = this.currentUser.role || 'user';

        switch (role) {
            case 'admin':
                window.location.href = '/admin-dashboard.html';
                break;
            case 'employee':
                window.location.href = '/employee-dashboard.html';
                break;
            case 'user':
                // Utilisateur normal (chauffeur ou passager)
                window.location.href = '/dashboard.html';
                break;
            default:
                window.location.href = '/';
        }
    }

    /**
     * Afficher la barre utilisateur en haut
     */
    displayUserBar() {
        // Créer ou mettre à jour la barre utilisateur
        let userBar = document.getElementById('userBar');
        
        if (!userBar) {
            userBar = document.createElement('div');
            userBar.id = 'userBar';
            userBar.className = 'user-bar';
            document.body.insertBefore(userBar, document.body.firstChild);
        }

        const displayName = this.currentUser.pseudo || this.currentUser.email;
        const credits = this.currentUser.credits || 0;

        userBar.innerHTML = `
            <div class="user-bar-content">
                <div class="user-info">
                    <img src="${this.currentUser.photo_url || 'https://i.pravatar.cc/150?img=default'}" 
                         alt="${displayName}" class="user-avatar">
                    <div class="user-details">
                        <span class="user-name">${displayName}</span>
                        <span class="user-role">${this.getRoleLabel(this.currentUser.role)}</span>
                    </div>
                    <div class="user-credits">
                        <span class="credits-icon">💳</span>
                        <span class="credits-value">${credits} crédits</span>
                    </div>
                </div>
                <div class="user-actions">
                    <a href="/dashboard.html" class="btn btn-sm btn-secondary">📊 Tableau de bord</a>
                    <button onclick="pageProtection.logout()" class="btn btn-sm btn-outline">🚪 Déconnexion</button>
                </div>
            </div>
        `;

        // Ajouter les styles
        this.addUserBarStyles();
    }

    /**
     * Récupérer le label du rôle
     */
    getRoleLabel(role) {
        const labels = {
            'admin': '👑 Administrateur',
            'employee': '👔 Employé',
            'user': '👤 Utilisateur'
        };
        return labels[role] || 'Utilisateur';
    }

    /**
     * Ajouter les styles du user bar
     */
    addUserBarStyles() {
        if (document.getElementById('userBarStyles')) return;

        const style = document.createElement('style');
        style.id = 'userBarStyles';
        style.textContent = `
            .user-bar {
                background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-dark) 100%);
                color: white;
                padding: 0.75rem 1.5rem;
                border-bottom: 2px solid var(--primary-dark);
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                position: sticky;
                top: 0;
                z-index: 999;
            }

            .user-bar-content {
                max-width: 1200px;
                margin: 0 auto;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 2rem;
            }

            .user-info {
                display: flex;
                align-items: center;
                gap: 1rem;
                flex: 1;
            }

            .user-avatar {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                border: 2px solid white;
                object-fit: cover;
            }

            .user-details {
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
            }

            .user-name {
                font-weight: 600;
                font-size: 0.95rem;
            }

            .user-role {
                font-size: 0.8rem;
                opacity: 0.9;
            }

            .user-credits {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                background: rgba(255, 255, 255, 0.2);
                padding: 0.5rem 1rem;
                border-radius: 20px;
                margin-left: auto;
                font-weight: 600;
            }

            .user-actions {
                display: flex;
                gap: 0.75rem;
            }

            .btn-sm {
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
            }

            /* Responsive */
            @media (max-width: 768px) {
                .user-bar-content {
                    flex-direction: column;
                    gap: 1rem;
                    align-items: flex-start;
                }

                .user-credits {
                    margin-left: 0;
                }

                .user-actions {
                    width: 100%;
                    flex-direction: column;
                }

                .user-actions .btn {
                    width: 100%;
                }
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Déconnexion
     */
    logout() {
        if (confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
            localStorage.removeItem('ecoride_token');
            localStorage.removeItem('ecoride_current_user');
            window.location.href = '/login.html';
        }
    }

    /**
     * Vérifier si l'utilisateur a un rôle spécifique
     */
    hasRole(role) {
        return this.currentUser && this.currentUser.role === role;
    }

    /**
     * Vérifier si l'utilisateur est connecté
     */
    isLoggedIn() {
        return !!this.currentUser && !!this.token;
    }

    /**
     * Obtenir l'utilisateur actuel
     */
    getCurrentUser() {
        return this.currentUser;
    }
}

// Initialiser la protection des pages au chargement du document
let pageProtection;
document.addEventListener('DOMContentLoaded', () => {
    pageProtection = new PageProtection();
});
