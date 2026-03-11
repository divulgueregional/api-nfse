<?php

namespace Divulgueregional\ApiNfse;

use DOMDocument;
use DOMXPath;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Classe para comunicaÃƒÆ’Ã‚Â§ÃƒÆ’Ã‚Â£o com a API do Sistema Nacional NFS-e
 * Baseada na documentaÃƒÆ’Ã‚Â§ÃƒÆ’Ã‚Â£o tÃƒÆ’Ã‚Â©cnica do SEFIN Nacional
 */
class NFSeNacional
{
    // URLs de ProduÃƒÆ’Ã‚Â§ÃƒÆ’Ã‚Â£o
    private const URL_PRODUCAO = 'https://sefin.nfse.gov.br';
    private const URL_PRODUCAO_DANFSE = 'https://adn.nfse.gov.br/danfse';
    private const URL_PRODUCAO_CONSULTA_PUBLICA = 'https://www.nfse.gov.br/ConsultaPublica';
    private const URL_PRODUCAO_PARAMETRIZACAO = 'https://adn.nfse.gov.br/parametrizacao';

    // URLs de HomologaÃƒÆ’Ã‚Â§ÃƒÆ’Ã‚Â£o
    private const URL_HOMOLOGACAO = 'https://sefin.producaorestrita.nfse.gov.br';
    private const URL_HOMOLOGACAO_DANFSE = 'https://adn.producaorestrita.nfse.gov.br/danfse';
    private const URL_HOMOLOGACAO_CONSULTA_PUBLICA = 'https://adn.producaorestrita.nfse.gov.br/consultapublica';
    private const URL_HOMOLOGACAO_PARAMETRIZACAO = 'https://adn.producaorestrita.nfse.gov.br/parametrizacao';

    // Namespace da NFS-e
    private const NS_NFSE = 'http://www.sped.fazenda.gov.br/nfse';

    // VersÃƒÆ’Ã‚Â£o do aplicativo
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

    /**
     * Construtor da classe
     * 
     * @param array $config ConfiguraÃƒÆ’Ã‚Â§ÃƒÆ’Ã‚Âµes do certificado digital
     *   - cert_path: Caminho do arquivo .pfx/.p12
     *   - cert_password: Senha do certificado
     *   - cert_content: ConteÃƒÆ’Ã‚Âºdo do certificado em base64 (alternativa ao cert_path)
     * @param int $tpAmb Tipo de ambiente (1=ProduÃƒÆ’Ã‚Â§ÃƒÆ’Ã‚Â£o, 2=HomologaÃƒÆ’Ã‚Â§ÃƒÆ’Ã‚Â£o)
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

        if ($this->certPath && $this->certPassword) {
            // O cURL nÃ£o lÃª PFX direto. Vamos extrair para um formato que ele aceite (PEM)
            $pfxContent = file_get_contents($this->certPath);
            if (openssl_pkcs12_read($pfxContent, $certs, $this->certPassword)) {
                // Criamos um arquivo temporÃ¡rio que contÃ©m o Certificado + Chave Privada
                $tempPem = tempnam(sys_get_temp_dir(), 'cert_');
                $pemData = $certs['cert'] . "\n" . $certs['pkey'];
                file_put_contents($tempPem, $pemData);
                
                // Passamos o caminho do arquivo temporÃ¡rio para o Guzzle
                $options['cert'] = [$tempPem, $this->certPassword];
                
                // Opcional: registrar para deletar o arquivo ao fim da execuÃ§Ã£o
                register_shutdown_function(function() use ($tempPem) {
                    if (file_exists($tempPem)) @unlink($tempPem);
                });
            }
        }

        $this->client = new Client($options);
    }

    // =========================================================================
    // MÃƒÆ’Ã¢â‚¬Â°TODOS PRINCIPAIS DA API
    // =========================================================================

    /**
     * Envia uma DPS (DeclaraÃƒÆ’Ã‚Â§ÃƒÆ’Ã‚Â£o de PrestaÃƒÆ’Ã‚Â§ÃƒÆ’Ã‚Â£o de ServiÃƒÆ’Ã‚Â§os) para gerar NFS-e
     * POST /nfse
     * 
     * @param string $xmlDps XML da DPS assinado
     * @return array Resultado da operaÃƒÆ’Ã‚Â§ÃƒÆ’Ã‚Â£o
     */
    public function enviarDPS(string $xmlDps): array
    {
        try {
            // Adiciona declaraÃƒÆ’Ã‚Â§ÃƒÆ’Ã‚Â£o XML se nÃƒÆ’Ã‚Â£o existir
            if (strpos($xmlDps, '<?xml') === false) {
                $xmlDps = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $xmlDps;
            }

            // Compacta e codifica em Base64
            $dpsXmlGZipB64 = $this->toGzipBase64($xmlDps);

            // Monta payload JSON
            $payload = json_encode(['dpsXmlGZipB64' => $dpsXmlGZipB64]);

            // Faz requisiÃƒÆ’Ã‚Â§ÃƒÆ’Ã‚Â£o POST
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


    // =========================================================================
    // FUNÇÕES AUXILIARES
    // =========================================================================

    /**
     * Compacta string com GZip e codifica em Base64
     */
    private function toGzipBase64(string $data): string
    {
        $compressed = gzencode($data, 9);
        return base64_encode($compressed);
    }

    /**
     * Decodifica Base64 e descompacta GZip
     */
    private function fromGzipBase64(string $data): string
    {
        $decoded = base64_decode($data);
        return gzdecode($decoded);
    }

    /**
     * Extrai o NÚMERO da NFS-e (nDFSe) do XML
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
     * Trata exceÇÕES de requisiÇÃO HTTP
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











}