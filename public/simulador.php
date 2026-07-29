<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/layout.php';
require_once __DIR__ . '/../src/util.php';
require_once __DIR__ . '/../src/charts.php';

$pdo = conexao_banco();
$familiaId = (int) $usuario_atual['familia_id'];
$mesAtual = date('Y-m');

$stmt = $pdo->prepare('SELECT COALESCE(SUM(valor_previsto),0) AS total FROM receita WHERE familia_id = ? AND DATE_FORMAT(data_prevista, "%Y-%m") = ?');
$stmt->execute([$familiaId, $mesAtual]);
$rendaInicial = (float) $stmt->fetch()['total'];

$itens = [];

$stmt = $pdo->prepare('SELECT id, nome, valor FROM conta_mes WHERE familia_id = ? AND status != "PAGA" AND DATE_FORMAT(vencimento, "%Y-%m") = ?');
$stmt->execute([$familiaId, $mesAtual]);
foreach ($stmt->fetchAll() as $c) {
    $itens[] = ['id' => 'conta-' . $c['id'], 'nome' => $c['nome'], 'categoria' => 'Contas do Mês', 'valorSugerido' => (float) $c['valor']];
}

// Todas as contas fixas cadastradas (independente de status/mês de
// vencimento) — não entram na simulação de cara (só as pendentes deste mês
// entram, acima), ficam disponíveis pro botão "Trazer contas fixas do mês"
// no card, pra projetar todas as recorrentes sem precisar cadastrar cada
// mês na mão.
$stmt = $pdo->prepare(
    'SELECT id, nome, CASE WHEN numero_parcelas IS NOT NULL THEN COALESCE(valor_parcela, 0) ELSE valor END AS valor_mensal
     FROM conta_mes WHERE familia_id = ? ORDER BY nome ASC'
);
$stmt->execute([$familiaId]);
$contasFixasDisponiveis = [];
foreach ($stmt->fetchAll() as $c) {
    $contasFixasDisponiveis[] = ['id' => 'conta-' . $c['id'], 'nome' => $c['nome'], 'categoria' => 'Contas do Mês', 'valorSugerido' => (float) $c['valor_mensal']];
}

$stmt = $pdo->prepare('SELECT id, nome, valor_parcela, numero_parcelas, parcelas_pagas FROM divida WHERE familia_id = ? AND status != "QUITADA"');
$stmt->execute([$familiaId]);
foreach ($stmt->fetchAll() as $d) {
    $item = ['id' => 'divida-' . $d['id'], 'nome' => $d['nome'], 'categoria' => 'Dívidas', 'valorSugerido' => (float) ($d['valor_parcela'] ?? 0)];
    if ($d['valor_parcela'] !== null) {
        $item['valorParcela'] = (float) $d['valor_parcela'];
    }
    if ($d['numero_parcelas'] !== null) {
        $item['parcelasRestantes'] = max(1, (int) $d['numero_parcelas'] - (int) $d['parcelas_pagas']);
    }
    $itens[] = $item;
}

$stmt = $pdo->prepare('SELECT id, nome, valor_estimado, valor_guardado, data_desejada FROM meta WHERE familia_id = ? AND status NOT IN ("CONCLUIDA","CANCELADA")');
$stmt->execute([$familiaId]);
foreach ($stmt->fetchAll() as $m) {
    $restante = max(0, (float) $m['valor_estimado'] - (float) $m['valor_guardado']);
    $meses = meses_restantes($m['data_desejada']);
    $itens[] = ['id' => 'meta-' . $m['id'], 'nome' => $m['nome'], 'categoria' => 'Metas', 'valorSugerido' => $meses ? $restante / $meses : 0];
}

$stmt = $pdo->prepare('SELECT id, item, valor_estimado, valor_guardado, mes_planejado FROM necessidade WHERE familia_id = ? AND status NOT IN ("CONCLUIDA","CANCELADA")');
$stmt->execute([$familiaId]);
foreach ($stmt->fetchAll() as $p) {
    $restante = max(0, (float) $p['valor_estimado'] - (float) $p['valor_guardado']);
    $meses = meses_restantes($p['mes_planejado']) ?? 1;
    $itens[] = ['id' => 'prioridade-' . $p['id'], 'nome' => $p['item'], 'categoria' => 'Prioridades', 'valorSugerido' => $restante / $meses];
}

$stmt = $pdo->prepare('SELECT id, nome, aporte_mensal FROM investimento WHERE familia_id = ?');
$stmt->execute([$familiaId]);
foreach ($stmt->fetchAll() as $i) {
    $itens[] = ['id' => 'investimento-' . $i['id'], 'nome' => $i['nome'], 'categoria' => 'Investimentos', 'valorSugerido' => (float) ($i['aporte_mensal'] ?? 0)];
}

layout_topo($usuario_atual, 'simulador', 'Simulador');
layout_rodape($usuario_atual);
?>

<div class="pilha">
  <div>
    <h1 class="pagina-titulo">Simulador</h1>
    <p class="pagina-subtitulo">Estime uma renda e veja o que consegue fazer com ela — ajuste os valores abaixo para testar cenários.</p>
  </div>

  <div id="simulador-raiz" data-mes-atual="<?= $mesAtual ?>" data-renda-inicial="<?= $rendaInicial ?>" data-itens='<?= htmlspecialchars(json_encode($itens, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>' data-contas-fixas='<?= htmlspecialchars(json_encode($contasFixasDisponiveis, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'>
    <p class="texto-suave">Carregando simulador…</p>
  </div>
</div>

<script src="/assets/simulador.js"></script>

<?php
fechar_layout();
