<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/util.php';
require_once __DIR__ . '/../src/cripto.php';

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
} elseif ($acao === 'salvar_extrato_gmail') {
    $imapEmail = trim((string) ($_POST['imap_email'] ?? ''));
    $imapSenhaApp = (string) ($_POST['imap_senha_app'] ?? '');

    if ($imapEmail === '') {
        redirecionar('/configuracoes.php?erro_extrato=' . urlencode('Preencha o e-mail do Gmail.'));
    }

    if ($imapSenhaApp !== '') {
        // Senha nova informada: atualiza e-mail + senha cifrada juntos.
        $stmt = $pdo->prepare('UPDATE familia SET imap_email = ?, imap_senha_app_cifrada = ? WHERE id = ?');
        $stmt->execute([$imapEmail, cifrar_segredo($imapSenhaApp), $familiaId]);
    } else {
        // Campo de senha em branco: mantém a senha já cadastrada, só atualiza o e-mail.
        $stmt = $pdo->prepare('UPDATE familia SET imap_email = ? WHERE id = ?');
        $stmt->execute([$imapEmail, $familiaId]);
    }

    redirecionar('/configuracoes.php?sucesso_extrato=' . urlencode('Credenciais do Gmail salvas.'));
} elseif ($acao === 'salvar_saldo_inicial') {
    $saldoInicial = parse_valor($_POST['saldo_inicial'] ?? null);
    $saldoInicialData = data_ou_null($_POST['saldo_inicial_data'] ?? null);

    $stmt = $pdo->prepare('UPDATE familia SET saldo_inicial = ?, saldo_inicial_data = ? WHERE id = ?');
    $stmt->execute([$saldoInicial, $saldoInicialData, $familiaId]);

    redirecionar('/configuracoes.php?sucesso_saldo=' . urlencode('Saldo inicial salvo.'));
}

redirecionar('/configuracoes.php');
