<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('/investimentos.php');
}

$pdo = conexao_banco();
$familiaId = (int) $usuario_atual['familia_id'];
$acao = $_POST['acao'] ?? '';

if ($acao === 'criar') {
    $valorAplicado = parse_valor($_POST['valor_aplicado'] ?? null);

    $stmt = $pdo->prepare(
        'INSERT INTO investimento (familia_id, nome, objetivo, instituicao, tipo, valor_aplicado, valor_atual, aporte_mensal, prazo, liquidez, rentabilidade_informada)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $familiaId,
        (string) $_POST['nome'],
        (string) $_POST['objetivo'],
        texto_ou_null($_POST['instituicao'] ?? null),
        texto_ou_null($_POST['tipo'] ?? null),
        $valorAplicado,
        $valorAplicado,
        parse_valor_ou_null($_POST['aporte_mensal'] ?? null),
        data_ou_null($_POST['prazo'] ?? null),
        texto_ou_null($_POST['liquidez'] ?? null),
        texto_ou_null($_POST['rentabilidade_informada'] ?? null),
    ]);
} elseif ($acao === 'aportar') {
    $id = (int) $_POST['id'];
    $valor = parse_valor($_POST['valor_aporte'] ?? null);

    $stmt = $pdo->prepare('SELECT valor_atual FROM investimento WHERE id = ? AND familia_id = ?');
    $stmt->execute([$id, $familiaId]);
    $inv = $stmt->fetch();

    if ($inv !== false) {
        $stmt = $pdo->prepare('UPDATE investimento SET valor_atual = ?, data_ultimo_aporte = CURDATE() WHERE id = ? AND familia_id = ?');
        $stmt->execute([(float) $inv['valor_atual'] + $valor, $id, $familiaId]);
    }
} elseif ($acao === 'excluir') {
    $stmt = $pdo->prepare('DELETE FROM investimento WHERE id = ? AND familia_id = ?');
    $stmt->execute([(int) $_POST['id'], $familiaId]);
}

redirecionar('/investimentos.php');
