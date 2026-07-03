# Enviar NFS-e com Decisão Judicial

## Descrição

Envia uma NFS-e com decisão judicial para registro no Ambiente Nacional.

## Método

```php
public function enviarNFSeDecisaoJudicial(string $xmlNfse): array
```

## Parâmetros

- `xmlNfse` (string): XML da NFS-e assinado (pode incluir ou não a declaração XML)

## Retorno

```php
[
    'codigo' => '000',           // 000 = Sucesso, 201 = Criado com sucesso
    'mensagem' => 'NFS-e com decisão judicial registrada com sucesso.',
    'chaveAcesso' => '12345678901234567890123456789012345678901234567890',
    'dataHoraProcessamento' => '2026-07-03T14:30:00-03:00',
    'bodyOriginal' => '...'       // Resposta original da API
]
```

## Exemplo de Uso

```php
require_once 'vendor/autoload.php';
use Divulgueregional\ApiNfse\NFSeNacional;

$config = [
    'cert_path' => '/caminho/para/certificado.pfx',
    'cert_password' => 'senha'
];

$tpAmb = 2; // 2 = Homologação
$nfse = new NFSeNacional($config, $tpAmb);

// XML da NFS-e assinado
$xmlNfse = '<?xml version="1.0" encoding="UTF-8"?>
<NFSe>
    <!-- conteúdo da NFS-e -->
</NFSe>';

// Enviar com decisão judicial
$resultado = $nfse->enviarNFSeDecisaoJudicial($xmlNfse);

if ($resultado['codigo'] === '000') {
    echo "NFS-e registrada com decisão judicial!";
    echo "Chave: " . $resultado['chaveAcesso'];
} else {
    echo "Erro: " . $resultado['mensagem'];
}
```

## Endpoint

- **Método**: POST
- **URL**: `/decisao-judicial/nfse`
- **Autenticação**: mTLS (certificado digital)
- **Content-Type**: application/json

## Payload

```json
{
    "nfseXmlGZipB64": "XML compactado em GZip e codificado em Base64"
}
```

## Erros Comuns

- **400**: XML inválido ou malformado.
- **403**: Certificado inválido ou sem permissão.
- **422**: Dados da NFS-e inconsistentes.
- **500**: Erro interno do servidor.
