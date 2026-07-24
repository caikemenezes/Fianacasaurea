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
$metaId = (int) ($_POST['meta_id'] ?? $_POST['id'] ?? 0);

/** Confirma que a meta pertence à família logada antes de mexer nela ou em qualquer sub-item. */
function meta_pertence_a_familia(PDO $pdo, int $metaId, int $familiaId): bool
{
    $stmt = $pdo->prepare('SELECT id FROM meta WHERE id = ? AND familia_id = ?');
    $stmt->execute([$metaId, $familiaId]);
    return $stmt->fetch() !== false;
}

if ($acao === 'atualizar_observacoes') {
    $id = (int) $_POST['id'];
    if (meta_pertence_a_familia($pdo, $id, $familiaId)) {
        $stmt = $pdo->prepare('UPDATE meta SET observacoes = ? WHERE id = ? AND familia_id = ?');
        $stmt->execute([texto_ou_null($_POST['observacoes'] ?? null), $id, $familiaId]);
    }
    redirecionar("/metas-detalhe.php?id={$id}");
}

if (!meta_pertence_a_familia($pdo, $metaId, $familiaId)) {
    redirecionar('/metas.php');
}

if ($acao === 'adicionar_cotacao') {
    $stmt = $pdo->prepare('INSERT INTO meta_cotacao (meta_id, item, fornecedor, valor, link) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $metaId,
        (string) $_POST['item'],
        (string) $_POST['fornecedor'],
        parse_valor($_POST['valor'] ?? null),
        texto_ou_null($_POST['link'] ?? null),
    ]);
} elseif ($acao === 'alternar_cotacao') {
    $id = (int) $_POST['id'];
    $stmt = $pdo->prepare('UPDATE meta_cotacao SET escolhida = NOT escolhida WHERE id = ? AND meta_id = ?');
    $stmt->execute([$id, $metaId]);
} elseif ($acao === 'excluir_cotacao') {
    $stmt = $pdo->prepare('DELETE FROM meta_cotacao WHERE id = ? AND meta_id = ?');
    $stmt->execute([(int) $_POST['id'], $metaId]);
} elseif ($acao === 'adicionar_item') {
    $stmt = $pdo->prepare('INSERT INTO meta_item_necessario (meta_id, nome, valor_estimado) VALUES (?, ?, ?)');
    $stmt->execute([$metaId, (string) $_POST['nome'], parse_valor($_POST['valor_estimado'] ?? null)]);
} elseif ($acao === 'alternar_item') {
    $id = (int) $_POST['id'];
    $stmt = $pdo->prepare('UPDATE meta_item_necessario SET concluido = NOT concluido WHERE id = ? AND meta_id = ?');
    $stmt->execute([$id, $metaId]);
} elseif ($acao === 'excluir_item') {
    $stmt = $pdo->prepare('DELETE FROM meta_item_necessario WHERE id = ? AND meta_id = ?');
    $stmt->execute([(int) $_POST['id'], $metaId]);
}

redirecionar("/metas-detalhe.php?id={$metaId}");
