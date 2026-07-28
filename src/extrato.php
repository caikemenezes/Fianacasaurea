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
 *
 * Se a conta ainda não tem identificador_extrato (nem cadastrado à mão, nem
 * preenchido por um casamento anterior) e a transação foi informada, grava a
 * chave do beneficiário automaticamente — sem isso, detectar_e_criar_contas_fixas()
 * não tem como saber que essa conta já existe e cria duplicata (bug real
 * encontrado: "Energia elétrica"/"Internet" vinculadas manualmente sem
 * identificador_extrato geraram "Enel..."/"Claro" duplicados na primeira
 * detecção automática).
 */
function aplicar_pagamento_conta_mes(PDO $pdo, array $conta, string $dataPagamento, ?string $descricaoTransacao = null): void
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

    if ($descricaoTransacao !== null && (string) ($conta['identificador_extrato'] ?? '') === '') {
        $chave = extrair_chave_beneficiario($descricaoTransacao);
        if ($chave !== '') {
            $stmt = $pdo->prepare('UPDATE conta_mes SET identificador_extrato = ? WHERE id = ? AND identificador_extrato IS NULL');
            $stmt->execute([$chave, $conta['id']]);
        }
    }
}

/**
 * Extrai uma "chave de beneficiário" normalizada da descrição de uma
 * transação, pra agrupar pagamentos ao mesmo destinatário/loja em meses
 * diferentes e detectar padrão de recorrência. Cobre os formatos reais do
 * Nubank: "Transferência enviada pelo Pix - NOME - CPF/CNPJ - BANCO..." (fica
 * só NOME) e "Compra no débito - LOJA" (fica só LOJA).
 */
function extrair_chave_beneficiario(string $descricao): string
{
    $prefixos = [
        'Estorno - Compra no débito - ',
        'Compra no débito - ',
        'Transferência enviada pelo Pix - ',
        'Transferência recebida pelo Pix - ',
        'Transferência Recebida - ',
        'Transferência enviada - ',
    ];

    $resto = $descricao;
    foreach ($prefixos as $prefixo) {
        if (str_starts_with($descricao, $prefixo)) {
            $resto = substr($descricao, strlen($prefixo));
            break;
        }
    }

    $partes = explode(' - ', $resto);

    return mb_strtoupper(trim($partes[0]), 'UTF-8');
}

/** true só pra Pix enviado (transferência com CPF/CNPJ do beneficiário) — usado pra restringir a detecção de conta fixa, ver detectar_e_criar_contas_fixas(). */
function eh_pix_enviado(string $descricao): bool
{
    return str_starts_with($descricao, 'Transferência enviada pelo Pix - ')
        || str_starts_with($descricao, 'Transferência enviada - ');
}

/**
 * Depois do casamento normal, procura entre as transações de saída ainda
 * PENDENTES (não bateram com nenhuma Conta do Mês já cadastrada) um padrão de
 * recorrência: mesmo beneficiário pagando em pelo menos $mesesMinimos meses
 * diferentes do extrato importado = provavelmente uma conta fixa que o
 * usuário nunca cadastrou manualmente (ex: ENEL, Claro, mensalidade da
 * academia). Cria a Conta do Mês sozinho — já marcada Paga, com o valor e
 * data do pagamento mais recente do grupo — e vincula também as ocorrências
 * mais antigas do mesmo beneficiário a ela (só de categorização, sem
 * reprocessar parcelas/vencimento pra cada uma, isso só acontece pra a mais
 * recente). Pedido explícito do usuário: não quer ficar cadastrando contas
 * fixas manualmente, o extrato deve reconhecer sozinho.
 *
 * Restrito a Pix enviado (tem CPF/CNPJ do beneficiário — sinal forte de
 * "conta" de verdade). Testado contra dado real: incluir compra no débito
 * classifica errado lugar visitado com frequência (mercado, Uber, iFood)
 * como conta fixa, porque esses acumulam mais meses de ocorrência que uma
 * conta de luz — não tem limite de meses que resolva isso pro débito.
 */
function detectar_e_criar_contas_fixas(PDO $pdo, int $familiaId, int $mesesMinimos = 3): array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM transacao_importada
         WHERE familia_id = ? AND status = 'PENDENTE' AND valor < 0
         ORDER BY data ASC"
    );
    $stmt->execute([$familiaId]);
    $pendentes = $stmt->fetchAll();

    $grupos = [];
    foreach ($pendentes as $t) {
        if (!eh_pix_enviado($t['descricao'])) {
            continue;
        }
        $chave = extrair_chave_beneficiario($t['descricao']);
        if ($chave === '') {
            continue;
        }
        $grupos[$chave][] = $t;
    }

    $criadas = 0;
    $transacoesAbsorvidas = 0;
    foreach ($grupos as $chave => $transacoes) {
        $meses = array_unique(array_map(fn(array $t): string => substr($t['data'], 0, 7), $transacoes));
        if (count($meses) < $mesesMinimos) {
            continue;
        }

        // Evita duplicar se o usuário já cadastrou manualmente uma conta com
        // essa palavra-chave, ou se uma leva anterior já criou.
        $existe = $pdo->prepare('SELECT id FROM conta_mes WHERE familia_id = ? AND identificador_extrato = ?');
        $existe->execute([$familiaId, $chave]);
        if ($existe->fetch() !== false) {
            continue;
        }

        usort($transacoes, fn(array $a, array $b): int => strcmp($a['data'], $b['data']));
        $maisRecente = end($transacoes);
        $nome = mb_convert_case($chave, MB_CASE_TITLE, 'UTF-8');

        $stmt = $pdo->prepare(
            "INSERT INTO conta_mes (familia_id, nome, categoria, valor, vencimento, tipo, recorrente_mensal, identificador_extrato, status, paga_em)
             VALUES (?, ?, 'Outros', ?, ?, 'FIXA', 1, ?, 'PAGA', ?)"
        );
        $stmt->execute([
            $familiaId, $nome, abs((float) $maisRecente['valor']), $maisRecente['data'], $chave, $maisRecente['data'],
        ]);
        $contaMesId = (int) $pdo->lastInsertId();

        foreach ($transacoes as $t) {
            $stmt = $pdo->prepare("UPDATE transacao_importada SET status = 'CONFIRMADA', conta_mes_id = ? WHERE id = ?");
            $stmt->execute([$contaMesId, $t['id']]);
        }

        $criadas++;
        $transacoesAbsorvidas += count($transacoes);
    }

    return ['contas' => $criadas, 'transacoes' => $transacoesAbsorvidas];
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
                aplicar_pagamento_conta_mes($pdo, $conta, $t['data'], $t['descricao']);
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
        $deteccao = detectar_e_criar_contas_fixas($pdo, $familiaId);
        $pdo->commit();
    } catch (Throwable $erro) {
        $pdo->rollBack();
        throw $erro;
    }

    return [
        'total_no_extrato' => count($transacoes),
        'novas' => $novas,
        'casadas' => $casadas,
        'contas_fixas_criadas' => $deteccao['contas'],
        'transacoes_absorvidas_por_contas_fixas' => $deteccao['transacoes'],
    ];
}
