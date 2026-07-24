<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('/configuracoes.php');
}

$pdo = conexao_banco();
$familiaId = (int) $usuario_atual['familia_id'];
$acao = $_POST['acao'] ?? '';

if ($acao === 'alterar_senha') {
    $senhaAtual = (string) ($_POST['senha_atual'] ?? '');
    $novaSenha = (string) ($_POST['nova_senha'] ?? '');

    $stmt = $pdo->prepare('SELECT senha_hash FROM usuario WHERE id = ?');
    $stmt->execute([$usuario_atual['id']]);
    $hashAtual = $stmt->fetch()['senha_hash'];

    if (!password_verify($senhaAtual, $hashAtual)) {
        redirecionar('/configuracoes.php?erro_senha=' . urlencode('Senha atual incorreta.'));
    }
    if (strlen($novaSenha) < 8) {
        redirecionar('/configuracoes.php?erro_senha=' . urlencode('A nova senha precisa ter pelo menos 8 caracteres.'));
    }

    $stmt = $pdo->prepare('UPDATE usuario SET senha_hash = ? WHERE id = ?');
    $stmt->execute([hash_senha($novaSenha), $usuario_atual['id']]);
    redirecionar('/configuracoes.php?sucesso_senha=' . urlencode('Senha alterada com sucesso.'));
} elseif ($acao === 'convidar') {
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');

    if ($nome === '' || $email === '' || strlen($senha) < 8) {
        redirecionar('/configuracoes.php?erro_convite=' . urlencode('Preencha nome, e-mail e uma senha com pelo menos 8 caracteres.'));
    }

    $stmt = $pdo->prepare('SELECT id FROM usuario WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch() !== false) {
        redirecionar('/configuracoes.php?erro_convite=' . urlencode('Já existe uma conta com esse e-mail.'));
    }

    $stmt = $pdo->prepare('INSERT INTO usuario (familia_id, nome, email, senha_hash) VALUES (?, ?, ?, ?)');
    $stmt->execute([$familiaId, $nome, $email, hash_senha($senha)]);
    redirecionar('/configuracoes.php?sucesso_convite=' . urlencode("Conta criada para {$nome}."));
}

redirecionar('/configuracoes.php');
