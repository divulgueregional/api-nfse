# DF-e NSF-e Nacional

## baixar xml
Equivalente ao serviço de Distribuição de DF-e que já existe na NF-e de produtos, mas voltada para a NFS-e Nacional<br>

O que ela faz?<br>
Diferente da consulta por chave, que busca uma nota específica, essa função serve para o contribuinte (ou seu software) perguntar ao governo: "Quais documentos (notas, eventos, cancelamentos) foram emitidos contra mim ou por mim desde a última vez que perguntei?"<br>

NSU (Número Sequencial Único): É como um contador. Você começa no 0 e o governo te devolve as notas 1, 2, 3... Na próxima vez, você pergunta a partir do 3.<br>

Contribuinte: Ela baixa documentos onde o CNPJ do certificado digital é o Prestador, o Tomador ou um intermediário.<br>

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

    $tpAmb = 2; // 2 = Homologação, 1 - produção
    $nfse = new NFSeNacional($config, $tpAmb);

    $nsuInicial = 0; 
    $resultado = $nfse->baixarDfeContribuinte($nsuInicial);

    echo "<h1>Resultado DFe</h1>";
    echo "<pre>";
    print_r($resultado);
    echo "</pre>";

    echo "<h1>Listagem de Documentos (DF-e)</h1>";

    if (isset($resultado['codigo']) && $resultado['codigo'] == '000' && isset($resultado['dados']['LoteDFe'])) {
        
        $lote = $resultado['dados']['LoteDFe'];

        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%; font-family: sans-serif;'>";
        echo "<tr style='background-color: #f2f2f2;'>
                <th>NSU</th>
                <th>Chave de Acesso</th>
                <th>Tipo Doc</th>
                <th>Evento</th>
                <th>Data/Hora Geração</th>
                <th>XML (Compactado)</th>
            </tr>";

        foreach ($lote as $doc) {
            $nsu = $doc['NSU'] ?? '-';
            $chave = $doc['ChaveAcesso'] ?? '-';
            $tipoDoc = $doc['TipoDocumento'] ?? '-';
            $dataGeracao = $doc['DataHoraGeracao'] ?? '-';
            $xml = $doc['ArquivoXml'] ?? '-';
            
            // Verifica se existe TipoEvento (comum em cancelamentos ou cartas de correção)
            $tipoEvento = isset($doc['TipoEvento']) ? $doc['TipoEvento'] : '-';

            echo "<tr>";
            echo "<td>{$nsu}</td>";
            echo "<td>{$chave}</td>";
            echo "<td>{$tipoDoc}</td>";
            echo "<td><strong>{$tipoEvento}</strong></td>";
            echo "<td>" . date('d/m/Y H:i:s', strtotime($dataGeracao)) . "</td>";
            echo "<td><textarea style='width: 100%; height: 50px; font-size: 10px;'>{$xml}</textarea></td>";
            echo "</tr>";
        }
        echo "</table>";

        // Exibe o último NSU para você salvar no banco e usar na próxima consulta
        $ultimoNSU = end($lote)['NSU'];
        echo "<p><strong>Próximo NSU a consultar:</strong> {$ultimoNSU}</p>";

    } else {
        echo "<p>Nenhum documento localizado ou erro na consulta.</p>";
        echo "<pre>";
        print_r($resultado);
        echo "</pre>";
    }