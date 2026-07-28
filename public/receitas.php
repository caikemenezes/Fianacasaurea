<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/layout.php';
require_once __DIR__ . '/../src/util.php';
require_once __DIR__ . '/../src/charts.php';
require_once __DIR__ . '/../src/extrato.php';

const TIPOS_RECEITA = [
    'Salário', 'Trabalho freelancer', 'Serviços de audiovisual', 'Venda de produtos',
    'Comissão', 'Renda extra', 'Benefícios', 'Cashback', 'Outros',
];

$pdo = conexao_banco();
$familiaId = (int) $usuario_atual['familia_id'];

// Meses com receita cadastrada, pro seletor abaixo — mais recente primeiro.
$stmt = $pdo->prepare(
    'SELECT DISTINCT DATE_FORMAT(data_prevista, "%Y-%m") AS mes FROM receita
     WHERE familia_id = ? ORDER BY mes DESC'
);
$stmt->execute([$familiaId]);
$mesesReceita = array_column($stmt->fetchAll(), 'mes');

$mesAtual = date('Y-m');
$mesParam = (string) ($_GET['mes'] ?? $mesAtual);
$mesSelecionado = $mesParam === 'todos' || preg_match('/^\d{4}-\d{2}$/', $mesParam) ? $mesParam : $mesAtual;
if ($mesSelecionado !== 'todos' && !in_array($mesSelecionado, $mesesReceita, true)) {
    $mesesReceita[] = $mesSelecionado;
    rsort($mesesReceita);
}

$sql = 'SELECT * FROM receita WHERE familia_id = ?';
$params = [$familiaId];
if ($mesSelecionado !== 'todos') {
    $sql .= ' AND DATE_FORMAT(data_prevista, "%Y-%m") = ?';
    $params[] = $mesSelecionado;
}
$sql .= ' ORDER BY data_prevista ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$receitas = $stmt->fetchAll();

$totalPrevisto = array_sum(array_map(fn(array $r): float => (float) $r['valor_previsto'], $receitas));
$totalRecebido = array_sum(array_map(
    fn(array $r): float => $r['status'] === 'RECEBIDO' ? (float) $r['valor_recebido'] : 0,
    $receitas
));

// Gasto real do mês selecionado, direto do extrato (não dá pra saber qual
// receita específica "pagou" qual conta — dinheiro numa conta bancária não é
// etiquetado por origem — então o que dá pra calcular de verdade é o total).
$sqlGasto = 'SELECT COALESCE(SUM(-valor), 0) AS total FROM transacao_importada WHERE familia_id = ? AND valor < 0';
$paramsGasto = [$familiaId];
if ($mesSelecionado !== 'todos') {
    $sqlGasto .= ' AND DATE_FORMAT(data, "%Y-%m") = ?';
    $paramsGasto[] = $mesSelecionado;
}
$stmt = $pdo->prepare($sqlGasto);
$stmt->execute($paramsGasto);
$totalGastoExtrato = (float) $stmt->fetch()['total'];

// "Saldo inicial" (o que já tinha antes desse mês) e "Sobra" (saldo real no
// fim desse mês) vêm da mesma função usada no Dashboard (saldo_disponivel_ate(),
// ver src/extrato.php) — usa o saldo real lido do OFX de cada mês (mais
// preciso que somar só o CSV), com fallback pro saldo_inicial manual de
// Configurações se ainda não tiver nenhum OFX processado.
if ($mesSelecionado === 'todos') {
    $saldoInicial = 0.0; // não faz sentido "o que já tinha antes de todos os meses"
    $sobra = saldo_disponivel_ate($pdo, $familiaId, $mesAtual);
} else {
    $mesAnteriorSelecionado = (DateTimeImmutable::createFromFormat('Y-m-d', $mesSelecionado . '-01'))
        ->modify('-1 month')->format('Y-m');
    $saldoInicial = saldo_disponivel_ate($pdo, $familiaId, $mesAnteriorSelecionado);
    $sobra = saldo_disponivel_ate($pdo, $familiaId, $mesSelecionado);
}

layout_topo($usuario_atual, 'receitas', 'Receitas');
layout_rodape($usuario_atual);
?>

<div class="pilha">
  <div>
    <h1 class="pagina-titulo">Receitas</h1>
    <p class="pagina-subtitulo">Tudo que entra: previsto e recebido.</p>
  </div>

  <form method="post" action="/receitas-processar.php" class="cartao form-grade">
    <?= csrf_campo_oculto($usuario_atual) ?>
    <?= info_icone('Cadastre aqui uma entrada de dinheiro prevista (salário, freelance, etc.). Depois marque como recebida quando o valor cair na conta — isso é o que alimenta a Renda do Mês no Dashboard.') ?>
    <input type="hidden" name="acao" value="criar">
    <input name="nome" placeholder="Nome" required class="campo">
    <select name="tipo" required class="campo">
      <option value="" disabled selected>Tipo</option>
      <?php foreach (TIPOS_RECEITA as $tipo): ?>
        <option value="<?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
    <input name="valor_previsto" placeholder="Valor previsto" type="text" inputmode="decimal" data-moeda required class="campo">
    <input name="data_prevista" type="date" required class="campo">
    <input name="categoria" placeholder="Categoria (opcional)" class="campo">
    <input name="identificador_extrato" placeholder="Palavra-chave no extrato (opcional, ex: nome de quem paga)" class="campo">
    <input name="conta_bancaria" placeholder="Conta bancária (opcional)" class="campo">
    <input name="observacao" placeholder="Observação (opcional)" class="campo">
    <label class="campo-checkbox"><input type="checkbox" name="recorrente"> Recorrente</label>
    <button type="submit" class="botao">Adicionar receita</button>
  </form>

  <form method="get" class="linha-flex" style="justify-content:flex-start">
    <label for="mes" class="texto-suave">Mês:</label>
    <select name="mes" id="mes" class="campo campo-tabela" onchange="this.form.submit()">
      <option value="todos" <?= $mesSelecionado === 'todos' ? 'selected' : '' ?>>Todos os meses</option>
      <?php foreach ($mesesReceita as $mes): ?>
        <option value="<?= htmlspecialchars($mes, ENT_QUOTES, 'UTF-8') ?>" <?= $mes === $mesSelecionado ? 'selected' : '' ?>>
          <?= ucfirst(formatar_mes_ano($mes . '-01')) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <div class="cartao pilha-pequena">
    <?= info_icone('Não dá pra saber qual receita específica pagou qual conta — dinheiro numa conta bancária não é etiquetado por origem, é tudo junto. O que dá pra calcular é o total: saldo que sobrou dos meses anteriores + quanto entrou de receita nesse mês - quanto já saiu de verdade (extrato) = quanto sobra agora. Só existe saldo inicial se tiver extrato importado de antes desse mês.') ?>
    <h2 class="cartao-titulo">Saldo do período</h2>
    <div class="linha-flex" style="gap:1.5rem">
      <?php if ($saldoInicial !== 0.0): ?>
        <span class="texto-suave">Saldo inicial: <strong style="color: <?= $saldoInicial >= 0 ? 'var(--sucesso)' : 'var(--perigo)' ?>"><?= formatar_moeda($saldoInicial) ?></strong></span>
      <?php endif; ?>
      <span class="texto-suave">Recebido: <strong style="color:var(--sucesso)"><?= formatar_moeda($totalRecebido) ?></strong></span>
      <span class="texto-suave">Gasto: <strong style="color:var(--perigo)"><?= formatar_moeda($totalGastoExtrato) ?></strong></span>
      <span class="texto-suave">Sobra: <strong style="color: <?= $sobra >= 0 ? 'var(--sucesso)' : 'var(--perigo)' ?>"><?= formatar_moeda($sobra) ?></strong></span>
    </div>
  </div>

  <div class="tabela-wrap">
    <table class="tabela">
      <thead><tr><th>Nome</th><th>Tipo</th><th>Previsto</th><th>Data prevista</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($receitas as $receita): ?>
          <tr>
            <td><strong><?= htmlspecialchars($receita['nome'], ENT_QUOTES, 'UTF-8') ?></strong></td>
            <td class="texto-suave"><?= htmlspecialchars($receita['tipo'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= formatar_moeda((float) $receita['valor_previsto']) ?></td>
            <td><?= formatar_data($receita['data_prevista']) ?></td>
            <td>
              <form method="post" action="/receitas-processar.php" style="display:inline">
                <?= csrf_campo_oculto($usuario_atual) ?>
                <input type="hidden" name="acao" value="marcar_recebida">
                <input type="hidden" name="id" value="<?= (int) $receita['id'] ?>">
                <button type="submit" class="selo <?= $receita['status'] === 'RECEBIDO' ? 'selo-sucesso' : 'selo-alerta' ?>" title="Clique pra mudar o status">
                  <?= $receita['status'] === 'RECEBIDO' ? 'Recebida' : 'Prevista' ?>
                </button>
              </form>
            </td>
            <td>
              <div class="acoes">
                <form method="post" action="/receitas-processar.php" onsubmit="return confirm('Excluir esta receita?');">
                  <?= csrf_campo_oculto($usuario_atual) ?>
                  <input type="hidden" name="acao" value="excluir">
                  <input type="hidden" name="id" value="<?= (int) $receita['id'] ?>">
                  <button class="link-acao link-perigo">Excluir</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (count($receitas) === 0): ?>
          <tr><td colspan="6" class="tabela-vazia"><?= $mesSelecionado === 'todos' ? 'Nenhuma receita cadastrada ainda.' : 'Nenhuma receita nesse mês.' ?></td></tr>
        <?php endif; ?>
      </tbody>
      <?php if (count($receitas) > 0): ?>
        <tfoot>
          <tr>
            <td colspan="2" class="texto-suave">Total</td>
            <td><strong><?= formatar_moeda($totalPrevisto) ?></strong></td>
            <td colspan="3" class="texto-suave">Recebido: <strong style="color:var(--sucesso)"><?= formatar_moeda($totalRecebido) ?></strong></td>
          </tr>
        </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<?php
fechar_layout();
