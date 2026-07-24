<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../src/dashboard.php';
require_once __DIR__ . '/../src/layout.php';

$pdo = conexao_banco();
$familiaId = (int) $usuario_atual['familia_id'];

$mes = mes_referencia_da_query();
$mesAnterior = mes_deslocado($mes, -1);
$mesSeguinte = mes_deslocado($mes, 1);

$resumo = resumo_do_mes($pdo, $familiaId, $mes);
$alertas = alertas_importantes($pdo, $familiaId);
$totalAlertas = array_sum(array_column($alertas, 'contagem'));
$serieGasto = serie_gasto_acumulado($pdo, $familiaId, $mes);
$porCategoria = gasto_por_categoria($pdo, $familiaId, $mes);
$percentualRecebido = $resumo['renda_prevista'] > 0
    ? (int) round(($resumo['renda_recebida'] / $resumo['renda_prevista']) * 100)
    : 0;

layout_topo($usuario_atual, 'dashboard', 'Dashboard');
?>
        <div class="seletor-mes">
          <a href="?mes=<?= $mesAnterior ?>" aria-label="Mês anterior"><?= icone('seta-esquerda') ?></a>
          <span><?= htmlspecialchars(nome_do_mes($mes), ENT_QUOTES, 'UTF-8') ?></span>
          <a href="?mes=<?= $mesSeguinte ?>" aria-label="Próximo mês"><?= icone('seta-direita') ?></a>
        </div>
        <a href="#alertas" class="sino-alertas" aria-label="Ver alertas">
          <?= icone('sino') ?>
          <?php if ($totalAlertas > 0): ?><span class="contagem-alertas"><?= $totalAlertas ?></span><?php endif; ?>
        </a>
<?php
layout_rodape($usuario_atual);

$saldo = $resumo['saldo_disponivel'];
$previsao = $resumo['saldo_previsto_fim_mes'];
?>

<div class="banner-notificacao" id="bannerNotificacao">
  <span><?= icone('sino') ?> Ativar notificações do navegador para avisos de vencimento (3 dias antes e no dia)?</span>
  <button type="button" class="botao-notificacao" id="botaoAtivarNotificacao">Ativar notificações</button>
</div>

<div class="grade-hero">
  <div class="coluna-saldo">
    <div class="cartao cartao-saldo">
      <div class="cartao-saldo-topo">
        <span class="rotulo">Saldo disponível</span>
        <span class="pill-mes"><?= htmlspecialchars(nome_do_mes($mes), ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <div class="valor-hero<?= $saldo < 0 ? ' negativo' : '' ?>"><?= formatar_moeda($saldo) ?></div>
      <div class="previsao">Previsão para o fim do mês: <strong style="color: <?= $previsao < 0 ? 'var(--perigo)' : '#fff' ?>"><?= formatar_moeda($previsao) ?></strong></div>
    </div>
    <div class="cartao cartao-planejamento">
      <h2>Planejamento do mês</h2>
      <p>Acompanhe contas, cartão e metas para saber exatamente quanto ainda pode gastar.</p>
      <?= tag_link('/contas.php', 'contas', 'botao-secundario') ?>Ver contas do mês <?= icone('seta-cta') ?><?= fechar_tag_link('contas') ?>
    </div>
  </div>

  <div class="cartao" id="alertas">
    <div class="cartao-cabecalho-alerta">
      <h2>Alertas importantes</h2>
      <?php if ($totalAlertas > 0): ?><span class="contagem-alertas contagem-inline"><?= $totalAlertas ?></span><?php endif; ?>
    </div>
    <?php if (count($alertas) === 0): ?>
      <p class="sem-alertas">Nenhum alerta por enquanto — tudo em dia.</p>
    <?php else: ?>
      <div class="lista-alertas">
        <?php foreach ($alertas as $alerta): $chave = chave_da_rota($alerta['link']); ?>
          <?= tag_link($alerta['link'], $chave, 'alerta-linha ' . $alerta['tipo']) ?>
            <span class="badge-alerta <?= $alerta['tipo'] ?>"><?= icone('alerta') ?></span>
            <span class="alerta-texto">
              <strong><?= htmlspecialchars(ucfirst($alerta['titulo']), ENT_QUOTES, 'UTF-8') ?></strong>
              <small><?= htmlspecialchars($alerta['subtitulo'], ENT_QUOTES, 'UTF-8') ?></small>
            </span>
            <?php if ($alerta['valor'] !== null): ?>
              <span class="alerta-valor"><?= formatar_moeda($alerta['valor']) ?></span>
            <?php endif; ?>
          <?= fechar_tag_link($chave) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="cartao">
    <h2>Resumo rápido</h2>
    <div class="mini-grade">
      <div class="mini-tile">
        <span class="icone-badge"><?= icone('receitas') ?></span>
        <span class="mini-tile-texto"><span class="rotulo">Renda do mês</span><span class="valor">R$ <?= number_format($resumo['renda_prevista'], 2, ',', '.') ?></span></span>
      </div>
      <div class="mini-tile">
        <span class="icone-badge"><?= icone('check') ?></span>
        <span class="mini-tile-texto"><span class="rotulo">Recebido <?php if ($resumo['renda_prevista'] > 0): ?><span class="badge-percentual"><?= $percentualRecebido ?>%</span><?php endif; ?></span><span class="valor">R$ <?= number_format($resumo['renda_recebida'], 2, ',', '.') ?></span></span>
      </div>
      <div class="mini-tile">
        <span class="icone-badge"><?= icone('check') ?></span>
        <span class="mini-tile-texto"><span class="rotulo">Contas pagas</span><span class="valor">R$ <?= number_format($resumo['total_pago'], 2, ',', '.') ?></span></span>
      </div>
    </div>
  </div>
</div>

<div class="grade-grupos">
  <div class="cartao grupo-stats">
    <h2>Contas do mês</h2>
    <div class="grade-stats">
      <?= tag_link('/contas.php', 'contas', 'stat-tile') ?>
        <span class="icone-badge"><?= icone('contas') ?></span>
        <span class="rotulo">Total de contas</span><span class="valor"><?= formatar_moeda($resumo['total_contas']) ?></span><span class="subtexto"><?= plural($resumo['qtd_contas'], 'conta', 'contas') ?></span>
      <?= fechar_tag_link('contas') ?>
      <?= tag_link('/contas.php', 'contas', 'stat-tile') ?>
        <span class="icone-badge"><?= icone('relogio') ?></span>
        <span class="rotulo">Contas pendentes</span><span class="valor"><?= formatar_moeda($resumo['total_pendente']) ?></span><span class="subtexto"><?= plural($resumo['qtd_pendentes'], 'conta', 'contas') ?></span>
      <?= fechar_tag_link('contas') ?>
      <?= tag_link('/contas.php', 'contas', 'stat-tile') ?>
        <span class="icone-badge"><?= icone('alerta') ?></span>
        <span class="rotulo">Contas atrasadas</span><span class="valor<?= $resumo['qtd_atrasadas'] > 0 ? ' negativo' : '' ?>"><?= formatar_moeda($resumo['total_atrasadas']) ?></span><span class="subtexto"><?= plural($resumo['qtd_atrasadas'], 'conta', 'contas') ?></span>
      <?= fechar_tag_link('contas') ?>
      <?= tag_link('/metas.php', 'metas', 'stat-tile') ?>
        <span class="icone-badge"><?= icone('metas') ?></span>
        <span class="rotulo">Metas</span><span class="valor"><?= formatar_moeda($resumo['total_metas']) ?></span><span class="subtexto">Reservado</span>
      <?= fechar_tag_link('metas') ?>
    </div>
  </div>

  <div class="cartao grupo-stats">
    <h2>Patrimônio</h2>
    <div class="grade-stats">
      <?= tag_link('/prioridades.php', 'prioridades', 'stat-tile') ?>
        <span class="icone-badge"><?= icone('prioridades') ?></span>
        <span class="rotulo">Prioridades</span><span class="valor"><?= formatar_moeda($resumo['total_prioridades']) ?></span><span class="subtexto">Reservado</span>
      <?= fechar_tag_link('prioridades') ?>
      <?= tag_link('/dividas.php', 'dividas', 'stat-tile') ?>
        <span class="icone-badge"><?= icone('dividas') ?></span>
        <span class="rotulo">Dívidas</span><span class="valor<?= $resumo['total_dividas'] > 0 ? ' negativo' : '' ?>"><?= formatar_moeda($resumo['total_dividas']) ?></span><span class="subtexto">Restante</span>
      <?= fechar_tag_link('dividas') ?>
      <?= tag_link('/investimentos.php', 'investimentos', 'stat-tile') ?>
        <span class="icone-badge"><?= icone('investimentos') ?></span>
        <span class="rotulo">Investimentos</span><span class="valor"><?= formatar_moeda($resumo['total_investimentos']) ?></span><span class="subtexto">Aplicado</span>
      <?= fechar_tag_link('investimentos') ?>
      <?= tag_link('/relatorios.php', 'relatorios', 'stat-tile') ?>
        <span class="icone-badge"><?= icone('relatorios') ?></span>
        <span class="rotulo">Previsão de saldo</span><span class="valor<?= $previsao < 0 ? ' negativo' : '' ?>"><?= formatar_moeda($previsao) ?></span><span class="subtexto">Fim do mês</span>
      <?= fechar_tag_link('relatorios') ?>
    </div>
  </div>
</div>

<div class="grade-graficos">
  <div class="cartao">
    <h2>Quanto gastei no mês</h2>
    <?= grafico_gasto_mensal($serieGasto) ?>
  </div>
  <div class="cartao">
    <h2>Gastos por categoria</h2>
    <?= grafico_categoria($porCategoria) ?>
  </div>
</div>

<script>
  (function () {
    var banner = document.getElementById('bannerNotificacao');
    if (!banner) return;
    if (!('Notification' in window) || Notification.permission !== 'default') {
      banner.style.display = 'none';
      return;
    }
    document.getElementById('botaoAtivarNotificacao').addEventListener('click', function () {
      Notification.requestPermission().then(function () { banner.style.display = 'none'; });
    });
  })();
</script>

<?php
fechar_layout();
