<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/util.php';
require_once __DIR__ . '/cripto.php';

/**
 * Credenciais do Gmail cadastradas pela família em Configurações > Extrato
 * automático (imap_email + imap_senha_app_cifrada, cifrada com src/cripto.php)
 * — cada família tem a própria, pra puxar o extrato da própria conta bancária
 * sem misturar com a de outra família. Retorna null se a família ainda não
 * cadastrou nada.
 */
function credenciais_gmail_da_familia(PDO $pdo, int $familiaId): ?array
{
    $stmt = $pdo->prepare('SELECT imap_email, imap_senha_app_cifrada FROM familia WHERE id = ?');
    $stmt->execute([$familiaId]);
    $familia = $stmt->fetch();

    if ($familia === false || (string) $familia['imap_email'] === '' || (string) $familia['imap_senha_app_cifrada'] === '') {
        return null;
    }

    $senha = decifrar_segredo($familia['imap_senha_app_cifrada']);
    if ($senha === null) {
        return null;
    }

    return ['email' => $familia['imap_email'], 'senha' => $senha];
}

/**
 * Conecta no Gmail via IMAP com a credencial informada (e-mail + "Senha de
 * app" do Google, não a senha normal da conta) e busca TODOS os e-mails do
 * Nubank com "Extrato" no assunto (não só o mais recente — a família pode ter
 * um e-mail de extrato por mês acumulado desde janeiro). Retorna a lista de
 * CSVs anexados (texto cru), um por e-mail encontrado.
 */
function baixar_csvs_extrato_gmail(string $email, string $senha): array
{
    // Sem isso, uma instabilidade de rede no meio da busca (ex: fetch de um
    // anexo) deixa o c-client esperando pra sempre — já travou o processo
    // aqui, então toda chamada IMAP tem no máximo 15s antes de desistir.
    imap_timeout(IMAP_OPENTIMEOUT, 15);
    imap_timeout(IMAP_READTIMEOUT, 15);
    imap_timeout(IMAP_WRITETIMEOUT, 15);
    imap_timeout(IMAP_CLOSETIMEOUT, 15);

    // novalidate-cert: a lib c-client do PHP no Windows não valida direito a
    // cadeia de certificado do Gmail (falso positivo de "self-signed cert"),
    // então pulamos essa checagem — a conexão continua criptografada (TLS na
    // porta 993), só não confirma a identidade do servidor.
    $caixa = '{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX';
    $conexao = @imap_open($caixa, $email, $senha);
    if ($conexao === false) {
        throw new RuntimeException('Não foi possível conectar no Gmail: ' . imap_last_error());
    }

    try {
        $ids = imap_search($conexao, 'SUBJECT "Extrato"', SE_FREE, 'UTF-8');
        if ($ids === false || count($ids) === 0) {
            return [];
        }
        sort($ids);

        $csvs = [];
        foreach ($ids as $id) {
            $estrutura = imap_fetchstructure($conexao, $id);
            $numeroParteCsv = localizar_parte_csv($estrutura);
            if ($numeroParteCsv === null) {
                continue; // e-mail sem anexo CSV, pula
            }
            $conteudo = imap_fetchbody($conexao, $id, $numeroParteCsv);
            $csvs[] = base64_decode($conteudo);
        }
        return $csvs;
    } finally {
        imap_close($conexao);
    }
}

/** Acha o número da parte MIME do anexo CSV, percorrendo a estrutura (pode ter subpartes aninhadas). */
function localizar_parte_csv(object $estrutura, string $prefixo = ''): ?string
{
    if (!isset($estrutura->parts) || count($estrutura->parts) === 0) {
        return ($estrutura->subtype ?? '') === 'CSV' ? ($prefixo !== '' ? $prefixo : '1') : null;
    }

    foreach ($estrutura->parts as $i => $parte) {
        $numero = $prefixo . ($i + 1);
        if (isset($parte->parts) && count($parte->parts) > 0) {
            $achado = localizar_parte_csv($parte, $numero . '.');
            if ($achado !== null) return $achado;
        } elseif (($parte->subtype ?? '') === 'CSV') {
            return $numero;
        }
    }
    return null;
}

/**
 * Interpreta o CSV do Nubank (cabeçalho: Data,Valor,Identificador,Descrição)
 * em uma lista de transações. Bem mais simples e confiável que ler o PDF —
 * valor já vem com sinal (negativo = saída, positivo = entrada) e cada linha
 * tem um identificador único (evita importar a mesma transação duas vezes).
 */
function interpretar_csv_extrato(string $csv): array
{
    $linhas = preg_split('/\r\n|\r|\n/', trim($csv));
    array_shift($linhas); // remove cabeçalho

    $transacoes = [];
    foreach ($linhas as $linha) {
        if (trim($linha) === '') continue;
        $campos = str_getcsv($linha);
        if (count($campos) < 4) continue;

        [$dataTexto, $valorTexto, $identificador, $descricao] = $campos;
        [$dia, $mes, $ano] = explode('/', $dataTexto);

        $transacoes[] = [
            'identificador' => $identificador,
            'data' => "{$ano}-{$mes}-{$dia}",
            'valor' => (float) $valorTexto,
            'descricao' => $descricao,
        ];
    }
    return $transacoes;
}

/**
 * Acha, entre as Contas do Mês ainda em aberto (PENDENTE/ATRASADA), qual bate
 * com a descrição/valor de uma transação de saída — por palavra-chave
 * (identificador_extrato, prioridade) ou, na falta dela, por valor exato
 * (só aceita se for a única conta aberta com esse valor, pra evitar casar errado).
 */
function achar_conta_mes_correspondente(PDO $pdo, int $familiaId, float $valorAbsoluto, string $descricao): ?array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM conta_mes WHERE familia_id = ? AND status IN ('PENDENTE', 'ATRASADA')"
    );
    $stmt->execute([$familiaId]);
    $candidatas = $stmt->fetchAll();

    foreach ($candidatas as $conta) {
        if ($conta['identificador_extrato'] !== null && $conta['identificador_extrato'] !== ''
            && mb_stripos($descricao, $conta['identificador_extrato']) !== false) {
            return $conta;
        }
    }

    $porValor = array_values(array_filter($candidatas, function (array $c) use ($valorAbsoluto): bool {
        $valorDaConta = $c['numero_parcelas'] !== null && $c['valor_parcela'] !== null
            ? (float) $c['valor_parcela']
            : (float) $c['valor'];
        return abs($valorDaConta - $valorAbsoluto) < 0.01;
    }));

    return count($porValor) === 1 ? $porValor[0] : null;
}

/** Mesma lógica de "achar_conta_mes_correspondente", mas pra Receitas (entradas) ainda não recebidas. */
function achar_receita_correspondente(PDO $pdo, int $familiaId, float $valor, string $descricao): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM receita WHERE familia_id = ? AND status = 'PREVISTO'");
    $stmt->execute([$familiaId]);
    $candidatas = $stmt->fetchAll();

    foreach ($candidatas as $receita) {
        if ($receita['identificador_extrato'] !== null && $receita['identificador_extrato'] !== ''
            && mb_stripos($descricao, $receita['identificador_extrato']) !== false) {
            return $receita;
        }
    }

    $porValor = array_values(array_filter($candidatas, fn(array $r): bool => abs((float) $r['valor_previsto'] - $valor) < 0.01));

    return count($porValor) === 1 ? $porValor[0] : null;
}

/**
 * Marca uma parcela como paga na Conta do Mês encontrada — mesma conta usada
 * em contas-processar.php (ação editar_parcelas): se for parcelada, avança
 * parcelas_pagas e vencimento em 1 mês; senão, só marca status PAGA.
 */
function aplicar_pagamento_conta_mes(PDO $pdo, array $conta, string $dataPagamento): void
{
    if ($conta['numero_parcelas'] !== null) {
        $novasParcelasPagas = (int) $conta['parcelas_pagas'] + 1;
        $novoVencimento = somar_meses($conta['vencimento'], 1);
        $stmt = $pdo->prepare('UPDATE conta_mes SET parcelas_pagas = ?, vencimento = ?, paga_em = ? WHERE id = ?');
        $stmt->execute([$novasParcelasPagas, $novoVencimento, $dataPagamento, $conta['id']]);
    } else {
        $stmt = $pdo->prepare("UPDATE conta_mes SET status = 'PAGA', paga_em = ? WHERE id = ?");
        $stmt->execute([$dataPagamento, $conta['id']]);
    }
}

function aplicar_recebimento_receita(PDO $pdo, array $receita, float $valor, string $dataRecebimento): void
{
    $stmt = $pdo->prepare("UPDATE receita SET status = 'RECEBIDO', valor_recebido = ?, data_recebimento = ? WHERE id = ?");
    $stmt->execute([$valor, $dataRecebimento, $receita['id']]);
}

/**
 * Orquestra tudo: baixa todos os CSVs de extrato do Gmail (um e-mail pode
 * cobrir cada mês), interpreta e junta tudo numa lista só, e pra cada
 * transação nova (que ainda não está em transacao_importada, deduplicada por
 * identificador_externo — então rodar de novo com e-mails já processados não
 * duplica nada) tenta casar com uma Conta do Mês (saída) ou Receita (entrada)
 * em aberto. Retorna um resumo pra mostrar na tela.
 */
function processar_extrato_automatico(PDO $pdo, int $familiaId): array
{
    $credenciais = credenciais_gmail_da_familia($pdo, $familiaId);
    if ($credenciais === null) {
        return ['erro' => 'Cadastre seu e-mail e senha de app do Gmail em Configurações > Extrato automático antes de verificar.'];
    }

    $csvs = baixar_csvs_extrato_gmail($credenciais['email'], $credenciais['senha']);
    if (count($csvs) === 0) {
        return ['erro' => 'Nenhum e-mail com "Extrato" encontrado na caixa de entrada.'];
    }

    $transacoes = [];
    foreach ($csvs as $csv) {
        array_push($transacoes, ...interpretar_csv_extrato($csv));
    }
    $novas = 0;
    $casadas = 0;

    // Uma transação de banco só pro lote inteiro: sob autocommit, cada INSERT/
    // UPDATE vira um fsync em disco (innodb_flush_log_at_trx_commit=1) — com
    // centenas de transações novas (ex: importando vários meses de uma vez),
    // isso deixava o processo praticamente travado por minutos.
    $pdo->beginTransaction();
    try {
    foreach ($transacoes as $t) {
        $stmt = $pdo->prepare('SELECT id FROM transacao_importada WHERE familia_id = ? AND identificador_externo = ?');
        $stmt->execute([$familiaId, $t['identificador']]);
        if ($stmt->fetch() !== false) {
            continue; // já processada antes
        }
        $novas++;

        $contaMesId = null;
        $receitaId = null;
        $status = 'PENDENTE';

        if ($t['valor'] < 0) {
            $conta = achar_conta_mes_correspondente($pdo, $familiaId, abs($t['valor']), $t['descricao']);
            if ($conta !== null) {
                aplicar_pagamento_conta_mes($pdo, $conta, $t['data']);
                $contaMesId = (int) $conta['id'];
                $status = 'CONFIRMADA';
                $casadas++;
            }
        } elseif ($t['valor'] > 0) {
            $receita = achar_receita_correspondente($pdo, $familiaId, $t['valor'], $t['descricao']);
            if ($receita !== null) {
                aplicar_recebimento_receita($pdo, $receita, $t['valor'], $t['data']);
                $receitaId = (int) $receita['id'];
                $status = 'CONFIRMADA';
                $casadas++;
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO transacao_importada (familia_id, identificador_externo, data, valor, descricao, status, conta_mes_id, receita_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$familiaId, $t['identificador'], $t['data'], $t['valor'], $t['descricao'], $status, $contaMesId, $receitaId]);
    }
        $pdo->commit();
    } catch (Throwable $erro) {
        $pdo->rollBack();
        throw $erro;
    }

    return ['total_no_extrato' => count($transacoes), 'novas' => $novas, 'casadas' => $casadas];
}
