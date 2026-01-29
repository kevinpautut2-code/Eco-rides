# 📋 Données de Connexion - ECO-ride

## ⚙️ Configuration Actuelle

Le projet utilise actuellement des **données JSON** en mémoire pour le développement (en attendant la configuration MySQL complète).

---

## 👥 Comptes de Test Disponibles

### 👮 **ADMINISTRATEUR**
```
Email: admin@ecoride.fr
Mot de passe: Test@2025!
Pseudo: admin
Crédits: 1000
Rôle: Administrateur
```

### 👔 **EMPLOYÉS**
```
Email: employe@ecoride.fr
Mot de passe: Test@2025!
Pseudo: employe1
Crédits: 100
Rôle: Employé

OU

Email: sophie.martin@ecoride.fr
Mot de passe: Test@2025!
Pseudo: employe2
Crédits: 100
Rôle: Employé
```

### 🚗 **CHAUFFEURS**
```
Email: chauffeur@ecoride.fr
Mot de passe: Test@2025!
Pseudo: chauffeur
Crédits: 150
Rôle: Utilisateur (Chauffeur)
Véhicule: Tesla Model 3 (Électrique)

OU

Email: marie.dupont@email.fr
Mot de passe: Test@2025!
Pseudo: marie_eco
Crédits: 200
Rôle: Utilisateur (Chauffeur)
Véhicule: Renault Zoe (Électrique)

OU

Email: thomas.bernard@email.fr
Mot de passe: Test@2025!
Pseudo: thomas_green
Crédits: 180
Rôle: Utilisateur (Chauffeur)
Véhicule: Nissan Leaf (Électrique)

OU

Email: julie.petit@email.fr
Mot de passe: Test@2025!
Pseudo: julie_planet
Crédits: 220
Rôle: Utilisateur (Chauffeur + Passager)
Véhicule: BMW i3 (Électrique)

OU

Email: lucas.robert@email.fr
Mot de passe: Test@2025!
Pseudo: lucas_drive
Crédits: 190
Rôle: Utilisateur (Chauffeur + Passager)
Véhicule: Hyundai Kona (Électrique)

OU

Email: emma.richard@email.fr
Mot de passe: Test@2025!
Pseudo: emma_green
Crédits: 160
Rôle: Utilisateur (Chauffeur)
Véhicule: Audi e-tron (Électrique)
```

### 👤 **PASSAGERS**
```
Email: passager@ecoride.fr
Mot de passe: Test@2025!
Pseudo: passager
Crédits: 50
Rôle: Utilisateur (Passager)

OU

Email: pierre.durand@email.fr
Mot de passe: Test@2025!
Pseudo: pierre_voyage
Crédits: 80
Rôle: Utilisateur (Passager)

OU

Email: sarah.moreau@email.fr
Mot de passe: Test@2025!
Pseudo: sarah_travel
Crédits: 60
Rôle: Utilisateur (Passager)

OU

Email: alex.simon@email.fr
Mot de passe: Test@2025!
Pseudo: alex_road
Crédits: 90
Rôle: Utilisateur (Passager)

OU

Email: nadia.laurent@email.fr
Mot de passe: Test@2025!
Pseudo: nadia_eco
Crédits: 70
Rôle: Utilisateur (Passager)
```

---

## 🚗 Trajets Disponibles (10 trajets de test)

### ✅ Trajets Écologiques (Électrique)
1. **Paris → Lyon** - Tesla Model 3 - 45 crédits - ⭐ 4.8/5
2. **Paris → Marseille** - Renault Zoe - 65 crédits - ⭐ 4.9/5
3. **Lyon → Nice** - Nissan Leaf - 50 crédits - ⭐ 4.7/5
4. **Paris → Bordeaux** - BMW i3 - 55 crédits - ⭐ 4.6/5
5. **Marseille → Toulouse** - Hyundai Kona - 42 crédits - ⭐ 4.8/5
6. **Paris → Lille** - Audi e-tron - 35 crédits - ⭐ 4.9/5
7. **Toulouse → Montpellier** - Tesla Model 3 - 40 crédits - ⭐ 4.8/5
8. **Lille → Amiens** - Nissan Leaf - 25 crédits - ⭐ 4.7/5

### ⚠️ Trajets Non-Écologiques
9. **Lyon → Strasbourg** - Peugeot 208 (Essence) - 48 crédits - ⭐ 4.5/5
10. **Bordeaux → Nantes** - VW Golf (Diesel) - 38 crédits - ⭐ 4.4/5

---

## 🧪 Comment Tester

### 1️⃣ Afficher les Covoiturages
Allez sur: **http://localhost:8000/rides.html**

Les 10 trajets s'affichent automatiquement avec les filtres :
- 🟢 Filtrer par véhicule écologique
- 💰 Filtrer par prix maximum
- ⭐ Filtrer par note minimale
- ⏱️ Filtrer par durée

### 2️⃣ Testez les Recherches
- **Paris** → Voir les trajets au départ de Paris
- **Lyon** → Voir les trajets au départ de Lyon
- **Marseille** → Voir les trajets au départ de Marseille

### 3️⃣ Page de Connexion
Allez sur: **http://localhost:8000/login.html**

Utilisez un des comptes ci-dessus pour vous connecter.

---

## 📊 Fichiers de Données

Les données sont stockées en JSON :
- `frontend/data/rides.json` - Tous les trajets
- `frontend/data/users.json` - Tous les utilisateurs

---

## ⚡ Prochaines Étapes pour MySQL

Une fois MySQL configuré, les données seront importées automatiquement :

```bash
# Créer la base de données et les tables
mysql -u root < database/sql/create_database.sql

# Importer les données de test
mysql -u root ecoride < database/sql/seed_data.sql
```

Jusqu'à ce moment, vous pouvez tester l'interface avec les données JSON ! 🎉
