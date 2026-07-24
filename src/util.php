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

/** Equivalente a parseCurrencyInput do format.ts original: aceita "1234,56" ou "1234.56". */
function parse_valor(mixed $valor): float
{
    if ($valor === null || $valor === '') {
        return 0.0;
    }
    return (float) str_replace(',', '.', (string) $valor);
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
