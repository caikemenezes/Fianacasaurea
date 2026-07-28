<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/util.php';
require_once __DIR__ . '/../src/extrato.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('/contas.php');
}

$pdo = conexao_banco();
$familiaId = (int) $usuario_atual['familia_id'];
$acao = $_POST['acao'] ?? '';

if ($acao === 'verificar') {
    try {
        $resultado = processar_extrato_automatico($pdo, $familiaId);
        if (isset($resultado['erro'])) {
            redirecionar('/contas.php?erro=' . urlencode($resultado['erro']));
        }
        redirecionar(
            "/contas.php?novas={$resultado['novas']}&casadas={$resultado['casadas']}"
            . "&fixas_criadas={$resultado['contas_fixas_criadas']}&fixas_transacoes={$resultado['transacoes_absorvidas_por_contas_fixas']}"
        );
    } catch (Throwable $erro) {
        redirecionar('/contas.php?erro=' . urlencode($erro->getMessage()));
    }
} elseif ($acao === 'vincular_conta') {
    $id = (int) $_POST['id'];
    $contaMesId = (int) $_POST['conta_mes_id'];

    $stmt = $pdo->prepare('SELECT * FROM transacao_importada WHERE id = ? AND familia_id = ? AND status = "PENDENTE"');
    $stmt->execute([$id, $familiaId]);
    $transacao = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT * FROM conta_mes WHERE id = ? AND familia_id = ?');
    $stmt->execute([$contaMesId, $familiaId]);
    $conta = $stmt->fetch();

    if ($transacao !== false && $conta !== false) {
        aplicar_pagamento_conta_mes($pdo, $conta, $transacao['data'], $transacao['descricao']);
        $stmt = $pdo->prepare('UPDATE transacao_importada SET status = "CONFIRMADA", conta_mes_id = ? WHERE id = ?');
        $stmt->execute([$contaMesId, $id]);
    }
} elseif ($acao === 'vincular_receita') {
    $id = (int) $_POST['id'];
    $receitaId = (int) $_POST['receita_id'];

    $stmt = $pdo->prepare('SELECT * FROM transacao_importada WHERE id = ? AND familia_id = ? AND status = "PENDENTE"');
    $stmt->execute([$id, $familiaId]);
    $transacao = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT * FROM receita WHERE id = ? AND familia_id = ?');
    $stmt->execute([$receitaId, $familiaId]);
    $receita = $stmt->fetch();

    if ($transacao !== false && $receita !== false) {
        aplicar_recebimento_receita($pdo, $receita, (float) $transacao['valor'], $transacao['data']);
        $stmt = $pdo->prepare('UPDATE transacao_importada SET status = "CONFIRMADA", receita_id = ? WHERE id = ?');
        $stmt->execute([$receitaId, $id]);
    }
} elseif ($acao === 'ignorar') {
    $id = (int) $_POST['id'];
    $stmt = $pdo->prepare('UPDATE transacao_importada SET status = "IGNORADA" WHERE id = ? AND familia_id = ?');
    $stmt->execute([$id, $familiaId]);
}

redirecionar('/contas.php');
