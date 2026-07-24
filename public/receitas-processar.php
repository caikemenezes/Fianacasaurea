<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('/receitas.php');
}

$pdo = conexao_banco();
$familiaId = (int) $usuario_atual['familia_id'];
$acao = $_POST['acao'] ?? '';

if ($acao === 'criar') {
    $stmt = $pdo->prepare(
        'INSERT INTO receita (familia_id, nome, tipo, valor_previsto, data_prevista, categoria, recorrente, conta_bancaria, observacao)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $familiaId,
        (string) $_POST['nome'],
        (string) $_POST['tipo'],
        parse_valor($_POST['valor_previsto'] ?? null),
        (string) $_POST['data_prevista'],
        texto_ou_null($_POST['categoria'] ?? null),
        isset($_POST['recorrente']) ? 1 : 0,
        texto_ou_null($_POST['conta_bancaria'] ?? null),
        texto_ou_null($_POST['observacao'] ?? null),
    ]);
} elseif ($acao === 'marcar_recebida') {
    // Alterna: prevista -> recebida, e recebida -> volta pra prevista.
    $id = (int) $_POST['id'];
    $stmt = $pdo->prepare('SELECT status, valor_previsto FROM receita WHERE id = ? AND familia_id = ?');
    $stmt->execute([$id, $familiaId]);
    $receita = $stmt->fetch();

    if ($receita !== false) {
        if ($receita['status'] === 'RECEBIDO') {
            $stmt = $pdo->prepare('UPDATE receita SET status = "PREVISTO", data_recebimento = NULL, valor_recebido = NULL WHERE id = ? AND familia_id = ?');
            $stmt->execute([$id, $familiaId]);
        } else {
            $stmt = $pdo->prepare('UPDATE receita SET status = "RECEBIDO", data_recebimento = CURDATE(), valor_recebido = ? WHERE id = ? AND familia_id = ?');
            $stmt->execute([$receita['valor_previsto'], $id, $familiaId]);
        }
    }
} elseif ($acao === 'excluir') {
    $stmt = $pdo->prepare('DELETE FROM receita WHERE id = ? AND familia_id = ?');
    $stmt->execute([(int) $_POST['id'], $familiaId]);
}

redirecionar('/receitas.php');
