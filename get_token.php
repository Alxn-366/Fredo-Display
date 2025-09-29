<?php
header('Content-Type: application/json');

// Ersetze mit deinem tatsächlichen AssemblyAI API-Key
$api_key = '49e31199b8b4483088ea138268816af6';

// cURL-Anfrage an AssemblyAI, um ein temporäres Token zu erhalten
$ch = curl_init('https://api.assemblyai.com/v2/realtime/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $api_key,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['expires_in' => 3600]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    echo $response;
} else {
    http_response_code($http_code);
    echo json_encode(['error' => 'Fehler beim Abrufen des Tokens']);
}
