<?php
// Startet die PHP-Session, um Benutzerdaten wie user_id und name zu speichern und über Seiten hinweg verfügbar zu machen
session_start();

// Lädt die Datenbankverbindung aus der Datei db.php im Verzeichnis src
require_once __DIR__ . '/../src/db.php';

// Prüft, ob der Benutzer eingeloggt ist, indem die Existenz von 'user_id' in der Session geprüft wird
// Falls nicht eingeloggt, wird der Benutzer zur Login-Seite weitergeleitet und das Skript beendet
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Konvertiert die Benutzer-ID aus der Session in einen Integer und speichert sie in $uid
$uid = (int)$_SESSION['user_id'];

// Prüft, ob eine Erfolgsmeldung für das Entsorgen eines Lebensmittels in der Session existiert
if (isset($_SESSION['discard_success'])) {
    // Gibt eine Bootstrap-Erfolgsmeldung aus, die die Nachricht aus der Session anzeigt
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            ' . $_SESSION['discard_success'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    // Entfernt die Erfolgsmeldung aus der Session, um sie nur einmal anzuzeigen
    unset($_SESSION['discard_success']);
}

// Prüft, ob eine Erfolgsmeldung für das Scannen eines Lebensmittels in der Session existiert
if (isset($_SESSION['scan_success'])) {
    // Gibt eine Bootstrap-Erfolgsmeldung aus, die die Nachricht aus der Session anzeigt
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            ' . $_SESSION['scan_success'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    // Entfernt die Erfolgsmeldung aus der Session
    unset($_SESSION['scan_success']);
}

// Verarbeitet Sprachbefehle, wenn eine POST-Anfrage mit dem Schlüssel 'voice_command' gesendet wird
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['voice_command'])) {
    // Entfernt führende und nachfolgende Leerzeichen und konvertiert den Sprachbefehl zu Kleinbuchstaben
    $voice_command = trim(strtolower($_POST['voice_command']));
    // Initialisiert ein Array für die Antwort, das Erfolg/Misserfolg und eine Nachricht enthält
    $response = ['success' => false, 'message' => ''];

    // Gibt den empfangenen Sprachbefehl zur Fehlersuche in die Error-Logs aus
    error_log("Voice Command Received: " . $voice_command);

    // Prüft, ob der Sprachbefehl nicht leer ist
    if (!empty($voice_command)) {
        // Entfernt die Aktivierungsphrase "hey fredo" aus dem Befehl und bereinigt ihn
        $voice_command = str_replace('hey fredo', '', $voice_command);
        $voice_command = trim($voice_command);

        // Gibt den bereinigten Sprachbefehl zur Fehlersuche in die Error-Logs aus
        error_log("Cleaned Command: " . $voice_command);

        // Verarbeitet Sprachbefehle zum Hinzufügen von Lebensmitteln mit einem regulären Ausdruck
        if (preg_match('/(füge|hinzufügen|addiere|nehme)\s+(\d+)\s*(\w+)\s+(.+)/', $voice_command, $matches)) {
            // Prüft, ob der reguläre Ausdruck mindestens 5 Gruppen (Befehl, Menge, Einheit, Name) erkannt hat
            if (count($matches) >= 5) {
                $quantity = (float)$matches[2]; // Konvertiert die Menge in eine Gleitkommazahl
                $unit = $matches[3]; // Speichert die Einheit (z.B. Gramm)
                $name = ucfirst(trim($matches[4])); // Macht den ersten Buchstaben des Namens groß und entfernt Leerzeichen

                // Bereitet eine SQL-Abfrage vor, um das Lebensmittel in die Datenbank einzufügen
                $stmt = $pdo->prepare("INSERT INTO foods (user_id, name, quantity, unit, category, added_at) VALUES (?, ?, ?, ?, 'Spracheingabe', NOW())");
                // Führt die Abfrage aus und speichert das Ergebnis
                if ($stmt->execute([$uid, $name, $quantity, $unit])) {
                    $response = ['success' => true, 'message' => "$quantity $unit $name hinzugefügt"];
                    $_SESSION['voice_success'] = $response['message']; // Speichert die Erfolgsmeldung in der Session
                } else {
                    $response['message'] = 'Fehler beim Hinzufügen'; // Setzt eine Fehlermeldung bei Misserfolg
                }
            } else {
                $response['message'] = 'Bitte formatieren: "Füge 500 Gramm Mehl hinzu"'; // Fehlermeldung bei falschem Format
            }
            // Verarbeitet Sprachbefehle zum Löschen von Lebensmitteln
        } elseif (preg_match('/(lösche|entferne|delete|weg)\s+(.+)/', $voice_command, $matches)) {
            // Prüft, ob der reguläre Ausdruck mindestens 3 Gruppen (Befehl, Name) erkannt hat
            if (count($matches) >= 3) {
                $name = trim($matches[2]); // Speichert den Namen des zu löschenden Lebensmittels

                // Bereitet eine SQL-Abfrage vor, um das Lebensmittel aus der Datenbank zu löschen
                $stmt = $pdo->prepare("DELETE FROM foods WHERE user_id = ? AND LOWER(name) LIKE ? LIMIT 1");
                // Führt die Abfrage aus
                if ($stmt->execute([$uid, '%' . strtolower($name) . '%'])) {
                    // Prüft, ob eine Zeile gelöscht wurde
                    if ($stmt->rowCount() > 0) {
                        $response = ['success' => true, 'message' => "$name gelöscht"];
                        $_SESSION['voice_success'] = $response['message']; // Speichert die Erfolgsmeldung in der Session
                    } else {
                        $response['message'] = "$name nicht gefunden"; // Fehlermeldung, wenn das Lebensmittel nicht existiert
                    }
                } else {
                    $response['message'] = 'Fehler beim Löschen'; // Fehlermeldung bei Misserfolg
                }
            } else {
                $response['message'] = 'Bitte formatieren: "Lösche Mehl"'; // Fehlermeldung bei falschem Format
            }
            // Verarbeitet Sprachbefehle zum Bearbeiten von Lebensmitteln
        } elseif (preg_match('/(bearbeite|ändere|verändere|änder)\s+(\d+)\s*(\w+)\s+(.+)/', $voice_command, $matches)) {
            // Prüft, ob der reguläre Ausdruck mindestens 5 Gruppen (Befehl, Menge, Einheit, Name) erkannt hat
            if (count($matches) >= 5) {
                $quantity = (float)$matches[2]; // Neue Menge als Gleitkommazahl
                $unit = $matches[3]; // Neue Einheit
                $name = trim($matches[4]); // Name des Lebensmittels

                // Bereitet eine SQL-Abfrage vor, um das Lebensmittel zu aktualisieren
                $stmt = $pdo->prepare("UPDATE foods SET quantity = ?, unit = ? WHERE user_id = ? AND LOWER(name) LIKE ? LIMIT 1");
                // Führt die Abfrage aus
                if ($stmt->execute([$quantity, $unit, $uid, '%' . strtolower($name) . '%'])) {
                    // Prüft, ob eine Zeile aktualisiert wurde
                    if ($stmt->rowCount() > 0) {
                        $response = ['success' => true, 'message' => "$name auf $quantity $unit aktualisiert"];
                        $_SESSION['voice_success'] = $response['message']; // Speichert die Erfolgsmeldung in der Session
                    } else {
                        $response['message'] = "$name nicht gefunden"; // Fehlermeldung, wenn das Lebensmittel nicht existiert
                    }
                } else {
                    $response['message'] = 'Fehler beim Aktualisieren'; // Fehlermeldung bei Misserfolg
                }
            } else {
                $response['message'] = 'Bitte formatieren: "Bearbeite Mehl auf 300 Gramm"'; // Fehlermeldung bei falschem Format
            }
            // Verarbeitet Sprachbefehle zum Anzeigen von Rezepten mit einer bestimmten Zutat
        } elseif (preg_match('/(zeige|zeig|suche|finde)\s+(rezepte|rezept).*\s+(\w+)/i', $voice_command, $matches)) {
            // Prüft, ob der reguläre Ausdruck mindestens 4 Gruppen (Befehl, Rezept, Zutat) erkannt hat
            if (count($matches) >= 4) {
                $ingredient = trim($matches[3]); // Speichert die Zutat für die Rezeptsuche
                $response = ['success' => true, 'message' => "Zeige Rezepte mit $ingredient", 'redirect' => "recipe_match.php?search=" . urlencode($ingredient)];
                $_SESSION['voice_success'] = $response['message']; // Speichert die Erfolgsmeldung in der Session
            } else {
                // Falls keine spezifische Zutat angegeben wurde, alle Rezepte anzeigen
                $response = ['success' => true, 'message' => "Zeige alle Rezepte", 'redirect' => "recipes.php"];
                $_SESSION['voice_success'] = $response['message'];
            }
            // Verarbeitet allgemeine Rezeptanfragen ohne spezifische Zutat
        } elseif (preg_match('/(rezepte|rezept)/i', $voice_command)) {
            $response = ['success' => true, 'message' => "Zeige alle Rezepte", 'redirect' => "recipes.php"];
            $_SESSION['voice_success'] = $response['message']; // Speichert die Erfolgsmeldung in der Session
        } else {
            // Fehlermeldung für unbekannte Sprachbefehle
            $response['message'] = 'Unbekannter Befehl. Verfügbare Befehle: füge, lösche, bearbeite, zeige rezepte';
            error_log("Unbekannter Befehl: " . $voice_command); // Loggt den unbekannten Befehl
        }
    } else {
        $response['message'] = 'Leerer Befehl empfangen'; // Fehlermeldung bei leerem Sprachbefehl
    }

    // Gibt die Serverantwort zur Fehlersuche in die Error-Logs aus
    error_log("Server Response: " . json_encode($response));

    // Gibt die Antwort als JSON zurück, wenn die Anfrage per AJAX erfolgt
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // Speichert die Sprachantwort in der Session und leitet zur index.php weiter
    $_SESSION['voice_response'] = $response;
    header('Location: index.php');
    exit;
}

// Prüft, ob eine Sprachantwort in der Session vorhanden ist
if (isset($_SESSION['voice_response'])) {
    $voiceResponse = $_SESSION['voice_response'];
    // Bestimmt die Bootstrap-Klasse basierend auf dem Erfolg der Antwort
    $alertClass = $voiceResponse['success'] ? 'alert-success' : 'alert-danger';
    // Gibt die Sprachantwort als Bootstrap-Alert aus
    echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">
            ' . htmlspecialchars($voiceResponse['message']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';

    // Führt einen Redirect aus, falls in der Antwort ein Redirect angegeben ist
    if ($voiceResponse['success'] && isset($voiceResponse['redirect'])) {
        echo '<script>setTimeout(function() { window.location.href = "' . $voiceResponse['redirect'] . '"; }, 1500);</script>';
    }

    // Entfernt die Sprachantwort aus der Session
    unset($_SESSION['voice_response']);
}

// Definiert eine Funktion zur Berechnung der Tage bis zum Ablaufdatum
function getDaysUntilExpiry($expiry_date)
{
    // Prüft, ob ein Ablaufdatum vorhanden ist
    if (!$expiry_date) {
        return null; // Kein Ablaufdatum vorhanden
    }

    $today = new DateTime(); // Erstellt ein DateTime-Objekt für das aktuelle Datum
    $expiry = new DateTime($expiry_date); // Erstellt ein DateTime-Objekt für das Ablaufdatum
    $interval = $today->diff($expiry); // Berechnet die Differenz zwischen den Daten
    $days = (int)$interval->format('%r%a'); // Extrahiert die Tage inkl. Vorzeichen als Integer

    return $days;
}

// Definiert eine Funktion zur Bestimmung der CSS-Klasse basierend auf den verbleibenden Tagen
function getExpiryClass($days)
{
    if ($days === null) return 'expiry-none'; // Kein Ablaufdatum
    if ($days < 0) return 'expiry-danger'; // Abgelaufen
    if ($days <= 2) return 'expiry-warning'; // Läuft in 1-2 Tagen ab
    if ($days <= 7) return 'expiry-soon'; // Läuft in 3-7 Tagen ab
    return 'expiry-normal'; // Länger haltbar
}

// Definiert eine Funktion zur Bestimmung der CSS-Klasse für die Tabellenzeile
function getTableRowClass($days)
{
    if ($days === null) return ''; // Kein Ablaufdatum
    if ($days < 0) return 'table-row-expiry-danger'; // Abgelaufen
    if ($days <= 2) return 'table-row-expiry-warning'; // Läuft in 1-2 Tagen ab
    if ($days <= 7) return 'table-row-expiry-soon'; // Läuft in 3-7 Tagen ab
    return 'table-row-expiry-normal'; // Länger haltbar
}

// Definiert eine Funktion zur Formatierung der Anzeige der verbleibenden Tage
function formatDaysDisplay($days)
{
    if ($days === null) {
        return '<span class="expiry-date expiry-none" title="Kein Ablaufdatum">-</span>'; // Kein Ablaufdatum
    }

    if ($days < 0) {
        // Anzeige für abgelaufene Lebensmittel
        return '<span class="expiry-date expiry-danger" title="Vor ' . abs($days) . ' Tagen abgelaufen">' . abs($days) . ' Tage überfällig</span>';
    } elseif ($days == 0) {
        // Anzeige für Lebensmittel, die heute ablaufen
        return '<span class="expiry-date expiry-danger" title="Läuft heute ab!">Heute</span>';
    } elseif ($days == 1) {
        // Anzeige für Lebensmittel, die morgen ablaufen
        return '<span class="expiry-date expiry-warning" title="Läuft morgen ab">1 Tag</span>';
    } else {
        // Anzeige für Lebensmittel mit mehr als einem Tag Restlaufzeit
        return '<span class="expiry-date ' . getExpiryClass($days) . '" title="Läuft in ' . $days . ' Tagen ab">' . $days . ' Tage</span>';
    }
}

// Bereitet eine SQL-Abfrage vor, um verfügbare (nicht abgelaufene) Lebensmittel abzurufen
$stmt = $pdo->prepare("SELECT * FROM foods WHERE user_id = ? AND status='available' AND (expiry_date IS NULL OR expiry_date >= CURRENT_DATE()) ORDER BY expiry_date ASC");
// Führt die Abfrage mit der Benutzer-ID aus
$stmt->execute([$uid]);
// Speichert die Ergebnisse in $foods
$foods = $stmt->fetchAll();

// Bereitet eine SQL-Abfrage vor, um abgelaufene Lebensmittel abzurufen
$expiredStmt = $pdo->prepare("SELECT * FROM foods WHERE user_id = ? AND status='available' AND expiry_date < CURRENT_DATE() ORDER BY expiry_date ASC");
// Führt die Abfrage mit der Benutzer-ID aus
$expiredStmt->execute([$uid]);
// Speichert die Ergebnisse in $expiredFoods
$expiredFoods = $expiredStmt->fetchAll();

// Bereitet eine SQL-Abfrage vor, um die Gesamtanzahl der verfügbaren Lebensmittel zu zählen
$totalStmt = $pdo->prepare("SELECT COUNT(*) as c FROM foods WHERE user_id = ? AND status='available'");
// Führt die Abfrage aus
$totalStmt->execute([$uid]);
// Speichert die Gesamtanzahl in $total
$total = $totalStmt->fetch()['c'] ?? 0;

// Bereitet eine SQL-Abfrage vor, um die Anzahl abgelaufener Lebensmittel zu zählen
$expiredCountStmt = $pdo->prepare("SELECT COUNT(*) as c FROM foods WHERE user_id = ? AND status='available' AND expiry_date < CURRENT_DATE()");
// Führt die Abfrage aus
$expiredCountStmt->execute([$uid]);
// Speichert die Anzahl in $expired
$expired = $expiredCountStmt->fetch()['c'] ?? 0;

// Bereitet eine SQL-Abfrage vor, um die Anzahl bald ablaufender Lebensmittel (innerhalb von 7 Tagen) zu zählen
$expiringSoonStmt = $pdo->prepare("SELECT COUNT(*) as c FROM foods WHERE user_id = ? AND status='available' AND expiry_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)");
// Führt die Abfrage aus
$expiringSoonStmt->execute([$uid]);
// Speichert die Anzahl in $expiringSoon
$expiringSoon = $expiringSoonStmt->fetch()['c'] ?? 0;

// Bereitet eine SQL-Abfrage vor, um die Anzahl länger haltbarer Lebensmittel zu zählen
$normalStmt = $pdo->prepare("SELECT COUNT(*) as c FROM foods WHERE user_id = ? AND status='available' AND (expiry_date IS NULL OR expiry_date > DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY))");
// Führt die Abfrage aus
$normalStmt->execute([$uid]);
// Speichert die Anzahl in $normal
$normal = $normalStmt->fetch()['c'] ?? 0;

// Bereitet eine SQL-Abfrage vor, um die Namen der bald ablaufenden Lebensmittel abzurufen
$expiringFoodsStmt = $pdo->prepare("SELECT name FROM foods WHERE user_id = ? AND status='available' AND expiry_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY) LIMIT 5");
// Führt die Abfrage aus
$expiringFoodsStmt->execute([$uid]);
// Speichert die Namen in $expiringFoods
$expiringFoods = $expiringFoodsStmt->fetchAll(PDO::FETCH_COLUMN);

// Initialisiert ein Array für empfohlene Rezepte
$recommendedRecipes = [];
// Prüft, ob bald ablaufende Lebensmittel vorhanden sind
if (!empty($expiringFoods)) {
    // Erstellt einen Suchstring aus den Namen der bald ablaufenden Lebensmittel
    $searchTerms = implode('|', $expiringFoods);

    // Bereitet eine SQL-Abfrage vor, um Rezepte zu finden, die die Zutaten enthalten
    $recipeStmt = $pdo->prepare("
        SELECT * FROM recipes 
        WHERE title REGEXP ? OR description REGEXP ?
        LIMIT 3
    ");
    // Führt die Abfrage mit den Suchbegriffen aus
    $recipeStmt->execute([$searchTerms, $searchTerms]);
    // Speichert die gefundenen Rezepte
    $recommendedRecipes = $recipeStmt->fetchAll();
}

// Falls keine Rezepte gefunden wurden, werden zufällige Rezepte abgerufen
if (empty($recommendedRecipes)) {
    // Bereitet eine SQL-Abfrage vor, um 3 zufällige Rezepte abzurufen
    $defaultRecipeStmt = $pdo->prepare("SELECT * FROM recipes ORDER BY RAND() LIMIT 3");
    // Führt die Abfrage aus
    $defaultRecipeStmt->execute();
    // Speichert die zufälligen Rezepte
    $recommendedRecipes = $defaultRecipeStmt->fetchAll();
}

// Bereitet eine SQL-Abfrage vor, um Statistiken für ein Diagramm zu erstellen
$chartStmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN expiry_date IS NULL THEN 'Kein Datum'
            WHEN expiry_date < CURDATE() THEN 'Abgelaufen'
            WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Bald ablaufend'
            ELSE 'Lange haltbar'
        END as status,
        COUNT(*) as count
    FROM foods 
    WHERE user_id = ? AND status='available' 
    GROUP BY 
        CASE 
            WHEN expiry_date IS NULL THEN 'Kein Datum'
            WHEN expiry_date < CURDATE() THEN 'Abgelaufen'
            WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Bald ablaufend'
            ELSE 'Lange haltbar'
        END
");
// Führt die Abfrage aus
$chartStmt->execute([$uid]);
// Initialisiert ein Array für die Diagrammdaten
$chartData = [];
// Speichert die Ergebnisse in $chartData
while ($row = $chartStmt->fetch(PDO::FETCH_ASSOC)) {
    $chartData[$row['status']] = $row['count'];
}
// Stellt sicher, dass alle Kategorien im Diagramm vorhanden sind, auch wenn keine Daten existieren
$chartData = array_merge(['Abgelaufen' => 0, 'Bald ablaufend' => 0, 'Lange haltbar' => 0, 'Kein Datum' => 0], $chartData);
?>

<!doctype html>
<html lang="de">
<!-- Definiert ein HTML5-Dokument mit der Sprache Deutsch -->

<head>
    <!-- Setzt die Zeichenkodierung auf UTF-8 -->
    <meta charset="utf-8">
    <!-- Setzt den Titel der Seite auf "Fredo" -->
    <title>Fredo</title>
    <!-- Lädt das Bootstrap CSS-Framework für responsives Design -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Lädt Font Awesome für Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Lädt benutzerdefinierte CSS-Datei aus dem assets-Verzeichnis -->
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- Lädt die Chart.js-Bibliothek für Diagramme -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Definiert benutzerdefinierte CSS-Styles innerhalb der HTML-Datei */

        /* Stylt den Hinweis für die Sprachsteuerung, der am unteren Bildschirmrand zentriert angezeigt wird */
        .voice-hint {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 500;
            z-index: 1000;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            text-align: center;
            min-width: 250px;
            animation: fadeInOut 3s ease-in-out infinite;
        }

        /* Definiert eine Animation für den Sprachhinweis, die ihn pulsieren lässt */
        @keyframes fadeInOut {

            0%,
            100% {
                opacity: 0.9;
                transform: translateX(-50%) translateY(0);
            }

            50% {
                opacity: 1;
                transform: translateX(-50%) translateY(-3px);
            }
        }

        /* Definiert eine Animation für den "Fredo"-Button, die ihn pulsieren lässt */
        @keyframes fredoPulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        /* Wendet die Pulsier-Animation auf aktive Fredo-Elemente an */
        .fredo-active {
            animation: fredoPulse 2s infinite;
        }

        /* Responsive Anpassungen für kleinere Bildschirme */
        @media (max-width: 768px) {
            .voice-hint {
                bottom: 10px;
                left: 10px;
                right: 10px;
                transform: none;
                min-width: auto;
            }
        }

        /* Stylt die Anzeige der Ablaufdaten */
        .expiry-date {
            font-size: 0.85rem;
            font-weight: 500;
            padding: 3px 8px;
            border-radius: 12px;
            display: inline-block;
        }

        /* Farben für unterschiedliche Ablaufstatus */
        .expiry-soon {
            background-color: rgba(255, 193, 7, 0.2);
            color: #d39e00;
            font-weight: bold;
        }

        .expiry-normal {
            background-color: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }

        .expiry-warning {
            background-color: rgba(253, 126, 20, 0.2);
            color: #fd7e14;
            font-weight: bold;
        }

        .expiry-danger {
            background-color: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            font-weight: bold;
        }

        .expiry-none {
            background-color: rgba(108, 117, 125, 0.2);
            color: #6c757d;
        }

        /* Hintergrundfarben für Tabellenzeilen basierend auf Ablaufstatus */
        .table-row-expiry-danger {
            background-color: rgba(220, 53, 69, 0.1) !important;
        }

        .table-row-expiry-warning {
            background-color: rgba(255, 193, 7, 0.1) !important;
        }

        .table-row-expiry-soon {
            background-color: rgba(253, 126, 20, 0.1) !important;
        }

        .table-row-expiry-normal {
            background-color: rgba(40, 167, 69, 0.05) !important;
        }

        /* Stylt Statistik-Karten mit Animationen und Farbverläufen */
        .stat-card {
            transition: all 0.3s ease;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
        }

        .stat-card.expired::before {
            background: linear-gradient(to bottom, #dc3545, #c82333);
        }

        .stat-card.expiring::before {
            background: linear-gradient(to bottom, #fd7e14, #e25a10);
        }

        .stat-card.normal::before {
            background: linear-gradient(to bottom, #28a745, #1e7e34);
        }

        .stat-card.total::before {
            background: linear-gradient(to bottom, #6f42c1, #5a36a9);
        }

        /* Definiert eine pulsierende Animation für hervorstechende Elemente */
        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        /* Stylt Rezeptkarten */
        .recipe-card {
            transition: all 0.3s ease;
            border-left: 4px solid #ff4e8d;
        }

        .recipe-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        /* Stylt den Dashboard-Titel mit einem Farbverlauf */
        .dashboard-title {
            background: linear-gradient(45deg, #ff4e8d, #9d50bb, #4ecdc4);
            -webkit-background-clip: text;
            padding-top: 20px;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: #ff4e8d;
            margin-bottom: 20px;
            display: inline-block;
        }

        /* Setzt die Mindestbreite für die Spalte mit Ablaufdaten */
        .days-cell {
            min-width: 120px;
        }

        /* Stylt den Bereich für abgelaufene Lebensmittel */
        .expired-section {
            border-left: 5px solid #dc3545;
            background-color: rgba(220, 53, 69, 0.05);
            border-radius: 8px;
            padding: 15px;
            margin-top: 30px;
        }

        /* Stylt Überschriften */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        /* Stylt den Entsorgen-Button */
        .discard-btn {
            background: linear-gradient(45deg, var(--danger), #ff8e6b);
            border: none;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .discard-btn:hover {
            background: linear-gradient(45deg, #c82333, #a71e2a);
            transform: translateY(-2px);
        }

        /* Stylt den Button zum Entsorgen aller abgelaufenen Lebensmittel */
        .discard-all-btn {
            background: linear-gradient(45deg, #dc3545, #bd2130);
            border: none;
            color: white;
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .discard-all-btn:hover {
            background: linear-gradient(45deg, #bd2130, #9c1c26);
            transform: translateY(-2px);
        }

        /* Stylt die Legende des Diagramms */
        .chart-legend {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.8rem;
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        /* Stylt abgelaufene Lebensmittel in der Sidebar */
        .expired-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(220, 53, 69, 0.2);
        }

        .expired-item:last-child {
            border-bottom: none;
        }

        .expired-item-content {
            flex: 1;
        }

        .expired-item-name {
            font-weight: 600;
            color: #dc3545;
            font-size: 0.9rem;
        }

        .expired-item-quantity {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .expired-item-actions {
            margin-left: 10px;
        }

        .expired-list {
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>

<body>
    <!-- Erstellt einen dekorativen Hintergrund mit schwebenden Elementen -->
    <div class="floating-bg">
        <div></div>
        <div></div>
        <div></div>
        <div></div>
    </div>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="container-fluid">
            <!-- Link zur Startseite mit dem Branding "Fredo" -->
            <a class="navbar-brand" href="index.php">Fredo<span>.</span></a>
            <!-- Begrüßt den Benutzer mit seinem Namen aus der Session -->
            <a class="navbar-brand" href="#">Hallo, <?= htmlspecialchars($_SESSION['name']) ?></a>
            <!-- Navigationslinks für Profil, Family-Chat und Logout -->
            <div class="d-flex">
                <a class="btn btn-outline btn-sm" href="profile.php" data-translate="profile">Profil</a>
                <a class="btn btn-outline btn-sm" href="chat.php">Family</a>
                <a class="btn btn-outline btn-sm" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <main class="container my-4">
        <!-- Haupttitel des Dashboards mit Farbverlauf -->
        <h3 class="dashboard-title mb-4">Kühlschrank Übersicht</h3>

        <div class="row">
            <div class="col-lg-8">
                <!-- Tabelle für verfügbare Lebensmittel -->
                <div class="card mb-3">
                    <div class="card-body">
                        <!-- Überschrift und Aktionsbuttons -->
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="dashboard-title">Meine Lebensmittel</h5>
                            <div>
                                <!-- Button zum Scannen von Lebensmitteln -->
                                <a class="btn btn-sm btn-primary" href="scanner.php"><i class="fas fa-barcode"></i></a>
                                <!-- Button zum Hinzufügen eines Lebensmittels -->
                                <a class="btn btn-sm btn-primary" href="add_food.php"><i class="fas fa-circle-plus"></i></a>
                                <!-- Button zur Sprachsteuerung -->
                                <a id="voiceBtn" class="btn btn-sm btn-primary" href="voice.php"><i class="fas fa-microphone"></i></a>
                                <!-- Button zur Anzeige von Rezepten -->
                                <a class="btn btn-sm btn-primary" href="recipes.php"><i class="fas fa-book-open"></i></a>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <!-- Tabelle für verfügbare Lebensmittel -->
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Menge</th>
                                        <th class="days-cell">bis</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Iteriert über die verfügbaren Lebensmittel -->
                                    <?php foreach ($foods as $f):
                                        // Berechnet die verbleibenden Tage bis zum Ablauf
                                        $days = getDaysUntilExpiry($f['expiry_date']);
                                        // Bestimmt die CSS-Klasse für die Tabellenzeile
                                        $table_row_class = getTableRowClass($days);
                                        // Formatiert die Anzeige der verbleibenden Tage
                                        $days_display = formatDaysDisplay($days);
                                    ?>
                                        <tr class="<?= $table_row_class ?>">
                                            <!-- Zeigt den Namen des Lebensmittels an -->
                                            <td><?= htmlspecialchars($f['name']) ?></td>
                                            <!-- Zeigt Menge und Einheit an -->
                                            <td><?= htmlspecialchars($f['quantity'] . ' ' . $f['unit']) ?></td>
                                            <!-- Zeigt die verbleibenden Tage an -->
                                            <td class="days-cell"><?= $days_display ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <!-- Button für Rezeptvorschläge basierend auf dem Lebensmittel -->
                                                    <a class="btn btn-sm btn-success" href="recipe_match.php?food_id=<?= $f['food_id'] ?>"><i class="fas fa-utensils"></i></a>
                                                    <!-- Button zum Bearbeiten des Lebensmittels -->
                                                    <a class="btn btn-sm btn-primary" href="edit_food.php?id=<?= $f['food_id'] ?>"><i class="fas fa-pencil"></i> </a>
                                                    <!-- Button zum Löschen des Lebensmittels mit Bestätigung -->
                                                    <a class="btn btn-sm btn-danger" href="delete_food.php?id=<?= $f['food_id'] ?>" onclick="return confirm('Löschen?')"><i class="fas fa-trash "></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <!-- Zeigt eine Info-Meldung, wenn keine Lebensmittel vorhanden sind -->
                            <?php if (empty($foods)): ?>
                                <div class="alert alert-info">Keine Lebensmittel gefunden. Füge etwas hinzu.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="col-lg-4">
                <!-- Anzeige abgelaufener Lebensmittel in der Sidebar -->
                <?php if (!empty($expiredFoods)): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <!-- Titel für abgelaufene Lebensmittel -->
                            <h6 class="dashboard-title text-danger">⚠️ Abgelaufene Lebensmittel</h6>
                            <div class="expired-list">
                                <!-- Iteriert über die abgelaufenen Lebensmittel -->
                                <?php foreach ($expiredFoods as $f):
                                    // Berechnet die verbleibenden Tage bis zum Ablauf
                                    $days = getDaysUntilExpiry($f['expiry_date']);
                                ?>
                                    <div class="expired-item">
                                        <div class="expired-item-content">
                                            <!-- Zeigt den Namen des Lebensmittels an -->
                                            <div class="expired-item-name"><?= htmlspecialchars($f['name']) ?></div>
                                            <!-- Zeigt Menge und Einheit an -->
                                            <div class="expired-item-quantity"><?= htmlspecialchars($f['quantity'] . ' ' . $f['unit']) ?></div>
                                        </div>
                                        <div class="expired-item-actions">
                                            <!-- Button zum Entsorgen des Lebensmittels mit Bestätigung -->
                                            <a href="delete_food.php?id=<?= $f['food_id'] ?>" class="btn btn-sm btn-danger discard-btn" onclick="return confirm('Dieses Lebensmittel wirklich entsorgen?')" title="Entsorgen">
                                                ✖
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Diagramm für Lebensmittelstatistiken -->
                <div class="card mb-3">
                    <div class="card-body">
                        <!-- Titel für das Statistik-Diagramm -->
                        <h6 class="dashboard-title">Statistiken</h6>
                        <div class="chart-container">
                            <!-- Canvas-Element für das Chart.js-Diagramm -->
                            <canvas id="usageChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Rezeptempfehlungen -->
                <?php if (!empty($recommendedRecipes)): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <!-- Titel für Rezeptempfehlungen -->
                            <h6 class="dashboard-title">Rezepte</h6>
                            <!-- Hinweis, dass die Rezepte auf bald ablaufenden Lebensmitteln basieren -->
                            <p class="small text-muted">Basierend auf deinen bald ablaufenden Lebensmitteln</p>
                            <!-- Iteriert über die empfohlenen Rezepte -->
                            <?php foreach ($recommendedRecipes as $recipe): ?>
                                <div class="recipe-card card mb-2">
                                    <div class="card-body p-3">
                                        <!-- Zeigt den Titel des Rezepts an -->
                                        <h6 class="card-title"><?= htmlspecialchars($recipe['title']) ?></h6>
                                        <!-- Zeigt die ersten 100 Zeichen der Beschreibung an -->
                                        <p class="card-text small"><?= nl2br(htmlspecialchars(substr($recipe['description'] ?? '', 0, 100))) ?>...</p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <!-- Badge für empfohlene Rezepte -->
                                            <span class="badge bg-primary">Empfohlen</span>
                                            <!-- Button zum Anzeigen des Rezepts -->
                                            <a href="recipe_match.php?recipe_id=<?= $recipe['recipe_id'] ?>" class="btn btn-sm btn-success">Rezept</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </main>

    <!-- Lädt Font Awesome für Icons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        // Definiert JavaScript-Variablen für die Sprachsteuerung
        let voiceRecognition = null; // Instanz der Spracherkennung
        let isVoiceListening = false; // Status, ob die Spracherkennung aktiv ist
        let activationDetected = false; // Status, ob "Hey Fredo" erkannt wurde
        let sessionTimeout = null; // Timeout für die Sprachsitzung
        let errorCount = 0; // Zähler für Spracherkennungsfehler
        const ACTIVATION_PHRASE = "hey fredo"; // Aktivierungsphrase
        const SESSION_DURATION = 10000; // Dauer einer Sprachsitzung in Millisekunden
        const MAX_ERROR_COUNT = 3; // Maximale Anzahl an Fehlern bevor Neustart
        const RETRY_DELAY = 2000; // Wartezeit vor erneutem Versuch in Millisekunden

        // Initialisiert die Sprachsteuerung
        function initializeVoiceControl() {
            // Prüft, ob die Spracherkennung im Browser verfügbar ist
            if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                createRecognitionInstance(); // Erstellt eine Spracherkennungs-Instanz
                startVoiceRecognition(); // Startet die Spracherkennung
            } else {
                updateVoiceStatus('Spracherkennung nicht unterstützt', 'error'); // Zeigt Fehlermeldung an
            }
        }

        // Erstellt eine neue Instanz der Spracherkennung
        function createRecognitionInstance() {
            voiceRecognition = new(window.SpeechRecognition || window.webkitSpeechRecognition)(); // Initialisiert die Spracherkennung
            voiceRecognition.continuous = true; // Ermöglicht kontinuierliche Erkennung
            voiceRecognition.interimResults = true; // Aktiviert Zwischenergebnisse
            voiceRecognition.lang = 'de-DE'; // Setzt die Sprache auf Deutsch

            // Event-Handler für den Start der Spracherkennung
            voiceRecognition.onstart = function() {
                isVoiceListening = true; // Setzt den Status auf aktiv
                errorCount = 0; // Setzt den Fehlerzähler zurück
                updateVoiceStatus('Bereit - Sage "Hey Fredo"', 'ready'); // Aktualisiert den Status
                console.log('Fredo Sprachsteuerung aktiv'); // Konsolenausgabe zur Fehlersuche
            };

            // Event-Handler für erkannte Sprachbefehle
            voiceRecognition.onresult = function(event) {
                let transcript = ''; // Initialisiert die Transkript-Variable
                // Iteriert über die Ergebnisse der Spracherkennung
                for (let i = event.resultIndex; i < event.results.length; i++) {
                    if (event.results[i].isFinal) { // Prüft, ob das Ergebnis final ist
                        transcript += event.results[i][0].transcript.toLowerCase(); // Fügt das Transkript hinzu
                    }
                }

                console.log('Erkannt:', transcript); // Konsolenausgabe des erkannten Textes

                // Definiert flexible Aktivierungsphrasen
                const activationPatterns = [
                    'hey fredo',
                    'hey fredo ',
                    ' hey fredo',
                    'hey fredo,',
                    'hey fredo.'
                ];

                // Prüft, ob eine Aktivierungsphrase im Transkript enthalten ist
                const hasActivation = activationPatterns.some(pattern =>
                    transcript.includes(pattern)
                );

                // Aktiviert die Fredo-Session, wenn die Aktivierungsphrase erkannt wird
                if (!activationDetected && hasActivation) {
                    activateFredoSession();
                    return;
                }

                // Verarbeitet den Befehl, wenn die Session aktiv ist und keine Aktivierungsphrase enthalten ist
                if (activationDetected && transcript && !hasActivation) {
                    let cleanTranscript = transcript; // Kopiert das Transkript
                    // Entfernt alle Aktivierungsphrasen
                    activationPatterns.forEach(pattern => {
                        cleanTranscript = cleanTranscript.replace(pattern, '');
                    });
                    cleanTranscript = cleanTranscript.trim(); // Entfernt Leerzeichen

                    if (cleanTranscript.length > 0) { // Prüft, ob das Transkript nicht leer ist
                        processVoiceCommand(cleanTranscript); // Verarbeitet den Befehl
                        resetSessionTimer(); // Setzt den Sitzungstimer zurück
                    }
                }
            };

            // Event-Handler für Fehler in der Spracherkennung
            voiceRecognition.onerror = function(event) {
                console.error('Spracherkennungsfehler:', event.error); // Konsolenausgabe des Fehlers
                errorCount++; // Erhöht den Fehlerzähler
                handleRecognitionError(event.error); // Behandelt den Fehler
            };

            // Event-Handler für das Ende der Spracherkennung
            voiceRecognition.onend = function() {
                isVoiceListening = false; // Setzt den Status auf inaktiv
                console.log('Spracherkennung beendet'); // Konsolenausgabe

                // Startet die Spracherkennung neu, wenn die Session nicht aktiv ist und die Fehlergrenze nicht erreicht ist
                if (!activationDetected && errorCount < MAX_ERROR_COUNT) {
                    setTimeout(startVoiceRecognition, 1000);
                }
            };
        }

        // Behandelt verschiedene Arten von Spracherkennungsfehlern
        function handleRecognitionError(errorType) {
            switch (errorType) {
                case 'no-speech':
                    updateVoiceStatus('Bereit - Sage "Hey Fredo"', 'ready'); // Keine Sprache erkannt
                    setTimeout(restartRecognition, RETRY_DELAY); // Startet nach Verzögerung neu
                    break;
                case 'audio-capture':
                    updateVoiceStatus('Mikrofon nicht verfügbar', 'error'); // Mikrofonproblem
                    setTimeout(restartRecognition, 1000); // Startet nach Verzögerung neu
                    break;
                case 'not-allowed':
                    updateVoiceStatus('Mikrofon-Berechtigung benötigt', 'error'); // Berechtigungsproblem
                    break;
                case 'network':
                    updateVoiceStatus('Netzwerkfehler', 'error'); // Netzwerkproblem
                    setTimeout(restartRecognition, RETRY_DELAY * 2); // Startet nach längerer Verzögerung neu
                    break;
                default:
                    updateVoiceStatus('Bereit - Sage "Hey Fredo"', 'ready'); // Andere Fehler
                    // Startet neu, wenn die Fehlergrenze nicht erreicht ist
                    if (errorCount >= MAX_ERROR_COUNT) {
                        setTimeout(fullRestart, RETRY_DELAY * 3);
                    } else {
                        setTimeout(restartRecognition, RETRY_DELAY);
                    }
                    break;
            }
        }

        // Startet die Spracherkennung neu
        function restartRecognition() {
            if (isVoiceListening) {
                try {
                    voiceRecognition.stop(); // Stoppt die aktuelle Erkennung
                } catch (e) {
                    console.log('Konnte Recognition nicht stoppen:', e); // Konsolenausgabe bei Fehler
                }
            }

            setTimeout(startVoiceRecognition, 500); // Startet nach kurzer Verzögerung neu
        }

        // Führt einen vollständigen Neustart der Spracherkennung durch
        function fullRestart() {
            errorCount = 0; // Setzt den Fehlerzähler zurück
            activationDetected = false; // Setzt den Aktivierungsstatus zurück

            if (voiceRecognition) {
                try {
                    voiceRecognition.stop(); // Stoppt die aktuelle Erkennung
                } catch (e) {
                    console.log('Fehler beim Stoppen:', e); // Konsolenausgabe bei Fehler
                }
                voiceRecognition = null; // Setzt die Instanz zurück
            }

            // Erstellt eine neue Instanz und startet die Erkennung
            setTimeout(() => {
                createRecognitionInstance();
                startVoiceRecognition();
            }, 1000);
        }

        // Startet die Spracherkennung
        function startVoiceRecognition() {
            if (!voiceRecognition) {
                createRecognitionInstance(); // Erstellt eine neue Instanz, wenn keine existiert
            }

            try {
                voiceRecognition.start(); // Startet die Spracherkennung
                updateVoiceStatus('Bereit - Sage "Hey Fredo"', 'ready'); // Aktualisiert den Status
            } catch (error) {
                console.error('Startfehler:', error); // Konsolenausgabe bei Fehler

                if (error.toString().includes('already started')) {
                    return; // Beendet, wenn die Erkennung bereits läuft
                }

                // Startet neu, wenn die Fehlergrenze nicht erreicht ist
                setTimeout(() => {
                    if (errorCount < MAX_ERROR_COUNT) {
                        startVoiceRecognition();
                    }
                }, RETRY_DELAY);
            }
        }

        // Aktiviert die Fredo-Session nach Erkennung der Aktivierungsphrase
        function activateFredoSession() {
            activationDetected = true; // Setzt den Aktivierungsstatus
            errorCount = 0; // Setzt den Fehlerzähler zurück
            updateVoiceStatus('Fredo hört zu...', 'listening'); // Aktualisiert den Status
            playActivationSound(); // Spielt Aktivierungston ab
            resetSessionTimer(); // Setzt den Sitzungstimer zurück
        }

        // Setzt den Sitzungstimer zurück
        function resetSessionTimer() {
            if (sessionTimeout) {
                clearTimeout(sessionTimeout); // Löscht den bestehenden Timeout
            }

            // Setzt einen neuen Timeout, um die Session nach SESSION_DURATION zu beenden
            sessionTimeout = setTimeout(() => {
                deactivateFredoSession();
            }, SESSION_DURATION);
        }

        // Deaktiviert die Fredo-Session
        function deactivateFredoSession() {
            activationDetected = false; // Setzt den Aktivierungsstatus zurück
            updateVoiceStatus('Bereit - Sage "Hey Fredo"', 'ready'); // Aktualisiert den Status
            playDeactivationSound(); // Spielt Deaktivierungston ab

            if (sessionTimeout) {
                clearTimeout(sessionTimeout); // Löscht den Timeout
                sessionTimeout = null; // Setzt den Timeout zurück
            }

            // Startet die Erkennung neu, wenn sie nicht läuft und die Fehlergrenze nicht erreicht ist
            if (!isVoiceListening && errorCount < MAX_ERROR_COUNT) {
                setTimeout(restartRecognition, 1000);
            }
        }

        // Verarbeitet einen Sprachbefehl
        function processVoiceCommand(transcript, retryCount = 0) {
            const MAX_RETRIES = 2; // Maximale Anzahl an Wiederholungen

            console.log('Roher Befehl:', transcript); // Konsolenausgabe des rohen Befehls

            updateVoiceStatus('Verarbeite Befehl...', 'processing'); // Aktualisiert den Status

            // Bereinigt das Transkript
            transcript = transcript.replace(ACTIVATION_PHRASE, '').trim();
            console.log('Bereinigter Befehl:', transcript); // Konsolenausgabe des bereinigten Befehls

            // Sendet den Befehl per AJAX an den Server
            fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `voice_command=${encodeURIComponent(transcript)}&ajax=true`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`); // Wirft einen Fehler bei HTTP-Problemen
                    }
                    return response.json(); // Konvertiert die Antwort in JSON
                })
                .then(data => {
                    console.log('Server-Antwort:', data); // Konsolenausgabe der Serverantwort

                    if (data.success) {
                        updateVoiceStatus('Befehl erfolgreich', 'success'); // Aktualisiert den Status bei Erfolg
                        playSuccessSound(); // Spielt Erfolgston ab
                        errorCount = 0; // Setzt den Fehlerzähler zurück

                        // Leitet weiter, wenn ein Redirect angegeben ist
                        if (data.redirect) {
                            setTimeout(() => {
                                window.location.href = data.redirect;
                            }, 1500);
                        } else {
                            // Lädt die Seite neu
                            setTimeout(() => window.location.reload(), 1500);
                        }
                    } else {
                        updateVoiceStatus('Befehl nicht verstanden', 'error'); // Aktualisiert den Status bei Misserfolg
                        console.log('Fehler vom Server:', data.message); // Konsolenausgabe der Fehlermeldung

                        // Wiederholt den Befehl bei bestimmten Fehlern
                        if (shouldRetryCommand(data.message) && retryCount < MAX_RETRIES) {
                            setTimeout(() => {
                                processVoiceCommand(transcript, retryCount + 1);
                            }, 1000);
                        } else {
                            playErrorSound(); // Spielt Fehlerton ab
                        }
                    }
                })
                .catch(error => {
                    console.error('Netzwerkfehler:', error); // Konsolenausgabe bei Netzwerkfehler
                    updateVoiceStatus('Verbindungsfehler', 'error'); // Aktualisiert den Status

                    // Wiederholt den Befehl bei Netzwerkfehlern
                    if (retryCount < MAX_RETRIES) {
                        setTimeout(() => {
                            processVoiceCommand(transcript, retryCount + 1);
                        }, 2000);
                    } else {
                        playErrorSound(); // Spielt Fehlerton ab
                    }
                });
        }

        // Prüft, ob ein Befehl erneut versucht werden sollte
        function shouldRetryCommand(errorMessage) {
            const retryErrors = [
                'netzwerk',
                'verbindung',
                'timeout',
                'server',
                'fehler beim',
                'error',
                'failed'
            ];

            // Prüft, ob die Fehlermeldung eines der Schlüsselwörter enthält
            return retryErrors.some(retryError =>
                errorMessage.toLowerCase().includes(retryError)
            );
        }

        // Aktualisiert den Status der Sprachsteuerung in der UI
        function updateVoiceStatus(message, status) {
            let voiceHint = document.getElementById('voiceHint'); // Sucht das Sprachhinweis-Element
            if (!voiceHint) {
                voiceHint = document.createElement('div'); // Erstellt ein neues Element, wenn keines existiert
                voiceHint.id = 'voiceHint';
                voiceHint.className = 'voice-hint';
                document.body.appendChild(voiceHint); // Fügt es dem DOM hinzu
            }

            // Bestimmt die Hintergrundfarbe basierend auf dem Status
            let backgroundColor = '#6f42c1';
            if (status === 'listening') {
                backgroundColor = '#28a745';
            } else if (status === 'error') {
                backgroundColor = '#dc3545';
            } else if (status === 'success') {
                backgroundColor = '#28a745';
            } else if (status === 'processing') {
                backgroundColor = '#fd7e14';
            }

            voiceHint.style.background = backgroundColor; // Setzt die Hintergrundfarbe
            voiceHint.innerHTML = `<i class="fas ${getStatusIcon(status)} me-2"></i>${message}`; // Setzt den Inhalt
            voiceHint.style.display = 'block'; // Macht das Element sichtbar
        }

        // Gibt das passende Icon für den Status zurück
        function getStatusIcon(status) {
            const icons = {
                'ready': 'fa-microphone',
                'listening': 'fa-ear-listen',
                'processing': 'fa-cog fa-spin',
                'success': 'fa-check',
                'error': 'fa-exclamation-triangle'
            };
            return icons[status] || 'fa-microphone'; // Gibt das Icon oder ein Standard-Icon zurück
        }

        // Spielt einen Aktivierungston ab
        function playActivationSound() {
            try {
                const context = new(window.AudioContext || window.webkitAudioContext)(); // Erstellt einen Audio-Kontext
                const oscillator = context.createOscillator(); // Erstellt einen Oszillator
                const gainNode = context.createGain(); // Erstellt einen Gain-Node

                oscillator.connect(gainNode); // Verbindet Oszillator mit Gain-Node
                gainNode.connect(context.destination); // Verbindet Gain-Node mit Ausgabe

                oscillator.frequency.value = 800; // Setzt die Frequenz auf 800 Hz
                gainNode.gain.value = 0.1; // Setzt die Lautstärke

                oscillator.start(); // Startet den Ton
                gainNode.gain.exponentialRampToValueAtTime(0.001, context.currentTime + 0.3); // Fade-Out
                oscillator.stop(context.currentTime + 0.3); // Stoppt den Ton nach 0.3 Sekunden
            } catch (e) {
                console.log('Audio nicht verfügbar'); // Konsolenausgabe bei Fehler
            }
        }

        // Spielt einen Deaktivierungston ab
        function playDeactivationSound() {
            try {
                const context = new(window.AudioContext || window.webkitAudioContext)();
                const oscillator = context.createOscillator();
                const gainNode = context.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(context.destination);

                oscillator.frequency.value = 600; // Setzt die Frequenz auf 600 Hz
                gainNode.gain.value = 0.1;

                oscillator.start();
                gainNode.gain.exponentialRampToValueAtTime(0.001, context.currentTime + 0.2);
                oscillator.stop(context.currentTime + 0.2);
            } catch (e) {
                console.log('Audio nicht verfügbar');
            }
        }

        // Spielt einen Erfolgston ab
        function playSuccessSound() {
            try {
                const context = new(window.AudioContext || window.webkitAudioContext)();
                const oscillator = context.createOscillator();
                const gainNode = context.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(context.destination);

                oscillator.frequency.value = 1000; // Setzt die Frequenz auf 1000 Hz
                gainNode.gain.value = 0.1;

                oscillator.start();
                gainNode.gain.exponentialRampToValueAtTime(0.001, context.currentTime + 0.2);
                oscillator.stop(context.currentTime + 0.2);
            } catch (e) {
                console.log('Audio nicht verfügbar');
            }
        }

        // Spielt einen Fehlerton ab
        function playErrorSound() {
            try {
                const context = new(window.AudioContext || window.webkitAudioContext)();
                const oscillator = context.createOscillator();
                const gainNode = context.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(context.destination);

                oscillator.frequency.value = 400; // Setzt die Frequenz auf 400 Hz
                gainNode.gain.value = 0.1;

                oscillator.start();
                gainNode.gain.exponentialRampToValueAtTime(0.001, context.currentTime + 0.5);
                oscillator.stop(context.currentTime + 0.5);
            } catch (e) {
                console.log('Audio nicht verfügbar');
            }
        }

        // Fordert die Mikrofon-Berechtigung an
        function requestMicrophonePermission() {
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                navigator.mediaDevices.getUserMedia({
                        audio: true
                    }) // Fordert Zugriff auf das Mikrofon an
                    .then(function(stream) {
                        console.log('Mikrofon-Berechtigung erhalten'); // Konsolenausgabe bei Erfolg
                        stream.getTracks().forEach(track => track.stop()); // Stoppt den Audio-Stream
                        initializeVoiceControl(); // Initialisiert die Sprachsteuerung
                    })
                    .catch(function(err) {
                        console.error('Mikrofon-Berechtigung verweigert:', err); // Konsolenausgabe bei Fehler
                        updateVoiceStatus('Mikrofon-Berechtigung benötigt', 'error'); // Aktualisiert den Status
                    });
            } else {
                updateVoiceStatus('Mikrofon nicht verfügbar', 'error'); // Aktualisiert den Status bei fehlender Unterstützung
            }
        }

        // Initialisiert Chart.js für das Diagramm
        document.addEventListener('DOMContentLoaded', function() {
            // Fordert die Mikrofon-Berechtigung nach 1 Sekunde an
            setTimeout(() => {
                requestMicrophonePermission();
            }, 1000);

            const ctx = document.getElementById('usageChart'); // Sucht das Canvas-Element für das Diagramm
            if (ctx) {
                // Definiert die Daten für das Diagramm
                const chartData = [{
                        status: 'Lange haltbar',
                        count: <?= $chartData['Lange haltbar'] ?>, // Anzahl langer haltbarer Lebensmittel
                        color: '#28a745' // Grün
                    },
                    {
                        status: 'Bald ablaufend',
                        count: <?= $chartData['Bald ablaufend'] ?>, // Anzahl bald ablaufender Lebensmittel
                        color: '#fd7e14' // Orange
                    },
                    {
                        status: 'Abgelaufen',
                        count: <?= $chartData['Abgelaufen'] ?>, // Anzahl abgelaufener Lebensmittel
                        color: '#dc3545' // Rot
                    },
                    {
                        status: 'Kein Datum',
                        count: <?= $chartData['Kein Datum'] ?>, // Anzahl Lebensmittel ohne Datum
                        color: '#6c757d' // Grau
                    }
                ];

                // Prüft, ob Daten für das Diagramm vorhanden sind
                if (chartData.some(item => item.count > 0)) {
                    new Chart(ctx, {
                        type: 'doughnut', // Definiert den Diagrammtyp als Donut
                        data: {
                            labels: chartData.map(r => r.status), // Setzt die Beschriftungen
                            datasets: [{
                                data: chartData.map(r => r.count), // Setzt die Werte
                                backgroundColor: chartData.map(r => r.color), // Setzt die Farben
                                borderWidth: 2, // Setzt die Randbreite
                                borderColor: '#ffffff' // Setzt die Randfarbe
                            }]
                        },
                        options: {
                            responsive: true, // Macht das Diagramm responsiv
                            maintainAspectRatio: false, // Deaktiviert das Seitenverhältnis
                            plugins: {
                                legend: {
                                    position: 'bottom', // Platziert die Legende unten
                                    labels: {
                                        padding: 20, // Abstand der Legendenbeschriftungen
                                        usePointStyle: true, // Verwendet Punkte für die Legende
                                        pointStyle: 'circle' // Setzt den Punktstil auf Kreise
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        // Anpassung der Tooltip-Beschriftung
                                        label: function(context) {
                                            return `${context.label}: ${context.raw} Lebensmittel`;
                                        }
                                    }
                                }
                            },
                            cutout: '60%', // Definiert den inneren Ausschnitt des Donut-Diagramms
                            animation: {
                                animateScale: true, // Aktiviert die Skalierungsanimation
                                animateRotate: true // Aktiviert die Rotationsanimation
                            }
                        }
                    });
                } else {
                    ctx.style.display = 'none'; // Versteckt das Canvas, wenn keine Daten vorhanden sind
                    const container = ctx.parentElement; // Sucht das Eltern-Element
                    if (container) {
                        // Zeigt eine Fallback-Meldung an
                        container.innerHTML = '<div class="chart-fallback">Keine Daten verfügbar</div>';
                    }
                }
            }

            // Fügt eine Pulsier-Animation zur Gesamtanzahl-Karte hinzu
            const totalCard = document.querySelector('.stat-card.total h2');
            if (totalCard) {
                totalCard.classList.add('pulse');
            }
        });
    </script>
</body>

</html>