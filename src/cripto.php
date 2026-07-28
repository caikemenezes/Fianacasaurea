<?php

declare(strict_types=1);

/**
 * Cifra/decifra segredos (hoje só a senha de app do Gmail de cada família)
 * antes de gravar no banco — AES-256-GCM com a chave em APP_ENCRYPTION_KEY
 * (.env). GCM autentica o conteúdo (detecta se o dado foi alterado/corrompido),
 * diferente de um AES-CBC simples. IV novo a cada chamada, guardado junto do
 * resultado (não precisa ser secreto, só não pode repetir com a mesma chave).
 *
 * Se a chave do .env mudar ou sumir, tudo que já foi cifrado com a chave
 * antiga vira ilegível — por isso ela precisa ser gerada uma vez e mantida.
 */

function chave_criptografia(): string
{
    $chaveBase64 = getenv('APP_ENCRYPTION_KEY') ?: '';
    if ($chaveBase64 === '') {
        throw new RuntimeException('APP_ENCRYPTION_KEY não configurada no .env.');
    }

    $chave = base64_decode($chaveBase64, true);
    if ($chave === false || strlen($chave) !== 32) {
        throw new RuntimeException('APP_ENCRYPTION_KEY inválida — precisa ser 32 bytes em base64.');
    }

    return $chave;
}

function cifrar_segredo(string $textoPuro): string
{
    $chave = chave_criptografia();
    $iv = random_bytes(12); // 96 bits, tamanho recomendado pro GCM
    $tag = '';

    $cifrado = openssl_encrypt($textoPuro, 'aes-256-gcm', $chave, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cifrado === false) {
        throw new RuntimeException('Falha ao cifrar segredo.');
    }

    return base64_encode($iv . $tag . $cifrado);
}

function decifrar_segredo(string $cifradoBase64): ?string
{
    $chave = chave_criptografia();
    $bruto = base64_decode($cifradoBase64, true);
    if ($bruto === false || strlen($bruto) < 12 + 16) {
        return null;
    }

    $iv = substr($bruto, 0, 12);
    $tag = substr($bruto, 12, 16);
    $cifrado = substr($bruto, 28);

    $textoPuro = openssl_decrypt($cifrado, 'aes-256-gcm', $chave, OPENSSL_RAW_DATA, $iv, $tag);
    return $textoPuro === false ? null : $textoPuro;
}
