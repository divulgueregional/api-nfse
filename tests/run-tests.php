<?php
/**
 * Testes de compatibilidade e funcionamento da classe NFSeNacional.
 *
 * Executa sem dependências externas (não usa PHPUnit):
 *   php tests/run-tests.php
 *
 * Requer PHP 8.1+ com as extensões dom, openssl e zlib.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Divulgueregional\ApiNfse\NFSeNacional;

$total = 0;
$falhas = 0;

function teste(string $nome, callable $fn): void
{
    global $total, $falhas;
    $total++;

    try {
        $fn();
        echo "  ok   {$nome}\n";
    } catch (Throwable $e) {
        $falhas++;
        echo "  FAIL {$nome}\n       {$e->getMessage()}\n";
    }
}

function afirmar(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

function afirmarIgual(mixed $esperado, mixed $obtido, string $mensagem): void
{
    if ($esperado !== $obtido) {
        throw new RuntimeException(
            $mensagem . ' (esperado: ' . var_export($esperado, true) . ', obtido: ' . var_export($obtido, true) . ')'
        );
    }
}

function afirmarExcecao(string $classe, callable $fn, string $mensagem): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        afirmar($e instanceof $classe, $mensagem . ' (recebido: ' . get_class($e) . ': ' . $e->getMessage() . ')');
        return;
    }

    throw new RuntimeException($mensagem . ' (nenhuma exceção lançada)');
}

/**
 * Gera um certificado autoassinado e exporta em PKCS#12 e PEM
 *
 * @return array{p12: string, pem: string, certPem: string}
 */
function gerarCertificadoTeste(): array
{
    $config = ['digest_alg' => 'sha256', 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
    $key = openssl_pkey_new($config);
    if ($key === false) {
        throw new RuntimeException('Não foi possível gerar a chave de teste: ' . (openssl_error_string() ?: ''));
    }

    $csr = openssl_csr_new(['commonName' => 'Teste NFSe Nacional', 'organizationName' => 'Teste'], $key, $config);
    if ($csr === false) {
        throw new RuntimeException('Não foi possível gerar o CSR de teste: ' . (openssl_error_string() ?: ''));
    }

    $cert = openssl_csr_sign($csr, null, $key, 2, $config);
    if ($cert === false) {
        throw new RuntimeException('Não foi possível assinar o certificado de teste: ' . (openssl_error_string() ?: ''));
    }

    $p12 = '';
    if (!openssl_pkcs12_export($cert, $p12, $key, '123456')) {
        throw new RuntimeException('Não foi possível exportar o PKCS#12 de teste: ' . (openssl_error_string() ?: ''));
    }

    openssl_x509_export($cert, $certPem);
    openssl_pkey_export($key, $keyPem);

    return ['p12' => $p12, 'pem' => $certPem . $keyPem, 'certPem' => $certPem];
}

function dadosDPS(NFSeNacional $nfse, array $sobrescrever = []): array
{
    $dados = [
        'idDps' => $nfse->gerarIdDPS('5208707', '12345678000190', 10, 5),
        'dhEmi' => '2026-01-05T10:00:00-03:00',
        'serie' => 10,
        'nDps' => 5,
        'dCompet' => '2026-01-05',
        'tpEmit' => '1',
        'cLocEmi' => '5208707',
        'prestador' => [
            'cnpj' => '12.345.678/0001-90',
            'im' => '123456',
            'email' => 'prestador@teste.com',
            'regTrib' => ['opSimpNac' => 3, 'regApTribSN' => 1, 'regEspTrib' => 0],
        ],
        'tomador' => [
            'cnpj' => '98.765.432/0001-00',
            'xNome' => 'Tomador & Cia',
            'fone' => '(62) 99999-9999',
            'email' => 'tomador@teste.com',
            'endereco' => [
                'cMun' => '5208707',
                'cep' => '74.275-050',
                'xLgr' => 'Rua São João',
                'nro' => '100',
                'xBairro' => 'Jardim América',
            ],
        ],
        'servico' => [
            'cLocPrestacao' => '5208707',
            'cTribNac' => '01.07.01',
            'xDescServ' => 'Suporte & manutenção',
            'xInfComp' => '{"infAdicLT":"5208707","infAdicES":"N"}',
            'cIntContrib' => 'VENDA-123',
        ],
        'valores' => [
            'vServ' => 120.5,
            'tribISSQN' => 1,
            'tpRetISSQN' => 1,
            'pTotTribSN' => 0,
        ],
    ];

    return array_replace_recursive($dados, $sobrescrever);
}

function textoDoNo(string $xml, string $tag): ?string
{
    $dom = new DOMDocument();
    $dom->loadXML($xml);

    $node = $dom->getElementsByTagName($tag)->item(0);

    return $node?->nodeValue;
}

/**
 * Valida a assinatura XML-DSIG (SignatureValue e DigestValue)
 */
function validarAssinatura(string $xmlAssinado, string $certPem, string $tagAssinada): void
{
    $dom = new DOMDocument();
    afirmar($dom->loadXML($xmlAssinado), 'XML assinado não é válido');

    $xp = new DOMXPath($dom);
    $xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

    $signatureValue = $xp->query('//ds:SignatureValue')->item(0);
    afirmar($signatureValue !== null, 'SignatureValue não encontrado');
    afirmar($signatureValue->nodeValue !== '', 'SignatureValue está vazio');

    $signedInfo = $xp->query('//ds:SignedInfo')->item(0);
    $publicKey = openssl_pkey_get_public($certPem);
    afirmar($publicKey !== false, 'Não foi possível ler a chave pública do certificado');
    afirmarIgual(
        1,
        openssl_verify($signedInfo->C14N(false, false), base64_decode($signatureValue->nodeValue), $publicKey, OPENSSL_ALGO_SHA1),
        'SignatureValue não confere com o certificado'
    );

    $noAssinado = $xp->query('//*[local-name()="' . $tagAssinada . '"]')->item(0);
    afirmar($noAssinado !== null, "Tag {$tagAssinada} não encontrada");
    $digest = $xp->query('//ds:DigestValue')->item(0);
    afirmarIgual(
        base64_encode(sha1($noAssinado->C14N(false, false), true)),
        $digest->nodeValue,
        'DigestValue não confere com o conteúdo assinado'
    );
}

echo "PHP " . PHP_VERSION . " | NFSeNacional\n\n";

$cert = gerarCertificadoTeste();
$dirTemp = sys_get_temp_dir() . '/nfse_teste_' . bin2hex(random_bytes(4));
mkdir($dirTemp, 0700, true);
file_put_contents("{$dirTemp}/cert.p12", $cert['p12']);
file_put_contents("{$dirTemp}/cert.pem", $cert['pem']);

$nfse = new NFSeNacional(['cert_path' => "{$dirTemp}/cert.p12", 'cert_password' => '123456'], 2);

// ---------------------------------------------------------------------------
// IdDPS
// ---------------------------------------------------------------------------
teste('gerarIdDPS monta 45 caracteres no padrão DPS', function () use ($nfse) {
    $id = $nfse->gerarIdDPS('5208707', '12.345.678/0001-90', 10, 5);
    afirmarIgual('DPS520870721234567800019000010000000000000005', $id, 'IdDPS inesperado');
    afirmarIgual(45, strlen($id), 'IdDPS deve ter 45 caracteres');
});

teste('gerarIdDPS usa tpInsc 2 para CNPJ e 1 para CPF (regra E0004)', function () use ($nfse) {
    $comCnpj = $nfse->gerarIdDPS('5208707', '12345678000190', 1, 1);
    afirmarIgual('2', substr($comCnpj, 10, 1), 'CNPJ deve usar tpInsc 2');

    $comCpf = $nfse->gerarIdDPS('5208707', '123.456.789-09', 1, 1);
    afirmarIgual('1', substr($comCpf, 10, 1), 'CPF deve usar tpInsc 1');
    afirmarIgual('DPS520870710001234567890900001000000000000001', $comCpf, 'IdDPS com CPF inesperado');
});

teste('gerarIdDPS rejeita município inválido', function () use ($nfse) {
    afirmarExcecao(InvalidArgumentException::class, fn() => $nfse->gerarIdDPS('123', '12345678000190', 1, 1), 'cMun inválido deve lançar exceção');
});

// ---------------------------------------------------------------------------
// XML da DPS
// ---------------------------------------------------------------------------
teste('montarXmlDPS gera XML válido com os dados informados', function () use ($nfse) {
    $xml = $nfse->montarXmlDPS(dadosDPS($nfse));

    $dom = new DOMDocument();
    afirmar($dom->loadXML($xml), 'XML da DPS inválido');

    afirmarIgual('2', textoDoNo($xml, 'tpAmb'), 'tpAmb incorreto');
    afirmarIgual('12345678000190', textoDoNo($xml, 'CNPJ'), 'CNPJ do prestador incorreto');
    afirmarIgual('010701', textoDoNo($xml, 'cTribNac'), 'cTribNac deveria conter apenas números');
    afirmarIgual('120.50', textoDoNo($xml, 'vServ'), 'vServ incorreto');
    afirmarIgual('VENDA-123', textoDoNo($xml, 'cIntContrib'), 'cIntContrib não foi incluído');
});

teste('montarXmlDPS não faz escape duplo de & e mantém texto íntegro', function () use ($nfse) {
    $xml = $nfse->montarXmlDPS(dadosDPS($nfse));

    afirmar(!str_contains($xml, '&amp;amp;'), 'XML contém escape duplo (&amp;amp;)');
    afirmarIgual('Tomador & Cia', textoDoNo($xml, 'xNome'), 'xNome do tomador corrompido');
    afirmarIgual('Suporte & manutenção', textoDoNo($xml, 'xDescServ'), 'xDescServ corrompido');
});

teste('montarXmlDPS remove acentos do endereço do tomador', function () use ($nfse) {
    $xml = $nfse->montarXmlDPS(dadosDPS($nfse));

    afirmarIgual('Rua Sao Joao', textoDoNo($xml, 'xLgr'), 'xLgr deveria estar sem acentos');
    afirmarIgual('Jardim America', textoDoNo($xml, 'xBairro'), 'xBairro deveria estar sem acentos');
});

teste('montarXmlDPS não cria nó toma quando o tomador não é informado', function () use ($nfse) {
    $dados = dadosDPS($nfse);
    unset($dados['tomador']);
    $xml = $nfse->montarXmlDPS($dados);

    $dom = new DOMDocument();
    $dom->loadXML($xml);
    afirmarIgual(0, $dom->getElementsByTagName('toma')->length, 'Nó toma não deveria existir');
});

teste('montarXmlDPS valida campos obrigatórios', function () use ($nfse) {
    afirmarExcecao(
        InvalidArgumentException::class,
        fn() => $nfse->montarXmlDPS(['idDps' => 'DPS123']),
        'DPS sem campos obrigatórios deve lançar exceção'
    );

    afirmarExcecao(
        InvalidArgumentException::class,
        fn() => $nfse->montarXmlDPS(dadosDPS($nfse, ['idDps' => '123'])),
        'IdDPS fora do padrão deve lançar exceção'
    );
});

// ---------------------------------------------------------------------------
// Assinatura
// ---------------------------------------------------------------------------
teste('assinarXML gera assinatura válida para o infDPS', function () use ($nfse, $cert) {
    $assinado = $nfse->assinarXML($nfse->montarXmlDPS(dadosDPS($nfse)));
    validarAssinatura($assinado, $cert['certPem'], 'infDPS');
});

teste('assinarXML não duplica a assinatura ao reassinar', function () use ($nfse) {
    $assinado = $nfse->assinarXML($nfse->montarXmlDPS(dadosDPS($nfse)));
    $reassinado = $nfse->assinarXML($assinado);

    $dom = new DOMDocument();
    $dom->loadXML($reassinado);
    afirmarIgual(1, $dom->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature')->length, 'Deveria existir apenas uma Signature');
});

teste('assinarXMLCan assina o infPedReg do cancelamento', function () use ($nfse, $cert) {
    $xml = $nfse->montarXmlCancelamento(str_repeat('1', 50), '12345678000190', 1, 'Erro na digitacao');
    $assinado = $nfse->assinarXMLCan($xml);

    validarAssinatura($assinado, $cert['certPem'], 'infPedReg');
});

teste('assinarXML falha com mensagem clara quando o certificado não existe', function () use ($dirTemp) {
    afirmarExcecao(
        RuntimeException::class,
        fn() => new NFSeNacional(['cert_path' => "{$dirTemp}/inexistente.p12", 'cert_password' => '1'], 2),
        'Certificado inexistente deve lançar exceção'
    );
});

teste('cert_content em base64 assina o XML', function () use ($cert) {
    $nfseConteudo = new NFSeNacional(['cert_content' => base64_encode($cert['p12']), 'cert_password' => '123456'], 2);
    $assinado = $nfseConteudo->assinarXML($nfseConteudo->montarXmlDPS(dadosDPS($nfseConteudo)));
    validarAssinatura($assinado, $cert['certPem'], 'infDPS');
});

teste('certificado em PEM assina o XML', function () use ($dirTemp, $cert) {
    $nfsePem = new NFSeNacional(['cert_path' => "{$dirTemp}/cert.pem"], 2);
    $assinado = $nfsePem->assinarXML($nfsePem->montarXmlDPS(dadosDPS($nfsePem)));
    validarAssinatura($assinado, $cert['certPem'], 'infDPS');
});

// ---------------------------------------------------------------------------
// Utilidades
// ---------------------------------------------------------------------------
teste('decodificarChaveNFSe separa os campos da chave de 50 dígitos', function () use ($nfse) {
    // Cód.Mun(7) + Amb.Ger(1) + Tipo Inscrição(1) + Inscrição Federal(14) + nNFSe(13) + AAMM(4) + Cód.Num(9) + DV(1)
    $chave = '5208707' . '1' . '2' . '12345678000195' . '0000000000001' . '2601' . '260350673' . '3';
    afirmarIgual(50, strlen($chave), 'Chave de teste deve ter 50 dígitos');

    $dados = $nfse->decodificarChaveNFSe($chave);

    afirmarIgual('52', $dados['uf'], 'UF incorreta');
    afirmarIgual('5208707', $dados['codigoMunicipio'], 'Município incorreto');
    afirmarIgual('12345678000195', $dados['cnpjPrestador'], 'CNPJ incorreto');
    afirmarIgual('3', $dados['dv'], 'DV incorreto');
});

teste('decodificarChaveNFSe rejeita chave com tamanho inválido', function () use ($nfse) {
    afirmarExcecao(Exception::class, fn() => $nfse->decodificarChaveNFSe('123'), 'Chave inválida deve lançar exceção');
});

teste('getTipoAmbiente e getUrlBase refletem o ambiente informado', function () {
    $homologacao = new NFSeNacional([], 2);
    afirmarIgual(2, $homologacao->getTipoAmbiente(), 'Ambiente de homologação incorreto');
    afirmarIgual('https://sefin.producaorestrita.nfse.gov.br', $homologacao->getUrlBase(), 'URL de homologação incorreta');

    $producao = new NFSeNacional([], 1);
    afirmarIgual(1, $producao->getTipoAmbiente(), 'Ambiente de produção incorreto');
    afirmarIgual('https://sefin.nfse.gov.br', $producao->getUrlBase(), 'URL de produção incorreta');
});

// limpeza
foreach (glob("{$dirTemp}/*") ?: [] as $arquivo) {
    @unlink($arquivo);
}
@rmdir($dirTemp);

echo "\n{$total} testes, {$falhas} falha(s)\n";

exit($falhas === 0 ? 0 : 1);
