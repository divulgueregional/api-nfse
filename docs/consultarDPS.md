# Consultar DPS para Exclusão

## Descrição

Consulta uma DPS (Declaração de Prestação de Serviços) pelo IdDPS antes de realizar a exclusão.

## Método

```php
public function consultarDPSExcluir(string $idDps): array
```

## Parâmetros

- `idDps` (string): ID da DPS com 42 dígitos numéricos. Pode incluir prefixos como "DPS" ou "NFS" que serão removidos automaticamente.

## Retorno

```php
[
    'codigo' => '000',           // 000 = Sucesso, 400 = IdDPS inválido
    'mensagem' => 'DPS encontrada.',
    'tipoAmbiente' => 2,         // 1 = Produção, 2 = Homologação
    'versaoAplicativo' => 'ApiNfse_v1.0',
    'dataHoraProcessamento' => '2026-07-03T14:30:00-03:00',
    'chaveAcesso' => '12345678901234567890123456789012345678901234567890',
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

// Consultar DPS antes de excluir
$resultado = $nfse->consultarDPSExcluir('DPS123456789012345678901234567890123456789012');

if ($resultado['codigo'] === '000') {
    echo "DPS encontrada: " . $resultado['chaveAcesso'];
} else {
    echo "Erro: " . $resultado['mensagem'];
}
```

## Endpoint

- **Método**: GET
- **URL**: `/dps/{idDps}`
- **Autenticação**: mTLS (certificado digital)

## Erros Comuns

- **400**: IdDPS inválido. Deve conter 42 números.
- **404**: DPS não encontrada.
- **403**: Certificado inválido ou sem permissão.
