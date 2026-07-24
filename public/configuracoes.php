<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/layout.php';
require_once __DIR__ . '/../src/util.php';

$pdo = conexao_banco();
$familiaId = (int) $usuario_atual['familia_id'];

$stmt = $pdo->prepare('SELECT id, nome, email, criado_em FROM usuario WHERE familia_id = ? ORDER BY criado_em ASC');
$stmt->execute([$familiaId]);
$membrosFamilia = $stmt->fetchAll();

$erroSenha = $_GET['erro_senha'] ?? null;
$sucessoSenha = $_GET['sucesso_senha'] ?? null;
$erroConvite = $_GET['erro_convite'] ?? null;
$sucessoConvite = $_GET['sucesso_convite'] ?? null;

layout_topo($usuario_atual, '', 'Configurações');
layout_rodape($usuario_atual);
?>

<div class="pilha">
  <div>
    <h1 class="pagina-titulo">Configurações</h1>
    <p class="pagina-subtitulo">Sua conta e acesso da família.</p>
  </div>

  <div class="cartao pilha-pequena">
    <?= info_icone('Seus dados de login. Pra trocar a senha, é preciso confirmar a senha atual primeiro.') ?>
    <h2 class="cartao-titulo">Sua conta</h2>
    <p class="texto-suave" style="font-size:0.85rem;margin:0">
      <?= htmlspecialchars($usuario_atual['nome'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($usuario_atual['email'], ENT_QUOTES, 'UTF-8') ?>
    </p>

    <?php if ($erroSenha): ?><p class="texto-suave" style="color:var(--perigo)"><?= htmlspecialchars($erroSenha, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($sucessoSenha): ?><p class="texto-suave" style="color:var(--sucesso)"><?= htmlspecialchars($sucessoSenha, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <form method="post" action="/configuracoes-processar.php" class="pilha-pequena">
      <?= csrf_campo_oculto($usuario_atual) ?>
      <input type="hidden" name="acao" value="alterar_senha">
      <input name="senha_atual" type="password" placeholder="Senha atual" required class="campo">
      <input name="nova_senha" type="password" placeholder="Nova senha (mín. 8 caracteres)" required minlength="8" class="campo">
      <button type="submit" class="botao" style="align-self:flex-start">Alterar senha</button>
    </form>
  </div>

  <div class="cartao pilha-pequena">
    <?= info_icone('Quem pode entrar no sistema. Crie um acesso pra cada pessoa da família com nome, e-mail e uma senha inicial — depois cada um pode trocar a própria senha.') ?>
    <h2 class="cartao-titulo">Família com acesso</h2>
    <p class="texto-suave" style="font-size:0.8rem;margin:0">Quem já tem login neste sistema.</p>

    <ul class="lista-simples">
      <?php foreach ($membrosFamilia as $membro): ?>
        <li>
          <div>
            <strong><?= htmlspecialchars($membro['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
            <span class="texto-suave"> · <?= htmlspecialchars($membro['email'], ENT_QUOTES, 'UTF-8') ?> · desde <?= formatar_data($membro['criado_em']) ?></span>
          </div>
          <?php if ((int) $membro['id'] === (int) $usuario_atual['id']): ?><span class="selo selo-info">Você</span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if ($erroConvite): ?><p class="texto-suave" style="color:var(--perigo)"><?= htmlspecialchars($erroConvite, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($sucessoConvite): ?><p class="texto-suave" style="color:var(--sucesso)"><?= htmlspecialchars($sucessoConvite, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <form method="post" action="/configuracoes-processar.php" class="form-grade">
      <?= csrf_campo_oculto($usuario_atual) ?>
      <input type="hidden" name="acao" value="convidar">
      <input name="nome" placeholder="Nome da pessoa" required class="campo">
      <input name="email" type="email" placeholder="E-mail" required class="campo">
      <input name="senha" type="password" placeholder="Senha inicial (mín. 8 caracteres)" required minlength="8" class="campo">
      <button type="submit" class="botao">Criar acesso</button>
    </form>
  </div>
</div>

<?php
fechar_layout();
