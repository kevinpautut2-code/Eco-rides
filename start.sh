#!/bin/bash

# ============================================
# EcoRide - Script de démarrage rapide
# macOS avec PHP server
# ============================================

echo "🚀 Démarrage d'ECO-ride..."
echo ""

# Vérifier que nous sommes dans le bon répertoire
if [ ! -f "frontend/index.html" ]; then
    echo "❌ Erreur: Veuillez exécuter ce script depuis la racine du projet ECO-ride"
    exit 1
fi

# Vérifier que PHP est installé
if ! command -v php &> /dev/null; then
    echo "❌ Erreur: PHP n'est pas installé. Veuillez installer PHP d'abord."
    exit 1
fi

echo "✅ PHP trouvé: $(php --version | head -n 1)"
echo ""

# Lancer le serveur PHP
echo "🌐 Lancement du serveur PHP sur http://localhost:8000..."
echo ""

php -S localhost:8000 -t frontend/

# Le script s'arrête quand le serveur s'arrête
echo ""
echo "🛑 Serveur arrêté."
