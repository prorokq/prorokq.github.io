<?php
declare(strict_types=1);

header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: application/json; charset=utf-8');
    echo '{"ok":false,"error":"method_not_allowed"}';
    exit;
}

if (!function_exists('curl_init')) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"ok":false,"error":"relay_unavailable"}';
    exit;
}

$body = file_get_contents('php://input');
if ($body === false) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"ok":false,"error":"bad_request"}';
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? 'application/octet-stream';
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
$responseHeaders = [];
$curl = curl_init('https://dezmarshall.mikhailparfenovmail-ru.workers.dev/api/lead');

curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: ' . $contentType,
        'Origin: https://dezmarshall.ru',
        'X-Forwarded-For: ' . $clientIp,
    ],
    CURLOPT_HEADERFUNCTION => static function ($curlHandle, string $line) use (&$responseHeaders): int {
        $length = strlen($line);
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
        }
        return $length;
    },
]);

$responseBody = curl_exec($curl);
$status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
$curlError = curl_error($curl);
curl_close($curl);

if ($responseBody === false || $status === 0) {
    error_log('DezMarshall lead relay failed: ' . $curlError);
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"ok":false,"error":"upstream_unavailable"}';
    exit;
}

http_response_code($status);
header('Content-Type: ' . ($responseHeaders['content-type'] ?? 'application/json; charset=utf-8'));
if (isset($responseHeaders['location']) && strpos($responseHeaders['location'], 'https://dezmarshall.ru/') === 0) {
    header('Location: ' . $responseHeaders['location']);
}
echo $responseBody;
