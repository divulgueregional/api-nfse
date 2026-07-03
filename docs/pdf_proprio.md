# PDF Próprio do DANFSe v2.0 (NT 008/2026)

Este documento fornece todos os arquivos e instruções necessários para gerar o PDF próprio do DANFSe (Documento Auxiliar da Nota Fiscal de Serviço Eletrônica) conforme a NT 008/2026.

## 📁 Estrutura de Arquivos

Para usar o PDF próprio, você precisará dos seguintes arquivos:

```
examples/danfse/
├── danfse_layout.php      # Layout HTML/CSS principal
├── nfse-logo.png          # Logo oficial da NFS-e
├── exemplo_geracao.php    # Exemplo de como gerar o PDF
└── QRCodePix.php          # Biblioteca para gerar QR Code (opcional)
```

## 🚀 Como Usar

### 1. Instalar Dompdf

Primeiro, instale a biblioteca Dompdf via Composer:

```bash
composer require dompdf/dompdf
```

### 2. Preparar os Dados da NFS-e

O layout espera um array associativo com todos os dados da nota fiscal. Veja o exemplo em `exemplo_geracao.php`.

### 3. Gerar o PDF

```php
require_once 'danfse_layout.php';

// Dados da NFS-e (deve ser populado com os dados reais do XML)
$dados = [
    'chaveAcesso' => '12345678901234567890123456789012345678901234567890',
    'nNFSe' => '12345',
    'dEmi' => '2026-07-02',
    'hEmi' => '14:30',
    'serie' => '90',
    'prest_xNome' => 'Razão Social do Prestador',
    'prest_cnpj' => '12345678000190',
    'prest_IM' => '123456',
    'prest_fone' => '(51) 1234-5678',
    'prest_xEmail' => 'email@prestador.com.br',
    'prest_ender' => 'Rua Exemplo',
    'prest_nro' => '123',
    'prest_bairro' => 'Centro',
    'prest_cep' => '90000-000',
    'prest_mun' => 'Porto Alegre',
    'prest_uf' => 'RS',
    'tom_xNome' => 'Razão Social do Tomador',
    'tom_cnpj' => '98765432000100',
    'tom_IM' => '',
    'tom_fone' => '(11) 9876-5432',
    'tom_xEmail' => 'email@tomador.com.br',
    'tom_ender' => 'Av. Exemplo',
    'tom_nro' => '456',
    'tom_bairro' => 'Bairro',
    'tom_cep' => '01000-000',
    'tom_mun' => 'São Paulo',
    'tom_uf' => 'SP',
    'serv_cLCServ' => '010105',
    'serv_cServ' => 'Consultoria em TI',
    'serv_xServ' => 'Descrição detalhada do serviço prestado',
    'serv_vServ' => 1000.00,
    'serv_vDesc' => 0.00,
    'serv_vISS' => 50.00,
    'serv_pISS' => 5.00,
    'irrf_retido' => 0.00,
    'prev_retida' => 0.00,
    'cs_retidas' => 0.00,
    'desc_cs_retidas' => '',
    'pis_debito_proprio' => 0.00,
    'cofins_debito_proprio' => 0.00,
    'trib_aprox_fed' => 150.00,
    'trib_aprox_est' => 75.00,
    'trib_aprox_mun' => 50.00,
    'tot_vServ' => 1000.00,
    'tot_vtLiq' => 950.00,
    'infAdic_arr' => ['Informação complementar 1', 'Informação complementar 2'],
    'isCancelada' => false
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
$options->setDefaultFont('Helvetica');
$options->setIsFontSubsettingEnabled(true);
$options->setIsHtml5ParserEnabled(true);
$options->setIsPhpEnabled(true);

$pdf = new Dompdf($options);
$pdf->loadHtml($html);
$pdf->setPaper('A4', 'portrait');
$pdf->render();

// Salvar ou exibir
$pdfContent = $pdf->output();
file_put_contents('danfse.pdf', $pdfContent);
```

## 📋 Estrutura dos Dados

| Campo | Descrição | Exemplo |
|-------|-----------|---------|
| `chaveAcesso` | Chave de acesso da NFS-e (50 dígitos) | `12345678901234567890123456789012345678901234567890` |
| `nNFSe` | Número da NFS-e | `12345` |
| `dEmi` | Data de emissão (YYYY-MM-DD) | `2026-07-02` |
| `hEmi` | Hora de emissão (HH:MM) | `14:30` |
| `serie` | Série da DPS | `90` |
| `prest_xNome` | Razão Social do Prestador | `Empresa Prestadora LTDA` |
| `prest_cnpj` | CNPJ/CPF do Prestador | `12345678000190` |
| `prest_IM` | Inscrição Municipal | `123456` |
| `prest_fone` | Telefone | `(51) 1234-5678` |
| `prest_xEmail` | E-mail | `email@prestador.com.br` |
| `prest_ender` | Endereço | `Rua Exemplo` |
| `prest_nro` | Número | `123` |
| `prest_bairro` | Bairro | `Centro` |
| `prest_cep` | CEP | `90000-000` |
| `prest_mun` | Município | `Porto Alegre` |
| `prest_uf` | UF | `RS` |
| `tom_xNome` | Nome do Tomador | `Cliente Tomador LTDA` |
| `tom_cnpj` | CNPJ/CPF do Tomador | `98765432000100` |
| `serv_cLCServ` | Código de Tributação Nacional | `010105` |
| `serv_cServ` | Código do Serviço | `Consultoria` |
| `serv_xServ` | Descrição do Serviço | `Descrição detalhada` |
| `serv_vServ` | Valor do Serviço | `1000.00` |
| `serv_vDesc` | Desconto | `0.00` |
| `serv_vISS` | Valor do ISSQN | `50.00` |
| `serv_pISS` | Alíquota do ISSQN | `5.00` |
| `irrf_retido` | IRRF Retido | `0.00` |
| `prev_retida` | Previdência Retida | `0.00` |
| `cs_retidas` | Contribuições Sociais Retidas | `0.00` |
| `trib_aprox_fed` | Tributos Federais Aproximados | `150.00` |
| `trib_aprox_est` | Tributos Estaduais Aproximados | `75.00` |
| `trib_aprox_mun` | Tributos Municipais Aproximados | `50.00` |
| `tot_vServ` | Valor Total do Serviço | `1000.00` |
| `tot_vtLiq` | Valor Líquido | `950.00` |
| `infAdic_arr` | Array de informações complementares | `['Info 1', 'Info 2']` |
| `isCancelada` | Se a nota está cancelada | `true`/`false` |

## 🎨 Personalização

### Alterar Logo

Substitua o arquivo `nfse-logo.png` pelo logo desejado ou altere o caminho no código:

```php
<img src="caminho/para/seu/logo.png" height="32">
```

### Ajustar Margens

No CSS do arquivo `danfse_layout.php`, altere:

```css
@page {
    margin: 6px 6px 6px 6px !important; /* Ajuste conforme necessário */
}
```

### Alterar Cores

No CSS, altere a cor do sombreamento:

```css
.bg-gray {
    background-color: #f7f7f7; /* Cor de fundo dos cabeçalhos */
}
```

## 🔧 Funções Auxiliares

O layout inclui funções auxiliares para formatação:

- `formatarCNPJCPF($cnpj)` - Formata CNPJ/CPF
- `formatarCEP($cep)` - Formata CEP
- `formatarData($data)` - Formata data
- `formatarValorMonetario($valor)` - Formata valor monetário

## 📝 Conformidade NT 008/2026

Este layout está em conformidade com a NT 008/2026 e inclui:

- ✅ Versão do DANFSe v2.0
- ✅ Logo oficial da NFS-e Nacional
- ✅ Cabeçalho com Ambiente Gerador e Tipo de Ambiente
- ✅ Fontes: Arial (títulos) e Microsoft Sans Serif (conteúdo)
- ✅ Tamanhos: 6pt (labels), 7pt (conteúdo), 9pt (cabeçalho)
- ✅ Margens: 0.15cm a 0.20cm
- ✅ QR Code com URL correta e tamanho mínimo 1.52cm
- ✅ Bloco de Tributação IBS/CBS
- ✅ Informações Complementares separadas por pipes
- ✅ Totais Aproximados dos Tributos (Lei 12.741/2012)
- ✅ Marca d'água CANCELADA (cor K35, tamanho mínimo 50pts)
- ✅ Sombreamento 5% de densidade
- ✅ Destinatário da Operação
- ✅ Dados específicos de tributação

## 📚 Referências

- [NT 008/2026 - Novo Leiaute do DANFSe](https://www.gov.br/nfse/pt-br/noticias/se-cgnfs-e-prorroga-o-prazo-para-adequacao-ao-novo-leiaute-do-danfse)
- [Portal Nacional da NFS-e](https://www.nfse.gov.br)
- [Documentação Dompdf](https://github.com/dompdf/dompdf)

## ⚠️ Notas Importantes

1. **QR Code**: Se não tiver a biblioteca QRCodePix, o QR Code não será gerado. Você pode usar qualquer biblioteca de QR Code ou remover essa funcionalidade.

2. **Dados do XML**: Os dados devem ser extraídos do XML da NFS-e. Consulte a documentação da API para entender a estrutura do XML.

3. **Ambiente de Produção**: Em produção, certifique-se de usar os dados reais da nota fiscal emitida.

4. **Performance**: Para alta performance, considere cache do HTML gerado ou usar templates compilados.
