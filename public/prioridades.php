<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/layout.php';
require_once __DIR__ . '/../src/util.php';
require_once __DIR__ . '/../src/charts.php';

const CATEGORIAS_PRIORIDADE = [
    'Alimentação', 'Fraldas', 'Higiene', 'Roupas', 'Calçados', 'Saúde', 'Medicamentos', 'Brinquedos',
    'Passeios', 'Educação', 'Escola', 'Material escolar', 'Transporte', 'Atividades', 'Festas e aniversários',
];
const PRIORIDADE_SELO = ['URGENTE' => 'selo-perigo', 'ALTA' => 'selo-alerta', 'MEDIA' => 'selo-info', 'BAIXA' => 'selo-neutro'];
const PRIORIDADE_LABEL = ['URGENTE' => 'Urgente', 'ALTA' => 'Alta', 'MEDIA' => 'Média', 'BAIXA' => 'Baixa'];

$pdo = conexao_banco();
$familiaId = (int) $usuario_atual['familia_id'];

$stmt = $pdo->prepare(
    'SELECT n.*, fm.nome AS membro_nome FROM necessidade n LEFT JOIN familia_membro fm ON fm.id = n.familia_membro_id
     WHERE n.familia_id = ? AND n.status != "CANCELADA" ORDER BY FIELD(n.prioridade, "URGENTE","ALTA","MEDIA","BAIXA"), n.mes_planejado ASC'
);
$stmt->execute([$familiaId]);
$itens = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT id, nome FROM familia_membro WHERE familia_id = ? ORDER BY nome ASC');
$stmt->execute([$familiaId]);
$membros = $stmt->fetchAll();

layout_topo($usuario_atual, 'prioridades', 'Prioridades');
layout_rodape($usuario_atual);
?>

<div class="pilha">
  <div>
    <h1 class="pagina-titulo">Prioridades</h1>
    <p class="pagina-subtitulo">Necessidades urgentes da família, com quanto falta guardar por mês.</p>
  </div>

  <form method="post" action="/prioridades-processar.php" class="cartao form-grade">
    <?= csrf_campo_oculto($usuario_atual) ?>
    <?= info_icone('Cadastre aqui uma necessidade urgente e mais pontual (tênis, mamadeira, material escolar), diferente de uma meta maior. Digite o nome de qualquer pessoa, mesmo que não esteja cadastrada em Família e Filhos.') ?>
    <input type="hidden" name="acao" value="criar">
    <input name="item" placeholder="Item (ex: Tênis)" required class="campo">
    <input name="pessoa_nome" placeholder="Nome da pessoa (opcional)" list="prioridades-sugestoes-nome" class="campo">
    <datalist id="prioridades-sugestoes-nome">
      <?php foreach ($membros as $membro): ?>
        <option value="<?= htmlspecialchars($membro['nome'], ENT_QUOTES, 'UTF-8') ?>">
      <?php endforeach; ?>
    </datalist>
    <select name="categoria" required class="campo">
      <option value="" disabled selected>Categoria</option>
      <?php foreach (CATEGORIAS_PRIORIDADE as $categoria): ?>
        <option value="<?= htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
    <select name="prioridade" class="campo">
      <option value="URGENTE">Urgente</option>
      <option value="ALTA">Alta</option>
      <option value="MEDIA" selected>Média</option>
      <option value="BAIXA">Baixa</option>
    </select>
    <input name="valor_estimado" placeholder="Valor estimado" type="text" inputmode="decimal" data-moeda required class="campo">
    <input name="mes_planejado" type="date" required class="campo">
    <button type="submit" class="botao botao-abrange-linha">Adicionar prioridade</button>
  </form>

  <div class="pilha-pequena">
    <?php foreach ($itens as $item):
      $valorRestante = max(0, (float) $item['valor_estimado'] - (float) $item['valor_guardado']);
      $meses = meses_restantes($item['mes_planejado']) ?? 1;
      $valorMensal = $valorRestante / $meses;
      $progresso = min(100, (int) round(((float) $item['valor_guardado'] / (float) $item['valor_estimado']) * 100));
      $quem = $item['membro_nome'] ?: $item['pessoa_nome'];
    ?>
      <div class="cartao pilha-pequena">
        <div class="linha-flex">
          <div>
            <h3 style="margin:0"><?= htmlspecialchars($item['item'], ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="texto-suave" style="font-size:0.8rem;margin:0.2rem 0 0">
              <?= htmlspecialchars($item['categoria'], ENT_QUOTES, 'UTF-8') ?>
              <?= $quem ? ' · ' . htmlspecialchars($quem, ENT_QUOTES, 'UTF-8') : '' ?>
              · até <?= formatar_mes_ano($item['mes_planejado']) ?>
              · <span class="selo <?= PRIORIDADE_SELO[$item['prioridade']] ?>"><?= PRIORIDADE_LABEL[$item['prioridade']] ?></span>
            </p>
          </div>
          <form method="post" action="/prioridades-processar.php" onsubmit="return confirm('Excluir esta prioridade?');">
            <?= csrf_campo_oculto($usuario_atual) ?>
            <input type="hidden" name="acao" value="excluir">
            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
            <button class="link-acao link-perigo">Excluir</button>
          </form>
        </div>

        <div class="progresso-trilho"><div class="progresso-barra" style="width: <?= $progresso ?>%"></div></div>

        <div class="grupo-valores">
          <div><p class="stat-rotulo">Estimado</p><p class="stat-valor" style="font-size:1rem"><?= formatar_moeda((float) $item['valor_estimado']) ?></p></div>
          <div><p class="stat-rotulo">Guardado</p><p class="stat-valor" style="font-size:1rem"><?= formatar_moeda((float) $item['valor_guardado']) ?></p></div>
          <div><p class="stat-rotulo">Falta</p><p class="stat-valor" style="font-size:1rem"><?= formatar_moeda($valorRestante) ?></p></div>
          <?php if ($valorRestante > 0): ?>
            <div><p class="stat-rotulo">Guardar por mês</p><p class="stat-valor" style="font-size:1rem"><?= formatar_moeda($valorMensal) ?></p></div>
          <?php endif; ?>
        </div>

        <?php if ($valorRestante > 0): ?>
          <form method="post" action="/prioridades-processar.php" class="linha-flex" style="justify-content:flex-start">
            <?= csrf_campo_oculto($usuario_atual) ?>
            <input type="hidden" name="acao" value="aportar">
            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
            <input name="valor_aporte" type="text" inputmode="decimal" data-moeda placeholder="Valor a guardar agora" required class="campo" style="width:12rem">
            <button class="botao botao-pequeno">Reservar valor</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (count($itens) === 0): ?>
      <p class="texto-suave">Nenhuma prioridade cadastrada ainda.</p>
    <?php endif; ?>
  </div>
</div>

<?php
fechar_layout();
