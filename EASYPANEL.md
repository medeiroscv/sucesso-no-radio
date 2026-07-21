# Sucesso no Rádio — Deploy EasyPanel

Site + painel admin (PHP 8.2 / Apache / **PostgreSQL**).

## Serviços no EasyPanel

| Serviço | Tipo |
|---------|------|
| `postgres` | Database → **Postgres** |
| `sucesso-radio` (ou nome que preferir) | App (GitHub + Dockerfile) |

## App

- **Source:** repositório deste projeto  
- **Branch:** `master`  
- **Dockerfile:** raiz  
- **Porta:** `80`

## Environment (App)

```bash
DB_HOST=SEU_PROJETO_postgres
DB_PORT=5432
DB_DATABASE=sucesso_radio
DB_USERNAME=sucesso
DB_PASSWORD=********

# ou:
# DATABASE_URL=postgres://sucesso:********@SEU_PROJETO_postgres:5432/sucesso_radio

AUTO_INSTALL=true
BOOTSTRAP_ADMIN_USER=admin
BOOTSTRAP_ADMIN_PASSWORD=SenhaForte123!
BOOTSTRAP_ADMIN_NAME=Administrador

APP_TIMEZONE=America/Sao_Paulo
APP_NAME=Sucesso no Rádio

# Asaas (financeiro — Pix + boleto)
# ASAAS_API_KEY=$aact_hmlg_...   # sandbox  |  $aact_prod_... em produção
# ASAAS_SANDBOX=true
# ASAAS_WEBHOOK_TOKEN=token-forte-opcional
```

### Financeiro (Asaas)

1. Crie conta em [sandbox.asaas.com](https://sandbox.asaas.com) (testes) ou [asaas.com](https://www.asaas.com) (produção).  
2. Gere a **API Key** em Integrações → API Key.  
3. Cadastre uma **chave Pix** na conta Asaas (menu Pix).  
4. Preencha `ASAAS_API_KEY` / `ASAAS_SANDBOX` no EasyPanel **ou** em Admin → Configurações → Financeiro.  
5. Ative o módulo financeiro e o bloqueio por atraso.  
6. Cadastre o webhook: `https://seu-dominio/api/asaas-webhook.php`  
   - Eventos: `PAYMENT_RECEIVED`, `PAYMENT_CONFIRMED`  
   - Token opcional: use o mesmo valor em `ASAAS_WEBHOOK_TOKEN` e no header de autenticação do webhook.  
7. Informe **CPF/CNPJ** no cadastro de cada cliente (obrigatório para cobranças).

Hostname interno do Postgres no EasyPanel: `{nome_do_projeto}_{servico}`  
(ex.: `sucesso_postgres`). Veja **Credentials** no painel.

## Volumes (obrigatório)

| Mount path no container | Uso |
|-------------------------|-----|
| `/var/www/html/uploads` | Capas de conteúdos e banners |
| `/var/www/html/data` | Sessões de login admin |
| `/var/www/html/config` | Config runtime (se usar) |

Sem volumes, **redeploy apaga** imagens e força login de novo.

## Primeiro acesso

1. Deploy com Postgres + volumes  
2. Abra `https://seu-dominio/admin/`  
3. Login com `BOOTSTRAP_ADMIN_USER` / `BOOTSTRAP_ADMIN_PASSWORD`  
4. Cadastre conteúdos (diários, semanais, informativos, programetes) e banners  

5. Site público: `https://seu-dominio/`  

## URLs

| URL | Função |
|-----|--------|
| `/` | Site (front) |
| `/contato.php` | Formulário de contato |
| `/programa.php?slug=...` | Detalhe do conteúdo |
| `/admin/` | Painel administrativo |
| `/admin/conteudos.php` | Conteúdos (diários, semanais, informativos, programetes) |
| `/admin/login.php` | Login |
| `/cliente/` | Área do cliente |
| `/api/asaas-webhook.php` | Webhook Asaas (pagamentos) |

## O que o admin controla

- **Conteúdos** — hub com 4 tipos: Diários, Semanais, Informativos, Programetes  
  - **Demonstrativos** (públicos na home)  
  - **Arquivos de entrega** (só área do cliente, atualização diária)  
- **Clientes** — cadastro com e-mail/senha, WhatsApp, rádio, CPF  
- **Textos a gravar** — envios dos clientes logados (com dados do cadastro)  
- **Financeiro** — faturas, emissão Pix/boleto Asaas, baixa manual  
- **Banners** da home  
- **Configurações** — site (logo/favicon), formulários, Asaas  

### Área do cliente (`/cliente/`)
- Login obrigatório  
- Acesso a diários, semanais, informativos e programetes + downloads de entrega  
- Envio de texto para gravação vinculado ao cliente  
- Financeiro (Pix QR + boleto) quando o módulo estiver ativo  

Tudo que aparece no front vem do banco via admin — sem editar HTML à mão.
