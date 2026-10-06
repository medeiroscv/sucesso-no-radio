# Sucesso no Rádio

Vitrine comercial dos produtos do **Sucesso no Rádio**.

O projeto não processa mais checkout, cobrança, Pix, boleto ou assinatura. A função pública do site é apresentar os produtos, demonstrativos, benefícios e preços; a contratação é encaminhada para o **WHMCS** através de um link configurado individualmente em cada produto.

## Stack

- PHP 8.2 + Apache
- PostgreSQL
- Docker / EasyPanel

## Fluxo atual

1. O visitante acessa a home ou o catálogo.
2. Abre a página de um produto.
3. Consulta descrição, recursos, preço e demonstrativos.
4. Clica em **Contratar**.
5. O site abre o link `whmcs_url` cadastrado no produto.

Se o produto ainda não tiver link WHMCS, o botão usa o WhatsApp como fallback.

## Administração

No painel administrativo, a operação principal fica concentrada em:

- Produtos
- Banners
- Contatos
- Configurações

Cada produto pode ter:

- nome e slug;
- tipo e periodicidade comercial;
- descrição;
- lista de recursos;
- preço opcional na vitrine;
- imagem de capa;
- áudios demonstrativos;
- destaque e ordem;
- texto do botão;
- mensagem de WhatsApp;
- link direto do WHMCS.

## Compatibilidade

As tabelas e rotinas legadas de clientes/financeiro permanecem preservadas no banco e no histórico do projeto para evitar perda de dados durante a transição, mas não fazem parte do fluxo público da vitrine.
