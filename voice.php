<?php
session_start();
require_once __DIR__ . '/../src/db.php';

// Prüfen ob User eingeloggt ist
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <title>Sprachassistent Fredo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <style>
        .voice-btn {
            background: linear-gradient(45deg, #6f42c1, #5a36a9);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 50px;
            transition: all 0.3s ease;
        }

        .voice-btn.listening {
            background: linear-gradient(45deg, #dc3545, #c82333);
            animation: pulse 1.5s infinite;
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

        .voice-help {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Fredo<span>.</span></a>
            <a class="navbar-brand" href="#">Hallo, <?= htmlspecialchars($_SESSION['name']) ?></a>
            <div class="d-flex">

                <a class="btn btn-outline btn-sm" href="index.php">Dashboard</a>
                <a class="btn btn-outline btn-sm" href="scanner.php">Scanner</a>
                <a class="btn btn-outline btn-sm" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <h3 class="mb-4  py-4">Sprachassistent Fredo</h3>


    <div class="card mb-4 mx-auto" style="max-width: 1200px;">
        <div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-3">
                <button id="voiceBtn" class="voice-btn">
                    <i class="fas fa-microphone me-2"></i>Fredo aktivieren
                </button>
                <div id="voiceStatus" class="text-muted">Bereit für "Hey Fredo"</div>
            </div>
            <div id="voiceHelp" class="voice-help">
                <h6 class="text-primary">Verfügbare Sprachbefehle:</h6>
                <ul class="mb-0">
                    <li><strong>"Hey Fredo, füge 500 Gramm Reis "</strong></li>
                    <li><strong>"Hey Fredo, lösche Mehl"</strong></li>
                    <li><strong>"Hey Fredo, bearbeite Mehl auf 300 Gramm"</strong></li>
                    <li><strong>"Hey Fredo, zeige mir Rezepte mit Mehl"</strong></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        

        <?php
        // Lebensmittel aus der Datenbank laden
        $stmt = $pdo->prepare("SELECT name, quantity, unit, added_at FROM foods WHERE user_id = ? ORDER BY added_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $lebensmittel = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($lebensmittel):
        ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Lebensmittel</th>
                            <th>Menge</th>
                            <th>Einheit</th>

                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lebensmittel as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= htmlspecialchars($item['quantity']) ?></td>
                                <td><?= htmlspecialchars($item['unit']) ?></td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">Noch keine Lebensmittel hinzugefügt.</div>
        <?php endif; ?>
    </div>





    <!-- Sprachsteuerungs-Skript -->
    <script>
        const voiceBtn = document.getElementById('voiceBtn');
        const voiceStatus = document.getElementById('voiceStatus');
        let recognition = null;
        let isListening = false;
        let activationPhrase = "hey fredo";
        let activationDetected = false;

        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            recognition = new(window.SpeechRecognition || window.webkitSpeechRecognition)();
            recognition.continuous = true;
            recognition.interimResults = true;
            recognition.lang = 'de-DE';

            recognition.onstart = function() {
                isListening = true;
                voiceBtn.classList.add('listening');
                voiceStatus.textContent = 'Höre zu... Sage "Hey Fredo"';
            };

            recognition.onresult = function(event) {
                let transcript = '';
                for (let i = event.resultIndex; i < event.results.length; i++) {
                    if (event.results[i].isFinal) {
                        transcript += event.results[i][0].transcript.toLowerCase();
                    }
                }

                if (!activationDetected && transcript.includes(activationPhrase)) {
                    activationDetected = true;
                    voiceStatus.textContent = 'Fredo aktiviert! Was kann ich für dich tun?';
                    return;
                }

                if (activationDetected && transcript) {
                    processVoiceCommand(transcript);
                    activationDetected = false;
                }
            };

            recognition.onerror = function(event) {
                voiceStatus.textContent = 'Fehler: ' + event.error;
                voiceBtn.classList.remove('listening');
                isListening = false;
                activationDetected = false;
            };

            recognition.onend = function() {
                voiceBtn.classList.remove('listening');
                isListening = false;
                activationDetected = false;
                setTimeout(() => {
                    if (!isListening) voiceStatus.textContent = 'Bereit für "Hey Fredo"';
                }, 3000);
            };

            voiceBtn.addEventListener('click', function() {
                if (isListening) {
                    recognition.stop();
                } else {
                    try {
                        recognition.start();
                        voiceStatus.textContent = 'Höre zu... Sage "Hey Fredo"';
                    } catch (error) {
                        voiceStatus.textContent = 'Mikrofon nicht verfügbar';
                    }
                }
            });
        } else {
            voiceBtn.disabled = true;
            voiceStatus.textContent = 'Spracherkennung nicht unterstützt';
            voiceBtn.innerHTML = '<i class="fas fa-microphone-slash me-2"></i>Nicht unterstützt';
        }

        function processVoiceCommand(transcript) {
            voiceStatus.textContent = `Verarbeite: "${transcript}"`;

            transcript = transcript.replace(activationPhrase, '').trim();

            fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `voice_command=${encodeURIComponent(transcript)}&ajax=true`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        voiceStatus.textContent = 'Erfolg: ' + data.message;
                        if (data.redirect) {
                            setTimeout(() => {
                                window.location.href = data.redirect;
                            }, 1500);
                        } else {
                            setTimeout(() => window.location.reload(), 1500);
                        }
                    } else {
                        voiceStatus.textContent = 'Fehler: ' + data.message;
                    }
                })
                .catch(error => {
                    voiceStatus.textContent = 'Netzwerkfehler';
                    console.error('Error:', error);
                });
        }
    </script>
</body>

</html>