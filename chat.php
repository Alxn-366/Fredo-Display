<?php
session_start();
require_once __DIR__ . '/../src/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$uid = (int)$_SESSION['user_id'];
$receiver_id = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : null;
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Nachrichten senden
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && $receiver_id) {
    $message = trim($_POST['message']);

    if (!empty($message)) {
        // Nachricht in die Datenbank einfügen
        $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$uid, $receiver_id, $message]);

        // Konversation aktualisieren oder erstellen
        $conversation_stmt = $pdo->prepare("
            INSERT INTO chat_conversations (user1_id, user2_id, last_message_at) 
            VALUES (LEAST(?, ?), GREATEST(?, ?), NOW())
            ON DUPLICATE KEY UPDATE last_message_at = NOW()
        ");
        $conversation_stmt->execute([$uid, $receiver_id, $uid, $receiver_id]);

        // Zurück zur Chat-Seite mit dem gleichen Empfänger
        header("Location: chat.php?receiver_id=$receiver_id");
        exit;
    }
}

// Nachrichten mit einem bestimmten Benutzer abrufen
if ($receiver_id) {
    // Überprüfen, ob der Empfänger existiert
    $receiver_stmt = $pdo->prepare("SELECT user_id, name, profile_picture FROM users WHERE user_id = ?");
    $receiver_stmt->execute([$receiver_id]);
    $receiver = $receiver_stmt->fetch();

    if ($receiver) {
        // Nachrichten abrufen
        $messages_stmt = $pdo->prepare("
            SELECT m.*, u.name as sender_name, u.profile_picture as sender_profile
            FROM chat_messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.created_at ASC
        ");
        $messages_stmt->execute([$uid, $receiver_id, $receiver_id, $uid]);
        $messages = $messages_stmt->fetchAll();

        // Nachrichten als gelesen markieren
        $read_stmt = $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0");
        $read_stmt->execute([$uid, $receiver_id]);
    }
}

// Alle Benutzer abrufen (außer dem aktuellen Benutzer)
$users_stmt = $pdo->prepare("SELECT user_id, name, profile_picture FROM users WHERE user_id != ? ORDER BY name");
$users_stmt->execute([$uid]);
$all_users = $users_stmt->fetchAll();

// Letzte Konversationen abrufen
$conversations_stmt = $pdo->prepare("
    SELECT c.*, 
           u.user_id as other_user_id,
           u.name as other_user_name,
           u.profile_picture as other_user_profile,
           (SELECT COUNT(*) FROM chat_messages WHERE receiver_id = ? AND sender_id = u.user_id AND is_read = 0) as unread_count
    FROM chat_conversations c
    JOIN users u ON (c.user1_id = u.user_id AND c.user1_id != ?) OR (c.user2_id = u.user_id AND c.user2_id != ?)
    WHERE c.user1_id = ? OR c.user2_id = ?
    ORDER BY c.last_message_at DESC
");
$conversations_stmt->execute([$uid, $uid, $uid, $uid, $uid]);
$conversations = $conversations_stmt->fetchAll();

// Benutzer-IDs mit Konversationen für einfachere Überprüfung
$user_ids_with_conversations = array();
foreach ($conversations as $conv) {
    $user_ids_with_conversations[] = $conv['other_user_id'];
}

// Benutzer ohne Konversationen finden
$users_without_conversations = array();
foreach ($all_users as $user) {
    if (!in_array($user['user_id'], $user_ids_with_conversations)) {
        $users_without_conversations[] = $user;
    }
}
?>
<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <title>Family</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .chat-container {
            height: 70vh;
            overflow: hidden;
        }

        .users-list {
            height: 100%;
            overflow-y: auto;
            border-right: 1px solid #dee2e6;
        }

        .chat-messages {
            height: calc(100% - 130px);
            overflow-y: auto;
            padding: 15px;
        }

        .chat-input {
            height: 80px;
        }

        .message {
            max-width: 75%;
            margin-bottom: 15px;
        }

        .message-sent {
            margin-left: auto;
        }

        .message-received {
            margin-right: auto;
        }

        .unread-count {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-item {
            position: relative;
            transition: all 0.3s;
        }

        .user-item:hover,
        .user-item.active {
            background-color: #f8f9fa;
        }

        .conversation-time {
            font-size: 12px;
            color: #6c757d;
        }

        .no-conversation {
            opacity: 0.7;
        }
    </style>
</head>

<body>
    <!-- Floating Background -->
    <div class="floating-bg">
        <div></div>
        <div></div>
        <div></div>
        <div></div>
    </div>

    <nav class="navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Fredo<span>.</span></a>
            <a class="navbar-brand" href="#">Hallo, <?= htmlspecialchars($_SESSION['name']) ?></a>
            <div class="d-flex">

                <a class="btn btn-outline btn-sm" href="index.php">Dashboard</a>
                <a class="btn btn-outline btn-sm" href="profile.php">Profil</a>
                <a class="btn btn-outline btn-sm" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="card shadow">
            <div class="card-body p-0">
                <div class="row g-0 chat-container">
                    <!-- Benutzerliste -->
                    <div class="col-md-4 users-list">
                        <div class="p-3 border-bottom">
                            <h5 class="mb-0">Family</h5>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php foreach ($conversations as $conv): ?>
                                <a href="chat.php?receiver_id=<?= $conv['other_user_id'] ?>"
                                    class="list-group-item list-group-item-action user-item <?= $receiver_id == $conv['other_user_id'] ? 'active' : '' ?>">
                                    <div class="d-flex align-items-center">
                                        <?php if ($conv['other_user_profile']): ?>
                                            <img src="../uploads/profiles/<?= htmlspecialchars($conv['other_user_profile']) ?>"
                                                alt="<?= htmlspecialchars($conv['other_user_name']) ?>"
                                                class="rounded-circle me-3"
                                                style="width: 40px; height: 40px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-3"
                                                style="width: 40px; height: 40px;">
                                                <span class="text-white"><?= strtoupper(substr($conv['other_user_name'], 0, 1)) ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><?= htmlspecialchars($conv['other_user_name']) ?></h6>
                                                <small class="conversation-time"><?= date('H:i', strtotime($conv['last_message_at'])) ?></small>
                                            </div>

                                        </div>

                                        <?php if ($conv['unread_count'] > 0): ?>
                                            <span class="unread-count"><?= $conv['unread_count'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>

                            <!-- Benutzer ohne Konversationen -->
                            <?php foreach ($users_without_conversations as $user): ?>
                                <a href="chat.php?receiver_id=<?= $user['user_id'] ?>"
                                    class="list-group-item list-group-item-action user-item no-conversation <?= $receiver_id == $user['user_id'] ? 'active' : '' ?>">
                                    <div class="d-flex align-items-center">
                                        <?php if ($user['profile_picture']): ?>
                                            <img src="../uploads/profiles/<?= htmlspecialchars($user['profile_picture']) ?>"
                                                alt="<?= htmlspecialchars($user['name']) ?>"
                                                class="rounded-circle me-3"
                                                style="width: 40px; height: 40px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-3"
                                                style="width: 40px; height: 40px;">
                                                <span class="text-white"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?= htmlspecialchars($user['name']) ?></h6>

                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>

                            <?php if (empty($conversations) && empty($users_without_conversations)): ?>
                                <div class="text-center p-3 text-muted">
                                    Keine anderen Benutzer gefunden.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Chat-Bereich -->
                    <div class="col-md-8 d-flex flex-column">
                        <?php if ($receiver_id && isset($receiver)): ?>
                            <!-- Chat-Header -->
                            <div class="p-3 border-bottom bg-light">
                                <div class="d-flex align-items-center">
                                    <?php if ($receiver['profile_picture']): ?>
                                        <img src="../uploads/profiles/<?= htmlspecialchars($receiver['profile_picture']) ?>"
                                            alt="<?= htmlspecialchars($receiver['name']) ?>"
                                            class="rounded-circle me-3"
                                            style="width: 40px; height: 40px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-3"
                                            style="width: 40px; height: 40px;">
                                            <span class="text-white"><?= strtoupper(substr($receiver['name'], 0, 1)) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <h5 class="mb-0"><?= htmlspecialchars($receiver['name']) ?></h5>
                                </div>
                            </div>

                            <!-- Nachrichten-Bereich -->
                            <div class="chat-messages flex-grow-1" id="chatMessages">
                                <?php if (!empty($messages)): ?>
                                    <?php foreach ($messages as $msg): ?>
                                        <div class="message <?= $msg['sender_id'] == $uid ? 'message-sent' : 'message-received' ?>">
                                            <div class="card <?= $msg['sender_id'] == $uid ? 'bg-primary text-white' : 'bg-light' ?>">
                                                <div class="card-body p-2">
                                                    <p class="card-text mb-0"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                                    <small class="<?= $msg['sender_id'] == $uid ? 'text-white-50' : 'text-muted' ?>">
                                                        <?= date('H:i', strtotime($msg['created_at'])) ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted mt-5">
                                        <i class="fas fa-comments fa-3x mb-3"></i>
                                        

                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Nachrichten-Eingabe -->
                            <div class="chat-input border-top p-3">
                                <form method="post" id="messageForm">
                                    <div class="input-group">
                                        <input type="text" name="message" class="form-control" placeholder="Nachricht eingeben..." required>
                                        <button type="submit" class="btn btn-primary">Senden</button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <!-- Platzhalter, wenn kein Chat ausgewählt -->
                            <div class="d-flex align-items-center justify-content-center h-100">
                                <div class="text-center text-muted">
                                    <i class="fas fa-comments fa-3x mb-3"></i>
                                    

                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Automatisch zum Nachrichten-Bereich scrollen
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        // Nachrichten-Formular handling
        document.addEventListener('DOMContentLoaded', function() {
            scrollToBottom();

            const messageForm = document.getElementById('messageForm');
            if (messageForm) {
                messageForm.addEventListener('submit', function(e) {
                    const input = this.querySelector('input[name="message"]');
                    if (input.value.trim() === '') {
                        e.preventDefault();
                    }
                });
            }

            // Automatisches Aktualisieren der Nachrichten alle 10 Sekunden
            <?php if ($receiver_id): ?>
                setInterval(function() {
                    fetch('chat_ajax.php?action=refresh&receiver_id=<?= $receiver_id ?>')
                        .then(response => response.text())
                        .then(data => {
                            document.getElementById('chatMessages').innerHTML = data;
                            scrollToBottom();
                        });
                }, 10000);
            <?php endif; ?>
        });
    </script>
</body>

</html>