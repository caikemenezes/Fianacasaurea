<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('/contas.php');
}

$pdo = conexao_banco();
$familiaId = (int) $usuario_atual['familia_id'];
$acao = $_POST['acao'] ?? '';

if ($acao === 'criar') {
    $stmt = $pdo->prepare(
        'INSERT INTO conta_mes (familia_id, nome, categoria, subcategoria, valor, vencimento, forma_pagamento, conta_bancaria, tipo, recorrente_mensal, observacoes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $familiaId,
        (string) $_POST['nome'],
        (string) $_POST['categoria'],
        texto_ou_null($_POST['subcategoria'] ?? null),
        parse_valor($_POST['valor'] ?? null),
        (string) $_POST['vencimento'],
        texto_ou_null($_POST['forma_pagamento'] ?? null),
        texto_ou_null($_POST['conta_bancaria'] ?? null),
        ($_POST['tipo'] ?? '') === 'VARIAVEL' ? 'VARIAVEL' : 'FIXA',
        isset($_POST['recorrente_mensal']) ? 1 : 0,
        texto_ou_null($_POST['observacoes'] ?? null),
    ]);
} elseif ($acao === 'marcar_paga') {
    // Ciclo manual completo: pendente -> atrasada -> paga -> pendente -> ...
    // Cada clique avança um passo, sempre visível (não é mais sobrescrito
    // por um cálculo automático de data por cima).
    $id = (int) $_POST['id'];
    $stmt = $pdo->prepare('SELECT status FROM conta_mes WHERE id = ? AND familia_id = ?');
    $stmt->execute([$id, $familiaId]);
    $conta = $stmt->fetch();

    if ($conta !== false) {
        $proximoStatus = match ($conta['status']) {
            'PENDENTE' => 'ATRASADA',
            'ATRASADA' => 'PAGA',
            'PAGA' => 'PENDENTE',
            default => 'PENDENTE',
        };
        $pagaEm = $proximoStatus === 'PAGA' ? date('Y-m-d') : null;
        $stmt = $pdo->prepare('UPDATE conta_mes SET status = ?, paga_em = ? WHERE id = ? AND familia_id = ?');
        $stmt->execute([$proximoStatus, $pagaEm, $id, $familiaId]);
    }
} elseif ($acao === 'excluir') {
    $stmt = $pdo->prepare('DELETE FROM conta_mes WHERE id = ? AND familia_id = ?');
    $stmt->execute([(int) $_POST['id'], $familiaId]);
}

redirecionar('/contas.php');
