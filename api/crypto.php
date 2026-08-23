<?php
declare(strict_types=1);

final class CryptoException extends RuntimeException {}

function encryptionKey(): string {
    static $key;
    if (is_string($key)) return $key;
    $encoded = envValue('APP_ENCRYPTION_KEY');
    if (!is_string($encoded) || $encoded === '') throw new CryptoException('APP_ENCRYPTION_KEY fehlt. Erwartet werden 32 zufällige Bytes in Base64.');
    $decoded = base64_decode($encoded, true);
    if ($decoded === false || strlen($decoded) !== 32 || base64_encode($decoded) !== $encoded) throw new CryptoException('APP_ENCRYPTION_KEY ist ungültig. Erwartet werden exakt 32 zufällige Bytes in kanonischem Base64.');
    return $key = $decoded;
}

function encryptSecret(string $plaintext): string {
    if ($plaintext === '') throw new CryptoException('Ein leeres Secret kann nicht verschlüsselt werden.');
    $iv = random_bytes(12); $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', encryptionKey(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($ciphertext === false || strlen($tag) !== 16) throw new CryptoException('Secret konnte nicht verschlüsselt werden.');
    $payload = ['v'=>1,'alg'=>'A256GCM','iv'=>base64_encode($iv),'tag'=>base64_encode($tag),'ct'=>base64_encode($ciphertext)];
    return 'spsec:' . base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

function decryptSecret(string $stored): string {
    if (!str_starts_with($stored, 'spsec:')) throw new CryptoException('Unbekanntes Secret-Format.');
    $json = base64_decode(substr($stored, 6), true);
    if ($json === false) throw new CryptoException('Beschädigtes Secret-Format.');
    try { $payload = json_decode($json, true, 8, JSON_THROW_ON_ERROR); } catch (JsonException) { throw new CryptoException('Beschädigtes Secret-Format.'); }
    if (!is_array($payload) || ($payload['v'] ?? null) !== 1 || ($payload['alg'] ?? null) !== 'A256GCM') throw new CryptoException('Nicht unterstützte Secret-Version.');
    foreach (['iv','tag','ct'] as $field) if (!isset($payload[$field]) || !is_string($payload[$field])) throw new CryptoException('Unvollständiges Secret-Format.');
    $iv=base64_decode($payload['iv'],true); $tag=base64_decode($payload['tag'],true); $ciphertext=base64_decode($payload['ct'],true);
    if($iv===false||strlen($iv)!==12||$tag===false||strlen($tag)!==16||$ciphertext===false) throw new CryptoException('Beschädigtes Secret-Format.');
    $plaintext=openssl_decrypt($ciphertext,'aes-256-gcm',encryptionKey(),OPENSSL_RAW_DATA,$iv,$tag);
    if($plaintext===false) throw new CryptoException('Secret-Authentifizierung fehlgeschlagen.');
    return $plaintext;
}
