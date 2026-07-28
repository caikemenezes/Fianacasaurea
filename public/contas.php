<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/layout.php';
require_once __DIR__ . '/../src/util.php';
require_once __DIR__ . '/../src/charts.php';
require_once __DIR__ . '/../src/dashboard.php';

const CATEGORIAS_CONTA = [
    'Moradia', 'Alimentação', 'Transporte', 'Saúde', 'Assinaturas', 'Trabalho e estudos', 'Cartão de Crédito',
];

const FILTROS_CONTA = [
    'todas' => 'Todas', 'pendente' => 'Pendentes', 'atrasada' => 'Atrasadas', 'paga' => 'Pagas',
];

$pdo = conexao_banco();
$familiaId = (int) $usuario_atual['familia_id'];

$filtroAtivo = array_key_exists($_GET['status'] ?? '', FILTROS_CONTA) ? $_GET['status'] : 'todas';

$stmt = $pdo->prepare('SELECT * FROM conta_mes WHERE familia_id = ? ORDER BY vencimento ASC');
$stmt->execute([$familiaId]);
$contasBrutas = $stmt->fetchAll();

// Status agora é totalmente manual (ciclo pendente -> atrasada -> paga no
// próprio selo/botão), então o "exibido" é só o status real gravado.
$contas = array_map(static function (array $conta): array {
    $conta['status_exibido'] = $conta['status'];
    return $conta;
}, $contasBrutas);

// Pra conta parcelada (numero_parcelas preenchido), "valor" é o total
// financiado, não o que vence este mês — quem representa isso é
// valor_parcela. Contas normais (sem parcelamento) continuam usando "valor".
// Enquanto valor_parcela não for preenchido numa conta parcelada, conta como
// 0 (nunca o total) — senão um financiamento de 60 mil aparece inteiro como
// "falta pagar este mês" só porque a parcela ainda não foi cadastrada.
$valorMensalDaConta = static fn(array $c): float => $c['numero_parcelas'] !== null
    ? (float) ($c['valor_parcela'] ?? 0)
    : (float) $c['valor'];

$totalPendente = array_sum(array_map($valorMensalDaConta, array_filter($contas, fn($c) => $c['status_exibido'] === 'PENDENTE')));
$totalAtrasado = array_sum(array_map($valorMensalDaConta, array_filter($contas, fn($c) => $c['status_exibido'] === 'ATRASADA')));
$totalPago = array_sum(array_map($valorMensalDaConta, array_filter($contas, fn($c) => $c['status_exibido'] === 'PAGA')));
$totalAPagar = $totalPendente + $totalAtrasado;

$resumoMes = resumo_do_mes($pdo, $familiaId, date('Y-m'));
$saldoEmCaixa = $resumoMes['saldo_disponivel'];
$maiorValor = max($totalAPagar, $totalPago, $saldoEmCaixa, 1);

// Meses com transações importadas, pro seletor abaixo — mais recente primeiro.
$stmt = $pdo->prepare(
    'SELECT DISTINCT DATE_FORMAT(data, "%Y-%m") AS mes FROM transacao_importada
     WHERE familia_id = ? ORDER BY mes DESC'
);
$stmt->execute([$familiaId]);
$mesesExtrato = array_column($stmt->fetchAll(), 'mes');

$mesAtual = date('Y-m');
$mesExtratoParam = (string) ($_GET['mes_extrato'] ?? $mesAtual);
$mesExtrato = preg_match('/^\d{4}-\d{2}$/', $mesExtratoParam) ? $mesExtratoParam : $mesAtual;
if (!in_array($mesExtrato, $mesesExtrato, true)) {
    $mesesExtrato[] = $mesExtrato;
    rsort($mesesExtrato);
}

$stmt = $pdo->prepare(
    'SELECT * FROM transacao_importada
     WHERE familia_id = ? AND status = "PENDENTE" AND DATE_FORMAT(data, "%Y-%m") = ?
     ORDER BY data DESC'
);
$stmt->execute([$familiaId, $mesExtrato]);
$transacoesPendentes = $stmt->fetchAll();

// Totais do mês selecionado do extrato (todas as transações já lidas dessa
// família nesse mês, independente de terem casado sozinhas ou ficado
// pendentes) — não é só o que está na tabela de conferência abaixo.
$stmt = $pdo->prepare(
    'SELECT
        COALESCE(SUM(CASE WHEN valor > 0 THEN valor ELSE 0 END), 0) AS total_entradas,
        COALESCE(SUM(CASE WHEN valor < 0 THEN -valor ELSE 0 END), 0) AS total_saidas
     FROM transacao_importada WHERE familia_id = ? AND DATE_FORMAT(data, "%Y-%m") = ?'
);
$stmt->execute([$familiaId, $mesExtrato]);
$totaisExtrato = $stmt->fetch();

$stmt = $pdo->prepare('SELECT id, nome FROM receita WHERE familia_id = ? AND status = "PREVISTO" ORDER BY nome ASC');
$stmt->execute([$familiaId]);
$receitasAbertas = $stmt->fetchAll();

$contasFiltradas = $filtroAtivo === 'todas'
    ? $contas
    : array_values(array_filter($contas, fn($c) => $c['status_exibido'] === strtoupper($filtroAtivo)));
$totalContasFiltradas = array_sum(array_map($valorMensalDaConta, $contasFiltradas));

$totalGastosVariaveis = array_sum(array_map(
    fn(array $t): float => (float) $t['valor'] < 0 ? abs((float) $t['valor']) : 0,
    $transacoesPendentes
));
$totalRecebimentosVariaveis = array_sum(array_map(
    fn(array $t): float => (float) $t['valor'] > 0 ? (float) $t['valor'] : 0,
    $transacoesPendentes
));

$STATUS_SELO = ['PAGA' => 'selo-sucesso', 'PENDENTE' => 'selo-alerta', 'ATRASADA' => 'selo-perigo'];
$STATUS_LABEL = ['PAGA' => 'Paga', 'PENDENTE' => 'Pendente', 'ATRASADA' => 'Atrasada'];

layout_topo($usuario_atual, 'contas', 'Contas do Mês');
layout_rodape($usuario_atual);
?>

<div class="pilha">
  <div>
    <h1 class="pagina-titulo">Contas do mês</h1>
    <p class="pagina-subtitulo">Tudo que precisa ser pago mensalmente.</p>
  </div>

  <form method="post" action="/contas-processar.php" class="cartao form-grade">
    <?= csrf_campo_oculto($usuario_atual) ?>
    <?= info_icone('Cadastre aqui uma conta que precisa ser paga todo mês (ou uma vez), como aluguel, água, luz ou assinaturas. Depois de criada, ela aparece na tabela abaixo pra você marcar como paga.') ?>
    <input type="hidden" name="acao" value="criar">
    <input name="nome" placeholder="Nome (ex: Aluguel)" required class="campo">
    <select name="categoria" required class="campo">
      <option value="" disabled selected>Categoria</option>
      <?php foreach (CATEGORIAS_CONTA as $categoria): ?>
        <option value="<?= htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
    <input name="subcategoria" placeholder="Subcategoria (opcional)" class="campo">
    <input name="valor" placeholder="Valor (total, se parcelado; senão valor mensal)" type="text" inputmode="decimal" data-moeda required class="campo">
    <input name="vencimento" type="date" required class="campo">
    <input name="forma_pagamento" placeholder="Forma de pagamento" class="campo">
    <input name="conta_bancaria" placeholder="Conta bancária ou cartão" class="campo">
    <select name="tipo" class="campo">
      <option value="FIXA">Conta fixa</option>
      <option value="VARIAVEL">Conta variável</option>
    </select>
    <input name="numero_parcelas" placeholder="Nº de parcelas (opcional, ex: financiamento)" type="number" min="1" class="campo">
    <input name="valor_parcela" placeholder="Valor da parcela (se for parcelado)" type="text" inputmode="decimal" data-moeda class="campo">
    <input name="identificador_extrato" placeholder="Palavra-chave no extrato (opcional, ex: nome do beneficiário)" class="campo">
    <input name="observacoes" placeholder="Observações (opcional)" class="campo">
    <label class="campo-checkbox"><input type="checkbox" name="recorrente_mensal" checked> Recorrente todo mês</label>
    <button type="submit" class="botao">Adicionar conta</button>
  </form>

  <div class="filtro-abas">
    <?php foreach (FILTROS_CONTA as $valor => $label):
      $qtd = $valor === 'todas' ? count($contas) : count(array_filter($contas, fn($c) => $c['status_exibido'] === strtoupper($valor)));
    ?>
      <a href="<?= $valor === 'todas' ? '/contas.php' : '/contas.php?status=' . $valor ?>" class="filtro-aba<?= $filtroAtivo === $valor ? ' filtro-aba-ativa' : '' ?>">
        <?= $label ?> <span class="filtro-aba-contagem"><?= $qtd ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <?php foreach ($contasFiltradas as $conta): if ($conta['numero_parcelas'] === null) continue; $formId = 'conta-form-' . (int) $conta['id']; ?>
    <form id="<?= $formId ?>" method="post" action="/contas-processar.php" style="display:none">
      <?= csrf_campo_oculto($usuario_atual) ?>
      <input type="hidden" name="acao" value="editar_parcelas">
      <input type="hidden" name="id" value="<?= (int) $conta['id'] ?>">
    </form>
  <?php endforeach; ?>

  <div class="tabela-wrap">
    <table class="tabela">
      <thead><tr><th>Nome</th><th>Categoria</th><th>Valor</th><th>Parcela</th><th>Parcelas</th><th>Vencimento</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($contasFiltradas as $conta):
          $status = $conta['status_exibido'];
          $formId = 'conta-form-' . (int) $conta['id'];
          $parcelado = $conta['numero_parcelas'] !== null;
          $valorPago = $parcelado ? (int) $conta['parcelas_pagas'] * (float) ($conta['valor_parcela'] ?? 0) : null;
          $progressoConta = $parcelado && (float) $conta['valor'] > 0 ? min(100, (int) round($valorPago / (float) $conta['valor'] * 100)) : null;
        ?>
          <tr>
            <td><?= htmlspecialchars($conta['nome'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="texto-suave"><?= htmlspecialchars($conta['categoria'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <?= formatar_moeda((float) $conta['valor']) ?>
              <?php if ($parcelado): ?>
                <div class="texto-suave" style="font-size:0.75rem;margin-top:0.15rem">pago: <?= formatar_moeda($valorPago) ?></div>
                <div class="progresso-trilho" style="margin-top:0.25rem"><div class="progresso-barra" style="width: <?= $progressoConta ?>%"></div></div>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($parcelado): ?>
                <input form="<?= $formId ?>" name="valor_parcela" type="text" inputmode="decimal" data-moeda value="<?= $conta['valor_parcela'] !== null ? formatar_valor_input((float) $conta['valor_parcela']) : '' ?>" class="campo campo-tabela" style="width:7rem">
              <?php else: ?>
                <span class="texto-suave">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($parcelado): ?>
                <input form="<?= $formId ?>" name="parcelas_pagas" type="number" min="0" max="<?= (int) $conta['numero_parcelas'] ?>" value="<?= (int) $conta['parcelas_pagas'] ?>" class="campo campo-tabela" style="width:4rem">
                <span class="texto-suave">/<?= (int) $conta['numero_parcelas'] ?></span>
              <?php else: ?>
                <span class="texto-suave">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($parcelado): ?>
                <input form="<?= $formId ?>" name="vencimento" type="date" value="<?= $conta['vencimento'] ?>" class="campo campo-tabela">
              <?php else: ?>
                <?= formatar_data($conta['vencimento']) ?>
              <?php endif; ?>
            </td>
            <td>
              <form method="post" action="/contas-processar.php" style="display:inline">
                <?= csrf_campo_oculto($usuario_atual) ?>
                <input type="hidden" name="acao" value="marcar_paga">
                <input type="hidden" name="id" value="<?= (int) $conta['id'] ?>">
                <button type="submit" class="selo <?= $STATUS_SELO[$status] ?>" title="Clique pra avançar o status (pendente → atrasada → paga)">
                  <?= $STATUS_LABEL[$status] ?>
                </button>
              </form>
            </td>
            <td>
              <div class="acoes">
                <form method="post" action="/contas-processar.php" onsubmit="return confirm('Excluir esta conta?');">
                  <?= csrf_campo_oculto($usuario_atual) ?>
                  <input type="hidden" name="acao" value="excluir">
                  <input type="hidden" name="id" value="<?= (int) $conta['id'] ?>">
                  <button class="link-acao link-perigo">Excluir</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (count($contasFiltradas) === 0): ?>
          <tr><td colspan="8" class="tabela-vazia"><?= count($contas) === 0 ? 'Nenhuma conta cadastrada ainda.' : 'Nenhuma conta nesse filtro.' ?></td></tr>
        <?php endif; ?>
      </tbody>
      <?php if (count($contasFiltradas) > 0): ?>
        <tfoot>
          <tr>
            <td colspan="2" class="texto-suave">Total</td>
            <td colspan="6"><strong><?= formatar_moeda($totalContasFiltradas) ?></strong></td>
          </tr>
        </tfoot>
      <?php endif; ?>
    </table>
  </div>

  <?php if (count($transacoesPendentes) > 0): ?>
    <div class="cartao pilha-pequena">
      <?= info_icone('Gasto do extrato que não é nenhuma das suas contas fixas cadastradas acima — mercado, Uber, lanche, compras avulsas. Se algum desses for na verdade uma conta fixa que você esqueceu de cadastrar, vincula na conta certa aqui; senão, ignora.') ?>
      <h2 class="cartao-titulo">Gastos variáveis do extrato</h2>
      <p class="texto-suave" style="font-size:0.8rem;margin:0">Mês: <?= ucfirst(formatar_mes_ano($mesExtrato . '-01')) ?> — mude o mês na seção "Extrato bancário automático" mais abaixo.</p>

      <div class="tabela-wrap">
        <table class="tabela">
          <thead><tr><th>Data</th><th>Descrição</th><th>Valor</th><th>Vincular a</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($transacoesPendentes as $t): $eSaida = (float) $t['valor'] < 0; ?>
              <tr>
                <td class="texto-suave"><?= formatar_data($t['data']) ?></td>
                <td><?= htmlspecialchars(mb_substr($t['descricao'], 0, 80), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="color: <?= $eSaida ? 'var(--perigo)' : 'var(--sucesso)' ?>"><?= formatar_moeda(abs((float) $t['valor'])) ?></td>
                <td>
                  <?php if ($eSaida): ?>
                    <form method="post" action="/extrato-processar.php" class="linha-flex" style="justify-content:flex-start">
                      <?= csrf_campo_oculto($usuario_atual) ?>
                      <input type="hidden" name="acao" value="vincular_conta">
                      <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                      <select name="conta_mes_id" required class="campo campo-tabela">
                        <option value="" disabled selected>Conta do Mês...</option>
                        <?php foreach ($contas as $c): if (!in_array($c['status'], ['PENDENTE', 'ATRASADA'], true)) continue; ?>
                          <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button class="botao botao-pequeno">Vincular</button>
                    </form>
                  <?php else: ?>
                    <form method="post" action="/extrato-processar.php" class="linha-flex" style="justify-content:flex-start">
                      <?= csrf_campo_oculto($usuario_atual) ?>
                      <input type="hidden" name="acao" value="vincular_receita">
                      <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                      <select name="receita_id" required class="campo campo-tabela">
                        <option value="" disabled selected>Receita...</option>
                        <?php foreach ($receitasAbertas as $r): ?>
                          <option value="<?= (int) $r['id'] ?>"><?= htmlspecialchars($r['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button class="botao botao-pequeno">Vincular</button>
                    </form>
                  <?php endif; ?>
                </td>
                <td>
                  <form method="post" action="/extrato-processar.php">
                    <?= csrf_campo_oculto($usuario_atual) ?>
                    <input type="hidden" name="acao" value="ignorar">
                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                    <button class="link-acao link-perigo">Ignorar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="2" class="texto-suave">Total</td>
              <td>
                <?php if ($totalGastosVariaveis > 0): ?><strong style="color:var(--perigo)">-<?= formatar_moeda($totalGastosVariaveis) ?></strong><?php endif; ?>
                <?php if ($totalRecebimentosVariaveis > 0): ?><strong style="color:var(--sucesso)">+<?= formatar_moeda($totalRecebimentosVariaveis) ?></strong><?php endif; ?>
              </td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <div class="cartao pilha-pequena">
    <?= info_icone('Mostra quanto ainda falta pagar, quanto já foi pago e quanto você tem em caixa neste mês. Os valores mudam conforme o filtro de status selecionado acima.') ?>
    <h2 class="cartao-titulo">Resumo de pagamentos</h2>

    <?php if ($filtroAtivo !== 'paga'):
      $rotulo = $filtroAtivo === 'pendente' ? 'Pendente' : ($filtroAtivo === 'atrasada' ? 'Atrasado' : 'Falta pagar');
      $sub = $filtroAtivo === 'pendente' ? 'Contas pendentes' : ($filtroAtivo === 'atrasada' ? 'Contas atrasadas' : 'Contas pendentes e atrasadas');
      $val = $filtroAtivo === 'pendente' ? $totalPendente : ($filtroAtivo === 'atrasada' ? $totalAtrasado : $totalAPagar);
    ?>
      <div class="item-lista-rica">
        <span class="item-icone"><?= icone($filtroAtivo === 'atrasada' ? 'alerta' : 'relogio') ?></span>
        <div class="item-corpo"><p class="item-titulo"><?= $rotulo ?></p><p class="item-subtitulo"><?= $sub ?></p></div>
        <span class="item-valor"><?= formatar_moeda($val) ?></span>
      </div>
      <div class="progresso-trilho"><div class="progresso-barra" style="width: <?= min(100, round($val / $maiorValor * 100)) ?>%"></div></div>
    <?php endif; ?>

    <?php if ($filtroAtivo === 'todas' || $filtroAtivo === 'paga'): ?>
      <div class="item-lista-rica">
        <span class="item-icone"><?= icone('check') ?></span>
        <div class="item-corpo"><p class="item-titulo">Já pago</p><p class="item-subtitulo">Contas quitadas</p></div>
        <span class="item-valor"><?= formatar_moeda($totalPago) ?></span>
      </div>
      <div class="progresso-trilho"><div class="progresso-barra" style="width: <?= min(100, round($totalPago / $maiorValor * 100)) ?>%"></div></div>
    <?php endif; ?>

    <div class="item-lista-rica">
      <span class="item-icone"><?= icone('carteira') ?></span>
      <div class="item-corpo"><p class="item-titulo">Em caixa</p><p class="item-subtitulo">Saldo disponível no mês</p></div>
      <span class="item-valor"><?= formatar_moeda($saldoEmCaixa) ?></span>
    </div>
    <div class="progresso-trilho"><div class="progresso-barra" style="width: <?= min(100, round(max($saldoEmCaixa, 0) / $maiorValor * 100)) ?>%"></div></div>
  </div>

  <details class="cartao pilha-pequena secao-recolhivel">
    <summary>Extrato bancário automático</summary>
    <?= info_icone('Procura no Gmail todos os e-mails de "Extrato" e lê o CSV anexado do Nubank. Casa automaticamente cada gasto com uma conta já cadastrada (por palavra-chave ou valor) e, se o mesmo beneficiário pagar por Pix em 3 meses ou mais sem bater com nenhuma conta, cria a conta fixa sozinho. O que sobrar sem reconhecer aparece em "Gastos variáveis do extrato", pra você vincular manualmente ou ignorar.') ?>
    <form method="post" action="/extrato-processar.php">
      <?= csrf_campo_oculto($usuario_atual) ?>
      <input type="hidden" name="acao" value="verificar">
      <button type="submit" class="botao">Verificar extrato agora</button>
    </form>

    <?php if (isset($_GET['erro'])): ?>
      <p class="texto-suave" style="color:var(--perigo)">Erro: <?= htmlspecialchars((string) $_GET['erro'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php elseif (isset($_GET['novas'])):
      $fixasCriadas = (int) ($_GET['fixas_criadas'] ?? 0);
      $fixasTransacoes = (int) ($_GET['fixas_transacoes'] ?? 0);
      $aindaPendente = (int) $_GET['novas'] - (int) $_GET['casadas'] - $fixasTransacoes;
    ?>
      <p class="texto-suave">
        Encontradas <strong><?= (int) $_GET['novas'] ?></strong> transações novas —
        <strong><?= (int) $_GET['casadas'] ?></strong> casadas automaticamente,
        <?php if ($fixasCriadas > 0): ?>
          <strong><?= $fixasCriadas ?></strong> conta<?= $fixasCriadas > 1 ? 's fixas novas reconhecidas' : ' fixa nova reconhecida' ?> sozinho,
        <?php endif; ?>
        <strong><?= max(0, $aindaPendente) ?></strong> em "Gastos variáveis do extrato" aguardando você.
      </p>
    <?php endif; ?>

    <form method="get" class="linha-flex" style="justify-content:flex-start">
      <?php if ($filtroAtivo !== 'todas'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filtroAtivo, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
      <label for="mes_extrato" class="texto-suave">Mês do extrato:</label>
      <select name="mes_extrato" id="mes_extrato" class="campo campo-tabela" onchange="this.form.submit()">
        <?php foreach ($mesesExtrato as $mes): ?>
          <option value="<?= htmlspecialchars($mes, ENT_QUOTES, 'UTF-8') ?>" <?= $mes === $mesExtrato ? 'selected' : '' ?>>
            <?= ucfirst(formatar_mes_ano($mes . '-01')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>

    <?php if ((float) $totaisExtrato['total_entradas'] > 0 || (float) $totaisExtrato['total_saidas'] > 0): ?>
      <div class="linha-flex" style="gap:1.5rem">
        <p class="texto-suave">Total de entradas do extrato: <strong style="color:var(--sucesso)"><?= formatar_moeda((float) $totaisExtrato['total_entradas']) ?></strong></p>
        <p class="texto-suave">Total de saídas do extrato: <strong style="color:var(--perigo)"><?= formatar_moeda((float) $totaisExtrato['total_saidas']) ?></strong></p>
      </div>
    <?php else: ?>
      <p class="texto-suave">Nenhuma transação importada nesse mês.</p>
    <?php endif; ?>
  </details>
</div>

<script src="/assets/contas-planilha.js"></script>

<?php
fechar_layout();
