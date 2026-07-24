<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('/metas.php');
}

$pdo = conexao_banco();
$familiaId = (int) $usuario_atual['familia_id'];
$acao = $_POST['acao'] ?? '';

if ($acao === 'criar') {
    $familiaMembroId = texto_ou_null($_POST['familia_membro_id'] ?? null);

    $stmt = $pdo->prepare(
        'INSERT INTO meta (familia_id, familia_membro_id, nome, tipo, categoria, valor_estimado, data_desejada, prioridade, links_pesquisados)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $familiaId,
        $familiaMembroId !== null ? (int) $familiaMembroId : null,
        (string) $_POST['nome'],
        (string) $_POST['tipo'],
        texto_ou_null($_POST['categoria'] ?? null),
        parse_valor($_POST['valor_estimado'] ?? null),
        data_ou_null($_POST['data_desejada'] ?? null),
        in_array($_POST['prioridade'] ?? '', ['URGENTE', 'ALTA', 'MEDIA', 'BAIXA'], true) ? $_POST['prioridade'] : 'MEDIA',
        texto_ou_null($_POST['links_pesquisados'] ?? null),
    ]);
} elseif ($acao === 'aportar') {
    $id = (int) $_POST['id'];
    $valor = parse_valor($_POST['valor_aporte'] ?? null);

    $stmt = $pdo->prepare('SELECT valor_estimado, valor_guardado FROM meta WHERE id = ? AND familia_id = ?');
    $stmt->execute([$id, $familiaId]);
    $meta = $stmt->fetch();

    if ($meta !== false) {
        $novoGuardado = (float) $meta['valor_guardado'] + $valor;
        $status = $novoGuardado >= (float) $meta['valor_estimado'] ? 'CONCLUIDA' : 'EM_ANDAMENTO';
        $stmt = $pdo->prepare('UPDATE meta SET valor_guardado = ?, status = ? WHERE id = ? AND familia_id = ?');
        $stmt->execute([$novoGuardado, $status, $id, $familiaId]);
    }
} elseif ($acao === 'excluir') {
    $stmt = $pdo->prepare('DELETE FROM meta WHERE id = ? AND familia_id = ?');
    $stmt->execute([(int) $_POST['id'], $familiaId]);
}

redirecionar('/metas.php');
