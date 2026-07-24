<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/layout.php';
require_once __DIR__ . '/../src/util.php';
require_once __DIR__ . '/../src/charts.php';

const TIPOS_RECEITA = [
    'Salário', 'Trabalho freelancer', 'Serviços de audiovisual', 'Venda de produtos',
    'Comissão', 'Renda extra', 'Benefícios', 'Cashback', 'Outros',
];

$pdo = conexao_banco();
$familiaId = (int) $usuario_atual['familia_id'];

$stmt = $pdo->prepare('SELECT * FROM receita WHERE familia_id = ? ORDER BY data_prevista ASC');
$stmt->execute([$familiaId]);
$receitas = $stmt->fetchAll();

layout_topo($usuario_atual, 'receitas', 'Receitas');
layout_rodape($usuario_atual);
?>

<div class="pilha">
  <div>
    <h1 class="pagina-titulo">Receitas</h1>
    <p class="pagina-subtitulo">Tudo que entra: previsto e recebido.</p>
  </div>

  <form method="post" action="/receitas-processar.php" class="cartao form-grade">
    <?= csrf_campo_oculto($usuario_atual) ?>
    <?= info_icone('Cadastre aqui uma entrada de dinheiro prevista (salário, freelance, etc.). Depois marque como recebida quando o valor cair na conta — isso é o que alimenta a Renda do Mês no Dashboard.') ?>
    <input type="hidden" name="acao" value="criar">
    <input name="nome" placeholder="Nome" required class="campo">
    <select name="tipo" required class="campo">
      <option value="" disabled selected>Tipo</option>
      <?php foreach (TIPOS_RECEITA as $tipo): ?>
        <option value="<?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
    <input name="valor_previsto" placeholder="Valor previsto" type="text" inputmode="decimal" data-moeda required class="campo">
    <input name="data_prevista" type="date" required class="campo">
    <input name="categoria" placeholder="Categoria (opcional)" class="campo">
    <input name="conta_bancaria" placeholder="Conta bancária (opcional)" class="campo">
    <input name="observacao" placeholder="Observação (opcional)" class="campo">
    <label class="campo-checkbox"><input type="checkbox" name="recorrente"> Recorrente</label>
    <button type="submit" class="botao">Adicionar receita</button>
  </form>

  <div class="tabela-wrap">
    <table class="tabela">
      <thead><tr><th>Nome</th><th>Tipo</th><th>Previsto</th><th>Data prevista</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($receitas as $receita): ?>
          <tr>
            <td><strong><?= htmlspecialchars($receita['nome'], ENT_QUOTES, 'UTF-8') ?></strong></td>
            <td class="texto-suave"><?= htmlspecialchars($receita['tipo'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= formatar_moeda((float) $receita['valor_previsto']) ?></td>
            <td><?= formatar_data($receita['data_prevista']) ?></td>
            <td>
              <form method="post" action="/receitas-processar.php" style="display:inline">
                <?= csrf_campo_oculto($usuario_atual) ?>
                <input type="hidden" name="acao" value="marcar_recebida">
                <input type="hidden" name="id" value="<?= (int) $receita['id'] ?>">
                <button type="submit" class="selo <?= $receita['status'] === 'RECEBIDO' ? 'selo-sucesso' : 'selo-alerta' ?>" title="Clique pra mudar o status">
                  <?= $receita['status'] === 'RECEBIDO' ? 'Recebida' : 'Prevista' ?>
                </button>
              </form>
            </td>
            <td>
              <div class="acoes">
                <form method="post" action="/receitas-processar.php" onsubmit="return confirm('Excluir esta receita?');">
                  <?= csrf_campo_oculto($usuario_atual) ?>
                  <input type="hidden" name="acao" value="excluir">
                  <input type="hidden" name="id" value="<?= (int) $receita['id'] ?>">
                  <button class="link-acao link-perigo">Excluir</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (count($receitas) === 0): ?>
          <tr><td colspan="6" class="tabela-vazia">Nenhuma receita cadastrada ainda.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
fechar_layout();
