<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/charts.php'; // formatar_moeda() usada nos textos de alerta
require_once __DIR__ . '/extrato.php'; // saldo_disponivel_ate() usada no resumo do mês

/**
 * Todo cálculo do Dashboard, isolado por família (familia_id), no mesmo
 * espírito do lib/dashboard.ts do sistema original.
 */

function mes_referencia_da_query(): string
{
    $mes = $_GET['mes'] ?? null;
    if (is_string($mes) && preg_match('/^\d{4}-\d{2}$/', $mes)) {
        return $mes;
    }
    return date('Y-m');
}

function mes_deslocado(string $mesReferencia, int $delta): string
{
    $data = DateTimeImmutable::createFromFormat('Y-m-d', $mesReferencia . '-01');
    return $data->modify(($delta >= 0 ? '+' : '') . $delta . ' months')->format('Y-m');
}

function nome_do_mes(string $mesReferencia): string
{
    $meses = [
        '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
        '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
        '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro',
    ];
    [$ano, $mes] = explode('-', $mesReferencia);
    return ($meses[$mes] ?? $mes) . ' de ' . $ano;
}

/**
 * Resumo financeiro do mês selecionado: renda, contas, metas, prioridades,
 * dívidas, investimentos e saldo — mesma lista da nota "Dashboard" do vault original.
 */
function resumo_do_mes(PDO $pdo, int $familiaId, string $mesReferencia): array
{
    $stmt = $pdo->prepare(
        'SELECT
            COALESCE(SUM(valor_previsto), 0) AS renda_prevista,
            COALESCE(SUM(CASE WHEN status = "RECEBIDO" THEN valor_recebido ELSE 0 END), 0) AS renda_recebida
         FROM receita WHERE familia_id = ? AND DATE_FORMAT(data_prevista, "%Y-%m") = ?'
    );
    $stmt->execute([$familiaId, $mesReferencia]);
    $receitas = $stmt->fetch();

    $stmt = $pdo->prepare(
        'SELECT
            COALESCE(SUM(valor), 0) AS total_contas,
            COUNT(*) AS qtd_contas,
            COALESCE(SUM(CASE WHEN status = "PENDENTE" AND vencimento >= CURDATE() THEN 1 ELSE 0 END), 0) AS qtd_pendentes,
            COALESCE(SUM(CASE WHEN status = "ATRASADA" OR (status = "PENDENTE" AND vencimento < CURDATE()) THEN 1 ELSE 0 END), 0) AS qtd_atrasadas,
            COALESCE(SUM(CASE WHEN status = "ATRASADA" OR (status = "PENDENTE" AND vencimento < CURDATE()) THEN valor ELSE 0 END), 0) AS total_atrasadas
         FROM conta_mes WHERE familia_id = ? AND DATE_FORMAT(vencimento, "%Y-%m") = ?'
    );
    $stmt->execute([$familiaId, $mesReferencia]);
    $contas = $stmt->fetch();

    // "Gastos do mês": tudo que já saiu de fato da conta neste mês, pela data
    // real do pagamento (paga_em) — não pelo status "PAGA" nem pelo
    // vencimento. Contas parceladas (financiamento) quase nunca mudam de
    // status PENDENTE, só avançam a parcela — por isso não davam pra contar
    // olhando só status="PAGA" (achado numa correção pedida pelo usuário,
    // depois de ver "Contas pagas" no Dashboard sem refletir parcela paga).
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(CASE WHEN numero_parcelas IS NOT NULL THEN COALESCE(valor_parcela, 0) ELSE valor END), 0) AS total_pago
         FROM conta_mes WHERE familia_id = ? AND paga_em IS NOT NULL AND DATE_FORMAT(paga_em, "%Y-%m") = ?'
    );
    $stmt->execute([$familiaId, $mesReferencia]);
    $totalPagoLinha = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(valor_guardado), 0) AS total FROM meta WHERE familia_id = ?');
    $stmt->execute([$familiaId]);
    $totalMetas = (float) $stmt->fetch()['total'];

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(valor_guardado), 0) AS total FROM necessidade WHERE familia_id = ?');
    $stmt->execute([$familiaId]);
    $totalPrioridades = (float) $stmt->fetch()['total'];

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(valor_atual), 0) AS total FROM divida WHERE familia_id = ? AND valor_atual > 0');
    $stmt->execute([$familiaId]);
    $totalDividas = (float) $stmt->fetch()['total'];

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(valor_atual), 0) AS total FROM investimento WHERE familia_id = ?');
    $stmt->execute([$familiaId]);
    $totalInvestimentos = (float) $stmt->fetch()['total'];

    // "Saldo disponível": precisa bater com o extrato de verdade, não só com
    // as Contas do Mês registradas — antes era renda_recebida - total_pago
    // (só contas cadastradas), o que mostrava dinheiro como "disponível"
    // mesmo já tendo sido gasto em compras do dia a dia (mercado, Uber) que
    // nunca viram Conta do Mês. Cálculo real (saldo do banco lido do OFX de
    // cada mês + extrato depois disso, com fallback pro saldo_inicial manual
    // se não tiver OFX ainda) centralizado em saldo_disponivel_ate(),
    // ver src/extrato.php — reaproveitado também em receitas.php.
    $saldoDisponivel = saldo_disponivel_ate($pdo, $familiaId, $mesReferencia);

    $rendaPrevista = (float) $receitas['renda_prevista'];
    $rendaRecebida = (float) $receitas['renda_recebida'];
    $totalContas = (float) $contas['total_contas'];
    $totalPago = (float) $totalPagoLinha['total_pago'];

    return [
        'renda_prevista' => $rendaPrevista,
        'renda_recebida' => $rendaRecebida,
        'total_contas' => $totalContas,
        'total_pago' => $totalPago,
        'total_pendente' => max(0, $totalContas - $totalPago),
        'qtd_contas' => (int) $contas['qtd_contas'],
        'qtd_pendentes' => (int) $contas['qtd_pendentes'],
        'qtd_atrasadas' => (int) $contas['qtd_atrasadas'],
        'total_atrasadas' => (float) $contas['total_atrasadas'],
        'total_metas' => $totalMetas,
        'total_prioridades' => $totalPrioridades,
        'total_dividas' => $totalDividas,
        'total_investimentos' => $totalInvestimentos,
        'saldo_disponivel' => $saldoDisponivel,
        'saldo_previsto_fim_mes' => $rendaPrevista - $totalContas,
    ];
}

function plural(int $n, string $singular, string $plural): string
{
    return $n . ' ' . ($n === 1 ? $singular : $plural);
}

/**
 * Alertas agrupados por tipo (contagem + valor total), no mesmo formato do card
 * "Alertas Importantes" do sistema original: uma linha por categoria de alerta,
 * não uma linha por conta. Independem do mês selecionado — usam a data real de hoje.
 */
function alertas_importantes(PDO $pdo, int $familiaId): array
{
    $grupos = [];

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS qtd, COALESCE(SUM(valor), 0) AS total FROM conta_mes
         WHERE familia_id = ? AND (status = "ATRASADA" OR (status = "PENDENTE" AND vencimento < CURDATE()))'
    );
    $stmt->execute([$familiaId]);
    $linha = $stmt->fetch();
    if ((int) $linha['qtd'] > 0) {
        $grupos[] = [
            'tipo' => 'critical',
            'titulo' => plural((int) $linha['qtd'], 'conta atrasada', 'contas atrasadas'),
            'subtitulo' => 'Em aberto',
            'valor' => (float) $linha['total'],
            'contagem' => (int) $linha['qtd'],
            'link' => '/contas.php',
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS qtd, COALESCE(SUM(valor), 0) AS total FROM conta_mes
         WHERE familia_id = ? AND status = "PENDENTE" AND vencimento = CURDATE()'
    );
    $stmt->execute([$familiaId]);
    $linha = $stmt->fetch();
    if ((int) $linha['qtd'] > 0) {
        $grupos[] = [
            'tipo' => 'warning',
            'titulo' => plural((int) $linha['qtd'], 'conta vence hoje', 'contas vencem hoje'),
            'subtitulo' => 'Vencimento hoje',
            'valor' => (float) $linha['total'],
            'contagem' => (int) $linha['qtd'],
            'link' => '/contas.php',
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS qtd, COALESCE(SUM(valor), 0) AS total FROM conta_mes
         WHERE familia_id = ? AND status = "PENDENTE" AND vencimento BETWEEN DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)'
    );
    $stmt->execute([$familiaId]);
    $linha = $stmt->fetch();
    if ((int) $linha['qtd'] > 0) {
        $grupos[] = [
            'tipo' => 'warning',
            'titulo' => plural((int) $linha['qtd'], 'conta vencendo em breve', 'contas vencendo em breve'),
            'subtitulo' => 'Próximos 3 dias',
            'valor' => (float) $linha['total'],
            'contagem' => (int) $linha['qtd'],
            'link' => '/contas.php',
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS qtd, COALESCE(SUM(valor_parcela), 0) AS total FROM divida
         WHERE familia_id = ? AND valor_atual > 0 AND vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)'
    );
    $stmt->execute([$familiaId]);
    $linha = $stmt->fetch();
    if ((int) $linha['qtd'] > 0) {
        $grupos[] = [
            'tipo' => 'warning',
            'titulo' => plural((int) $linha['qtd'], 'parcela de dívida vencendo', 'parcelas de dívida vencendo'),
            'subtitulo' => 'Próximos 7 dias',
            'valor' => (float) $linha['total'],
            'contagem' => (int) $linha['qtd'],
            'link' => '/dividas.php',
        ];
    }

    $mesAtual = date('Y-m');
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS qtd FROM necessidade
         WHERE familia_id = ? AND DATE_FORMAT(mes_planejado, "%Y-%m") = ? AND status NOT IN ("CONCLUIDA", "CANCELADA")'
    );
    $stmt->execute([$familiaId, $mesAtual]);
    $linha = $stmt->fetch();
    if ((int) $linha['qtd'] > 0) {
        $grupos[] = [
            'tipo' => 'info',
            'titulo' => plural((int) $linha['qtd'], 'prioridade planejada', 'prioridades planejadas'),
            'subtitulo' => 'Para este mês',
            'valor' => null,
            'contagem' => (int) $linha['qtd'],
            'link' => '/prioridades.php',
        ];
    }

    return $grupos;
}

/** Série diária de gasto acumulado no mês, para o gráfico "Quanto gastei no mês". */
function serie_gasto_acumulado(PDO $pdo, int $familiaId, string $mesReferencia): array
{
    $inicio = DateTimeImmutable::createFromFormat('Y-m-d', $mesReferencia . '-01');
    $diasNoMes = (int) $inicio->format('t');
    $hoje = new DateTimeImmutable('today');
    $ehMesAtual = $inicio->format('Y-m') === $hoje->format('Y-m');
    $ultimoDia = $ehMesAtual ? (int) $hoje->format('j') : $diasNoMes;

    $stmt = $pdo->prepare(
        'SELECT DAY(paga_em) AS dia,
            SUM(CASE WHEN numero_parcelas IS NOT NULL THEN COALESCE(valor_parcela, 0) ELSE valor END) AS valor
         FROM conta_mes
         WHERE familia_id = ? AND paga_em IS NOT NULL AND DATE_FORMAT(paga_em, "%Y-%m") = ?
         GROUP BY DAY(paga_em)'
    );
    $stmt->execute([$familiaId, $mesReferencia]);
    $porDia = [];
    foreach ($stmt->fetchAll() as $linha) {
        $porDia[(int) $linha['dia']] = (float) $linha['valor'];
    }

    $pontos = [];
    $acumulado = 0.0;
    for ($dia = 1; $dia <= max(1, $ultimoDia); $dia++) {
        $acumulado += $porDia[$dia] ?? 0.0;
        $pontos[] = ['dia' => $dia, 'valor' => $acumulado];
    }

    return $pontos;
}

/** Total de contas do mês agrupado por categoria, para o gráfico de rosca. */
function gasto_por_categoria(PDO $pdo, int $familiaId, string $mesReferencia): array
{
    $stmt = $pdo->prepare(
        'SELECT categoria, SUM(valor) AS total FROM conta_mes
         WHERE familia_id = ? AND DATE_FORMAT(vencimento, "%Y-%m") = ?
         GROUP BY categoria'
    );
    $stmt->execute([$familiaId, $mesReferencia]);

    $resultado = [];
    foreach ($stmt->fetchAll() as $linha) {
        $resultado[$linha['categoria']] = (float) $linha['total'];
    }
    return $resultado;
}
