<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

if (obter_usuario_atual() !== null) {
    header('Location: /index.php');
    exit;
}

$erro = isset($_GET['erro']) ? htmlspecialchars($_GET['erro'], ENT_QUOTES, 'UTF-8') : null;
$aba = ($_GET['aba'] ?? 'entrar') === 'criar_conta' ? 'criar_conta' : 'entrar';
?>
<!DOCTYPE html>
<html lang="pt-BR" data-tema="escuro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Entrar — Áurea</title>
<link rel="icon" href="/favicon.png" type="image/png">
<link rel="stylesheet" href="/assets/dashboard.css">
</head>
<body>
  <div class="tela-login">
    <div class="tela-login-marca">
      <span class="marca-icone marca-icone-grande"><img src="/assets/porquinho.png" alt=""></span>
    </div>
    <div style="text-align:center">
      <h1 class="pagina-titulo">Áurea</h1>
      <p class="pagina-subtitulo"><?= $aba === 'entrar' ? 'Entre com sua conta pra continuar.' : 'Crie sua conta pra começar.' ?></p>
    </div>

    <div class="cartao pilha-pequena tela-login-cartao">
      <?php if ($erro !== null): ?>
        <p class="tela-login-erro"><?= $erro ?></p>
      <?php endif; ?>

      <?php if ($aba === 'entrar'): ?>
        <form method="post" action="/login-processar.php" class="pilha-pequena">
          <input type="hidden" name="acao" value="entrar">
          <input type="email" name="email" placeholder="E-mail" required autofocus class="campo">
          <input type="password" name="senha" placeholder="Senha" required class="campo">
          <button type="submit" class="botao">Entrar</button>
        </form>
      <?php else: ?>
        <form method="post" action="/login-processar.php" class="pilha-pequena">
          <input type="hidden" name="acao" value="criar_conta">
          <input type="text" name="nome" placeholder="Seu nome" required autofocus class="campo">
          <input type="email" name="email" placeholder="E-mail" required class="campo">
          <input type="password" name="senha" placeholder="Crie uma senha (mín. 8 caracteres)" required minlength="8" class="campo">
          <p class="texto-suave tela-login-aviso">
            Isso cria um espaço novo e separado, só seu — não entra em nenhuma família existente.
            Pra se juntar à família de alguém, peça um convite em Configurações pra essa pessoa.
          </p>
          <button type="submit" class="botao botao-claro">Criar conta</button>
        </form>
      <?php endif; ?>

      <a href="?aba=<?= $aba === 'entrar' ? 'criar_conta' : 'entrar' ?>" class="link-acao tela-login-alternar">
        <?= $aba === 'entrar' ? 'Não tem conta? Criar conta' : 'Já tem conta? Entrar' ?>
      </a>
    </div>
  </div>
</body>
</html>
