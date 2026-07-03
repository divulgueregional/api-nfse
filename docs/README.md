# API-NFS-e

## Introdução

Essa documentação visa auxiliar a implementação com a API NFS-e Ambiente Nacional, disponibilizando um conjunto de funcionalidade que permitem acesso seguro as operações aqui disponíveis

## PRIMEIRO PASSO
No ambiente de homologação você pode testar com qualquer cidade e CPF/CNPJ válido com certificado digital A1. Para produção apenas as cidades que aderiram a emissão ao Ambiente Nacional.


# PROCESSOS DISPONIVEIS

## API NFS-e

- enviarDPS (Gerar NFS-e)
- montarXmlDPS (Montar XML da DPS)
- gerarIdDPS (Gerar ID da DPS)
- assinarXML (Assinar XML da DPS)
- cancelarNFSe (Cancelar NFS-e)
- montarXmlCancelamento (Montar XML de Cancelamento)
- assinarXMLCan (Assinar XML de Cancelamento)
- consultarNFSePorChave (Consultar NFS-e por Chave de Acesso)
- downloadDANFSe (Obter PDF da NFS-e) - ⚠️ DESCONTINUADO em 15/07/2026
- baixarDfeContribuinte (Baixar DFe do contribuinte)
- consultarDPSExcluir (Consultar DPS para exclusão)
- consultarEventoNFSe (Consultar eventos de NFS-e)
- enviarNFSeDecisaoJudicial (Enviar NFS-e com decisão judicial)
- getTipoAmbiente (Retornar tipo de ambiente atual)
- getUrlBase (Retornar URL base atual)
- decodificarChaveNFSe (Decodificar chave de acesso da NFS-e)

## Documentação Detalhada

- [consultarDPS.md](consultarDPS.md) - Consulta DPS para exclusão
- [consultarEvento.md](consultarEvento.md) - Consulta eventos de NFS-e
- [decisaoJudicial.md](decisaoJudicial.md) - Envio de NFS-e com decisão judicial
- [enviarDPS.md](enviarDPS.md) - Envio de DPS para gerar NFS-e
- [cancelar.md](cancelar.md) - Cancelamento de NFS-e
- [consultar.md](consultar.md) - Consulta de NFS-e
- [dfe.md](dfe.md) - Download de DFe do contribuinte
- [pdf.md](pdf.md) - Download de PDF (descontinuado)
- [pdf_proprio.md](pdf_proprio.md) - PDF próprio gerado localmente
- [xml.md](xml.md) - Consulta de XML