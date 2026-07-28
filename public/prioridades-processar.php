<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('/prioridades.php');
}

$pdo = conexao_banco();
$familiaId = (int) $usuario_atual['familia_id'];
$acao = $_POST['acao'] ?? '';

if ($acao === 'criar') {
    $stmt = $pdo->prepare(
        'INSERT INTO necessidade (familia_id, item, pessoa_nome, categoria, prioridade, valor_estimado, mes_planejado, identificador_extrato)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $familiaId,
        (string) $_POST['item'],
        texto_ou_null($_POST['pessoa_nome'] ?? null),
        (string) $_POST['categoria'],
        in_array($_POST['prioridade'] ?? '', ['URGENTE', 'ALTA', 'MEDIA', 'BAIXA'], true) ? $_POST['prioridade'] : 'MEDIA',
        parse_valor($_POST['valor_estimado'] ?? null),
        (string) $_POST['mes_planejado'],
        texto_ou_null($_POST['identificador_extrato'] ?? null),
    ]);
} elseif ($acao === 'aportar') {
    $id = (int) $_POST['id'];
    $valor = parse_valor($_POST['valor_aporte'] ?? null);

    $stmt = $pdo->prepare('SELECT valor_estimado, valor_guardado FROM necessidade WHERE id = ? AND familia_id = ?');
    $stmt->execute([$id, $familiaId]);
    $item = $stmt->fetch();

    if ($item !== false) {
        $novoGuardado = (float) $item['valor_guardado'] + $valor;
        $status = $novoGuardado >= (float) $item['valor_estimado'] ? 'CONCLUIDA' : 'EM_ANDAMENTO';
        $stmt = $pdo->prepare('UPDATE necessidade SET valor_guardado = ?, status = ? WHERE id = ? AND familia_id = ?');
        $stmt->execute([$novoGuardado, $status, $id, $familiaId]);
    }
} elseif ($acao === 'excluir') {
    $stmt = $pdo->prepare('DELETE FROM necessidade WHERE id = ? AND familia_id = ?');
    $stmt->execute([(int) $_POST['id'], $familiaId]);
}

redirecionar('/prioridades.php');
