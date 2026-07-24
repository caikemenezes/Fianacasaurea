<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const NOME_COOKIE_SESSAO = 'sessao';
const DIAS_VALIDADE_SESSAO = 30;

/**
 * Hash usado só pra gastar o mesmo tempo de CPU quando o e-mail digitado
 * não existe no banco — evita descobrir e-mails cadastrados por timing attack
 * (mesma mitigação feita no sistema original, ver "Login e Autenticação" no vault).
 */
const HASH_FALSO_TIMING = '$2y$10$/C2YZRru8rVl1Hn0rX6mAer5rzFS4XbElXr.O5X6ntv5WBUkZs2a6';

function hash_senha(string $senha): string
{
    return password_hash($senha, PASSWORD_DEFAULT);
}

/**
 * Cria uma família nova + o primeiro usuário dela, numa transação.
 * Cadastro público fica sempre aberto: cada cadastro isola uma família nova,
 * a segurança não depende de "fechar o cadastro depois do primeiro usuário".
 */
function criar_conta(string $nome, string $email, string $senha): int
{
    $pdo = conexao_banco();

    $existente = $pdo->prepare('SELECT id FROM usuario WHERE email = ?');
    $existente->execute([$email]);
    if ($existente->fetch() !== false) {
        throw new RuntimeException('Já existe uma conta com esse e-mail.');
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec('INSERT INTO familia () VALUES ()');
        $familiaId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO usuario (familia_id, nome, email, senha_hash) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$familiaId, $nome, $email, hash_senha($senha)]);
        $usuarioId = (int) $pdo->lastInsertId();

        $pdo->commit();
        return $usuarioId;
    } catch (Throwable $erro) {
        $pdo->rollBack();
        throw $erro;
    }
}

const LIMITE_TENTATIVAS_LOGIN = 5;
const JANELA_TENTATIVAS_LOGIN_MINUTOS = 15;

/**
 * Proteção contra força bruta: bloqueia por e-mail (não por IP, já que o
 * objetivo é impedir alguém de tentar adivinhar a senha de UMA conta-alvo,
 * mesmo trocando de IP) depois de várias tentativas erradas seguidas.
 */
function bloqueado_por_tentativas(string $email): bool
{
    $pdo = conexao_banco();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS qtd FROM tentativa_login_falha
         WHERE email = ? AND criado_em > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
    );
    $stmt->execute([$email, JANELA_TENTATIVAS_LOGIN_MINUTOS]);
    return (int) $stmt->fetch()['qtd'] >= LIMITE_TENTATIVAS_LOGIN;
}

function registrar_tentativa_falha(string $email): void
{
    $pdo = conexao_banco();
    $stmt = $pdo->prepare('INSERT INTO tentativa_login_falha (email, ip) VALUES (?, ?)');
    $stmt->execute([$email, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
}

function limpar_tentativas_falhas(string $email): void
{
    $pdo = conexao_banco();
    $stmt = $pdo->prepare('DELETE FROM tentativa_login_falha WHERE email = ?');
    $stmt->execute([$email]);
}

/**
 * Confere e-mail/senha. Sempre roda password_verify (mesmo quando o e-mail
 * não existe) pra manter o tempo de resposta igual nos dois casos.
 *
 * @return array|null dados do usuário autenticado, ou null se inválido
 */
function autenticar(string $email, string $senha): ?array
{
    $pdo = conexao_banco();

    $stmt = $pdo->prepare('SELECT id, familia_id, nome, email, senha_hash FROM usuario WHERE email = ?');
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    $hashParaConferir = $usuario['senha_hash'] ?? HASH_FALSO_TIMING;
    $senhaValida = password_verify($senha, $hashParaConferir);

    if ($usuario === false || !$senhaValida) {
        usleep(300_000); // mesmo pequeno atraso do original em toda tentativa falhada
        registrar_tentativa_falha($email);
        return null;
    }

    limpar_tentativas_falhas($email);
    unset($usuario['senha_hash']);
    return $usuario;
}

function criar_sessao(int $usuarioId): void
{
    $pdo = conexao_banco();

    $id = bin2hex(random_bytes(32));
    $csrfToken = bin2hex(random_bytes(32));
    $expiraEm = (new DateTimeImmutable('+' . DIAS_VALIDADE_SESSAO . ' days'))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare('INSERT INTO sessao (id, usuario_id, expira_em, csrf_token) VALUES (?, ?, ?, ?)');
    $stmt->execute([$id, $usuarioId, $expiraEm, $csrfToken]);

    setcookie(NOME_COOKIE_SESSAO, $id, [
        'expires' => time() + DIAS_VALIDADE_SESSAO * 24 * 60 * 60,
        'path' => '/',
        'httponly' => true,
        'secure' => getenv('APP_SECURE_COOKIE') === '1',
        'samesite' => 'Lax',
    ]);
}

function destruir_sessao(): void
{
    $id = $_COOKIE[NOME_COOKIE_SESSAO] ?? null;

    if ($id !== null) {
        $pdo = conexao_banco();
        $stmt = $pdo->prepare('DELETE FROM sessao WHERE id = ?');
        $stmt->execute([$id]);
    }

    setcookie(NOME_COOKIE_SESSAO, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'secure' => getenv('APP_SECURE_COOKIE') === '1',
        'samesite' => 'Lax',
    ]);
}

/**
 * @return array|null usuário logado (id, familia_id, nome, email, csrf_token) ou null
 */
function obter_usuario_atual(): ?array
{
    $id = $_COOKIE[NOME_COOKIE_SESSAO] ?? null;
    if ($id === null) {
        return null;
    }

    $pdo = conexao_banco();
    $stmt = $pdo->prepare(
        'SELECT u.id, u.familia_id, u.nome, u.email, s.csrf_token
         FROM sessao s
         JOIN usuario u ON u.id = s.usuario_id
         WHERE s.id = ? AND s.expira_em > NOW()'
    );
    $stmt->execute([$id]);
    $usuario = $stmt->fetch();

    return $usuario === false ? null : $usuario;
}

/** Campo oculto pronto pra colar em qualquer <form method="post"> autenticado. */
function csrf_campo_oculto(array $usuarioAtual): string
{
    $token = htmlspecialchars((string) ($usuarioAtual['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Confere o token enviado no POST contra o gravado na sessão (comparação em
 * tempo constante). Chamado centralizado em auth_guard.php pra todo POST
 * autenticado — nenhum *-processar.php precisa lembrar de checar isso sozinho.
 */
function csrf_valido(string $tokenEnviado, ?string $tokenEsperado): bool
{
    return $tokenEsperado !== null && $tokenEsperado !== '' && hash_equals($tokenEsperado, $tokenEnviado);
}

/**
 * Equivalente ao exigirUsuarioAtual() do lib/auth.ts original: usar no topo
 * de toda página protegida. Redireciona pra /login.php se não houver sessão válida.
 */
function exigir_usuario_atual(): array
{
    $usuario = obter_usuario_atual();

    if ($usuario === null) {
        header('Location: /login.php');
        exit;
    }

    return $usuario;
}
