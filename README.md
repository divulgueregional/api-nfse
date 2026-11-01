# NFS-e Nacional PHP

Projeto completo utilizando comunicação com a API NFS-e do Ambiente Nacional. Essa biblioteca pode ser facilmente integrada ao seu software e/ou ERP.

## ✅ Requisitos

- **PHP 8.1 ou superior** (testado no PHP 8.3)
- Extensões: `dom`, `json`, `openssl`, `zlib` e, recomendado, `curl`
- Certificado digital A1 (`.pfx`/`.p12`) ou o mesmo certificado convertido para PEM

## 📦 Instalação

```bash
composer require divulgueregional/api-nfse
```
## Atualizar:

<b>Pela última tag: </b>

```bash
composer update divulgueregional/api-nfse 1.2.1
```

Acesse a pasta docs e leia o README.md

## 🧪 Testes

Os testes não dependem de PHPUnit (geram um certificado autoassinado em tempo de execução):

```bash
composer test
# ou
php tests/run-tests.php
```

## 🔐 Certificado digital

O certificado pode ser informado de três formas:

```php
$config = [
    'cert_path' => '/caminho/certificado.pfx', // PKCS#12
    'cert_password' => 'senha',
];

// ou conteúdo do certificado em base64
$config = ['cert_content' => base64_encode(file_get_contents('certificado.pfx')), 'cert_password' => 'senha'];

// ou certificado já convertido para PEM (certificado + chave privada no mesmo arquivo)
$config = ['cert_path' => '/caminho/certificado.pem'];
```

> Certificados A1 antigos usam algoritmos legados (RC2/3DES) que o **OpenSSL 3** (usado pelo PHP 8.3) não lê.
> Nesse caso converta o certificado uma única vez e use o PEM:
>
> ```bash
> openssl pkcs12 -in certificado.pfx -nodes -out certificado.pem
> # se o comando acima falhar por algoritmo legado:
> openssl pkcs12 -legacy -in certificado.pfx -nodes -out certificado.pem
> ```

## 📚 Documentação Completa

Para detalhes profundos sobre a API NFSe Ambiente Nacional, consulte os portais oficiais:

- **Portal da Nota Fiscal de Serviço eletrônica**:<br>
 [https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/apis-prod-restrita-e-producao](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/apis-prod-restrita-e-producao)
- **Portal de Gestão NFS-e - Contribuinte (Produção)**:<br>
[https://www.nfse.gov.br/EmissorNacional/Login?ReturnUrl=%2fEmissorNacional](https://www.nfse.gov.br/EmissorNacional/Login?ReturnUrl=%2fEmissorNacional)
- **Portal de Gestão NFS-e - Contribuinte (Ambiente de Produção Restrita-Homologação)**:<br>
[https://www.producaorestrita.nfse.gov.br/EmissorNacional/Login?ReturnUrl=%2FEmissorNacional%2F](https://www.producaorestrita.nfse.gov.br/EmissorNacional/Login?ReturnUrl=%2FEmissorNacional%2F)
- **Consulta Pública NFS-e**:<br>
[https://www.nfse.gov.br/consultapublica](https://www.nfse.gov.br/consultapublica)

## O QUE VOCÊ PODE UTILIZAR

- Gerar nota
- Cancelar Nota
- Consultar Nota
- Obter pdf
- Baixar DFe

## Autor

Roseno Matos (developer)  
rosenomatos@gmail.com
