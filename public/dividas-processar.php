<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('/dividas.php');
}

$pdo = conexao_banco();
$familiaId = (int) $usuario_atual['familia_id'];
$acao = $_POST['acao'] ?? '';

if ($acao === 'criar') {
    $valorOriginal = parse_valor($_POST['valor_original'] ?? null);
    $numeroParcelas = texto_ou_null($_POST['numero_parcelas'] ?? null);

    $stmt = $pdo->prepare(
        'INSERT INTO divida (familia_id, nome, credor, tipo, valor_original, valor_atual, numero_parcelas, valor_parcela, vencimento, prioridade, possibilidade_negociacao, identificador_extrato)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $familiaId,
        (string) $_POST['nome'],
        (string) $_POST['credor'],
        (string) $_POST['tipo'],
        $valorOriginal,
        $valorOriginal,
        $numeroParcelas !== null ? (int) $numeroParcelas : null,
        parse_valor_ou_null($_POST['valor_parcela'] ?? null),
        data_ou_null($_POST['vencimento'] ?? null),
        in_array($_POST['prioridade'] ?? '', ['URGENTE', 'ALTA', 'MEDIA', 'BAIXA'], true) ? $_POST['prioridade'] : 'MEDIA',
        isset($_POST['possibilidade_negociacao']) ? 1 : 0,
        texto_ou_null($_POST['identificador_extrato'] ?? null),
    ]);
} elseif ($acao === 'alternar_status') {
    // Ciclo manual completo: pendente -> atrasada -> paga -> pendente -> ...
    // Só troca o selo/status, nunca mexe em valor_atual/parcelas — igual ao
    // padrão de Contas do Mês.
    $id = (int) $_POST['id'];
    $stmt = $pdo->prepare('SELECT status FROM divida WHERE id = ? AND familia_id = ?');
    $stmt->execute([$id, $familiaId]);
    $divida = $stmt->fetch();

    if ($divida !== false) {
        $proximoStatus = match ($divida['status']) {
            'EM_DIA' => 'ATRASADA',
            'ATRASADA' => 'QUITADA',
            'QUITADA' => 'EM_DIA',
            default => 'EM_DIA',
        };
        $stmt = $pdo->prepare('UPDATE divida SET status = ? WHERE id = ? AND familia_id = ?');
        $stmt->execute([$proximoStatus, $id, $familiaId]);
    }
} elseif ($acao === 'editar') {
    // Planilha: cada campo salva sozinho ao perder o foco (ver dividas-planilha.js),
    // mas o form da linha inteira é reenviado a cada vez — não dá pra saber só
    // pelo POST qual campo o usuário realmente mexeu. Por isso comparamos
    // "parcelas_pagas" enviado com o valor já gravado no banco: se mudou, é
    // sinal de que uma parcela foi paga (ou corrigida), e valor_atual/vencimento
    // são recalculados automaticamente a partir da diferença — em vez de
    // confiar no que veio no POST pra esses dois campos (que é só o valor que
    // já estava na tela, não uma edição intencional).
    $id = (int) $_POST['id'];

    $stmt = $pdo->prepare('SELECT parcelas_pagas, valor_atual, valor_parcela, vencimento FROM divida WHERE id = ? AND familia_id = ?');
    $stmt->execute([$id, $familiaId]);
    $atual = $stmt->fetch();

    if ($atual !== false) {
        $novasParcelasPagas = max(0, (int) ($_POST['parcelas_pagas'] ?? 0));
        $delta = $novasParcelasPagas - (int) $atual['parcelas_pagas'];
        $novoValorParcela = parse_valor_ou_null($_POST['valor_parcela'] ?? null) ?? (float) $atual['valor_parcela'];

        if ($delta !== 0) {
            $novoValorAtual = max(0, (float) $atual['valor_atual'] - ($delta * $novoValorParcela));
            $novoVencimento = $atual['vencimento'] !== null ? somar_meses($atual['vencimento'], $delta) : data_ou_null($_POST['vencimento'] ?? null);
        } else {
            $novoValorAtual = max(0, parse_valor($_POST['valor_atual'] ?? null));
            $novoVencimento = data_ou_null($_POST['vencimento'] ?? null);
        }

        $stmt = $pdo->prepare(
            'UPDATE divida SET nome = ?, credor = ?, parcelas_pagas = ?, valor_atual = ?, valor_parcela = ?, vencimento = ?
             WHERE id = ? AND familia_id = ?'
        );
        $stmt->execute([
            (string) $_POST['nome'],
            (string) $_POST['credor'],
            $novasParcelasPagas,
            $novoValorAtual,
            $novoValorParcela,
            $novoVencimento,
            $id,
            $familiaId,
        ]);
    }
} elseif ($acao === 'excluir') {
    $stmt = $pdo->prepare('DELETE FROM divida WHERE id = ? AND familia_id = ?');
    $stmt->execute([(int) $_POST['id'], $familiaId]);
}

redirecionar('/dividas.php');
