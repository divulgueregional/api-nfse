<?php
/**
 * Exemplo de Geração do DANFSe v2.0
 * 
 * Este exemplo demonstra como usar o layout do DANFSe para gerar um PDF
 * conforme a NT 008/2026.
 * 
 * Requisitos:
 * - Composer instalado
 * - Dompdf instalado: composer require dompdf/dompdf
 */

// Carrega o layout
require_once 'danfse_layout.php';

// Dados de exemplo da NFS-e
// Em produção, estes dados devem ser extraídos do XML da NFS-e
$dados = [
    // Identificação
    'chaveAcesso' => '12345678901234567890123456789012345678901234567890',
    'nNFSe' => '12345',
    'dEmi' => '2026-07-02',
    'hEmi' => '14:30',
    'serie' => '90',
    
    // Prestador do Serviço
    'prest_xNome' => 'Empresa Prestadora de Serviços LTDA',
    'prest_cnpj' => '12345678000190',
    'prest_IM' => '123456',
    'prest_fone' => '(51) 1234-5678',
    'prest_xEmail' => 'contato@prestador.com.br',
    'prest_ender' => 'Rua Exemplo',
    'prest_nro' => '123',
    'prest_bairro' => 'Centro',
    'prest_cep' => '90000-000',
    'prest_mun' => 'Porto Alegre',
    'prest_uf' => 'RS',
    
    // Tomador do Serviço
    'tom_xNome' => 'Cliente Tomador de Serviços LTDA',
    'tom_cnpj' => '98765432000100',
    'tom_IM' => '',
    'tom_fone' => '(11) 9876-5432',
    'tom_xEmail' => 'financeiro@tomador.com.br',
    'tom_ender' => 'Avenida Paulista',
    'tom_nro' => '1000',
    'tom_bairro' => 'Bela Vista',
    'tom_cep' => '01310-100',
    'tom_mun' => 'São Paulo',
    'tom_uf' => 'SP',
    
    // Serviço
    'serv_cLCServ' => '010105',
    'serv_cServ' => 'Consultoria em TI',
    'serv_xServ' => 'Consultoria em tecnologia da informação, desenvolvimento de sistemas e suporte técnico especializado.',
    'serv_vServ' => 1000.00,
    'serv_vDesc' => 0.00,
    'serv_vISS' => 50.00,
    'serv_pISS' => 5.00,
    
    // Tributação Federal
    'irrf_retido' => 0.00,
    'prev_retida' => 0.00,
    'cs_retidas' => 0.00,
    'desc_cs_retidas' => '',
    'pis_debito_proprio' => 0.00,
    'cofins_debito_proprio' => 0.00,
    
    // Totais Aproximados de Tributos
    'trib_aprox_fed' => 150.00,
    'trib_aprox_est' => 75.00,
    'trib_aprox_mun' => 50.00,
    
    // Totais
    'tot_vServ' => 1000.00,
    'tot_vtLiq' => 950.00,
    
    // Informações Complementares
    'infAdic_arr' => [
        'Serviço prestado conforme contrato nº 001/2026',
        'Pagamento em 30 dias a partir da data de emissão'
    ],
    
    // Status
    'isCancelada' => false
];

// Gerar o HTML do DANFSe
$html = gerarDANFSeHTML($dados);

// Converter para PDF usando Dompdf
require_once 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// Configurar opções do Dompdf
$options = new Options();
$options->setChroot(__DIR__);
$options->setIsRemoteEnabled(true);
$options->setDefaultFont('Helvetica');
$options->setIsFontSubsettingEnabled(true);
$options->setIsHtml5ParserEnabled(true);
$options->setIsPhpEnabled(true);

// Criar instância do Dompdf
$pdf = new Dompdf($options);

// Carregar o HTML
$pdf->loadHtml($html);

// Definir papel (A4 retrato)
$pdf->setPaper('A4', 'portrait');

// Renderizar o PDF
$pdf->render();

// Opção 1: Salvar em arquivo
$arquivoSaida = 'danfse_exemplo.pdf';
$pdfContent = $pdf->output();
file_put_contents($arquivoSaida, $pdfContent);

echo "PDF gerado com sucesso: $arquivoSaida\n";

// Opção 2: Exibir no navegador (descomente para usar)
/*
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="danfse.pdf"');
echo $pdfContent;
exit;
*/

// Opção 3: Forçar download (descomente para usar)
/*
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="danfse.pdf"');
echo $pdfContent;
exit;
*/
