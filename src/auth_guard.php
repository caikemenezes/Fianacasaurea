<?php

declare(strict_types=1);

/**
 * Equivalente ao src/proxy.ts do sistema original: incluir no topo de toda
 * página protegida (require_once). Redireciona pra /login.php se não houver
 * sessão válida. Deixa o usuário logado disponível em $usuario_atual.
 */

require_once __DIR__ . '/auth.php';

$usuario_atual = exigir_usuario_atual();

/**
 * Proteção CSRF centralizada: qualquer POST autenticado (criar/editar/excluir
 * em qualquer módulo) passa por aqui, já que todo *-processar.php inclui esse
 * arquivo. Cada formulário precisa do campo oculto de csrf_campo_oculto().
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenEnviado = (string) ($_POST['csrf_token'] ?? '');
    if (!csrf_valido($tokenEnviado, $usuario_atual['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Requisição inválida ou sessão expirada. Recarregue a página e tente de novo.');
    }
}
