<?php

namespace Divulgueregional\ApiNfse;

use DOMDocument;
use DOMXPath;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Classe para comunicação com a API do Sistema Nacional NFS-e
 * Baseada na documentação técnica do SEFIN Nacional
 */
class NFSeNacional
{
    // URLs de Produção
    private const URL_PRODUCAO = 'https://sefin.nfse.gov.br';
    private const URL_PRODUCAO_DANFSE = 'https://adn.nfse.gov.br/danfse';
    private const URL_PRODUCAO_CONSULTA_PUBLICA = 'https://www.nfse.gov.br/ConsultaPublica';
    private const URL_PRODUCAO_PARAMETRIZACAO = 'https://adn.nfse.gov.br/parametrizacao';

    // URLs de Homologação
    private const URL_HOMOLOGACAO = 'https://sefin.producaorestrita.nfse.gov.br';
    private const URL_HOMOLOGACAO_DANFSE = 'https://adn.producaorestrita.nfse.gov.br/danfse';
    private const URL_HOMOLOGACAO_CONSULTA_PUBLICA = 'https://adn.producaorestrita.nfse.gov.br/consultapublica';
    private const URL_HOMOLOGACAO_PARAMETRIZACAO = 'https://adn.producaorestrita.nfse.gov.br/parametrizacao';

    // Namespace da NFS-e
    private const NS_NFSE = 'http://www.sped.fazenda.gov.br/nfse';

    // Versão do aplicativo
    private const VER_APLIC = 'ApiNfse_v1.0';

    private Client $client;
    private int $tpAmb;
    private string $urlBase;
    private string $urlDanfse;
    private string $urlConsultaPublica;
    private string $urlParametrizacao;
    private ?string $certPath = null;
    private ?string $certPassword = null;
    private ?string $certContent = null;
    private ?string $tempCertFile = null;
    private ?array $certificado = null;

    /**
     * Construtor da classe
     * 
     * @param array $config Configurações do certificado digital
     *   - cert_path: Caminho do arquivo .pfx/.p12
     *   - cert_password: Senha do certificado
     *   - cert_content: Conteúdo do certificado em base64 (alternativa ao cert_path)
     * @param int $tpAmb Tipo de ambiente (1=Produção, 2=Homologação)
     */
    public function __construct(array $config, int $tpAmb = 2)
    {
        $this->tpAmb = $tpAmb;

        if ($tpAmb === 1) {
            $this->urlBase = self::URL_PRODUCAO;
            $this->urlDanfse = self::URL_PRODUCAO_DANFSE;
            $this->urlConsultaPublica = self::URL_PRODUCAO_CONSULTA_PUBLICA;
            $this->urlParametrizacao = self::URL_PRODUCAO_PARAMETRIZACAO;
        } else {
            $this->urlBase = self::URL_HOMOLOGACAO;
            $this->urlDanfse = self::URL_HOMOLOGACAO_DANFSE;
            $this->urlConsultaPublica = self::URL_HOMOLOGACAO_CONSULTA_PUBLICA;
            $this->urlParametrizacao = self::URL_HOMOLOGACAO_PARAMETRIZACAO;
        }

        $this->certPath = $config['cert_path'] ?? null;
        $this->certPassword = $config['cert_password'] ?? null;
        $this->certContent = $config['cert_content'] ?? null;

        $this->initClient();
    }

    /**
     * Inicializa o cliente HTTP com certificado digital (mTLS)
     */
    private function initClient(): void
    {
        $options = [
            'base_uri' => $this->urlBase,
            'timeout' => 60,
            'verify' => true,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'ApiNfse-PHP/1.0'
            ]
        ];

        // O cURL aceita o próprio PKCS#12 (.pfx/.p12) ou um arquivo PEM (certificado + chave)
        $certFile = $this->resolverArquivoCertificado();
        if ($certFile !== null) {
            $options['cert'] = [$certFile, (string)$this->certPassword];
        }

        $this->client = new Client($options);
    }

    /**
     * Remove o arquivo temporário do certificado (quando veio via cert_content)
     */
    public function __destruct()
    {
        if ($this->tempCertFile !== null && file_exists($this->tempCertFile)) {
            @unlink($this->tempCertFile);
        }
    }

    /**
     * Resolve o arquivo do certificado digital usado na conexão mTLS.
     * Retorna null quando nenhum certificado foi informado (uso offline).
     */
    private function resolverArquivoCertificado(): ?string
    {
        if ($this->certContent !== null && $this->certContent !== '') {
            $conteudo = $this->conteudoCertificado();
            $this->tempCertFile = $this->criarArquivoTemporario((string)$conteudo);
            return $this->tempCertFile;
        }

        if ($this->certPath !== null && $this->certPath !== '') {
            if (!is_file($this->certPath) || !is_readable($this->certPath)) {
                throw new RuntimeException("Certificado digital não encontrado ou sem permissão de leitura: {$this->certPath}");
            }
            return $this->certPath;
        }

        return null;
    }

    /**
     * Lê o certificado de cert_content (base64 ou binário) ou de cert_path
     */
    private function conteudoCertificado(): ?string
    {
        if ($this->certContent !== null && $this->certContent !== '') {
            if (str_contains($this->certContent, '-----BEGIN')) {
                return $this->certContent;
            }

            $decodificado = base64_decode($this->certContent, true);
            return $decodificado !== false ? $decodificado : $this->certContent;
        }

        if ($this->certPath !== null && $this->certPath !== '') {
            if (!is_file($this->certPath) || !is_readable($this->certPath)) {
                throw new RuntimeException("Certificado digital não encontrado ou sem permissão de leitura: {$this->certPath}");
            }

            $conteudo = file_get_contents($this->certPath);
            if ($conteudo === false) {
                throw new RuntimeException("Não foi possível ler o certificado digital: {$this->certPath}");
            }
            return $conteudo;
        }

        return null;
    }

    /**
     * Cria um arquivo temporário com permissão restrita para o certificado
     */
    private function criarArquivoTemporario(string $conteudo): string
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'nfse_cert_');
        if ($arquivo === false) {
            throw new RuntimeException('Não foi possível criar o arquivo temporário do certificado digital.');
        }

        @chmod($arquivo, 0600);
        if (file_put_contents($arquivo, $conteudo) === false) {
            @unlink($arquivo);
            throw new RuntimeException('Não foi possível gravar o arquivo temporário do certificado digital.');
        }

        return $arquivo;
    }

    /**
     * Carrega o certificado e a chave privada usados na assinatura XML
     *
     * @return array{cert: string, pkey: string}
     */
    private function carregarCertificado(): array
    {
        if ($this->certificado !== null) {
            return $this->certificado;
        }

        $conteudo = $this->conteudoCertificado();
        if ($conteudo === null) {
            throw new RuntimeException('Certificado digital não configurado. Informe cert_path ou cert_content.');
        }

        if (str_contains($conteudo, '-----BEGIN')) {
            $this->certificado = $this->extrairPem($conteudo, (string)$this->certPassword);
            return $this->certificado;
        }

        $certs = [];
        if (!openssl_pkcs12_read($conteudo, $certs, (string)$this->certPassword)) {
            throw new RuntimeException(
                'Não foi possível ler o certificado PKCS#12: ' . $this->ultimoErroOpenSSL() .
                '. Verifique a senha e se o arquivo é um .pfx/.p12 válido. ' .
                'Certificados antigos (RC2/3DES) não são lidos pelo OpenSSL 3 e precisam ser convertidos para PEM: ' .
                'openssl pkcs12 -in cert.pfx -nodes -out cert.pem'
            );
        }

        if (empty($certs['cert']) || empty($certs['pkey'])) {
            throw new RuntimeException('O certificado PKCS#12 não contém certificado e chave privada.');
        }

        $this->certificado = ['cert' => $certs['cert'], 'pkey' => $certs['pkey']];

        return $this->certificado;
    }

    /**
     * Extrai certificado e chave privada de um conteúdo PEM
     *
     * @return array{cert: string, pkey: string}
     */
    private function extrairPem(string $pem, string $senha): array
    {
        if (!preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $pem, $certificado)) {
            throw new RuntimeException('Certificado PEM inválido: bloco CERTIFICATE não encontrado.');
        }

        if (!preg_match('/-----BEGIN (?:RSA |EC |ENCRYPTED )?PRIVATE KEY-----.*?-----END (?:RSA |EC |ENCRYPTED )?PRIVATE KEY-----/s', $pem, $chave)) {
            throw new RuntimeException('Certificado PEM inválido: chave privada não encontrada.');
        }

        $chavePrivada = $chave[0];
        if (str_contains($chavePrivada, 'ENCRYPTED')) {
            $pkey = openssl_pkey_get_private($chavePrivada, $senha);
            if ($pkey === false) {
                throw new RuntimeException('Não foi possível descriptografar a chave privada PEM: ' . $this->ultimoErroOpenSSL());
            }
            openssl_pkey_export($pkey, $chavePrivada);
        }

        return ['cert' => $certificado[0], 'pkey' => $chavePrivada];
    }

    /**
     * Retorna a mensagem da última falha do OpenSSL
     */
    private function ultimoErroOpenSSL(): string
    {
        $erros = [];
        while (($erro = openssl_error_string()) !== false) {
            $erros[] = $erro;
        }

        return $erros === [] ? 'erro desconhecido' : implode('; ', $erros);
    }

    /**
     * Monta o XML da DPS (Declaração de Prestação de Serviços)
     * 
     * @param array $dados Dados da DPS
     * @return string XML da DPS (sem assinatura)
     */
    public function montarXmlDPS(array $dados): string
    {
        $ns = self::NS_NFSE;

        $this->validarDadosDPS($dados);

        // Dados obrigatórios
        $idDps = $dados['idDps'];
        $dhEmi = $dados['dhEmi'] ?? date('Y-m-d\TH:i:sP');
        $verAplic = $dados['verAplic'] ?? self::VER_APLIC;
        $serie = $dados['serie'] ?? '1';
        $nDps = $dados['nDps'] ?? '1';
        $dCompet = $dados['dCompet'] ?? date('Y-m-d');
        $tpEmit = $dados['tpEmit'] ?? '1';
        $cLocEmi = $dados['cLocEmi'];

        // Prestador
        $prest = $dados['prestador'];

        // Tomador
        $toma = $dados['tomador'] ?? [];

        // Serviço
        $serv = $dados['servico'];

        // Valores
        $valores = $dados['valores'];

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        // Elemento raiz DPS
        $dps = $xml->createElementNS($ns, 'DPS');
        $dps->setAttribute('versao', '1.00');
        $xml->appendChild($dps);

        // infDPS
        $infDPS = $xml->createElement('infDPS');
        $infDPS->setAttribute('Id', $idDps);
        $dps->appendChild($infDPS);

        // Campos obrigatórios
        $this->addElement($xml, $infDPS, 'tpAmb', (string)$this->tpAmb);
        $this->addElement($xml, $infDPS, 'dhEmi', $dhEmi);
        $this->addElement($xml, $infDPS, 'verAplic', $verAplic);
        $this->addElement($xml, $infDPS, 'serie', $serie);
        $this->addElement($xml, $infDPS, 'nDPS', $nDps);
        $this->addElement($xml, $infDPS, 'dCompet', $dCompet);
        $this->addElement($xml, $infDPS, 'tpEmit', $tpEmit);
        $this->addElement($xml, $infDPS, 'cLocEmi', $cLocEmi);

        // Prestador
        $prestEl = $xml->createElement('prest');
        $infDPS->appendChild($prestEl);

        if (!empty($prest['cnpj'])) {
            $this->addElement($xml, $prestEl, 'CNPJ', $this->apenasNumeros($prest['cnpj']));
        }
        if (!empty($prest['cpf'])) {
            $this->addElement($xml, $prestEl, 'CPF', $this->apenasNumeros($prest['cpf']));
        }
        if (!empty($prest['im'])) {
            $this->addElement($xml, $prestEl, 'IM', $prest['im']);
        }
        if (!empty($prest['fone'])) {
            $this->addElement($xml, $prestEl, 'fone', $this->apenasNumeros($prest['fone']));
        }
        if (!empty($prest['email'])) {
            $this->addElement($xml, $prestEl, 'email', $prest['email']);
        }

        // Regime tributário do prestador
        if (!empty($prest['regTrib'])) {
            $regTrib = $xml->createElement('regTrib');
            $prestEl->appendChild($regTrib);

            if (isset($prest['regTrib']['opSimpNac'])) {
                $this->addElement($xml, $regTrib, 'opSimpNac', (string)$prest['regTrib']['opSimpNac']);
            }
            if (!empty($prest['regTrib']['regApTribSN'])) {
                $this->addElement($xml, $regTrib, 'regApTribSN', (string)$prest['regTrib']['regApTribSN']);
            }
            if (isset($prest['regTrib']['regEspTrib'])) {
                $this->addElement($xml, $regTrib, 'regEspTrib', (string)$prest['regTrib']['regEspTrib']);
            }
        }

        // Tomador
        $tomaEl = $xml->createElement('toma');
        if (!empty($toma)) {
            $infDPS->appendChild($tomaEl);
        }

        if (!empty($toma['cnpj'])) {
            $this->addElement($xml, $tomaEl, 'CNPJ', $this->apenasNumeros($toma['cnpj']));
        }
        if (!empty($toma['cpf'])) {
            $this->addElement($xml, $tomaEl, 'CPF', $this->apenasNumeros($toma['cpf']));
        }
        if (!empty($toma['xNome'])) {
            $this->addElement($xml, $tomaEl, 'xNome', $toma['xNome']);
        }

        // Endereço do tomador
        if (!empty($toma['endereco'])) {
            $endEl = $xml->createElement('end');
            $tomaEl->appendChild($endEl);

            $endNac = $xml->createElement('endNac');
            $endEl->appendChild($endNac);

            if (!empty($toma['endereco']['cMun'])) {
                $this->addElement($xml, $endNac, 'cMun', $toma['endereco']['cMun']);
            }
            if (!empty($toma['endereco']['cep'])) {
                $this->addElement($xml, $endNac, 'CEP', $this->apenasNumeros($toma['endereco']['cep']));
            }

            if (!empty($toma['endereco']['xLgr'])) {
                $this->addElement($xml, $endEl, 'xLgr', $this->removerAcentos($toma['endereco']['xLgr']));
            }
            if (!empty($toma['endereco']['nro'])) {
                $this->addElement($xml, $endEl, 'nro', $toma['endereco']['nro']);
            }
            if (!empty($toma['endereco']['xBairro'])) {
                $this->addElement($xml, $endEl, 'xBairro', $this->removerAcentos($toma['endereco']['xBairro']));
            }
        }

        if (!empty($toma['fone'])) {
            $this->addElement($xml, $tomaEl, 'fone', $this->apenasNumeros($toma['fone']));
        }
        if (!empty($toma['email'])) {
            $this->addElement($xml, $tomaEl, 'email', $toma['email']);
        }

        // Serviço
        $servEl = $xml->createElement('serv');
        $infDPS->appendChild($servEl);

        // Local de prestação
        $locPrest = $xml->createElement('locPrest');
        $servEl->appendChild($locPrest);
        $this->addElement($xml, $locPrest, 'cLocPrestacao', $serv['cLocPrestacao']);

        // Código do serviço
        $cServ = $xml->createElement('cServ');
        $servEl->appendChild($cServ);
        $this->addElement($xml, $cServ, 'cTribNac', $this->apenasNumeros($serv['cTribNac']));
        $this->addElement($xml, $cServ, 'xDescServ', $serv['xDescServ']);
        if (!empty($serv['cIntContrib'])) {
            $this->addElement($xml, $cServ, 'cIntContrib', $serv['cIntContrib']);
        }

        // Informações complementares
        if (!empty($serv['xInfComp'])) {
            $infoCompl = $xml->createElement('infoCompl');
            $servEl->appendChild($infoCompl);
            $this->addElement($xml, $infoCompl, 'xInfComp', $serv['xInfComp']);
        }

        // Valores
        $valoresEl = $xml->createElement('valores');
        $infDPS->appendChild($valoresEl);

        // Valor do serviço prestado
        $vServPrest = $xml->createElement('vServPrest');
        $valoresEl->appendChild($vServPrest);
        $this->addElement($xml, $vServPrest, 'vServ', $this->formatarValor($valores['vServ']));

        // Tributos
        // --- Tributos (Estrutura Final Validada) ---
        $trib = $xml->createElement('trib');
        $valoresEl->appendChild($trib); // trib entra em valores

        // 1. Tributos Municipais (ISSQN)
        $tribMun = $xml->createElement('tribMun');
        $trib->appendChild($tribMun);
        
        $tpRetISSQN = (string)($valores['tpRetISSQN'] ?? '1');
        
        $this->addElement($xml, $tribMun, 'tribISSQN', (string)($valores['tribISSQN'] ?? '1'));
        $this->addElement($xml, $tribMun, 'tpRetISSQN', $tpRetISSQN);
        
        // REGRA E0625: Só envia alíquota se houver retenção (tpRetISSQN != 1)
        // Ou se você não for do Simples Nacional. 
        // Para o seu teste atual, vamos apenas comentar/remover o envio da pAliq:
        /* if (isset($valores['pAliq'])) {
            $this->addElement($xml, $tribMun, 'pAliq', $this->formatarValor($valores['pAliq']));
        }
        */

        



        // 2. Tributos Federais
        $tribFed = $xml->createElement('tribFed');
        $trib->appendChild($tribFed);

        $piscofins = $xml->createElement('piscofins');
        $tribFed->appendChild($piscofins);
        $this->addElement($xml, $piscofins, 'CST', '99'); 
        $this->addElement($xml, $piscofins, 'vPis', '0.00');
        $this->addElement($xml, $piscofins, 'vCofins', '0.00');

        // REMOVA OU COMENTE ESTAS LINHAS ABAIXO:
        // O erro E0700 sugere que ao ver a tag, o governo espera um valor > 0.
        // $this->addElement($xml, $tribFed, 'vRetCP', '0.00');
        // $this->addElement($xml, $tribFed, 'vRetIRRF', '0.00');
        // $this->addElement($xml, $tribFed, 'vRetCSLL', '0.00');





        // 3. Totais dos Tributos (Solução para Simples Nacional / ME-EPP)
        $totTrib = $xml->createElement('totTrib');
        $trib->appendChild($totTrib);

        // O Schema exige um filho. O Negócio proibiu o 'indTotTrib'.
        // Usaremos o 'pTotTribSN' que é o campo específico para o seu regime.
        $this->addElement($xml, $totTrib, 'pTotTribSN', $this->formatarValor($valores['pTotTribSN'] ?? 0));

        $xmlDps = $xml->saveXML();
        if ($xmlDps === false) {
            throw new RuntimeException('Não foi possível gerar o XML da DPS.');
        }

        return $xmlDps;
    }

    /**
     * Valida os campos obrigatórios da DPS antes de montar o XML
     */
    private function validarDadosDPS(array $dados): void
    {
        foreach (['idDps', 'cLocEmi', 'prestador', 'servico', 'valores'] as $campo) {
            if (empty($dados[$campo])) {
                throw new InvalidArgumentException("Campo obrigatório ausente na DPS: {$campo}");
            }
        }

        if (!preg_match('/^DPS\d{42}$/', (string)$dados['idDps'])) {
            throw new InvalidArgumentException('IdDPS inválido. Use gerarIdDPS() para montar o valor no padrão DPS + 42 dígitos.');
        }

        if (empty($dados['prestador']['cnpj']) && empty($dados['prestador']['cpf'])) {
            throw new InvalidArgumentException('Informe o CNPJ ou CPF do prestador.');
        }

        foreach (['cLocPrestacao', 'cTribNac', 'xDescServ'] as $campo) {
            if (empty($dados['servico'][$campo])) {
                throw new InvalidArgumentException("Campo obrigatório ausente no serviço: {$campo}");
            }
        }

        if (!isset($dados['valores']['vServ']) || $dados['valores']['vServ'] === '') {
            throw new InvalidArgumentException('Campo obrigatório ausente nos valores: vServ');
        }
    }

    // =========================================================================
    // MÉTODOS PRINCIPAIS DA API
    // =========================================================================

    /**
     * Envia uma DPS (Declaração de Prestação de Serviços) para gerar NFS-e
     * POST /nfse
     * 
     * @param string $xmlDps XML da DPS assinado
     * @return array Resultado da operação
     */
    public function enviarDPS(string $xmlDps): array
    {
        try {
            // Adiciona declaração XML se não existir
            if (strpos($xmlDps, '<?xml') === false) {
                $xmlDps = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $xmlDps;
            }

            // Compacta e codifica em Base64
            $dpsXmlGZipB64 = $this->toGzipBase64($xmlDps);

            // Monta payload JSON
            $payload = json_encode(['dpsXmlGZipB64' => $dpsXmlGZipB64]);

            // Faz requisição POST
            $response = $this->client->post('/SefinNacional/nfse', [
                'body' => $payload
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $retorno = json_decode($body, true);

            if ($statusCode === 201) {
                // Sucesso - decodifica XML da NFS-e
                $xmlNfse = null;
                if (!empty($retorno['nfseXmlGZipB64'])) {
                    $xmlNfse = $this->fromGzipBase64($retorno['nfseXmlGZipB64']);
                }

                return [
                    'codigo' => $statusCode,
                    'mensagem' => 'DPS registrada com sucesso.',
                    'tipoAmbiente' => $retorno['tipoAmbiente'] ?? null,
                    'dataHoraProcessamento' => $retorno['dataHoraProcessamento'] ?? null,
                    'idDps' => $retorno['idDps'] ?? null,
                    'chaveAcesso' => $retorno['chaveAcesso'] ?? null,
                    'xmlNfse' => $xmlNfse,
                    'ndfse' => $xmlNfse ? $this->extrairNDFSe($xmlNfse) : null,
                    'alertas' => $retorno['alertas'] ?? [],
                    'bodyOriginal' => $body,
                    'retorno' => $retorno
                ];
            }

            return [
                'codigo' => (string)$statusCode,
                'mensagem' => 'Erro ao enviar DPS.',
                'bodyOriginal' => $body
            ];

        } catch (RequestException $e) {
            return $this->tratarExcecaoRequest($e, 'Erro ao enviar DPS');
        } catch (Exception $e) {
            return [
                'codigo' => '999',
                'mensagem' => 'Erro ao enviar DPS: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Gera o IdDPS conforme padrão nacional
     * Formato: DPS + cMun(7) + tpInsc(1) + CNPJ/CPF(14) + serie(5) + nDPS(15)
     * 
     * @param string $cMun Código IBGE do município (7 dígitos)
     * @param string $cnpjCpf CNPJ ou CPF do prestador
     * @param int $serie Série da DPS
     * @param int $nDps Número da DPS
     * @return string IdDPS com 45 caracteres
     */
    public function gerarIdDPS(string $cMun, string $cnpjCpf, int $serie, int $nDps): string
    {
        $cMun = $this->apenasNumeros($cMun);
        if (strlen($cMun) !== 7) {
            throw new InvalidArgumentException('cMun deve conter 7 dígitos (código IBGE do município).');
        }

        $cnpjCpf = $this->apenasNumeros($cnpjCpf);
        if (!in_array(strlen($cnpjCpf), [11, 14], true)) {
            throw new InvalidArgumentException('CNPJ/CPF do prestador deve conter 11 ou 14 dígitos.');
        }

        if ($serie < 0 || $serie > 99999) {
            throw new InvalidArgumentException('A série da DPS deve estar entre 0 e 99999.');
        }

        if ($nDps < 1 || $nDps > 999999999999999) {
            throw new InvalidArgumentException('O número da DPS deve estar entre 1 e 999999999999999.');
        }

        $tpInsc = strlen($cnpjCpf) === 14 ? '2' : '1'; // 1=CPF, 2=CNPJ (regra E0004)

        // Padroniza CNPJ/CPF para 14 dígitos
        $cnpjCpf = str_pad($cnpjCpf, 14, '0', STR_PAD_LEFT);

        // Padroniza série para 5 dígitos
        $serieStr = str_pad((string)$serie, 5, '0', STR_PAD_LEFT);

        // Padroniza número para 15 dígitos
        $nDpsStr = str_pad((string)$nDps, 15, '0', STR_PAD_LEFT);

        return "DPS{$cMun}{$tpInsc}{$cnpjCpf}{$serieStr}{$nDpsStr}";
    }

    /**
     * Realiza a assinatura digital XML-DSIG no XML da DPS
     * * @param string $xml Elemento XML limpo
     * @return string XML assinado
     */
    public function assinarXML(string $xml): string
    {
        return $this->assinarXmlTag($xml, 'infDPS');
    }

    /**
     * Assina digitalmente (XML-DSIG) o nó indicado do XML
     *
     * @param string $xml XML a assinar
     * @param string $rootTag Nome da tag que receberá a assinatura (infDPS, infPedReg, ...)
     * @return string XML assinado
     */
    private function assinarXmlTag(string $xml, string $rootTag): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if (!@$dom->loadXML($xml)) {
            throw new RuntimeException('XML inválido para assinatura.');
        }

        // Identifica o nó que será assinado (infDPS, infPedReg, ...)
        $node = $dom->getElementsByTagName($rootTag)->item(0);
        if (!$node instanceof \DOMElement) {
            throw new RuntimeException("Tag {$rootTag} não encontrada no XML para assinatura.");
        }

        $id = $node->getAttribute('Id');
        if ($id === '') {
            throw new RuntimeException("Atributo Id ausente na tag {$rootTag}.");
        }

        // Extrai a chave privada do certificado (PFX ou PEM)
        $certificado = $this->carregarCertificado();
        $privateKey = $certificado['pkey'];
        $publicCert = $certificado['cert'];

        // Remove assinaturas anteriores para permitir reassinatura do mesmo XML
        foreach (iterator_to_array($dom->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature')) as $assinaturaAntiga) {
            $assinaturaAntiga->parentNode?->removeChild($assinaturaAntiga);
        }

        // 1. Limpa o certificado (remove headers/footers) para o nó X509Certificate
        $cleanCert = preg_replace('/-----[^-]+-----|\s+/', '', $publicCert);

        // 2. Canonicalização do nó assinado (C14N)
        $canonNode = $node->C14N(false, false);
        if ($canonNode === false) {
            throw new RuntimeException('Não foi possível canonicalizar o XML para assinatura.');
        }
        $digestValue = base64_encode(sha1($canonNode, true));

        // 3. Monta o nó Signature
        $signature = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'Signature');
        $dom->documentElement->appendChild($signature);

        $signedInfo = $dom->createElement('SignedInfo');
        $signature->appendChild($signedInfo);

        // Métodos de Canonicalização e Assinatura
        $nav = $dom->createElement('CanonicalizationMethod');
        $nav->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($nav);

        $sm = $dom->createElement('SignatureMethod');
        $sm->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
        $signedInfo->appendChild($sm);

        // Referência ao ID do nó assinado
        $reference = $dom->createElement('Reference');
        $reference->setAttribute('URI', "#$id");
        $signedInfo->appendChild($reference);

        $transforms = $dom->createElement('Transforms');
        $reference->appendChild($transforms);

        $t1 = $dom->createElement('Transform');
        $t1->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($t1);

        $t2 = $dom->createElement('Transform');
        $t2->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $transforms->appendChild($t2);

        $dm = $dom->createElement('DigestMethod');
        $dm->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $reference->appendChild($dm);

        $dv = $dom->createElement('DigestValue', $digestValue);
        $reference->appendChild($dv);

        // 4. Gera a SignatureValue (Hash do SignedInfo assinado com Private Key)
        $canonSignedInfo = $signedInfo->C14N(false, false);
        if ($canonSignedInfo === false) {
            throw new RuntimeException('Não foi possível canonicalizar o SignedInfo para assinatura.');
        }

        if (!openssl_sign($canonSignedInfo, $signatureValue, $privateKey, OPENSSL_ALGO_SHA1)) {
            throw new RuntimeException('Falha ao assinar o XML: ' . $this->ultimoErroOpenSSL());
        }
        $signatureValueB64 = base64_encode($signatureValue);

        $sv = $dom->createElement('SignatureValue', $signatureValueB64);
        $signature->appendChild($sv);

        // 5. Adiciona os dados do Certificado (KeyInfo)
        $keyInfo = $dom->createElement('KeyInfo');
        $signature->appendChild($keyInfo);
        $x509Data = $dom->createElement('X509Data');
        $keyInfo->appendChild($x509Data);
        $x509Cert = $dom->createElement('X509Certificate', (string)$cleanCert);
        $x509Data->appendChild($x509Cert);

        $xmlAssinado = $dom->saveXML();
        if ($xmlAssinado === false) {
            throw new RuntimeException('Não foi possível gerar o XML assinado.');
        }

        return $xmlAssinado;
    }
    
    /**
     * Consulta os dados detalhados de uma NFSe pela Chave de Acesso
     * GET /SefinNacional/nfse/{chaveAcesso}
     */
    public function consultarNFSePorChave(string $chaveAcesso): array
    {
        try {

            $chaveAcesso = trim($chaveAcesso);

            // A consulta da NFS-e é feita no SEFIN Nacional (mesma base do envio da DPS)
            $response = $this->client->get("/SefinNacional/nfse/{$chaveAcesso}", [
                'headers' => [
                    'Accept' => 'application/json'
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new Exception("Falha na consulta da NFS-e");
            }

            $body = $response->getBody()->getContents();

            return [
                'codigo' => '000',
                'mensagem' => 'Consulta realizada com sucesso',
                'dados' => json_decode($body, true)
            ];

        } catch (RequestException $e) {
            return $this->tratarExcecaoRequest($e, 'Erro ao consultar NFS-e');
        } catch (Exception $e) {
            return [
                'codigo' => '999',
                'mensagem' => 'Erro ao consultar NFS-e: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Download do PDF da NFS-e (DANFSE)
     * GET /danfse/{chaveAcesso}
     * 
     * @param string $chaveAcesso Chave de acesso da NFS-e
     * @return array Resultado com bytes do PDF
     */
    public function downloadDANFSe(string $chaveAcesso): array
    {
        try {
            $chaveAcesso = trim($chaveAcesso);
            $response = $this->client->get("{$this->urlDanfse}/{$chaveAcesso}", [
                'headers' => [
                    'Accept' => 'application/pdf'
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new Exception("Falha ao baixar DANFSe. Status: " . $response->getStatusCode());
            }

            $pdfContent = $response->getBody()->getContents();

            return [
                'codigo' => '000',
                'mensagem' => 'PDF baixado com sucesso.',
                'pdfContent' => $pdfContent,
                'contentType' => $response->getHeaderLine('Content-Type')
            ];

        } catch (RequestException $e) {
            return $this->tratarExcecaoRequest($e, 'Erro ao baixar DANFSE');
        } catch (Exception $e) {
            return [
                'codigo' => '999',
                'mensagem' => 'Erro ao baixar DANFSE: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancela uma NFS-e
     * POST /nfse/{chaveAcesso}/eventos
     * 
     * @param string $chaveAcesso Chave de acesso da NFS-e
     * @param string $cnpjAutor CNPJ do autor do cancelamento
     * @param int $cMotivo Código do motivo (1=Erro na emissão, 2=Serviço não prestado, 3=Outros)
     * @param string $xMotivo Descrição do motivo
     * @param string $xmlEventoAssinado XML do pedido de registro de evento assinado
     * @return array Resultado do cancelamento
     */
    /**
     * Cancela uma NFS-e enviando o evento assinado e compactado
     */
    public function cancelarNFSe(string $chaveAcesso, string $cnpjAutor, int $cMotivo, string $xMotivo): array
    {
        try {
            // 1. Gera o XML limpo
            $xmlLimpo = $this->montarXmlCancelamento($chaveAcesso, $cnpjAutor, $cMotivo, $xMotivo);
            
            // 2. Assina o XML (passando a tag correta infPedReg)
            $xmlAssinado = $this->assinarXMLCan($xmlLimpo, 'infPedReg');
            
            // 3. Compacta e Codifica (O segredo do sucesso)
            $gz = gzencode($xmlAssinado);
            $peloGzipB64 = base64_encode($gz);
            
            $dados = [
                'pedidoRegistroEventoXmlGZipB64' => $peloGzipB64
            ];

            // 4. Envia para o governo
            // O endpoint de cancelamento geralmente é POST /nfse/{chave}/eventos
            $response = $this->client->post("/SefinNacional/nfse/{$chaveAcesso}/eventos", [
                'json' => $dados
            ]);

            $body = $response->getBody()->getContents();
            return [
                'codigo' => '000',
                'mensagem' => 'Requisição de cancelamento enviada.',
                'resposta' => json_decode($body, true)
            ];

        } catch (RequestException $e) {
            return $this->tratarExcecaoRequest($e, 'Erro ao cancelar NFS-e');
        } catch (Exception $e) {
            return ['codigo' => '999', 'mensagem' => $e->getMessage()];
        }
    }

    /**
     * Monta o XML do Pedido de Registro de Evento (cancelamento)
     * 
     * @param string $chaveAcesso Chave de acesso da NFS-e
     * @param string $cnpjAutor CNPJ do autor
     * @param int $cMotivo Código do motivo (1, 2 ou 3)
     * @param string $xMotivo Descrição do motivo
     * @return string XML do pedido de evento (sem assinatura)
     */
    public function montarXmlCancelamento(string $chaveAcesso, string $cnpjAutor, int $cMotivo, string $xMotivo): string
    {
        $ns = self::NS_NFSE;
        $codEvento = '101101';

        $idInfPed = "PRE{$chaveAcesso}{$codEvento}";
        $dhEvento = date('Y-m-d\TH:i:sP');

        $xDesc = 'Cancelamento de NFS-e';
        switch ($cMotivo) {
            case 1:
                $motivoDescricao = 'Erro na emissao';
                break;
            case 2:
                $motivoDescricao = 'Servico nao prestado';
                break;
            default:
                $motivoDescricao = 'Outros';
        }

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $pedRegEvento = $xml->createElementNS($ns, 'pedRegEvento');
        $pedRegEvento->setAttribute('versao', '1.00');
        $xml->appendChild($pedRegEvento);

        $infPedReg = $xml->createElement('infPedReg');
        $infPedReg->setAttribute('Id', $idInfPed);
        $pedRegEvento->appendChild($infPedReg);

        $this->addElement($xml, $infPedReg, 'tpAmb', (string)$this->tpAmb);
        $this->addElement($xml, $infPedReg, 'verAplic', self::VER_APLIC);
        $this->addElement($xml, $infPedReg, 'dhEvento', $dhEvento);
        $this->addElement($xml, $infPedReg, 'CNPJAutor', $this->apenasNumeros($cnpjAutor));
        $this->addElement($xml, $infPedReg, 'chNFSe', $chaveAcesso);

        $e101101 = $xml->createElement('e101101');
        $infPedReg->appendChild($e101101);

        $this->addElement($xml, $e101101, 'xDesc', $xDesc);
        $this->addElement($xml, $e101101, 'cMotivo', (string)$cMotivo);
        $this->addElement($xml, $e101101, 'xMotivo', $xMotivo ?: $motivoDescricao);

        $xmlCancelamento = $xml->saveXML();
        if ($xmlCancelamento === false) {
            throw new RuntimeException('Não foi possível gerar o XML do cancelamento.');
        }

        return $xmlCancelamento;
    }

    public function assinarXMLCan(string $xml, string $rootTag = 'infPedReg'): string
    {
        return $this->assinarXmlTag($xml, $rootTag);
    }

    /**
     * Distribuição de DF-e para o Contribuinte (Baixar notas por NSU)
     * * @param int $nsu Número Sequencial Único para início da consulta
     * @param string|null $cnpjConsulta CNPJ a ser consultado (opcional se for o do certificado)
     * @param bool $lote Define se deve retornar um lote de documentos ou apenas um
     */
    public function baixarDfeContribuinte(int $nsu = 0, ?string $cnpjConsulta = null, bool $lote = true): array
    {
        try {
            $queryParams = [];
            if ($cnpjConsulta) {
                $queryParams['cnpjConsulta'] = preg_replace('/\D/', '', $cnpjConsulta);
            }
            $queryParams['lote'] = $lote ? 'true' : 'false';

            // No padrão Nacional, esse serviço costuma rodar na URL do ADN (mesma do DANFSE)
            // Mas o caminho base depende da implementação da prefeitura/nacional.
            // Vamos usar a urlParametrizacao ou urlDanfse como base de host se necessário
            $baseUrlADN = str_replace('/danfse', '', $this->urlDanfse);
            $url = "{$baseUrlADN}/contribuintes/DFe/{$nsu}";

            $response = $this->client->get($url, [
                'query' => $queryParams
            ]);

            $body = $response->getBody()->getContents();
            
            return [
                'codigo' => '000',
                'mensagem' => 'Consulta de DF-e realizada.',
                'dados' => json_decode($body, true),
                'bodyOriginal' => $body
            ];

        } catch (RequestException $e) {
            return $this->tratarExcecaoRequest($e, 'Erro ao baixar DFe');
        } catch (Exception $e) {
            return [
                'codigo' => '999',
                'mensagem' => 'Erro: ' . $e->getMessage()
            ];
        }
    }

    // =========================================================================
    // FUNÇÕES AUXILIARES
    // =========================================================================

    /**
     * Compacta string com GZip e codifica em Base64
     */
    private function toGzipBase64(string $data): string
    {
        $compressed = gzencode($data, 9);
        if ($compressed === false) {
            throw new RuntimeException('Não foi possível compactar o XML.');
        }
        return base64_encode($compressed);
    }

    /**
     * Decodifica Base64 e descompacta GZip
     */
    private function fromGzipBase64(string $data): string
    {
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            throw new RuntimeException('Conteúdo Base64 inválido na resposta da API.');
        }

        $xml = gzdecode($decoded);
        if ($xml === false) {
            throw new RuntimeException('Não foi possível descompactar o XML da resposta da API.');
        }

        return $xml;
    }

    /**
     * Remove acentos de uma string
     */
    private function removerAcentos(string $string): string
    {
        $acentos = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
            'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N',
        ];

        return strtr($string, $acentos);
    }

    /**
     * Extrai o número da NFS-e (nDFSe) do XML
     */
    private function extrairNDFSe(string $xmlNfse): ?string
    {
        try {
            $dom = new DOMDocument();
            $dom->loadXML($xmlNfse);

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('nfse', self::NS_NFSE);

            $nodes = $xpath->query('//nfse:nDFSe');
            if ($nodes->length > 0) {
                return $nodes->item(0)->nodeValue;
            }

            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Trata exceções de requisição HTTP
     */
    private function tratarExcecaoRequest(RequestException $e, string $prefixo): array
    {
        $httpStatus = 0;
        $bodyErro = '';
        $codErro = '';
        $msgErro = $e->getMessage();

        if ($e->hasResponse()) {
            $response = $e->getResponse();
            $httpStatus = $response->getStatusCode();
            $bodyErro = $response->getBody()->getContents();

            // Tenta extrair erro do JSON
            $retornoErro = json_decode($bodyErro, true);
            if ($retornoErro) {
                if (!empty($retornoErro['erro'])) {
                    $codErro = $retornoErro['erro']['codigo'] ?? '';
                    $msgErro = $retornoErro['erro']['descricao'] ?? $msgErro;
                } elseif (!empty($retornoErro['erros']) && is_array($retornoErro['erros'])) {
                    $primeiroErro = $retornoErro['erros'][0] ?? [];
                    $codErro = $primeiroErro['Codigo'] ?? $primeiroErro['codigo'] ?? '';
                    $msgErro = $primeiroErro['Descricao'] ?? $primeiroErro['descricao'] ?? $msgErro;
                    // if (!empty($primeiroErro['Complemento'] ?? $primeiroErro['complemento'])) {
                    //     $msgErro .= ' - ' . ($primeiroErro['Complemento'] ?? $primeiroErro['complemento']);
                    // }
                }
            }
        }

        return [
            'codigo' => $httpStatus > 0 ? (string)$httpStatus : '999',
            'mensagem' => "{$prefixo}: " . ($codErro ? "{$codErro} - " : '') . $msgErro,
            'codigoErro' => $codErro,
            'bodyOriginal' => $bodyErro
        ];
    }

    /**
     * Remove caracteres não numéricos
     */
    private function apenasNumeros(string $valor): string
    {
        return preg_replace('/[^0-9]/', '', $valor) ?? '';
    }

    /**
     * Adiciona elemento ao XML
     */
    private function addElement(DOMDocument $dom, \DOMElement $parent, string $name, string $value): void
    {
        if ($value !== '') {
            // createTextNode já escapa o conteúdo; htmlspecialchars aqui geraria escape duplo (&amp;amp;)
            $element = $dom->createElement($name);
            $element->appendChild($dom->createTextNode($value));
            $parent->appendChild($element);
        }
    }

    /**
     * Formata valor decimal para 2 casas
     */
    private function formatarValor($valor): string
    {
        return number_format((float)$valor, 2, '.', '');
    }

    // =========================================================================
    // MÉTODOS ADICIONAIS
    // =========================================================================

    /**
     * Consulta uma DPS pelo IdDPS
     * GET /dps/{idDps}
     * 
     * @param string $idDps ID da DPS (42 dígitos numéricos)
     * @return array Resultado da consulta
     */
    public function consultarDPSExcluir(string $idDps): array
    {
        try {
            // Normaliza IdDPS (remove prefixos)
            $idDps = str_replace(['DPS', 'NFS'], '', $idDps);
            $idDps = trim($idDps);

            if (strlen($idDps) !== 42 || !ctype_digit($idDps)) {
                return [
                    'codigo' => '400',
                    'mensagem' => 'IdDPS inválido. Deve conter 42 números.'
                ];
            }

            $response = $this->client->get("/SefinNacional/dps/{$idDps}");
            $body = $response->getBody()->getContents();
            $retorno = json_decode($body, true);

            return [
                'codigo' => '000',
                'mensagem' => 'DPS encontrada.',
                'tipoAmbiente' => $retorno['tipoAmbiente'] ?? null,
                'versaoAplicativo' => $retorno['versaoAplicativo'] ?? null,
                'dataHoraProcessamento' => $retorno['dataHoraProcessamento'] ?? null,
                'chaveAcesso' => $retorno['chaveAcesso'] ?? null,
                'bodyOriginal' => $body
            ];

        } catch (RequestException $e) {
            return $this->tratarExcecaoRequest($e, 'Erro ao consultar DPS');
        } catch (Exception $e) {
            return [
                'codigo' => '999',
                'mensagem' => 'Erro ao consultar DPS: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Consulta eventos de uma NFS-e
     * GET /nfse/{chaveAcesso}/eventos/{tipoEvento}/{numSeqEvento}
     * 
     * @param string $chaveAcesso Chave de acesso da NFS-e
     * @param int $tipoEvento Tipo do evento (padrão 101101 para cancelamento)
     * @param int $numSeqEvento Número sequencial do evento
     * @return array Resultado da consulta
     */
    public function consultarEventoNFSe(string $chaveAcesso, int $tipoEvento = 101101, int $numSeqEvento = 1): array
    {
        try {
            $chaveAcesso = trim($chaveAcesso);

            $response = $this->client->get("/SefinNacional/nfse/{$chaveAcesso}/eventos/{$tipoEvento}/{$numSeqEvento}");
            $body = $response->getBody()->getContents();
            $retorno = json_decode($body, true);

            $eventos = [];
            if (!empty($retorno['eventos'])) {
                foreach ($retorno['eventos'] as $evento) {
                    $xmlEvento = null;
                    if (!empty($evento['arquivoXml'])) {
                        $xmlEvento = $this->decodificarArquivoXml($evento['arquivoXml']);
                    }
                    $eventos[] = [
                        'chaveAcesso' => $evento['chaveAcesso'] ?? null,
                        'tipoEvento' => $evento['tipoEvento'] ?? null,
                        'numeroPedidoRegistroEvento' => $evento['numeroPedidoRegistroEvento'] ?? null,
                        'dataHoraRecebimento' => $evento['dataHoraRecebimento'] ?? null,
                        'xmlEvento' => $xmlEvento
                    ];
                }
            }

            return [
                'codigo' => '000',
                'mensagem' => 'Eventos encontrados.',
                'eventos' => $eventos,
                'bodyOriginal' => $body
            ];

        } catch (RequestException $e) {
            return $this->tratarExcecaoRequest($e, 'Erro ao consultar evento NFS-e');
        } catch (Exception $e) {
            return [
                'codigo' => '999',
                'mensagem' => 'Erro ao consultar evento NFS-e: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Envia NFS-e com decisão judicial
     * POST /decisao-judicial/nfse
     * 
     * @param string $xmlNfse XML da NFS-e assinado
     * @return array Resultado da operação
     */
    public function enviarNFSeDecisaoJudicial(string $xmlNfse): array
    {
        try {
            if (strpos($xmlNfse, '<?xml') === false) {
                $xmlNfse = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $xmlNfse;
            }

            $nfseXmlGZipB64 = $this->toGzipBase64($xmlNfse);
            $payload = json_encode(['nfseXmlGZipB64' => $nfseXmlGZipB64]);

            $response = $this->client->post('/SefinNacional/decisao-judicial/nfse', [
                'body' => $payload
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $retorno = json_decode($body, true);

            if ($statusCode === 201) {
                return [
                    'codigo' => '000',
                    'mensagem' => 'NFS-e com decisão judicial registrada com sucesso.',
                    'chaveAcesso' => $retorno['chaveAcesso'] ?? null,
                    'dataHoraProcessamento' => $retorno['dataHoraProcessamento'] ?? null,
                    'bodyOriginal' => $body
                ];
            }

            return [
                'codigo' => (string)$statusCode,
                'mensagem' => 'Erro ao enviar NFS-e decisão judicial.',
                'bodyOriginal' => $body
            ];

        } catch (RequestException $e) {
            return $this->tratarExcecaoRequest($e, 'Erro ao enviar NFS-e decisão judicial');
        } catch (Exception $e) {
            return [
                'codigo' => '999',
                'mensagem' => 'Erro ao enviar NFS-e decisão judicial: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Retorna o tipo de ambiente atual
     * 
     * @return int Tipo de ambiente (1=Produção, 2=Homologação)
     */
    public function getTipoAmbiente(): int
    {
        return $this->tpAmb;
    }

    /**
     * Retorna a URL base atual
     * 
     * @return string URL base
     */
    public function getUrlBase(): string
    {
        return $this->urlBase;
    }

    /**
     * Decodifica a chave de acesso da NFS-e
     * 
     * @param string $chave Chave de acesso (50 dígitos)
     * @return array Dados decodificados da chave
     */
    public function decodificarChaveNFSe(string $chave): array
    {
        $chave = preg_replace('/\D/', '', $chave);

        if (strlen($chave) !== 50) {
            throw new \Exception("Chave de acesso inválida para este padrão. Esperado: 50 caracteres.");
        }

        return [
            'uf'              => substr($chave, 0, 2),        // 52
            'codigoMunicipio' => substr($chave, 0, 7),        // 5208707
            'cnpjPrestador'   => substr($chave, 9, 14),       // 35485290000195
            'serie'           => substr($chave, 23, 3),       // 000
            'numeroNota'      => substr($chave, 26, 10),      // 0000000001
            'codigoNumerico'  => substr($chave, 36, 13),      // 2603506738219
            'dv'              => substr($chave, 49, 1)        // 3
        ];
    }

    /**
     * Decodifica arquivo XML da resposta da API
     * 
     * @param string $arquivoXml XML codificado em base64
     * @return string XML decodificado
     */
    private function decodificarArquivoXml(string $arquivoXml): ?string
    {
        try {
            $decoded = base64_decode($arquivoXml);
            
            // Verifica se é GZip (magic bytes 1F 8B)
            if (strlen($decoded) >= 2 && ord($decoded[0]) === 0x1F && ord($decoded[1]) === 0x8B) {
                $decoded = gzdecode($decoded);
            }

            return $decoded ?: $arquivoXml;
        } catch (Exception $e) {
            return null;
        }
    }
}