<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/layout.php';
require_once __DIR__ . '/../src/util.php';
require_once __DIR__ . '/../src/charts.php';

const TIPOS_META = [
    'Necessidade', 'Desejo', 'Saúde', 'Educação', 'Família', 'Trabalho', 'Casa', 'Investimento', 'Emergência',
];
const STATUS_META_LABEL = ['PLANEJADA' => 'Planejada', 'EM_ANDAMENTO' => 'Em andamento', 'CONCLUIDA' => 'Concluída', 'CANCELADA' => 'Cancelada'];

$pdo = conexao_banco();
$familiaId = (int) $usuario_atual['familia_id'];

$visao = ($_GET['visao'] ?? '') === 'concluidas' ? 'concluidas' : 'ativas';

$stmt = $pdo->prepare(
    'SELECT m.*, fm.nome AS membro_nome FROM meta m LEFT JOIN familia_membro fm ON fm.id = m.familia_membro_id
     WHERE m.familia_id = ? AND m.status = "CONCLUIDA" ORDER BY m.data_desejada DESC'
);
$stmt->execute([$familiaId]);
$metasConcluidas = $stmt->fetchAll();

if ($visao === 'concluidas') {
    $metas = $metasConcluidas;
} else {
    $stmt = $pdo->prepare(
        'SELECT m.*, fm.nome AS membro_nome FROM meta m LEFT JOIN familia_membro fm ON fm.id = m.familia_membro_id
         WHERE m.familia_id = ? AND m.status NOT IN ("CANCELADA", "CONCLUIDA")
         ORDER BY FIELD(m.prioridade, "URGENTE","ALTA","MEDIA","BAIXA"), m.data_desejada ASC'
    );
    $stmt->execute([$familiaId]);
    $metas = $stmt->fetchAll();
}

$stmt = $pdo->prepare('SELECT id, nome FROM familia_membro WHERE familia_id = ? ORDER BY nome ASC');
$stmt->execute([$familiaId]);
$membros = $stmt->fetchAll();

layout_topo($usuario_atual, 'metas', 'Metas');
layout_rodape($usuario_atual);
?>

<div class="pilha">
  <div class="linha-flex">
    <div>
      <h1 class="pagina-titulo"><?= $visao === 'concluidas' ? 'Metas concluídas' : 'Metas e prioridades' ?></h1>
      <p class="pagina-subtitulo">
        <?= $visao === 'concluidas'
          ? 'Objetivos que você já alcançou.'
          : 'Objetivos e necessidades futuras, com quanto falta guardar por mês.' ?>
      </p>
    </div>
    <?php if ($visao === 'concluidas'): ?>
      <a href="/metas.php" class="link-voltar"><?= icone('seta-esquerda') ?> Voltar para Metas</a>
    <?php else: ?>
      <a href="/metas.php?visao=concluidas" class="link-voltar">
        <?= icone('check') ?> Metas concluídas
        <?php if (count($metasConcluidas) > 0): ?><span class="pill-mes"><?= count($metasConcluidas) ?></span><?php endif; ?>
      </a>
    <?php endif; ?>
  </div>

  <?php if ($visao !== 'concluidas'): ?>
    <form method="post" action="/metas-processar.php" class="cartao form-grade">
      <?= csrf_campo_oculto($usuario_atual) ?>
      <?= info_icone("Cadastre aqui um objetivo maior — viagem, carro, um convênio médico. Depois clique em 'Ver detalhes' pra comparar cotações e listar os itens que a meta vai precisar.") ?>
      <input type="hidden" name="acao" value="criar">
      <input name="nome" placeholder="Nome da meta" required class="campo">
      <select name="tipo" required class="campo">
        <option value="" disabled selected>Tipo</option>
        <?php foreach (TIPOS_META as $tipo): ?>
          <option value="<?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
      <input name="valor_estimado" placeholder="Valor estimado" type="number" step="0.01" required class="campo">
      <input name="data_desejada" type="date" class="campo">
      <select name="prioridade" class="campo">
        <option value="URGENTE">Urgente</option>
        <option value="ALTA">Alta</option>
        <option value="MEDIA" selected>Média</option>
        <option value="BAIXA">Baixa</option>
      </select>
      <select name="familia_membro_id" class="campo">
        <option value="">Família (geral)</option>
        <?php foreach ($membros as $membro): ?>
          <option value="<?= (int) $membro['id'] ?>"><?= htmlspecialchars($membro['nome'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
      <input name="categoria" placeholder="Categoria (opcional)" class="campo">
      <input name="links_pesquisados" placeholder="Links/orçamentos pesquisados (opcional)" class="campo">
      <button type="submit" class="botao">Adicionar meta</button>
    </form>
  <?php endif; ?>

  <div class="pilha-pequena">
    <?php foreach ($metas as $meta):
      $valorRestante = max(0, (float) $meta['valor_estimado'] - (float) $meta['valor_guardado']);
      $meses = meses_restantes($meta['data_desejada']);
      $valorMensal = $meses ? $valorRestante / $meses : null;
      $progresso = min(100, (int) round(((float) $meta['valor_guardado'] / (float) $meta['valor_estimado']) * 100));
    ?>
      <div class="cartao pilha-pequena">
        <div class="linha-flex">
          <div>
            <a href="/metas-detalhe.php?id=<?= (int) $meta['id'] ?>" style="text-decoration:none;color:inherit">
              <h3 style="margin:0"><?= htmlspecialchars($meta['nome'], ENT_QUOTES, 'UTF-8') ?></h3>
            </a>
            <p class="texto-suave" style="font-size:0.8rem;margin:0.2rem 0 0">
              <?= htmlspecialchars($meta['tipo'], ENT_QUOTES, 'UTF-8') ?>
              <?= $meta['membro_nome'] ? ' · ' . htmlspecialchars($meta['membro_nome'], ENT_QUOTES, 'UTF-8') : '' ?>
              <?= $meta['data_desejada'] ? ' · até ' . formatar_data($meta['data_desejada']) : '' ?>
              · <?= STATUS_META_LABEL[$meta['status']] ?>
            </p>
          </div>
          <div class="acoes">
            <a href="/metas-detalhe.php?id=<?= (int) $meta['id'] ?>" class="link-acao">Ver detalhes</a>
            <form method="post" action="/metas-processar.php" onsubmit="return confirm('Excluir esta meta?');">
              <?= csrf_campo_oculto($usuario_atual) ?>
              <input type="hidden" name="acao" value="excluir">
              <input type="hidden" name="id" value="<?= (int) $meta['id'] ?>">
              <button class="link-acao link-perigo">Excluir</button>
            </form>
          </div>
        </div>

        <div class="progresso-trilho"><div class="progresso-barra" style="width: <?= $progresso ?>%"></div></div>

        <div class="grupo-valores">
          <div><p class="stat-rotulo">Estimado</p><p class="stat-valor" style="font-size:1rem"><?= formatar_moeda((float) $meta['valor_estimado']) ?></p></div>
          <div><p class="stat-rotulo">Guardado</p><p class="stat-valor" style="font-size:1rem"><?= formatar_moeda((float) $meta['valor_guardado']) ?></p></div>
          <div><p class="stat-rotulo">Falta</p><p class="stat-valor" style="font-size:1rem"><?= formatar_moeda($valorRestante) ?></p></div>
          <?php if ($valorMensal !== null && $valorRestante > 0): ?>
            <div><p class="stat-rotulo">Guardar por mês</p><p class="stat-valor" style="font-size:1rem"><?= formatar_moeda($valorMensal) ?></p></div>
          <?php endif; ?>
        </div>

        <?php if ($valorRestante > 0): ?>
          <form method="post" action="/metas-processar.php" class="linha-flex" style="justify-content:flex-start">
            <?= csrf_campo_oculto($usuario_atual) ?>
            <input type="hidden" name="acao" value="aportar">
            <input type="hidden" name="id" value="<?= (int) $meta['id'] ?>">
            <input name="valor_aporte" type="number" step="0.01" placeholder="Valor a guardar agora" required class="campo" style="width:12rem">
            <button class="botao botao-pequeno">Reservar valor</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (count($metas) === 0): ?>
      <p class="texto-suave"><?= $visao === 'concluidas' ? 'Nenhuma meta concluída ainda.' : 'Nenhuma meta cadastrada ainda.' ?></p>
    <?php endif; ?>
  </div>
</div>

<?php
fechar_layout();
