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
```

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

## O que o admin controla

- **Conteúdos** — hub com 4 tipos lado a lado:  
  - **Diários**  
  - **Semanais**  
  - **Informativos**  
  - **Programetes**  
  (capa, duração, blocos, dias, inserções, demos MP3, WhatsApp)  
- **Banners** da home  
- **Contatos** recebidos no site  
- **Configurações** (nome, slogan, WhatsApp, e-mail, sobre)  


Tudo que aparece no front vem do banco via admin — sem editar HTML à mão.
