<?php
session_start();
require_once __DIR__ . '/../src/db.php';

if (!isset($_SESSION['user_id'])) {
    exit;
}

$uid = (int)$_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$receiver_id = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : null;

if ($action === 'refresh' && $receiver_id) {
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

    // Nachrichten ausgeben
    foreach ($messages as $msg): ?>
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
    <?php endforeach;

    if (empty($messages)): ?>
        <div class="text-center text-muted mt-5">
            Noch keine Nachrichten.
        </div>
<?php endif;
}

if ($action === 'get_unread_count') {
    // Ungelesene Nachrichten zählen
    $unread_stmt = $pdo->prepare("SELECT COUNT(*) as unread_count FROM chat_messages WHERE receiver_id = ? AND is_read = 0");
    $unread_stmt->execute([$uid]);
    $unread = $unread_stmt->fetch();

    echo $unread['unread_count'] > 0 ? $unread['unread_count'] : '';
}
?>