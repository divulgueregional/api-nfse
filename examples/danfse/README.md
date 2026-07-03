# Exemplo de Implementação do DANFSe v2.0

Esta pasta contém todos os arquivos necessários para gerar o PDF próprio do DANFSe conforme a NT 008/2026.

## 📁 Arquivos

- **danfse_layout.php** - Layout HTML/CSS principal do DANFSe. Contém a função `gerarDANFSeHTML()` que recebe um array com os dados da NFS-e e retorna o HTML.
- **nfse-logo.png** - Logo oficial da NFS-e Nacional (horizontal).
- **exemplo_geracao.php** - Exemplo completo de como gerar o PDF usando o layout.
- **README.md** - Este arquivo.

## 🚀 Como Usar

### 1. Instalar Dependências

```bash
composer require dompdf/dompdf
```

### 2. Executar o Exemplo

```bash
php exemplo_geracao.php
```

Isso irá gerar o arquivo `danfse_exemplo.pdf` no diretório atual.

### 3. Integrar no Seu Sistema

```php
require_once 'danfse_layout.php';

// Preparar os dados da NFS-e
$dados = [
    'chaveAcesso' => '...',
    'nNFSe' => '...',
    // ... outros campos
];

// Gerar o HTML
$html = gerarDANFSeHTML($dados);

// Converter para PDF com Dompdf
require_once 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->setChroot(__DIR__);
$options->setIsRemoteEnabled(true);
$pdf = new Dompdf($options);
$pdf->loadHtml($html);
$pdf->setPaper('A4', 'portrait');
$pdf->render();

// Salvar o PDF
file_put_contents('danfse.pdf', $pdf->output());
```

## 📋 Estrutura de Dados

O array `$dados` deve conter os seguintes campos:

```php
$dados = [
    // Identificação
    'chaveAcesso' => '50 dígitos',
    'nNFSe' => 'Número da NFS-e',
    'dEmi' => 'YYYY-MM-DD',
    'hEmi' => 'HH:MM',
    'serie' => 'Série da DPS',
    
    // Prestador
    'prest_xNome' => 'Razão Social',
    'prest_cnpj' => 'CNPJ/CPF',
    'prest_IM' => 'Inscrição Municipal',
    'prest_fone' => 'Telefone',
    'prest_xEmail' => 'E-mail',
    'prest_ender' => 'Endereço',
    'prest_nro' => 'Número',
    'prest_bairro' => 'Bairro',
    'prest_cep' => 'CEP',
    'prest_mun' => 'Município',
    'prest_uf' => 'UF',
    
    // Tomador
    'tom_xNome' => 'Nome',
    'tom_cnpj' => 'CNPJ/CPF',
    'tom_IM' => 'Inscrição Municipal',
    'tom_fone' => 'Telefone',
    'tom_xEmail' => 'E-mail',
    'tom_ender' => 'Endereço',
    'tom_nro' => 'Número',
    'tom_bairro' => 'Bairro',
    'tom_cep' => 'CEP',
    'tom_mun' => 'Município',
    'tom_uf' => 'UF',
    
    // Serviço
    'serv_cLCServ' => 'Código NBS',
    'serv_cServ' => 'Código do Serviço',
    'serv_xServ' => 'Descrição',
    'serv_vServ' => 0.00,
    'serv_vDesc' => 0.00,
    'serv_vISS' => 0.00,
    'serv_pISS' => 0.00,
    
    // Tributação
    'irrf_retido' => 0.00,
    'prev_retida' => 0.00,
    'cs_retidas' => 0.00,
    'desc_cs_retidas' => '',
    'pis_debito_proprio' => 0.00,
    'cofins_debito_proprio' => 0.00,
    'trib_aprox_fed' => 0.00,
    'trib_aprox_est' => 0.00,
    'trib_aprox_mun' => 0.00,
    
    // Totais
    'tot_vServ' => 0.00,
    'tot_vtLiq' => 0.00,
    
    // Informações complementares
    'infAdic_arr' => ['Info 1', 'Info 2'],
    
    // Status
    'isCancelada' => false
];
```

## 📚 Documentação Completa

Para mais detalhes, consulte:
- [docs/pdf_proprio.md](../../docs/pdf_proprio.md) - Documentação completa do PDF próprio
- [NT 008/2026](https://www.gov.br/nfse/pt-br/noticias/se-cgnfs-e-prorroga-o-prazo-para-adequacao-ao-novo-leiaute-do-danfse) - Especificação técnica

## ⚠️ Notas

1. **QR Code**: O layout suporta QR Code. Se você tiver uma biblioteca de QR Code, crie uma função `gerarQRCodeBase64($url, $size, $margin)` que retorne o QR Code em base64.

2. **Dados do XML**: Em produção, os dados devem ser extraídos do XML da NFS-e. Consulte a documentação da API para entender a estrutura do XML.

3. **Personalização**: Você pode personalizar o layout alterando o CSS no arquivo `danfse_layout.php`.

## 🎨 Personalização

### Alterar Logo

Substitua o arquivo `nfse-logo.png` ou altere o caminho no código:

```php
<img src="caminho/para/logo.png" height="32">
```

### Ajustar Margens

No CSS do arquivo `danfse_layout.php`:

```css
@page {
    margin: 6px 6px 6px 6px !important;
}
```

### Alterar Cores

```css
.bg-gray {
    background-color: #f7f7f7;
}
```
