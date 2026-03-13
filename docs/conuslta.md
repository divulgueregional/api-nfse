# OBTER XML NSF-e Nacional

## PDF
Busca a consulta da nota de serviço no Ambiente Nacional.

```php
    ob_start(); 
    error_reporting(E_ALL); 
    ini_set('display_errors', 1);

    require '../../../vendor/autoload.php';
    use Divulgueregional\ApiNfse\NFSeNacional;

    // 1. Configurações e Certificado
    $config = [
        'cert_path' => __DIR__ . '/../certs/cert_empresa.pfx',
        'cert_password' => '123456'
    ];

    $tpAmb = 2; // 2= Homologação, 1= produção
    $nfse = new NFSeNacional($config, $tpAmb);
    $chaveAcesso = '';
    $resultado = $nfse->consultarNFSePorChave($chaveAcesso);

    echo "<h1>Resultado da Consulta</h1>";
    echo "<pre>";
    print_r($resultado);
    echo "</pre>";

    if ($resultado['codigo'] === '000') {
        $dados = $resultado['dados'];
        
        echo "<h2>Resultado da Consulta</h2>";
        echo "<strong>Ambiente:</strong> " . $dados['TipoAmbiente'] . "<br>";
        
        // Separando Data e Hora do processamento
        $dataHoraProc = explode('T', $dados['DataHoraProcessamento']);
        echo "<strong>Data Processamento:</strong> " . date('d/m/Y', strtotime($dataHoraProc[0])) . "<br>";
        echo "<strong>Hora Processamento:</strong> " . substr($dataHoraProc[1], 0, 8) . "<br>";
        echo "<hr>";

        if (!empty($dados['LoteDFe'])) {
            foreach ($dados['LoteDFe'] as $item) {
                echo "<h3>Dados do Documento</h3>";
                echo "<strong>NSU:</strong> " . $item['NSU'] . "<br>";
                echo "<strong>Chave de Acesso:</strong> " . $item['ChaveAcesso'] . "<br>";
                echo "<strong>Tipo:</strong> " . $item['TipoDocumento'] . "<br>";
                
                // Data/Hora Geração
                $dataHoraGen = explode('T', $item['DataHoraGeracao']);
                echo "<strong>Gerado em:</strong> " . date('d/m/Y', strtotime($dataHoraGen[0])) . " às " . substr($dataHoraGen[1], 0, 8) . "<br>";

                // 1. Mostrar Compactado (Como vem da API, ideal para salvar no Banco de Dados)
                $xmlCompactado = $item['ArquivoXml'];
                echo "<h4>1. XML Compactado (Base64):</h4>";
                echo "<textarea style='width:100%; height:100px; background:#f4f4f4;' readonly>" . $xmlCompactado . "</textarea>";

                // 2. Descompactar o XML (Gzip -> String)
                // A API nacional geralmente envia em Base64 + Gzip
                $xmlDecodificado = gzdecode(base64_decode($xmlCompactado));

                // 3. Mostrar como XML na tela (Texto legível)
                echo "<h4>2. Conteúdo do XML (Visualização):</h4>";
                echo "<pre style='background:#eef; padding:10px; border:1px solid #ccc; max-height:300px; overflow:auto;'>" . htmlspecialchars($xmlDecodificado) . "</pre>";

                // 4. Gravar o XML na pasta local
                $nomeArquivo = $item['ChaveAcesso'] . "-recuperado.xml";
                file_put_contents(__DIR__ . "/xml/" . $nomeArquivo, $xmlDecodificado);
                echo "<p style='color:green;'><strong>Arquivo salvo com sucesso:</strong> {$nomeArquivo}</p>";
                echo "<hr>";
            }
        }
    } else {
        echo "Erro na consulta: " . $resultado['mensagem'];
    }