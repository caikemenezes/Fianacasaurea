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

$stmt = $pdo->prepare('SELECT imap_email, saldo_inicial, saldo_inicial_data FROM familia WHERE id = ?');
$stmt->execute([$familiaId]);
$familiaAtual = $stmt->fetch();
$imapEmailAtual = $familiaAtual['imap_email'];

$erroSenha = $_GET['erro_senha'] ?? null;
$sucessoSenha = $_GET['sucesso_senha'] ?? null;
$erroConvite = $_GET['erro_convite'] ?? null;
$sucessoConvite = $_GET['sucesso_convite'] ?? null;
$erroExtrato = $_GET['erro_extrato'] ?? null;
$sucessoExtrato = $_GET['sucesso_extrato'] ?? null;
$sucessoSaldo = $_GET['sucesso_saldo'] ?? null;

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

  <div class="cartao pilha-pequena">
    <?= info_icone('E-mail e senha de app do Gmail da SUA família, usados só pelo botão "Verificar extrato agora" em Contas do Mês. Cada família tem a própria credencial — a sua não é vista nem usada por outras famílias no sistema. A senha fica cifrada no banco, nunca em texto puro. É uma "Senha de app" do Google (myaccount.google.com/apppasswords), não a senha normal da conta — exige verificação em duas etapas ativada.') ?>
    <h2 class="cartao-titulo">Extrato automático (Gmail)</h2>
    <p class="texto-suave" style="font-size:0.8rem;margin:0">
      <?= $imapEmailAtual !== null && $imapEmailAtual !== ''
        ? 'Configurado: ' . htmlspecialchars($imapEmailAtual, ENT_QUOTES, 'UTF-8')
        : 'Ainda não configurado — o botão "Verificar extrato" não funciona até isso ser preenchido.' ?>
    </p>

    <?php if ($erroExtrato): ?><p class="texto-suave" style="color:var(--perigo)"><?= htmlspecialchars($erroExtrato, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($sucessoExtrato): ?><p class="texto-suave" style="color:var(--sucesso)"><?= htmlspecialchars($sucessoExtrato, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <form method="post" action="/configuracoes-processar.php" class="form-grade">
      <?= csrf_campo_oculto($usuario_atual) ?>
      <input type="hidden" name="acao" value="salvar_extrato_gmail">
      <input name="imap_email" type="email" placeholder="E-mail do Gmail" required value="<?= htmlspecialchars((string) $imapEmailAtual, ENT_QUOTES, 'UTF-8') ?>" class="campo">
      <input name="imap_senha_app" type="password" placeholder="<?= $imapEmailAtual !== null && $imapEmailAtual !== '' ? 'Senha de app (deixe em branco pra manter a atual)' : 'Senha de app' ?>" class="campo">
      <button type="submit" class="botao">Salvar</button>
    </form>
  </div>

  <div class="cartao pilha-pequena">
    <?= info_icone('Normalmente não precisa mexer aqui: o sistema já lê o saldo real da conta direto do extrato (arquivo OFX de cada e-mail, que o próprio Nubank informa) toda vez que "Verificar extrato" roda. Esse campo é só uma reserva pra quando ainda não tiver nenhum extrato processado — ex: primeiro uso do sistema, antes do primeiro "Verificar extrato".') ?>
    <h2 class="cartao-titulo">Saldo inicial (reserva)</h2>
    <p class="texto-suave" style="font-size:0.8rem;margin:0">
      <?= (float) $familiaAtual['saldo_inicial'] !== 0.0
        ? 'Atual: ' . formatar_moeda((float) $familiaAtual['saldo_inicial']) . ($familiaAtual['saldo_inicial_data'] !== null ? ' (em ' . formatar_data($familiaAtual['saldo_inicial_data']) . ')' : '')
        : 'Ainda não configurado (considerando R$ 0,00 antes do extrato começar).' ?>
    </p>

    <?php if ($sucessoSaldo): ?><p class="texto-suave" style="color:var(--sucesso)"><?= htmlspecialchars($sucessoSaldo, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <form method="post" action="/configuracoes-processar.php" class="form-grade">
      <?= csrf_campo_oculto($usuario_atual) ?>
      <input type="hidden" name="acao" value="salvar_saldo_inicial">
      <input name="saldo_inicial" placeholder="Saldo inicial" type="text" inputmode="decimal" data-moeda value="<?= formatar_valor_input((float) $familiaAtual['saldo_inicial']) ?>" class="campo">
      <input name="saldo_inicial_data" type="date" value="<?= htmlspecialchars((string) $familiaAtual['saldo_inicial_data'], ENT_QUOTES, 'UTF-8') ?>" class="campo">
      <button type="submit" class="botao">Salvar</button>
    </form>
  </div>
</div>

<?php
fechar_layout();
