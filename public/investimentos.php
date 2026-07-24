<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/layout.php';
require_once __DIR__ . '/../src/util.php';
require_once __DIR__ . '/../src/charts.php';

const OBJETIVOS_INVESTIMENTO = ['Reserva de emergência', 'Objetivo específico', 'Aposentadoria', 'Curto prazo', 'Longo prazo'];

$pdo = conexao_banco();
$familiaId = (int) $usuario_atual['familia_id'];

$stmt = $pdo->prepare('SELECT * FROM investimento WHERE familia_id = ? ORDER BY criado_em DESC');
$stmt->execute([$familiaId]);
$investimentos = $stmt->fetchAll();

$totalAplicado = array_sum(array_column($investimentos, 'valor_aplicado'));
$totalAtual = array_sum(array_column($investimentos, 'valor_atual'));
$rendimento = $totalAtual - $totalAplicado;

layout_topo($usuario_atual, 'investimentos', 'Investimentos e Reservas');
layout_rodape($usuario_atual);
?>

<div class="pilha">
  <div>
    <h1 class="pagina-titulo">Investimentos e reservas</h1>
    <p class="pagina-subtitulo">
      Total investido: <strong><?= formatar_moeda($totalAtual) ?></strong> ·
      Rendimento acumulado: <strong style="color: <?= $rendimento >= 0 ? 'var(--sucesso)' : 'var(--perigo)' ?>"><?= formatar_moeda($rendimento) ?></strong>
    </p>
  </div>

  <form method="post" action="/investimentos-processar.php" class="cartao form-grade">
    <?= csrf_campo_oculto($usuario_atual) ?>
    <?= info_icone("Cadastre um investimento ou reserva (CDB, Tesouro, poupança...). Depois use 'Registrar aporte' na tabela pra ir somando valor conforme você guarda dinheiro nele.") ?>
    <input type="hidden" name="acao" value="criar">
    <input name="nome" placeholder="Nome do investimento" required class="campo">
    <select name="objetivo" required class="campo">
      <option value="" disabled selected>Objetivo</option>
      <?php foreach (OBJETIVOS_INVESTIMENTO as $objetivo): ?>
        <option value="<?= htmlspecialchars($objetivo, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($objetivo, ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
    <input name="instituicao" placeholder="Instituição (opcional)" class="campo">
    <input name="tipo" placeholder="Tipo (ex: CDB, Tesouro, Poupança)" class="campo">
    <input name="valor_aplicado" placeholder="Valor aplicado" type="number" step="0.01" required class="campo">
    <input name="aporte_mensal" placeholder="Aporte mensal planejado (opcional)" type="number" step="0.01" class="campo">
    <input name="prazo" type="date" class="campo" title="Prazo (opcional)">
    <input name="liquidez" placeholder="Liquidez (ex: imediata, 30 dias)" class="campo">
    <input name="rentabilidade_informada" placeholder="Rentabilidade informada (ex: 110% do CDI)" class="campo">
    <button type="submit" class="botao botao-abrange-linha">Adicionar investimento</button>
  </form>

  <div class="tabela-wrap">
    <table class="tabela">
      <thead><tr><th>Nome</th><th>Objetivo</th><th>Valor atual</th><th>Aporte mensal</th><th>Último aporte</th><th></th><th></th></tr></thead>
      <tbody>
        <?php foreach ($investimentos as $inv): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($inv['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
              <?php if ($inv['instituicao']): ?>
                <p class="texto-suave" style="font-size:0.8rem;margin:0"><?= htmlspecialchars($inv['instituicao'], ENT_QUOTES, 'UTF-8') ?><?= $inv['tipo'] ? ' · ' . htmlspecialchars($inv['tipo'], ENT_QUOTES, 'UTF-8') : '' ?></p>
              <?php endif; ?>
            </td>
            <td class="texto-suave"><?= htmlspecialchars($inv['objetivo'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= formatar_moeda((float) $inv['valor_atual']) ?></td>
            <td><?= $inv['aporte_mensal'] !== null ? formatar_moeda((float) $inv['aporte_mensal']) : '—' ?></td>
            <td class="texto-suave"><?= $inv['data_ultimo_aporte'] !== null ? formatar_data($inv['data_ultimo_aporte']) : '—' ?></td>
            <td>
              <form method="post" action="/investimentos-processar.php" class="linha-flex" style="justify-content:flex-start">
                <?= csrf_campo_oculto($usuario_atual) ?>
                <input type="hidden" name="acao" value="aportar">
                <input type="hidden" name="id" value="<?= (int) $inv['id'] ?>">
                <input name="valor_aporte" type="number" step="0.01" placeholder="Valor do aporte" required class="campo" style="width:9rem">
                <button class="botao botao-pequeno">Registrar aporte</button>
              </form>
            </td>
            <td>
              <form method="post" action="/investimentos-processar.php" onsubmit="return confirm('Excluir este investimento?');">
                <?= csrf_campo_oculto($usuario_atual) ?>
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="id" value="<?= (int) $inv['id'] ?>">
                <button class="link-acao link-perigo">Excluir</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (count($investimentos) === 0): ?>
          <tr><td colspan="7" class="tabela-vazia">Nenhum investimento cadastrado ainda.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
fechar_layout();
