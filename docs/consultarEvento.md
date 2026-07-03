# Consultar Evento de NFS-e

## Descrição

Consulta eventos de uma NFS-e, como cancelamentos, decisões judiciais, entre outros.

## Método

```php
public function consultarEventoNFSe(string $chaveAcesso, int $tipoEvento = 101101, int $numSeqEvento = 1): array
```

## Parâmetros

- `chaveAcesso` (string): Chave de acesso da NFS-e (50 dígitos)
- `tipoEvento` (int): Tipo do evento (padrão 101101 para cancelamento)
- `numSeqEvento` (int): Número sequencial do evento (padrão 1)

## Tipos de Evento

| Código | Descrição |
|--------|-----------|
| 101101 | Cancelamento |
| 101102 | Decisão Judicial |
| 101103 | Outros |

## Retorno

```php
[
    'codigo' => '000',
    'mensagem' => 'Eventos encontrados.',
    'eventos' => [
        [
            'chaveAcesso' => '12345678901234567890123456789012345678901234567890',
            'tipoEvento' => 101101,
            'numeroPedidoRegistroEvento' => 123,
            'dataHoraRecebimento' => '2026-07-03T14:30:00-03:00',
            'xmlEvento' => '<?xml ...' // XML do evento decodificado
        ]
    ],
    'bodyOriginal' => '...'
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

// Consultar evento de cancelamento
$resultado = $nfse->consultarEventoNFSe('12345678901234567890123456789012345678901234567890', 101101);

if ($resultado['codigo'] === '000') {
    foreach ($resultado['eventos'] as $evento) {
        echo "Evento: " . $evento['tipoEvento'] . "\n";
        echo "Data: " . $evento['dataHoraRecebimento'] . "\n";
        echo "XML: " . substr($evento['xmlEvento'], 0, 100) . "...\n";
    }
} else {
    echo "Erro: " . $resultado['mensagem'];
}
```

## Endpoint

- **Método**: GET
- **URL**: `/nfse/{chaveAcesso}/eventos/{tipoEvento}/{numSeqEvento}`
- **Autenticação**: mTLS (certificado digital)

## Erros Comuns

- **404**: Chave de acesso não encontrada.
- **403**: Certificado inválido ou sem permissão.
- **400**: Parâmetros inválidos.
