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
        'INSERT INTO divida (familia_id, nome, credor, tipo, valor_original, valor_atual, numero_parcelas, valor_parcela, vencimento, prioridade, possibilidade_negociacao)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
    // Planilha: cada campo salva sozinho ao perder o foco (ver dividas-planilha.js).
    // Só grava os valores digitados — não mexe no status, que é controlado
    // à parte pelo ciclo manual do selo (alternar_status).
    $id = (int) $_POST['id'];
    $stmt = $pdo->prepare(
        'UPDATE divida SET nome = ?, credor = ?, parcelas_pagas = ?, valor_atual = ?, valor_parcela = ?, vencimento = ?
         WHERE id = ? AND familia_id = ?'
    );
    $stmt->execute([
        (string) $_POST['nome'],
        (string) $_POST['credor'],
        max(0, (int) ($_POST['parcelas_pagas'] ?? 0)),
        max(0, parse_valor($_POST['valor_atual'] ?? null)),
        parse_valor_ou_null($_POST['valor_parcela'] ?? null),
        data_ou_null($_POST['vencimento'] ?? null),
        $id,
        $familiaId,
    ]);
} elseif ($acao === 'excluir') {
    $stmt = $pdo->prepare('DELETE FROM divida WHERE id = ? AND familia_id = ?');
    $stmt->execute([(int) $_POST['id'], $familiaId]);
}

redirecionar('/dividas.php');
