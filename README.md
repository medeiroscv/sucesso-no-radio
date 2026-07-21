# Sucesso no Rádio

Site institucional + catálogo de conteúdos para rádios (diários, semanais, informativos e programetes), com **área administrativa** e deploy no **EasyPanel**.

## Stack

- PHP 8.2 + Apache  
- PostgreSQL  
- Docker (Dockerfile na raiz)  

## Estrutura

```
/
  index.php, programa.php, contato.php   # front público
  admin/                                 # painel (login + CRUD)
  includes/                              # db, env, layout
  assets/css/                            # estilos
  uploads/                               # mídias (volume EasyPanel)
  data/                                  # sessões (volume)
  Dockerfile, docker-entrypoint.sh
  EASYPANEL.md                           # guia de deploy
```

## Local (opcional)

Com Postgres rodando e variáveis `DB_*` (ou `.env` no host):

```bash
# subir container ou php -S com document root na pasta do projeto
php -S localhost:8080
```

Acesse:

- Site: http://localhost:8080/  
- Admin: http://localhost:8080/admin/  

## EasyPanel

Veja **[EASYPANEL.md](EASYPANEL.md)** — Postgres + volumes + `BOOTSTRAP_ADMIN_*`.

## Financeiro (Asaas)

Pix + boleto via [Asaas](https://docs.asaas.com/). Configure a API Key em Admin → Configurações → Financeiro (ou `ASAAS_*` no ambiente).  
Webhook: `/api/asaas-webhook.php`. Detalhes em **EASYPANEL.md**.

## Próximos passos possíveis

- Editor de páginas estáticas  
- Upload de áudio demo por programa  
- Multi-usuário / permissões  
- SEO por programa (meta tags)  
- Assinaturas recorrentes Asaas  
