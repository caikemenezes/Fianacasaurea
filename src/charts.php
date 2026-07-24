<?php

declare(strict_types=1);

/**
 * Gráficos em SVG puro, gerados no servidor — sem biblioteca de gráficos,
 * mesma decisão do sistema original (ver src/components/charts.tsx lá).
 * Paleta validada com o skill de dataviz (ver nota "Dashboard (Fundação Visual)"
 * no vault) contra o fundo escuro fixo do sistema.
 */

const COR_DESPESA = '#e66767'; // status "critical" (dark), reservado pra despesa/atrasado/negativo
const COR_RECEITA = '#22c55e'; // status "sucesso"
const COR_DOURADO_LINHA = '#f0c13a'; // --dourado-linha do sistema original
const COR_MUTED = '#898781';
const COR_GRID = '#2c2c2a';

/**
 * Rampa monocromática dourada (ordinal, maior valor → menor), idêntica à do
 * sistema original (--grafico-dourado-1..6, validada lá com o skill de
 * dataviz contra o fundo escuro dos cartões) — usada em "Gastos por Categoria".
 */
const RAMPA_DOURADA = ['#f7e7bf', '#efd080', '#e7b840', '#cc9a19', '#9a7413', '#76590f'];

function formatar_moeda(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/**
 * Gráfico de linha/área de uma série só: gasto acumulado no mês, dia a dia.
 *
 * @param array<int, array{dia:int, valor:float}> $pontos
 */
function grafico_gasto_mensal(array $pontos): string
{
    $largura = 640;
    $altura = 220;
    $padEsq = 48;
    $padDir = 12;
    $padTopo = 16;
    $padBase = 28;
    $cor = COR_DOURADO_LINHA;

    $n = count($pontos);
    $max = 0.0;
    foreach ($pontos as $p) {
        $max = max($max, $p['valor']);
    }
    $maxEscala = $max > 0 ? $max * 1.15 : 100.0;

    $larguraUtil = $largura - $padEsq - $padDir;
    $alturaUtil = $altura - $padTopo - $padBase;

    $coordX = fn(int $i): float => $n <= 1 ? $padEsq : $padEsq + ($larguraUtil * $i / ($n - 1));
    $coordY = fn(float $v): float => $padTopo + $alturaUtil - ($v / $maxEscala) * $alturaUtil;

    $linha = '';
    $pontosDado = '';
    foreach ($pontos as $i => $p) {
        $x = $coordX($i);
        $y = $coordY($p['valor']);
        $linha .= ($i === 0 ? "M{$x},{$y}" : " L{$x},{$y}");

        $rotuloDia = $p['dia'] === 0 ? 'Início do mês' : "Dia {$p['dia']}";
        $pontosDado .= '<circle class="ponto-dado" cx="' . $x . '" cy="' . $y . '" r="1" data-rotulo="' . htmlspecialchars($rotuloDia, ENT_QUOTES, 'UTF-8') . '" data-valor="' . htmlspecialchars(formatar_moeda($p['valor']), ENT_QUOTES, 'UTF-8') . '"/>';
    }
    $baseY = $coordY(0);
    $ultimoValor = $n > 0 ? $pontos[$n - 1]['valor'] : 0.0;
    $area = $linha . " L{$coordX(max(0, $n - 1))},{$baseY} L{$coordX(0)},{$baseY} Z";

    $ultimoX = $coordX(max(0, $n - 1));
    $ultimoY = $coordY($ultimoValor);

    $linhasGrade = '';
    $rotulosY = '';
    for ($i = 0; $i <= 2; $i++) {
        $valor = $maxEscala * $i / 2;
        $y = $coordY($valor);
        $linhasGrade .= '<line x1="' . $padEsq . '" y1="' . $y . '" x2="' . ($largura - $padDir) . '" y2="' . $y . '" stroke="' . COR_GRID . '" stroke-width="1"/>';
        $rotulosY .= '<text x="' . ($padEsq - 8) . '" y="' . ($y + 4) . '" text-anchor="end" font-size="10" fill="' . COR_MUTED . '">' . formatar_moeda($valor) . '</text>';
    }

    $rotulosX = '';
    $passo = (int) max(1, round($n / 6));
    foreach ($pontos as $i => $p) {
        if ($i % $passo !== 0 && $i !== $n - 1) {
            continue;
        }
        $rotulosX .= '<text x="' . $coordX($i) . '" y="' . ($altura - 6) . '" text-anchor="middle" font-size="10" fill="' . COR_MUTED . '">' . $p['dia'] . '</text>';
    }

    $tabela = '<table><thead><tr><th>Dia</th><th>Gasto acumulado</th></tr></thead><tbody>';
    foreach ($pontos as $p) {
        $tabela .= '<tr><td>' . $p['dia'] . '</td><td>' . formatar_moeda($p['valor']) . '</td></tr>';
    }
    $tabela .= '</tbody></table>';

    $topoLinha = $padTopo;
    $baseLinha = $padTopo + $alturaUtil;

    return <<<SVG
    <div class="grafico grafico-interativo" style="position:relative" data-largura="{$largura}" data-altura="{$altura}">
      <svg viewBox="0 0 {$largura} {$altura}" role="img" aria-label="Gasto acumulado no mês" class="grafico-svg-hover">
        <defs>
          <linearGradient id="gradienteDourado" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="{$cor}" stop-opacity="0.32"/>
            <stop offset="100%" stop-color="{$cor}" stop-opacity="0"/>
          </linearGradient>
          <filter id="brilhoPontoGasto" x="-100%" y="-100%" width="300%" height="300%">
            <feGaussianBlur stdDeviation="2.2" result="blur"/>
            <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
          </filter>
        </defs>
        {$linhasGrade}
        {$rotulosY}
        <path d="{$area}" fill="url(#gradienteDourado)" stroke="none"/>
        <path d="{$linha}" fill="none" stroke="{$cor}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
        <circle cx="{$ultimoX}" cy="{$ultimoY}" r="4" fill="{$cor}"/>
        {$rotulosX}
        {$pontosDado}
        <g class="grafico-crosshair" style="display:none">
          <line class="grafico-crosshair-linha" y1="{$topoLinha}" y2="{$baseLinha}" stroke="#c3c2b7" stroke-width="1" stroke-dasharray="3 3"/>
          <circle class="grafico-crosshair-ponto" r="5" fill="{$cor}" stroke="#16151d" stroke-width="2" filter="url(#brilhoPontoGasto)"/>
        </g>
      </svg>
      <div class="grafico-tooltip" style="display:none"></div>
      <details class="tabela-alt"><summary>Ver como tabela</summary>{$tabela}</details>
    </div>
    SVG;
}

/**
 * Gráfico de rosca (donut): valor por categoria no mês.
 *
 * @param array<string, float> $porCategoria categoria => valor somado
 */
function grafico_categoria(array $porCategoria): string
{
    $total = array_sum($porCategoria);
    $raio = 70;
    $centro = 90;
    $tamanhoSvg = $centro * 2;
    $espessura = 26;
    $circunferencia = 2 * M_PI * $raio;

    arsort($porCategoria);

    $arcos = '';
    $legenda = '';
    $tabela = '<table><thead><tr><th>Categoria</th><th>Valor</th><th>%</th></tr></thead><tbody>';
    $offset = 0.0;
    $gap = $total > 0 ? 2.2 : 0;

    if ($total <= 0) {
        $arcos = '<circle cx="' . $centro . '" cy="' . $centro . '" r="' . $raio . '" fill="none" stroke="' . COR_GRID . '" stroke-width="' . $espessura . '" stroke-dasharray="4 6"/>';
        $legenda = '<p class="legenda-vazia">Sem gastos lançados neste mês ainda.</p>';
    } else {
        $indice = 0;
        foreach ($porCategoria as $categoria => $valor) {
            if ($valor <= 0) {
                continue;
            }
            $cor = RAMPA_DOURADA[$indice] ?? RAMPA_DOURADA[count(RAMPA_DOURADA) - 1];
            $indice++;
            $fatia = ($valor / $total) * $circunferencia;
            $dash = max(0, $fatia - $gap);
            $arcos .= '<circle cx="' . $centro . '" cy="' . $centro . '" r="' . $raio . '" fill="none" stroke="' . $cor . '" stroke-width="' . $espessura . '"'
                . ' stroke-dasharray="' . $dash . ' ' . ($circunferencia - $dash) . '" stroke-dashoffset="' . (-$offset) . '"'
                . ' transform="rotate(-90 ' . $centro . ' ' . $centro . ')"><title>' . htmlspecialchars($categoria . ': ' . formatar_moeda($valor), ENT_QUOTES, 'UTF-8') . '</title></circle>';
            $offset += $fatia;

            $pct = round(($valor / $total) * 100);
            $legenda .= '<li><span class="ponto" style="background:' . $cor . '"></span>' . htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8')
                . ' <span class="valor-legenda">' . formatar_moeda($valor) . ' · ' . $pct . '%</span></li>';
            $tabela .= '<tr><td>' . htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8') . '</td><td>' . formatar_moeda($valor) . '</td><td>' . $pct . '%</td></tr>';
        }
        $legenda = '<ul class="legenda-categorias">' . $legenda . '</ul>';
    }
    $tabela .= '</tbody></table>';

    $totalFormatado = $total > 0 ? formatar_moeda($total) : 'R$ 0,00';

    return <<<SVG
    <div class="grafico grafico-donut">
      <svg viewBox="0 0 {$tamanhoSvg} {$tamanhoSvg}" role="img" aria-label="Gastos por categoria no mês">
        {$arcos}
        <text x="{$centro}" y="{$centro}" text-anchor="middle" font-size="14" fill="#fff" font-weight="600">{$totalFormatado}</text>
      </svg>
      {$legenda}
      <details class="tabela-alt"><summary>Ver como tabela</summary>{$tabela}</details>
    </div>
    SVG;
}

/**
 * Gráfico de linha com 2 séries (Receitas x Despesas por mês) — usado em Relatórios.
 *
 * @param array<int, array{label:string, receitas:float, despesas:float}> $pontos 12 meses
 */
function grafico_evolucao_mensal(array $pontos): string
{
    $largura = 680;
    $altura = 240;
    $padEsq = 52;
    $padDir = 12;
    $padTopo = 16;
    $padBase = 28;
    $corReceita = COR_RECEITA;
    $corDespesa = COR_DESPESA;

    $n = count($pontos);
    $max = 1.0;
    foreach ($pontos as $p) {
        $max = max($max, $p['receitas'], $p['despesas']);
    }
    $maxEscala = $max * 1.15;

    $larguraUtil = $largura - $padEsq - $padDir;
    $alturaUtil = $altura - $padTopo - $padBase;
    $coordX = fn(int $i): float => $n <= 1 ? $padEsq : $padEsq + ($larguraUtil * $i / ($n - 1));
    $coordY = fn(float $v): float => $padTopo + $alturaUtil - ($v / $maxEscala) * $alturaUtil;

    $linhaReceitas = '';
    $linhaDespesas = '';
    $hover = '';
    foreach ($pontos as $i => $p) {
        $x = $coordX($i);
        $linhaReceitas .= ($i === 0 ? "M{$x}," : " L{$x},") . $coordY($p['receitas']);
        $linhaDespesas .= ($i === 0 ? "M{$x}," : " L{$x},") . $coordY($p['despesas']);

        $xIni = $i === 0 ? $padEsq : ($coordX($i - 1) + $x) / 2;
        $xFim = $i === $n - 1 ? $largura - $padDir : ($x + $coordX($i + 1)) / 2;
        $titulo = htmlspecialchars($p['label'] . ': receitas ' . formatar_moeda($p['receitas']) . ' · despesas ' . formatar_moeda($p['despesas']), ENT_QUOTES, 'UTF-8');
        $hover .= '<rect x="' . $xIni . '" y="' . $padTopo . '" width="' . max(0, $xFim - $xIni) . '" height="' . $alturaUtil . '" fill="transparent"><title>' . $titulo . '</title></rect>';
    }

    $linhasGrade = '';
    $rotulosY = '';
    for ($i = 0; $i <= 2; $i++) {
        $valor = $maxEscala * $i / 2;
        $y = $coordY($valor);
        $linhasGrade .= '<line x1="' . $padEsq . '" y1="' . $y . '" x2="' . ($largura - $padDir) . '" y2="' . $y . '" stroke="' . COR_GRID . '" stroke-width="1"/>';
        $rotulosY .= '<text x="' . ($padEsq - 8) . '" y="' . ($y + 4) . '" text-anchor="end" font-size="10" fill="' . COR_MUTED . '">' . formatar_moeda($valor) . '</text>';
    }

    $rotulosX = '';
    foreach ($pontos as $i => $p) {
        $rotulosX .= '<text x="' . $coordX($i) . '" y="' . ($altura - 6) . '" text-anchor="middle" font-size="10" fill="' . COR_MUTED . '">' . htmlspecialchars($p['label'], ENT_QUOTES, 'UTF-8') . '</text>';
    }

    $tabela = '<table><thead><tr><th>Mês</th><th>Receitas</th><th>Despesas</th></tr></thead><tbody>';
    foreach ($pontos as $p) {
        $tabela .= '<tr><td>' . htmlspecialchars($p['label'], ENT_QUOTES, 'UTF-8') . '</td><td>' . formatar_moeda($p['receitas']) . '</td><td>' . formatar_moeda($p['despesas']) . '</td></tr>';
    }
    $tabela .= '</tbody></table>';

    return <<<SVG
    <div class="grafico">
      <svg viewBox="0 0 {$largura} {$altura}" role="img" aria-label="Receitas x despesas por mês">
        {$linhasGrade}
        {$rotulosY}
        <path d="{$linhaReceitas}" fill="none" stroke="{$corReceita}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
        <path d="{$linhaDespesas}" fill="none" stroke="{$corDespesa}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
        {$rotulosX}
        {$hover}
      </svg>
      <details class="tabela-alt"><summary>Ver como tabela</summary>{$tabela}</details>
    </div>
    SVG;
}

/** Anel de progresso dourado (usado na página de detalhe da Meta). */
function anel_progresso(float $percentual, int $tamanho = 64): string
{
    $espessura = 5;
    $raio = ($tamanho - $espessura) / 2;
    $centro = $tamanho / 2;
    $circunferencia = 2 * M_PI * $raio;
    $pct = min(100, max(0, $percentual));
    $tracoVisivel = ($pct / 100) * $circunferencia;
    $circunferenciaRestante = $circunferencia - $tracoVisivel;
    $fonte = $tamanho * 0.24;
    $textoY = $centro + 4;
    $pctArredondado = (int) round($pct);
    $cor = COR_DOURADO_LINHA;

    return <<<SVG
    <svg width="{$tamanho}" height="{$tamanho}" viewBox="0 0 {$tamanho} {$tamanho}" role="img" aria-label="{$pctArredondado}% concluído" style="flex-shrink:0">
      <circle cx="{$centro}" cy="{$centro}" r="{$raio}" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="{$espessura}"/>
      <circle cx="{$centro}" cy="{$centro}" r="{$raio}" fill="none" stroke="{$cor}" stroke-width="{$espessura}" stroke-linecap="round"
        stroke-dasharray="{$tracoVisivel} {$circunferenciaRestante}" transform="rotate(-90 {$centro} {$centro})"/>
      <text x="{$centro}" y="{$textoY}" text-anchor="middle" font-size="{$fonte}" font-weight="700" fill="#fff">{$pctArredondado}%</text>
    </svg>
    SVG;
}
