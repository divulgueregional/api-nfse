<?php
/**
 * DANFSe Layout v2.0 - NT 008/2026
 * 
 * Este arquivo gera o HTML do DANFSe conforme a NT 008/2026.
 * Recebe um array associativo com os dados da NFS-e e retorna o HTML.
 * 
 * @param array $dados Dados da NFS-e
 * @return string HTML do DANFSe
 */

if (!function_exists('formatarCNPJCPF')) {
    function formatarCNPJCPF($valor) {
        $valor = preg_replace('/[^0-9]/', '', $valor);
        if (strlen($valor) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $valor);
        } elseif (strlen($valor) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $valor);
        }
        return $valor;
    }
}

if (!function_exists('formatarCEP')) {
    function formatarCEP($cep) {
        $cep = preg_replace('/[^0-9]/', '', $cep);
        return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $cep);
    }
}

if (!function_exists('formatarData')) {
    function formatarData($data) {
        if (empty($data)) return '-';
        $data = preg_replace('/[^0-9]/', '', $data);
        if (strlen($data) === 8) {
            return substr($data, 6, 2) . '/' . substr($data, 4, 2) . '/' . substr($data, 0, 4);
        }
        return $data;
    }
}

if (!function_exists('formatarValorMonetario')) {
    function formatarValorMonetario($valor) {
        return 'R$ ' . number_format((float)$valor, 2, ',', '.');
    }
}

if (!function_exists('formatarFederalGov')) {
    function formatarFederalGov($val) {
        return ($val > 0) ? 'R$ ' . number_format((float)$val, 2, ',', '.') : '-';
    }
}

/**
 * Gera o HTML do DANFSe
 * 
 * @param array $dados Dados da NFS-e
 * @return string HTML do DANFSe
 */
function gerarDANFSeHTML($dados) {
    // Extrair dados
    $chaveAcesso = $dados['chaveAcesso'] ?? '';
    $nNFSe = $dados['nNFSe'] ?? '';
    $dEmi = $dados['dEmi'] ?? '';
    $hEmi = $dados['hEmi'] ?? '';
    $serie = $dados['serie'] ?? '90';
    
    // Prestador
    $prest_xNome = $dados['prest_xNome'] ?? '';
    $prest_cnpj = $dados['prest_cnpj'] ?? '';
    $prest_IM = $dados['prest_IM'] ?? '';
    $prest_fone = $dados['prest_fone'] ?? '';
    $prest_xEmail = $dados['prest_xEmail'] ?? '';
    $prest_ender = $dados['prest_ender'] ?? '';
    $prest_nro = $dados['prest_nro'] ?? '';
    $prest_bairro = $dados['prest_bairro'] ?? '';
    $prest_cep = $dados['prest_cep'] ?? '';
    $prest_mun = $dados['prest_mun'] ?? '';
    $prest_uf = $dados['prest_uf'] ?? '';
    
    // Tomador
    $tom_xNome = $dados['tom_xNome'] ?? '';
    $tom_cnpj = $dados['tom_cnpj'] ?? '';
    $tom_IM = $dados['tom_IM'] ?? '';
    $tom_fone = $dados['tom_fone'] ?? '';
    $tom_xEmail = $dados['tom_xEmail'] ?? '';
    $tom_ender = $dados['tom_ender'] ?? '';
    $tom_nro = $dados['tom_nro'] ?? '';
    $tom_bairro = $dados['tom_bairro'] ?? '';
    $tom_cep = $dados['tom_cep'] ?? '';
    $tom_mun = $dados['tom_mun'] ?? '';
    $tom_uf = $dados['tom_uf'] ?? '';
    
    // Serviço
    $serv_cLCServ = $dados['serv_cLCServ'] ?? '';
    $serv_cServ = $dados['serv_cServ'] ?? '';
    $serv_xServ = $dados['serv_xServ'] ?? '';
    $serv_vServ = $dados['serv_vServ'] ?? 0;
    $serv_vDesc = $dados['serv_vDesc'] ?? 0;
    $serv_vISS = $dados['serv_vISS'] ?? 0;
    $serv_pISS = $dados['serv_pISS'] ?? 0;
    
    // Tributação
    $irrf_retido = $dados['irrf_retido'] ?? 0;
    $prev_retida = $dados['prev_retida'] ?? 0;
    $cs_retidas = $dados['cs_retidas'] ?? 0;
    $desc_cs_retidas = $dados['desc_cs_retidas'] ?? '';
    $pis_debito_proprio = $dados['pis_debito_proprio'] ?? 0;
    $cofins_debito_proprio = $dados['cofins_debito_proprio'] ?? 0;
    
    // Totais
    $trib_aprox_fed = $dados['trib_aprox_fed'] ?? 0;
    $trib_aprox_est = $dados['trib_aprox_est'] ?? 0;
    $trib_aprox_mun = $dados['trib_aprox_mun'] ?? 0;
    $tot_vServ = $dados['tot_vServ'] ?? 0;
    $tot_vtLiq = $dados['tot_vtLiq'] ?? 0;
    
    // Informações complementares
    $infAdic_arr = $dados['infAdic_arr'] ?? [];
    $isCancelada = $dados['isCancelada'] ?? false;
    
    // Gerar QR Code (se disponível)
    $qrcode_base64 = '';
    $qrcode_url = "https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=" . $chaveAcesso;
    
    // Se tiver biblioteca QR Code, gerar
    if (function_exists('gerarQRCodeBase64')) {
        try {
            $qrcode_base64 = gerarQRCodeBase64($qrcode_url, 57, 2);
        } catch (Exception $e) {
            $qrcode_base64 = '';
        }
    }
    
    // Gerar HTML
    $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>DANFSe NFS-e Nacional ' . $nNFSe . '</title>
    <style>
        @page {
            /* NT 008/2026 exige margens de 0.15cm a 0.20cm (5.7px a 7.6px) */
            margin: 6px 6px 6px 6px !important;
            padding: 0px !important;
        }
        body {
            /* NT 008/2026: Arial para títulos/labels, Microsoft Sans Serif para conteúdos */
            font-family: "Microsoft Sans Serif", Arial, sans-serif;
            font-size: 7pt;
            line-height: 1.1;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            /* NT 008/2026: Borda com 1pt de espessura */
            border: 1px solid #000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
        }
        td, th {
            padding: 2px 4px;
            vertical-align: top;
            /* NT 008/2026: Linhas divisórias com 0.5pt de espessura */
            border: 0.5px solid #000;
        }
        .label {
            display: block;
            /* NT 008/2026: 6pts para labels de campos */
            font-size: 6pt;
            font-weight: bold;
            color: #000;
            /* NT 008/2026: primeira letra maiúscula, restante minúscula */
            text-transform: none;
            margin-bottom: 1px;
            font-family: Arial, sans-serif;
        }
        .value {
            font-weight: normal;
            /* NT 008/2026: 7pts para conteúdo */
            font-size: 7pt;
        }
        .bold {
            font-weight: bold;
            /* NT 008/2026: Arial para títulos/labels */
            font-family: Arial, sans-serif;
        }
        .center {
            text-align: center;
        }
        .right {
            text-align: right;
        }
        .w-100 { width: 100%; }
        .no-border-top { border-top: none; }
        .no-border-bottom { border-bottom: none; }
        .no-border-left { border-left: none; }
        .no-border-right { border-right: none; }
        .bg-gray {
            /* NT 008/2026: 5% de densidade (cinza claro) */
            background-color: #f7f7f7;
            font-weight: bold;
            text-align: center;
            /* NT 008/2026: 7pts para títulos de blocos */
            font-size: 7pt;
            padding: 2px 4px;
            font-family: Arial, sans-serif;
            text-transform: uppercase;
            margin-top: 2px;
        }
        .watermark {
            position: fixed;
            top: 300px;
            left: 0px;
            width: 100%;
            text-align: center;
            /* NT 008/2026: tamanho mínimo 50pts (aprox. 67px) */
            font-size: 67px;
            font-weight: bold;
            /* NT 008/2026: cor K35 (cinza) */
            color: #595959;
            transform: rotate(-35deg);
            transform-origin: 50% 50%;
            z-index: -1000;
            text-transform: uppercase;
            font-family: Arial, sans-serif;
        }
    </style>
</head>
<body>
    ' . ($isCancelada ? '<div class="watermark">CANCELADA</div>' : '') . '
    <div class="container">
        <!-- 1. Cabeçalho -->
        <table>
            <tr>
                <td width="25%" class="center" style="vertical-align: middle;">
                    <!-- NT 008/2026: Logo oficial do governo (versão local) -->
                    <img src="nfse-logo.png" height="32" style="margin: 2px 0;">
                </td>
                <td width="50%" class="center" style="vertical-align: middle;">
                    <!-- NT 008/2026: Versão v2.0, 9pts, Arial, negrito -->
                    <div style="font-size: 9pt; font-weight: bold; margin-bottom: 1px; font-family: Arial, sans-serif;">DANFSe v2.0</div>
                    <div style="font-size: 9pt; font-weight: bold; font-family: Arial, sans-serif;">Documento Auxiliar da NFS-e</div>
                </td>
                <td width="25%" class="center" style="vertical-align: middle;">
                    <span style="font-size: 8pt; font-weight: bold; color: #333; display: block; margin-top: 5px; margin-bottom: 2px; letter-spacing: 0.5px;">SISTEMA</span><br>
                    <!-- NT 008/2026: Município do emitente, 8pts -->
                    <span style="font-size: 7pt; font-weight: normal; display:block;">Município: ' . $prest_mun . ' - ' . $prest_uf . '</span>
                    <!-- NT 008/2026: Ambiente gerador, 6pts -->
                    <span style="font-size: 6pt; font-weight: normal; display:block;">Ambiente Gerador: Web/ERP</span>
                    <!-- NT 008/2026: Tipo de ambiente, 6pts -->
                    <span style="font-size: 6pt; font-weight: normal; display:block;">Ambiente: Produção</span>
                </td>
            </tr>
        </table>

        <!-- 2. Identificação da Chave e QR Code -->
        <table style="border-top: none;">
            <tr>
                <td width="80%" class="no-border-top">
                    <span class="label">Chave de Acesso da NFS-e</span>
                    <!-- NT 008/2026: Chave em único bloco de 50 dígitos -->
                    <span class="value bold" style="font-size: 7pt; letter-spacing: 0.5px;">' . $chaveAcesso . '</span>
                </td>
                <td width="20%" rowspan="3" class="center" style="vertical-align: middle; border-top: none;">
                    <!-- NT 008/2026: Mínimo 1.52cm x 1.52cm (57px) -->
                    ' . ($qrcode_base64 ? '<img src="' . $qrcode_base64 . '" width="57" height="57">' : '<div style="width:57px;height:57px;border:1px solid #ccc;display:flex;align-items:center;justify-content:center;font-size:6pt;">QR Code</div>') . '
                </td>
            </tr>
            <tr>
                <td style="padding: 0; border: none;">
                    <table style="width: 100%; border: none;">
                        <tr>
                            <td width="16%" style="border-left: none; border-bottom: none;"><span class="label">Número da NFS-e</span><span class="value bold">' . $nNFSe . '</span></td>
                            <td width="16%" style="border-bottom: none;"><span class="label">Competência</span><span class="value">' . formatarData($dEmi) . '</span></td>
                            <td width="20%" style="border-bottom: none;"><span class="label">Data/Hora Emissão</span><span class="value">' . formatarData($dEmi) . ' ' . $hEmi . '</span></td>
                            <td width="16%" style="border-bottom: none;"><span class="label">Nº da DPS</span><span class="value">1</span></td>
                            <td width="16%" style="border-bottom: none;"><span class="label">Série da DPS</span><span class="value">' . $serie . '</span></td>
                            <td width="16%" style="border-right: none; border-bottom: none;"><span class="label">Emissão DPS</span><span class="value">' . formatarData($dEmi) . '</span></td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td style="padding: 2px 5px; border: none; text-align: right;">
                    <!-- NT 008/2026: Texto complementar do QR Code, 6pts -->
                    <span style="font-size: 6pt; font-weight: normal;">A autenticidade desta NFS-e pode ser verificada pela leitura deste código QR ou pela consulta da chave de acesso no portal nacional da NFS-e</span>
                </td>
            </tr>
        </table>

        <!-- 3. Prestador do Serviço -->
        <div class="bg-gray">EMITENTE DA NFS-e (Prestador do Serviço)</div>
        <table>
            <tr>
                <td width="35%"><span class="label">Razão Social / Nome Empresarial</span><span class="value bold">' . $prest_xNome . '</span></td>
                <td width="20%"><span class="label">CNPJ / CPF</span><span class="value">' . formatarCNPJCPF($prest_cnpj) . '</span></td>
                <td width="25%"><span class="label">Inscrição Municipal</span><span class="value">' . ($prest_IM ?: '-') . '</span></td>
                <td width="20%"><span class="label">Telefone</span><span class="value">' . ($prest_fone ?: '-') . '</span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">Endereço</span><span class="value">' . $prest_ender . ', ' . $prest_nro . ' - ' . $prest_bairro . '</span></td>
                <td><span class="label">CEP</span><span class="value">' . formatarCEP($prest_cep) . '</span></td>
                <td><span class="label">E-mail</span><span class="value">' . $prest_xEmail . '</span></td>
            </tr>
            <tr>
                <td><span class="label">Município / UF</span><span class="value">' . $prest_mun . ' - ' . $prest_uf . '</span></td>
                <td colspan="3"><span class="label">País</span><span class="value">Brasil</span></td>
            </tr>
        </table>

        <!-- 4. Tomador do Serviço -->
        <div class="bg-gray">TOMADOR DO SERVIÇO</div>
        <table>
            <tr>
                <td width="55%"><span class="label">Nome / Nome Empresarial</span><span class="value bold">' . $tom_xNome . '</span></td>
                <td width="25%"><span class="label">CNPJ / CPF / NIF</span><span class="value">' . formatarCNPJCPF($tom_cnpj) . '</span></td>
                <td width="20%"><span class="label">Telefone</span><span class="value">' . ($tom_fone ?: '-') . '</span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">Endereço</span><span class="value">' . $tom_ender . ', ' . $tom_nro . ' - ' . $tom_bairro . '</span></td>
                <td><span class="label">CEP</span><span class="value">' . formatarCEP($tom_cep) . '</span></td>
            </tr>
            <tr>
                <td><span class="label">Município / UF</span><span class="value">' . $tom_mun . ' - ' . $tom_uf . '</span></td>
                <td><span class="label">E-mail</span><span class="value">' . $tom_xEmail . '</span></td>
                <td><span class="label">Inscrição Municipal</span><span class="value">' . ($tom_IM ?: '-') . '</span></td>
            </tr>
        </table>

        <!-- NT 008/2026: Destinatário da Operação -->
        <div class="bg-gray">DESTINATÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e</div>

        <!-- 5. Intermediário -->
        <div class="bg-gray">INTERMEDIÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e</div>

        <!-- 6. Serviço Prestado -->
        <div class="bg-gray">SERVIÇO PRESTADO</div>
        <table>
            <tr>
                <td width="25%"><span class="label">Código de Tributação Nacional</span><span class="value bold">' . $serv_cLCServ . ' - ' . $serv_cServ . '</span></td>
                <td width="25%"><span class="label">Código de Tributação Municipal</span><span class="value">-</span></td>
                <td width="25%"><span class="label">Local da Prestação</span><span class="value">' . $prest_mun . ' - ' . $prest_uf . '</span></td>
                <td width="25%"><span class="label">País da Prestação</span><span class="value">Brasil</span></td>
            </tr>
            <tr>
                <td colspan="4"><span class="label">Descrição do Serviço</span><span class="value" style="font-size: 7pt; font-weight: bold; white-space: pre-wrap;">' . nl2br($serv_xServ) . '</span></td>
            </tr>
        </table>

        <!-- 7. Tributação Municipal -->
        <div class="bg-gray">TRIBUTAÇÃO MUNICIPAL (ISSQN)</div>
        <table>
            <tr>
                <td width="25%"><span class="label">Tipo de Tributação do ISSQN</span><span class="value">Operação Tributável</span></td>
                <td width="25%"><span class="label">Município / Sigla UF / País da Incidência</span><span class="value">' . $prest_mun . ' - ' . $prest_uf . ' - Brasil</span></td>
                <td width="25%"><span class="label">Regime Especial de Tributação do ISSQN</span><span class="value">-</span></td>
                <td width="25%"><span class="label">Tipo de Imunidade do ISSQN</span><span class="value">-</span></td>
            </tr>
            <tr>
                <td width="25%"><span class="label">Suspensão da Exigibilidade do ISSQN</span><span class="value">-</span></td>
                <td width="25%"><span class="label">Número Processo Suspensão</span><span class="value">-</span></td>
                <td width="25%"><span class="label">Benefício Municipal</span><span class="value">-</span></td>
                <td width="25%"><span class="label">Cálculo do BM</span><span class="value">-</span></td>
            </tr>
            <tr>
                <td width="25%"><span class="label">Total Deduções/Reduções</span><span class="value">' . formatarFederalGov(0.0) . '</span></td>
                <td width="25%"><span class="label">Desconto Incondicionado</span><span class="value">' . formatarFederalGov($serv_vDesc) . '</span></td>
                <td width="25%"><span class="label">BC ISSQN</span><span class="value bold">' . formatarValorMonetario($serv_vServ) . '</span></td>
                <td width="25%"><span class="label">Alíquota Aplicada</span><span class="value">' . number_format($serv_pISS, 2, ',', '.') . ' %</span></td>
            </tr>
            <tr>
                <td width="25%"><span class="label">Retenção do ISSQN</span><span class="value">' . ($serv_vISS > 0 ? 'Retido' : 'Não Retido') . '</span></td>
                <td width="25%"><span class="label">ISSQN Apurado</span><span class="value bold">' . formatarValorMonetario($serv_vISS) . '</span></td>
                <td width="25%"><span class="label">&nbsp;</span><span class="value">&nbsp;</span></td>
                <td width="25%"><span class="label">&nbsp;</span><span class="value">&nbsp;</span></td>
            </tr>
        </table>

        <!-- 8. Tributação Federal -->
        <div class="bg-gray">TRIBUTAÇÃO FEDERAL (EXCETO CBS)</div>
        <table>
            <tr>
                <td width="25%"><span class="label">IRRF</span><span class="value">' . formatarFederalGov($irrf_retido) . '</span></td>
                <td width="25%"><span class="label">Contribuição Previdenciária - Retida</span><span class="value">' . formatarFederalGov($prev_retida) . '</span></td>
                <td width="25%"><span class="label">Contribuições Sociais - Retidas</span><span class="value">' . formatarFederalGov($cs_retidas) . '</span></td>
                <td width="25%"><span class="label">Descrição Contrib. Sociais - Retidas</span><span class="value" style="font-size: 6pt;">' . htmlspecialchars($desc_cs_retidas) . '</span></td>
            </tr>
            <tr>
                <td width="25%"><span class="label">PIS - Débito Apuração Própria</span><span class="value">' . formatarFederalGov($pis_debito_proprio) . '</span></td>
                <td width="25%"><span class="label">COFINS - Débito Apuração Própria</span><span class="value">' . formatarFederalGov($cofins_debito_proprio) . '</span></td>
                <td width="25%"><span class="label">&nbsp;</span><span class="value">&nbsp;</span></td>
                <td width="25%"><span class="label">&nbsp;</span><span class="value">&nbsp;</span></td>
            </tr>
        </table>

        <!-- NT 008/2026: Tributação IBS/CBS -->
        <div class="bg-gray">TRIBUTAÇÃO IBS / CBS</div>
        <table>
            <tr>
                <td width="25%"><span class="label">CST / cClassTrib</span><span class="value">-</span></td>
                <td width="25%"><span class="label">Indicador de Operação / Município Incidência</span><span class="value">-</span></td>
                <td width="25%"><span class="label">Exclusões e Reduções da BC</span><span class="value">' . formatarFederalGov(0.0) . '</span></td>
                <td width="25%"><span class="label">Base de Cálculo Após Exclusões</span><span class="value">' . formatarFederalGov($serv_vServ) . '</span></td>
            </tr>
            <tr>
                <td width="25%"><span class="label">Red. Alíquota IBS / CBS</span><span class="value">- / -</span></td>
                <td width="25%"><span class="label">Alíquota IBS UF / IBS Mun</span><span class="value">- / -</span></td>
                <td width="25%"><span class="label">Alíq. Efetiva Municipal - IBS</span><span class="value">' . formatarFederalGov(0.0) . ' %</span></td>
                <td width="25%"><span class="label">Valor Apurado Municipal - IBS</span><span class="value">' . formatarFederalGov($trib_aprox_est) . '</span></td>
            </tr>
            <tr>
                <td width="25%"><span class="label">Alíq. Efetiva Estadual - IBS</span><span class="value">' . formatarFederalGov(0.0) . ' %</span></td>
                <td width="25%"><span class="label">Valor Apurado Estadual - IBS</span><span class="value">' . formatarFederalGov(0.0) . '</span></td>
                <td width="25%"><span class="label">Valor Total Apurado - IBS</span><span class="value">' . formatarFederalGov($trib_aprox_est) . '</span></td>
                <td width="25%"><span class="label">&nbsp;</span><span class="value">&nbsp;</span></td>
            </tr>
            <tr>
                <td width="25%"><span class="label">Alíquota - CBS</span><span class="value">' . formatarFederalGov(0.0) . ' %</span></td>
                <td width="25%"><span class="label">Alíquota Efetiva - CBS</span><span class="value">' . formatarFederalGov(0.0) . ' %</span></td>
                <td width="25%"><span class="label">Valor Total Apurado - CBS</span><span class="value">' . formatarFederalGov($trib_aprox_fed) . '</span></td>
                <td width="25%"><span class="label">&nbsp;</span><span class="value">&nbsp;</span></td>
            </tr>
        </table>

        <!-- TOTAIS APROXIMADOS DOS TRIBUTOS -->
        <div class="bg-gray">TOTAIS APROXIMADOS DOS TRIBUTOS</div>
        <table>
            <tr>
                <td colspan="4">
                    <!-- NT 008/2026: Formato obrigatório -->
                    <span class="value" style="font-size: 6pt;">Totais Aproximados dos Tributos cfe. Lei nº 12.741/2012: Federais: ' . formatarFederalGov($trib_aprox_fed) . ' ; Estaduais: ' . formatarFederalGov($trib_aprox_est) . ' ; Municipais: ' . formatarFederalGov($trib_aprox_mun) . '</span>
                </td>
            </tr>
        </table>

        <!-- 9. Totais e Valores -->
        <div class="bg-gray">VALOR TOTAL DA NFS-e</div>
        <table>
            <tr>
                <td width="25%"><span class="label">Valor da Operação / Serviço</span><span class="value bold">' . formatarValorMonetario($tot_vServ) . '</span></td>
                <td width="25%"><span class="label">Desconto Incondicionado</span><span class="value">' . formatarValorMonetario($serv_vDesc) . '</span></td>
                <td width="25%"><span class="label">Desconto Condicionado</span><span class="value">R$ 0,00</span></td>
                <td width="25%"><span class="label">Total das Retenções (ISSQN / Federais)</span><span class="value">' . formatarValorMonetario($irrf_retido + $prev_retida + $cs_retidas + $serv_vISS) . '</span></td>
            </tr>
            <tr>
                <td width="25%"><span class="label">Valor Líquido da NFS-e</span><span class="value bold">' . formatarValorMonetario($tot_vtLiq) . '</span></td>
                <td width="25%"><span class="label">Total do IBS/CBS</span><span class="value">' . formatarValorMonetario($trib_aprox_fed + $trib_aprox_est) . '</span></td>
                <td colspan="2" class="bg-gray" style="text-align: left;"><span class="label" style="color: #000;">Valor Líquido da NFS-e + IBS/CBS</span><span class="value bold" style="font-size: 7pt;">' . formatarValorMonetario($tot_vtLiq + $trib_aprox_fed + $trib_aprox_est) . '</span></td>
            </tr>
        </table>

        <!-- 10. Informações Adicionais -->
        <div class="bg-gray">INFORMAÇÕES COMPLEMENTARES</div>
        <div style="padding: 4px 8px; font-size: 6pt; min-height: 50px;">';

        // NT 008/2026: Informações separadas por pipes (|)
        $infComplParts = [];

        // Totais Aproximados dos Tributos (obrigatório)
        $infComplParts[] = 'Totais Aproximados dos Tributos cfe. Lei nº 12.741/2012: Federais: ' . formatarFederalGov($trib_aprox_fed) . ' ; Estaduais: ' . formatarFederalGov($trib_aprox_est) . ' ; Municipais: ' . formatarFederalGov($trib_aprox_mun);

        if (!empty($infAdic_arr)) {
            foreach ($infAdic_arr as $info) {
                $infComplParts[] = $info;
            }
        }

        // NT 008/2026: Separar por pipes (|)
        $html .= '<span style="font-size: 6pt;">' . htmlspecialchars(implode(' | ', $infComplParts)) . '</span>';
        
        $html .= '
            <div style="margin-top: 5px; font-size: 6pt; color: #555; text-align: center; border-top: 1px dashed #ccc; padding-top: 3px;">
                Esta nota fiscal foi gerada e assinada de acordo com as especificações do Ambiente de Dados Nacional (ADN) da NFS-e.
            </div>
        </div>
    </div>
</body>
</html>';

    return $html;
}
