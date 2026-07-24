<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/layout.php';
require_once __DIR__ . '/../src/util.php';
require_once __DIR__ . '/../src/charts.php';

const MESES_LABEL_CURTO = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

$pdo = conexao_banco();
$familiaId = (int) $usuario_atual['familia_id'];

$anoAtual = (int) date('Y');
$ano = isset($_GET['ano']) && preg_match('/^\d{4}$/', (string) $_GET['ano']) ? (int) $_GET['ano'] : $anoAtual;

$stmt = $pdo->prepare(
    'SELECT MONTH(data_recebimento) AS mes, SUM(valor_recebido) AS total FROM receita
     WHERE familia_id = ? AND status = "RECEBIDO" AND YEAR(data_recebimento) = ? GROUP BY MONTH(data_recebimento)'
);
$stmt->execute([$familiaId, $ano]);
$receitasPorMes = [];
foreach ($stmt->fetchAll() as $linha) {
    $receitasPorMes[(int) $linha['mes']] = (float) $linha['total'];
}

$stmt = $pdo->prepare(
    'SELECT MONTH(paga_em) AS mes, SUM(valor) AS total FROM conta_mes
     WHERE familia_id = ? AND status = "PAGA" AND YEAR(paga_em) = ? GROUP BY MONTH(paga_em)'
);
$stmt->execute([$familiaId, $ano]);
$despesasPorMes = [];
foreach ($stmt->fetchAll() as $linha) {
    $despesasPorMes[(int) $linha['mes']] = (float) $linha['total'];
}

$pontosMensais = [];
for ($mes = 1; $mes <= 12; $mes++) {
    $pontosMensais[] = ['mes' => $mes, 'label' => MESES_LABEL_CURTO[$mes - 1], 'receitas' => $receitasPorMes[$mes] ?? 0.0, 'despesas' => $despesasPorMes[$mes] ?? 0.0];
}

$totalReceitasAno = array_sum(array_column($pontosMensais, 'receitas'));
$totalDespesasAno = array_sum(array_column($pontosMensais, 'despesas'));
$saldoAno = $totalReceitasAno - $totalDespesasAno;

$stmt = $pdo->prepare('SELECT categoria, SUM(valor) AS total FROM conta_mes WHERE familia_id = ? AND YEAR(vencimento) = ? GROUP BY categoria');
$stmt->execute([$familiaId, $ano]);
$gastosPorCategoria = [];
foreach ($stmt->fetchAll() as $linha) {
    $gastosPorCategoria[$linha['categoria']] = (float) $linha['total'];
}

layout_topo($usuario_atual, 'relatorios', 'Relatórios');
layout_rodape($usuario_atual);
?>

<div class="pilha">
  <div class="linha-flex">
    <div>
      <h1 class="pagina-titulo">Relatórios</h1>
      <p class="pagina-subtitulo">Resumo do ano — receitas, despesas e categorias.</p>
    </div>
    <div class="seletor-periodo">
      <a href="/relatorios.php?ano=<?= $ano - 1 ?>" class="botao-icone"><?= icone('seta-esquerda') ?></a>
      <span><?= $ano ?></span>
      <a href="/relatorios.php?ano=<?= $ano + 1 ?>" class="botao-icone"><?= icone('seta-direita') ?></a>
    </div>
  </div>

  <div class="stats-grade">
    <div class="cartao">
      <p class="stat-rotulo">Receitas recebidas no ano</p>
      <p class="stat-valor stat-positivo"><?= formatar_moeda($totalReceitasAno) ?></p>
    </div>
    <div class="cartao">
      <p class="stat-rotulo">Contas pagas no ano</p>
      <p class="stat-valor stat-negativo"><?= formatar_moeda($totalDespesasAno) ?></p>
    </div>
    <div class="cartao">
      <p class="stat-rotulo">Saldo do ano</p>
      <p class="stat-valor <?= $saldoAno >= 0 ? 'stat-positivo' : 'stat-negativo' ?>"><?= formatar_moeda($saldoAno) ?></p>
    </div>
  </div>

  <div class="cartao">
    <?= info_icone('Compara, mês a mês, quanto entrou (receitas recebidas) e quanto saiu (contas pagas) no ano selecionado.') ?>
    <div class="grafico-cabecalho">
      <h2 class="grafico-titulo">Receitas x Despesas por mês</h2>
      <div class="grafico-legenda">
        <span class="legenda-item"><span class="legenda-marcador-linha" style="background: var(--sucesso)"></span>Receitas</span>
        <span class="legenda-item"><span class="legenda-marcador-linha" style="background: var(--perigo)"></span>Despesas</span>
      </div>
    </div>
    <?= grafico_evolucao_mensal($pontosMensais) ?>
  </div>

  <div class="cartao">
    <?= info_icone('Soma de todas as contas do ano, agrupadas por categoria (Moradia, Alimentação, etc.), pra ver onde o dinheiro mais foi.') ?>
    <div class="grafico-cabecalho"><h2 class="grafico-titulo">Gastos por categoria no ano</h2></div>
    <?= grafico_categoria($gastosPorCategoria) ?>
  </div>
</div>

<?php
fechar_layout();
