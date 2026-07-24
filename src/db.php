<?php

declare(strict_types=1);

/**
 * Sem isso, o PHP assume UTC por padrão — enquanto o MySQL usa o fuso do
 * sistema operacional (America/Sao_Paulo, UTC-3). A diferença de 3h faz o PHP
 * achar que já é "amanhã" horas antes do banco achar o mesmo, o que classifica
 * contas/dívidas como atrasadas cedo demais (bug real encontrado numa auditoria
 * de verificação: Dashboard usa CURDATE() do MySQL e acertava, mas contas.php/
 * dividas.php recalculavam o status com date('Y-m-d') do PHP e erravam).
 */
date_default_timezone_set('America/Sao_Paulo');

/**
 * Carrega as variáveis do .env pra dentro de getenv()/$_ENV, sem depender de
 * nenhuma biblioteca (mesma filosofia de "poucas dependências" do sistema original).
 */
function carregar_env(string $caminho): void
{
    if (!is_file($caminho)) {
        return;
    }

    foreach (file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        $linha = trim($linha);
        if ($linha === '' || str_starts_with($linha, '#')) {
            continue;
        }

        [$chave, $valor] = array_pad(explode('=', $linha, 2), 2, '');
        $chave = trim($chave);
        $valor = trim($valor);

        if ($chave === '') {
            continue;
        }

        putenv("{$chave}={$valor}");
        $_ENV[$chave] = $valor;
    }
}

function conexao_banco(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $nome = getenv('DB_NAME') ?: '';
    $usuario = getenv('DB_USER') ?: '';
    $senha = getenv('DB_PASS') ?: '';

    $dsn = "mysql:host={$host};port={$port};dbname={$nome};charset=utf8mb4";

    $pdo = new PDO($dsn, $usuario, $senha, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

carregar_env(__DIR__ . '/../.env');

/**
 * Erro nunca aparece na tela por padrão — só se APP_DEBUG=1 no .env (uso local).
 * Sem isso, um erro de PHP/SQL em produção vazaria caminho de arquivo, query
 * e estrutura do banco pra qualquer visitante (achado numa auditoria de segurança).
 */
$appDebug = getenv('APP_DEBUG') === '1';
error_reporting(E_ALL);
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('log_errors', '1');

/**
 * Cabeçalhos de segurança básicos, aplicados em toda página (db.php é
 * requerido bem cedo por tudo, antes de qualquer HTML ser impresso).
 * CSP libera 'unsafe-inline' em script/style de propósito: o sistema usa
 * onsubmit="return confirm(...)" e style="..." inline em vários lugares —
 * travar isso sem reescrever tudo pra JS/CSS externo quebraria a página.
 * Ainda assim já bloqueia recurso/script injetado de outro domínio.
 */
header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'");
