<?php

declare(strict_types=1);

require_once __DIR__ . '/icons.php';

/**
 * Ícone ⓘ no canto de um cartão, explicando o que aquela seção faz — réplica
 * do componente InfoIcone original (abre ao passar o mouse OU clicar, fecha
 * ao clicar fora; ver public/assets/info-icone.js). Não usar no Dashboard
 * (decisão do original: lá os cartões já são autoexplicativos).
 */
function info_icone(string $texto): string
{
    return '<div class="info-icone-wrapper">'
        . '<button type="button" class="botao-icone-info" aria-label="Sobre esta seção" aria-expanded="false">' . icone('info') . '</button>'
        . '<div class="info-balao" role="tooltip" hidden>' . htmlspecialchars($texto, ENT_QUOTES, 'UTF-8') . '</div>'
        . '</div>';
}

/**
 * Equivalente a parseCurrencyInput do format.ts original, mas adaptado pro
 * campo de valor ter ganhado máscara em 2026-07-24 (ver mascara-moeda.js):
 * o valor chega como "10.000,00" (ponto = separador de milhar, vírgula =
 * decimal), não mais como "1234.56" — remove os pontos antes de trocar a
 * vírgula por ponto, senão "10.000,00" viraria 10.0 em vez de 10000.0.
 */
function parse_valor(mixed $valor): float
{
    if ($valor === null || $valor === '') {
        return 0.0;
    }
    $texto = str_replace('.', '', (string) $valor);
    return (float) str_replace(',', '.', $texto);
}

/** Formata um valor pra pré-preencher um campo com data-moeda (ver mascara-moeda.js). */
function formatar_valor_input(float $valor): string
{
    return number_format($valor, 2, ',', '.');
}

function parse_valor_ou_null(mixed $valor): ?float
{
    if ($valor === null || trim((string) $valor) === '') {
        return null;
    }
    return parse_valor($valor);
}

/** Converte string vazia em null (equivalente a `String(x || "") || null` do original). */
function texto_ou_null(mixed $valor): ?string
{
    $texto = trim((string) ($valor ?? ''));
    return $texto === '' ? null : $texto;
}

function data_ou_null(mixed $valor): ?string
{
    $texto = trim((string) ($valor ?? ''));
    return $texto === '' ? null : $texto;
}

function formatar_data(?string $data): string
{
    if ($data === null) {
        return '—';
    }
    $timestamp = strtotime($data);
    return $timestamp === false ? '—' : date('d/m/Y', $timestamp);
}

function formatar_mes_ano(?string $data): string
{
    if ($data === null) {
        return '—';
    }
    $meses = ['01' => 'janeiro', '02' => 'fevereiro', '03' => 'março', '04' => 'abril', '05' => 'maio', '06' => 'junho',
        '07' => 'julho', '08' => 'agosto', '09' => 'setembro', '10' => 'outubro', '11' => 'novembro', '12' => 'dezembro'];
    $timestamp = strtotime($data);
    if ($timestamp === false) {
        return '—';
    }
    return $meses[date('m', $timestamp)] . ' de ' . date('Y', $timestamp);
}

/** Meses entre hoje e a data informada (mínimo 1) — equivalente a mesesRestantes() do original. */
function meses_restantes(?string $data): ?int
{
    if ($data === null) {
        return null;
    }
    $alvo = new DateTimeImmutable($data);
    $hoje = new DateTimeImmutable('today');
    $meses = ((int) $alvo->format('Y') - (int) $hoje->format('Y')) * 12 + ((int) $alvo->format('n') - (int) $hoje->format('n'));
    return max(1, $meses);
}

function redirecionar(string $destino): never
{
    header("Location: {$destino}");
    exit;
}

/**
 * Soma (ou subtrai, se $meses for negativo) meses a uma data 'Y-m-d'. Usada
 * pra avançar automaticamente o vencimento de dívidas/contas parceladas
 * quando o número de parcelas pagas muda (ver dividas-processar.php e
 * contas-processar.php, ação "editar"/"marcar_paga").
 */
function somar_meses(string $data, int $meses): string
{
    return (new DateTimeImmutable($data))->modify("{$meses} months")->format('Y-m-d');
}
