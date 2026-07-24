<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/layout.php';
require_once __DIR__ . '/../src/util.php';
require_once __DIR__ . '/../src/charts.php';

const TIPOS_DIVIDA = [
    'Cartão de crédito', 'Empréstimo', 'Financiamento', 'Conta atrasada', 'Dívida com pessoa', 'Parcelamento', 'Imposto', 'Outros',
];
const STATUS_DIVIDA_SELO = ['EM_DIA' => 'selo-alerta', 'ATRASADA' => 'selo-perigo', 'QUITADA' => 'selo-sucesso'];
const STATUS_DIVIDA_LABEL = ['EM_DIA' => 'Pendente', 'ATRASADA' => 'Atrasada', 'QUITADA' => 'Paga'];

$pdo = conexao_banco();
$familiaId = (int) $usuario_atual['familia_id'];

$stmt = $pdo->prepare(
    'SELECT * FROM divida WHERE familia_id = ?
     ORDER BY FIELD(prioridade, "URGENTE","ALTA","MEDIA","BAIXA"), vencimento ASC'
);
$stmt->execute([$familiaId]);
$dividasBrutas = $stmt->fetchAll();

// Status agora é totalmente manual (ciclo pendente -> atrasada -> paga no
// próprio selo/botão), então o "exibido" é só o status real gravado. Dívidas
// pagas continuam aparecendo na lista (não somem mais), só não entram no
// total restante.
$dividas = array_map(static function (array $d): array {
    $d['status_exibido'] = $d['status'];
    return $d;
}, $dividasBrutas);

$totalRestante = array_sum(array_column(array_filter($dividas, fn($d) => $d['status'] !== 'QUITADA'), 'valor_atual'));

layout_topo($usuario_atual, 'dividas', 'Dívidas');
layout_rodape($usuario_atual);
?>

<div class="pilha">
  <div>
    <h1 class="pagina-titulo">Dívidas</h1>
    <p class="pagina-subtitulo">Total ainda restante: <strong><?= formatar_moeda($totalRestante) ?></strong></p>
  </div>

  <form method="post" action="/dividas-processar.php" class="cartao form-grade">
    <?= csrf_campo_oculto($usuario_atual) ?>
    <?= info_icone('Cadastre aqui uma dívida (empréstimo, financiamento, cartão, parcelamento...). Clique no status na tabela pra ir alternando entre pendente, atrasada e paga.') ?>
    <input type="hidden" name="acao" value="criar">
    <input name="nome" placeholder="Nome da dívida" required class="campo">
    <input name="credor" placeholder="Credor" required class="campo">
    <select name="tipo" required class="campo">
      <option value="" disabled selected>Tipo</option>
      <?php foreach (TIPOS_DIVIDA as $tipo): ?>
        <option value="<?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
    <input name="valor_original" placeholder="Valor original" type="number" step="0.01" required class="campo">
    <input name="numero_parcelas" placeholder="Nº de parcelas (opcional)" type="number" min="1" class="campo">
    <input name="valor_parcela" placeholder="Valor da parcela (opcional)" type="number" step="0.01" class="campo">
    <input name="vencimento" type="date" class="campo">
    <select name="prioridade" class="campo">
      <option value="URGENTE">Urgente</option>
      <option value="ALTA">Alta</option>
      <option value="MEDIA" selected>Média</option>
      <option value="BAIXA">Baixa</option>
    </select>
    <label class="campo-checkbox"><input type="checkbox" name="possibilidade_negociacao"> Possibilidade de negociação</label>
    <button type="submit" class="botao">Adicionar dívida</button>
  </form>

  <?php foreach ($dividas as $divida): $formId = 'divida-form-' . (int) $divida['id']; ?>
    <form id="<?= $formId ?>" method="post" action="/dividas-processar.php" style="display:none">
      <?= csrf_campo_oculto($usuario_atual) ?>
      <input type="hidden" name="acao" value="editar">
      <input type="hidden" name="id" value="<?= (int) $divida['id'] ?>">
    </form>
  <?php endforeach; ?>

  <div class="tabela-wrap">
    <table class="tabela">
      <thead><tr><th>Nome</th><th>Credor</th><th>Parcelas pagas</th><th>Valor original</th><th>Valor atual</th><th>Valor da parcela</th><th>Próximo vencimento</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($dividas as $divida):
          $status = $divida['status_exibido'];
          $formId = 'divida-form-' . (int) $divida['id'];
          $valorOriginal = (float) $divida['valor_original'];
          $valorPagoDivida = max(0, $valorOriginal - (float) $divida['valor_atual']);
          $progressoDivida = $valorOriginal > 0 ? min(100, (int) round($valorPagoDivida / $valorOriginal * 100)) : 0;
        ?>
          <tr>
            <td><input form="<?= $formId ?>" name="nome" value="<?= htmlspecialchars($divida['nome'], ENT_QUOTES, 'UTF-8') ?>" required class="campo campo-tabela"></td>
            <td><input form="<?= $formId ?>" name="credor" value="<?= htmlspecialchars($divida['credor'], ENT_QUOTES, 'UTF-8') ?>" required class="campo campo-tabela"></td>
            <td>
              <input form="<?= $formId ?>" name="parcelas_pagas" type="number" min="0" value="<?= (int) $divida['parcelas_pagas'] ?>" class="campo campo-tabela" style="width:4rem">
              <?php if ($divida['numero_parcelas']): ?><span class="texto-suave">/<?= (int) $divida['numero_parcelas'] ?></span><?php endif; ?>
            </td>
            <td>
              <?= formatar_moeda($valorOriginal) ?>
              <div class="texto-suave" style="font-size:0.75rem;margin-top:0.15rem">pago: <?= formatar_moeda($valorPagoDivida) ?></div>
              <div class="progresso-trilho" style="margin-top:0.25rem"><div class="progresso-barra" style="width: <?= $progressoDivida ?>%"></div></div>
            </td>
            <td><input form="<?= $formId ?>" name="valor_atual" type="number" step="0.01" min="0" value="<?= (float) $divida['valor_atual'] ?>" class="campo campo-tabela" style="width:7rem"></td>
            <td><input form="<?= $formId ?>" name="valor_parcela" type="number" step="0.01" min="0" value="<?= $divida['valor_parcela'] !== null ? (float) $divida['valor_parcela'] : '' ?>" class="campo campo-tabela" style="width:7rem"></td>
            <td><input form="<?= $formId ?>" name="vencimento" type="date" value="<?= $divida['vencimento'] ?? '' ?>" class="campo campo-tabela"></td>
            <td>
              <form method="post" action="/dividas-processar.php" style="display:inline">
                <?= csrf_campo_oculto($usuario_atual) ?>
                <input type="hidden" name="acao" value="alternar_status">
                <input type="hidden" name="id" value="<?= (int) $divida['id'] ?>">
                <button type="submit" class="selo <?= STATUS_DIVIDA_SELO[$status] ?>" title="Clique pra avançar o status (pendente → atrasada → paga)">
                  <?= STATUS_DIVIDA_LABEL[$status] ?>
                </button>
              </form>
            </td>
            <td>
              <div class="acoes">
                <form method="post" action="/dividas-processar.php" onsubmit="return confirm('Excluir esta dívida?');">
                  <?= csrf_campo_oculto($usuario_atual) ?>
                  <input type="hidden" name="acao" value="excluir">
                  <input type="hidden" name="id" value="<?= (int) $divida['id'] ?>">
                  <button class="link-acao link-perigo">Excluir</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (count($dividas) === 0): ?>
          <tr><td colspan="9" class="tabela-vazia">Nenhuma dívida cadastrada.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <p class="texto-suave" style="font-size:0.8rem">
    Edite nome, credor, parcelas pagas, valor atual, valor da parcela ou vencimento direto na tabela — salva sozinho ao sair do campo, sem precisar de um botão "Salvar".
  </p>
</div>

<script src="/assets/dividas-planilha.js"></script>

<?php
fechar_layout();
