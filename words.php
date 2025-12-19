<?php
// words.php

// Tableau de paires de mots [mot commun, mot undercover]
$wordPairs = [
    ["Pizza", "Calzone"],
    ["Hamburger", "Cheeseburger"],
    ["Kebab", "Tacos"],
    ["Raclette", "Fondue"],
    ["Pâtes", "Riz"],
    ["Coca-Cola", "Pepsi"],
    ["Bière", "Vin"],
    ["Vodka", "Rhum"],
    ["Mojito", "Caïpirinha"],
    ["Croissant", "Pain au chocolat"],

    ["Chat", "Chien"],
    ["Lion", "Tigre"],
    ["Dauphin", "Requin"],
    ["Panda", "Koala"],
    ["Cheval", "Âne"],
    ["Poulet", "Dinde"],
    ["Canard", "Oie"],
    ["Vache", "Taureau"],
    ["Singe", "Gorille"],
    ["Renard", "Loup"],

    ["Plage", "Piscine"],
    ["Montagne", "Campagne"],
    ["Forêt", "Jungle"],
    ["Désert", "Savane"],
    ["Ville", "Village"],
    ["Hôtel", "Auberge"],
    ["Camping", "Glamping"],
    ["Mer", "Océan"],
    ["Lac", "Étang"],
    ["Île", "Archipel"],

    ["Voiture", "Moto"],
    ["Vélo", "Trottinette"],
    ["Bus", "Tram"],
    ["Train", "Métro"],
    ["Avion", "Hélicoptère"],
    ["Bateau", "Yacht"],
    ["Taxi", "VTC"],
    ["Fusée", "Navette spatiale"],
    ["Camion", "Fourgon"],
    ["Scooter", "Mob"],

    ["Netflix", "Prime Video"],
    ["YouTube", "TikTok"],
    ["Instagram", "Snapchat"],
    ["Facebook", "LinkedIn"],
    ["Twitter", "Threads"],
    ["Spotify", "Deezer"],
    ["PlayStation", "Xbox"],
    ["Nintendo", "Sega"],
    ["iPhone", "Android"],
    ["Windows", "MacOS"],

    ["Harry Potter", "Le Seigneur des Anneaux"],
    ["Star Wars", "Star Trek"],
    ["Marvel", "DC"],
    ["Batman", "Spiderman"],
    ["Iron Man", "Captain America"],
    ["Gandalf", "Dumbledore"],
    ["Homer Simpson", "Peter Griffin"],
    ["Rick Sanchez", "Doctor Who"],
    ["Shrek", "Kung Fu Panda"],
    ["Titanic", "Avatar"],

    ["École", "Université"],
    ["Professeur", "Instituteur"],
    ["Examen", "Contrôle"],
    ["Devoirs", "Révisions"],
    ["Récréation", "Pause"],
    ["Stage", "Alternance"],
    ["Bureau", "Open space"],
    ["Réunion", "Conférence"],
    ["Patron", "Manager"],
    ["Télétravail", "Présentiel"]
];

// Fonction pour choisir une paire aléatoire
function getRandomWordPair() {
    global $wordPairs;
    return $wordPairs[array_rand($wordPairs)];
}
