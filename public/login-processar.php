<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login.php');
    exit;
}

function redirecionar_com_erro(string $aba, string $mensagem): never
{
    $query = http_build_query(['aba' => $aba, 'erro' => $mensagem]);
    header("Location: /login.php?{$query}");
    exit;
}

$acao = $_POST['acao'] ?? '';

if ($acao === 'criar_conta') {
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');

    if ($nome === '' || $email === '' || strlen($senha) < 8) {
        redirecionar_com_erro('criar_conta', 'Preencha nome, e-mail e uma senha com pelo menos 8 caracteres.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirecionar_com_erro('criar_conta', 'E-mail inválido.');
    }

    try {
        $usuarioId = criar_conta($nome, $email, $senha);
    } catch (RuntimeException $erro) {
        redirecionar_com_erro('criar_conta', $erro->getMessage());
    }

    criar_sessao($usuarioId);
    header('Location: /index.php');
    exit;
}

if ($acao === 'entrar') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');

    if (bloqueado_por_tentativas($email)) {
        redirecionar_com_erro('entrar', 'Muitas tentativas erradas. Tente de novo em alguns minutos.');
    }

    $usuario = autenticar($email, $senha);

    if ($usuario === null) {
        redirecionar_com_erro('entrar', 'E-mail ou senha inválidos.');
    }

    criar_sessao($usuario['id']);
    header('Location: /index.php');
    exit;
}

header('Location: /login.php');
exit;
