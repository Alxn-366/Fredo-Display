<?php
// Startet die PHP-Sitzung, um Benutzerdaten (z. B. user_id, name) zu speichern und zu verwalten
session_start();
// Prüft, ob der Benutzer eingeloggt ist (user_id in der Sitzung vorhanden)
// Wenn nicht, wird der Benutzer zur Login-Seite (login.php) weitergeleitet
if (!isset($_SESSION['user_id'])) header('Location: login.php');
?>
<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <title>Barcode Scanner</title>
    <!-- Lädt Bootstrap CSS für das responsive Design und Styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Lädt eine benutzerdefinierte CSS-Datei für zusätzliche Stile -->
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        /* Styling für den Scanner-Container */
        #scanner {
            position: relative;
            width: 100%;
            height: 400px;
            background: #000;
            /* Schwarzer Hintergrund für die Kameraansicht */
            overflow: hidden;
            /* Verhindert, dass Inhalte über die Containergrenzen hinausgehen */
            border-radius: 10px;
            /* Abgerundete Ecken für ein modernes Aussehen */
        }

        /* Styling für den grünen Rahmen, der den Scan-Bereich markiert */
        .scanner-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 250px;
            height: 150px;
            border: 3px solid limegreen;
            /* Grüne Umrandung für den Scan-Bereich */
            border-radius: 8px;
            transform: translate(-50%, -50%);
            /* Zentriert den Rahmen im Container */
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.5);
            /* Leichter Schatten für visuelle Tiefe */
            pointer-events: none;
            /* Verhindert, dass der Rahmen Klicks blockiert */
        }

        /* Styling für die Maske, die den Bereich außerhalb des Scan-Rahmens abdunkelt */
        .scanner-mask {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            /* Halbtransparenter schwarzer Hintergrund */
        }

        /* Erstellt eine transparente Aussparung für den Scan-Bereich */
        .scanner-mask:before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 250px;
            height: 150px;
            background: transparent;
            transform: translate(-50%, -50%);
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.4);
            /* Abdunklung außerhalb des Scan-Fensters */
            pointer-events: none;
        }
    </style>
</head>

<body>
    <!-- Floating Background (vermutlich dekorative Animationen) -->
    <div class="floating-bg">
        <div></div>
        <div></div>
        <div></div>
        <div></div>
    </div>

    <!-- Navigationsleiste -->
    <nav class="navbar">
        <div class="container-fluid">
            <!-- Link zur Startseite -->
            <a class="navbar-brand" href="index.php">Fredo<span>.</span></a>
            <!-- Begrüßt den Benutzer mit seinem Namen (aus der Sitzung) -->
            <a class="navbar-brand" href="#">Hallo, <?= htmlspecialchars($_SESSION['name']) ?></a>
            <div class="d-flex">
                <div class="d-flex">
                    <!-- Button zum Dashboard -->
                    <a class="btn btn-outline btn-sm" href="index.php">Dashboard</a>
                    <!-- Button zum Ausloggen -->
                    <a class="btn btn-outline btn-sm" href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hauptinhalt -->
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <!-- Card für den Scanner -->
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h4 class="text-center mb-4">Barcode Scanner</h4>
                        <p class="text-center">Erlaube Kamera und richte das Barcode im grünen Rahmen aus.</p>

                        <!-- Container für die Kamera -->
                        <div id="scanner">
                            <div class="scanner-mask"></div>
                            <div class="scanner-overlay"></div>
                        </div>

                        <!-- Eingabefeld für den gescannten Barcode und Button -->
                        <div class="mt-3">
                            <!-- Anzeige des gescannten Barcodes, readonly verhindert manuelle Eingaben -->
                            <input id="scanned" class="form-control mb-3" placeholder="Gescanntes Barcode" readonly>
                            <!-- Button zum manuellen Hinzufügen eines Lebensmittels -->
                            <a id="toAdd" class="btn btn-success w-100" href="add_food.php">Als neues Lebensmittel hinzufügen</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lädt die QuaggaJS-Bibliothek für die Barcode-Erkennung -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
    <script>
        // DOM-Elemente für das Eingabefeld und den Button
        const resultField = document.getElementById('scanned');
        const toAdd = document.getElementById('toAdd');

        // Variablen zur Vermeidung von Mehrfachscans
        let lastScanned = null; // Speichert den zuletzt gescannten Barcode
        let scanCooldown = false; // Verhindert Mehrfachauslösungen während eines Cooldowns

        // Initialisiert QuaggaJS
        Quagga.init({
            inputStream: {
                name: "Live",
                type: "LiveStream",
                target: document.querySelector('#scanner'), // Kamera-Output im #scanner-Div
                constraints: {
                    facingMode: "environment" // Verwendet die Kamera des Geräts
                }
            },
            decoder: {
                readers: ["ean_reader", "code_128_reader", "upc_reader"] // Unterstützte Barcode-Formate
            }
        }, function(err) {
            // Fehlerbehandlung bei der Initialisierung
            if (err) {
                console.error(err);
                return;
            }
            // Startet die Kamera
            Quagga.start();
        });

        // Event-Handler für erkannte Barcodes
        Quagga.onDetected(function(data) {
            const code = data.codeResult.code; // identifiziert den gescannten Barcode

            // Verhindert Mehrfachverarbeitung des gleichen Barcodes oder während des Cooldowns
            if (scanCooldown || code === lastScanned) return;

            // Setzt den Cooldown und speichert den Barcode
            lastScanned = code;
            scanCooldown = true;

            // Zeigt den Barcode im Eingabefeld an
            resultField.value = code;

            // Fügt das Produkt automatisch zum Kühlschrank hinzu
            addToFridge(code);

            // Aktualisiert den Link für das manuelle Hinzufügen
            toAdd.href = 'add_food.php?barcode=' + encodeURIComponent(code);

            // Hebt den Cooldown nach 2 Sekunden auf
            setTimeout(() => {
                scanCooldown = false;
                lastScanned = null; // Erlaubt erneutes Scannen desselben Barcodes
            }, 2000);
        });

        // Funktion zum Hinzufügen des Barcodes zum Kühlschrank
        function addToFridge(barcode) {
            // Sendet eine POST-Anfrage an add_to_fridge.php
            fetch('add_to_fridge.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        barcode: barcode // Sendet den Barcode als JSON
                    })
                })
                .then(response => response.json()) // Parst die JSON-Antwort
                .then(data => {
                    // Erfolgsfall: Produkt wurde hinzugefügt
                    if (data.status === 'success') {
                        alert(data.message);
                        // Leitet nach 1,5 Sekunden zum Dashboard weiter
                        setTimeout(() => {
                            window.location.href = 'index.php';
                        }, 1500);
                        // Produkt nicht gefunden: Bietet manuelles Hinzufügen an
                    } else if (data.status === 'not_found') {
                        if (confirm('Produkt nicht gefunden. Möchtest du es manuell hinzufügen?')) {
                            window.location.href = 'add_food.php?barcode=' + encodeURIComponent(barcode);
                        }
                        // Fehlerbehandlung
                    } else {
                        alert('Fehler: ' + data.message);
                    }
                })
                .catch(error => {
                    // Netzwerk- oder Serverfehler
                    console.error('Error:', error);
                    alert('Ein Fehler ist aufgetreten.');
                });
        }

        // Event-Listener für den Button zum manuellen Hinzufügen
        toAdd.addEventListener('click', function(e) {
            const barcode = resultField.value;
            // Verhindert Klick, wenn kein Barcode gescannt wurde
            if (!barcode) {
                e.preventDefault();
                alert('Bitte erst einen Barcode scannen!');
            }
        });
    </script>
</body>

</html>