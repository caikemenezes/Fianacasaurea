<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/layout.php';
require_once __DIR__ . '/../src/util.php';
require_once __DIR__ . '/../src/charts.php';

const STATUS_META_LABEL_DET = ['PLANEJADA' => 'Planejada', 'EM_ANDAMENTO' => 'Em andamento', 'CONCLUIDA' => 'Concluída', 'CANCELADA' => 'Cancelada'];
const PRIORIDADE_LABEL_DET = ['URGENTE' => 'Urgente', 'ALTA' => 'Alta', 'MEDIA' => 'Média', 'BAIXA' => 'Baixa'];
const PRIORIDADE_SELO_DET = ['URGENTE' => 'selo-perigo', 'ALTA' => 'selo-alerta', 'MEDIA' => 'selo-info', 'BAIXA' => 'selo-neutro'];

$pdo = conexao_banco();
$familiaId = (int) $usuario_atual['familia_id'];
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT m.*, fm.nome AS membro_nome FROM meta m LEFT JOIN familia_membro fm ON fm.id = m.familia_membro_id
     WHERE m.id = ? AND m.familia_id = ?'
);
$stmt->execute([$id, $familiaId]);
$meta = $stmt->fetch();

if ($meta === false) {
    http_response_code(404);
    layout_topo($usuario_atual, 'metas', 'Meta não encontrada');
    layout_rodape($usuario_atual);
    echo '<div class="pilha"><p class="texto-suave">Meta não encontrada.</p><a href="/metas.php" class="link-acao">Voltar para Metas</a></div>';
    fechar_layout();
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM meta_cotacao WHERE meta_id = ? ORDER BY criado_em ASC');
$stmt->execute([$id]);
$cotacoes = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM meta_item_necessario WHERE meta_id = ? ORDER BY criado_em ASC');
$stmt->execute([$id]);
$itensNecessarios = $stmt->fetchAll();

$valorRestante = max(0, (float) $meta['valor_estimado'] - (float) $meta['valor_guardado']);
$meses = meses_restantes($meta['data_desejada']);
$valorMensal = $meses ? $valorRestante / $meses : null;
$progresso = ((float) $meta['valor_guardado'] / (float) $meta['valor_estimado']) * 100;

$totalCotacoesEscolhidas = array_sum(array_column(array_filter($cotacoes, fn($c) => (int) $c['escolhida'] === 1), 'valor'));
$totalItensNecessarios = array_sum(array_column($itensNecessarios, 'valor_estimado'));
$totalItensConcluidos = array_sum(array_column(array_filter($itensNecessarios, fn($i) => (int) $i['concluido'] === 1), 'valor_estimado'));
$totalCalculado = $totalCotacoesEscolhidas + $totalItensNecessarios;

$cotacoesPorItem = [];
foreach ($cotacoes as $c) {
    $cotacoesPorItem[$c['item']][] = $c;
}

layout_topo($usuario_atual, 'metas', htmlspecialchars($meta['nome'], ENT_QUOTES, 'UTF-8'));
layout_rodape($usuario_atual);
?>

<div class="pilha">
  <div>
    <a href="/metas.php" class="link-voltar"><?= icone('seta-esquerda') ?> Voltar para Metas</a>
  </div>

  <div class="cartao">
    <?= info_icone('Visão geral da meta: valor estimado, quanto já foi guardado, quanto falta e o progresso. Esses números vêm dos aportes feitos na página de Metas.') ?>
    <div class="linha-flex">
      <div>
        <h1 class="pagina-titulo"><?= htmlspecialchars($meta['nome'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="pagina-subtitulo">
          <?= htmlspecialchars($meta['tipo'], ENT_QUOTES, 'UTF-8') ?>
          <?= $meta['categoria'] ? ' · ' . htmlspecialchars($meta['categoria'], ENT_QUOTES, 'UTF-8') : '' ?>
          <?= $meta['membro_nome'] ? ' · ' . htmlspecialchars($meta['membro_nome'], ENT_QUOTES, 'UTF-8') : '' ?>
          <?= $meta['data_desejada'] ? ' · até ' . formatar_data($meta['data_desejada']) : '' ?>
        </p>
      </div>
      <div style="display:flex;gap:0.4rem;flex-wrap:wrap">
        <span class="selo <?= PRIORIDADE_SELO_DET[$meta['prioridade']] ?>"><?= PRIORIDADE_LABEL_DET[$meta['prioridade']] ?></span>
        <span class="selo selo-neutro"><?= STATUS_META_LABEL_DET[$meta['status']] ?></span>
      </div>
    </div>

    <div class="linha-flex" style="margin-top:1rem;align-items:center;gap:1.5rem">
      <?= anel_progresso($progresso, 64) ?>
      <div class="grupo-valores">
        <div><p class="stat-rotulo">Estimado</p><p class="stat-valor" style="font-size:1rem"><?= formatar_moeda((float) $meta['valor_estimado']) ?></p></div>
        <div><p class="stat-rotulo">Guardado</p><p class="stat-valor" style="font-size:1rem"><?= formatar_moeda((float) $meta['valor_guardado']) ?></p></div>
        <div><p class="stat-rotulo">Falta</p><p class="stat-valor" style="font-size:1rem"><?= formatar_moeda($valorRestante) ?></p></div>
        <?php if ($valorMensal !== null && $valorRestante > 0): ?>
          <div><p class="stat-rotulo">Guardar por mês</p><p class="stat-valor" style="font-size:1rem"><?= formatar_moeda($valorMensal) ?></p></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="cartao pilha-pequena">
    <?= info_icone('Anotações livres sobre a meta — onde vai ficar, o que precisa resolver antes, qualquer detalhe que ajude a lembrar depois.') ?>
    <h2 class="cartao-titulo">Observações</h2>
    <form method="post" action="/metas-detalhe-processar.php" class="pilha-pequena">
      <?= csrf_campo_oculto($usuario_atual) ?>
      <input type="hidden" name="acao" value="atualizar_observacoes">
      <input type="hidden" name="id" value="<?= $id ?>">
      <textarea name="observacoes" placeholder="Onde vamos ficar, o que precisa ser resolvido antes, detalhes gerais..." class="campo" rows="3" style="resize:vertical;font-family:inherit"><?= htmlspecialchars($meta['observacoes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
      <button type="submit" class="botao botao-pequeno" style="align-self:flex-start">Salvar observações</button>
    </form>
  </div>

  <div class="cartao pilha-pequena">
    <?= info_icone('Compare opções pesquisadas pra cada item da meta (ex: hotéis diferentes pra "Hospedagem") e marque a escolhida. Só as escolhidas entram no total calculado lá embaixo.') ?>
    <div class="linha-flex">
      <h2 class="cartao-titulo">Cotações</h2>
      <p class="texto-suave" style="font-size:0.85rem">Escolhidas: <strong><?= formatar_moeda($totalCotacoesEscolhidas) ?></strong></p>
    </div>
    <p class="texto-suave" style="font-size:0.8rem;margin:0">Compare as opções pesquisadas (hospedagem, passagem, etc.) e marque a escolhida.</p>

    <form method="post" action="/metas-detalhe-processar.php" class="form-grade">
      <?= csrf_campo_oculto($usuario_atual) ?>
      <input type="hidden" name="acao" value="adicionar_cotacao">
      <input type="hidden" name="meta_id" value="<?= $id ?>">
      <input name="item" placeholder="Item (ex: Hospedagem)" required class="campo">
      <input name="fornecedor" placeholder="Fornecedor/opção (ex: Hotel X)" required class="campo">
      <input name="valor" placeholder="Valor" type="number" step="0.01" required class="campo">
      <input name="link" placeholder="Link (opcional)" class="campo">
      <button type="submit" class="botao botao-abrange-linha">Adicionar cotação</button>
    </form>

    <?php if (count($cotacoes) === 0): ?>
      <p class="item-vazio">Nenhuma cotação cadastrada ainda.</p>
    <?php endif; ?>

    <?php foreach ($cotacoesPorItem as $item => $cotacoesDoItem): ?>
      <div class="pilha-pequena">
        <p style="margin:0.5rem 0 0;font-weight:600;font-size:0.85rem"><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></p>
        <div class="tabela-wrap">
          <table class="tabela">
            <thead><tr><th>Fornecedor/opção</th><th>Valor</th><th>Link</th><th></th><th></th></tr></thead>
            <tbody>
              <?php foreach ($cotacoesDoItem as $c): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($c['fornecedor'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                  <td><?= formatar_moeda((float) $c['valor']) ?></td>
                  <td><?php if ($c['link']): ?><a href="<?= htmlspecialchars($c['link'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noreferrer" class="link-acao">Ver link</a><?php else: ?><span class="texto-suave">—</span><?php endif; ?></td>
                  <td><?php if ((int) $c['escolhida'] === 1): ?><span class="selo selo-sucesso">Escolhida</span><?php else: ?><span class="texto-suave" style="font-size:0.8rem">—</span><?php endif; ?></td>
                  <td>
                    <div class="acoes">
                      <form method="post" action="/metas-detalhe-processar.php">
                        <?= csrf_campo_oculto($usuario_atual) ?>
                        <input type="hidden" name="acao" value="alternar_cotacao">
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                        <input type="hidden" name="meta_id" value="<?= $id ?>">
                        <button class="link-acao link-sucesso"><?= (int) $c['escolhida'] === 1 ? 'Desmarcar' : 'Escolher' ?></button>
                      </form>
                      <form method="post" action="/metas-detalhe-processar.php">
                        <?= csrf_campo_oculto($usuario_atual) ?>
                        <input type="hidden" name="acao" value="excluir_cotacao">
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                        <input type="hidden" name="meta_id" value="<?= $id ?>">
                        <button class="link-acao link-perigo">Excluir</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="cartao pilha-pequena">
    <?= info_icone('Lista do que precisa ser comprado ou resolvido pra meta acontecer, com o valor de cada item. Marque como resolvido conforme for providenciando.') ?>
    <div class="linha-flex">
      <h2 class="cartao-titulo">O que vai precisar</h2>
      <p class="texto-suave" style="font-size:0.85rem">Total: <strong><?= formatar_moeda($totalItensNecessarios) ?></strong> · Resolvido: <strong><?= formatar_moeda($totalItensConcluidos) ?></strong></p>
    </div>
    <p class="texto-suave" style="font-size:0.8rem;margin:0">Lista do que precisa ser comprado, reservado ou resolvido para essa meta acontecer.</p>

    <form method="post" action="/metas-detalhe-processar.php" class="form-grade">
      <?= csrf_campo_oculto($usuario_atual) ?>
      <input type="hidden" name="acao" value="adicionar_item">
      <input type="hidden" name="meta_id" value="<?= $id ?>">
      <input name="nome" placeholder="Item (ex: Passagem aérea)" required class="campo">
      <input name="valor_estimado" placeholder="Valor estimado" type="number" step="0.01" required class="campo">
      <button type="submit" class="botao botao-abrange-linha">Adicionar item</button>
    </form>

    <?php if (count($itensNecessarios) === 0): ?>
      <p class="item-vazio">Nenhum item cadastrado ainda.</p>
    <?php else: ?>
      <div class="tabela-wrap">
        <table class="tabela">
          <thead><tr><th>Item</th><th>Valor estimado</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($itensNecessarios as $i): ?>
              <tr>
                <td style="<?= (int) $i['concluido'] === 1 ? 'text-decoration:line-through;opacity:0.6' : '' ?>"><?= htmlspecialchars($i['nome'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= formatar_moeda((float) $i['valor_estimado']) ?></td>
                <td><span class="selo <?= (int) $i['concluido'] === 1 ? 'selo-sucesso' : 'selo-alerta' ?>"><?= (int) $i['concluido'] === 1 ? 'Resolvido' : 'Pendente' ?></span></td>
                <td>
                  <div class="acoes">
                    <form method="post" action="/metas-detalhe-processar.php">
                      <?= csrf_campo_oculto($usuario_atual) ?>
                      <input type="hidden" name="acao" value="alternar_item">
                      <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                      <input type="hidden" name="meta_id" value="<?= $id ?>">
                      <button class="link-acao link-sucesso"><?= (int) $i['concluido'] === 1 ? 'Reabrir' : 'Marcar resolvido' ?></button>
                    </form>
                    <form method="post" action="/metas-detalhe-processar.php">
                      <?= csrf_campo_oculto($usuario_atual) ?>
                      <input type="hidden" name="acao" value="excluir_item">
                      <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                      <input type="hidden" name="meta_id" value="<?= $id ?>">
                      <button class="link-acao link-perigo">Excluir</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="cartao">
    <?= info_icone('Compara o valor estimado que você digitou ao criar a meta com o total calculado a partir das cotações escolhidas e itens necessários — útil pra saber se a estimativa original ainda faz sentido.') ?>
    <div class="linha-flex">
      <div><p class="stat-rotulo">Valor estimado (manual)</p><p class="stat-valor"><?= formatar_moeda((float) $meta['valor_estimado']) ?></p></div>
      <div>
        <p class="stat-rotulo">Total calculado (cotações escolhidas + itens)</p>
        <p class="stat-valor" style="color: <?= $totalCalculado > (float) $meta['valor_estimado'] ? 'var(--perigo)' : 'var(--sucesso)' ?>"><?= formatar_moeda($totalCalculado) ?></p>
      </div>
    </div>
    <?php if ($totalCalculado > (float) $meta['valor_estimado']): ?>
      <p class="texto-suave" style="font-size:0.8rem;margin-top:0.5rem">
        O que já foi cotado e listado passa o valor estimado em <?= formatar_moeda($totalCalculado - (float) $meta['valor_estimado']) ?>. Pode valer a pena revisar o valor estimado da meta.
      </p>
    <?php endif; ?>
  </div>
</div>

<?php
fechar_layout();
