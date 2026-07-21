<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/asaas.php';
require_once __DIR__ . '/../includes/update.php';

$pdo = app_pdo();
$secoes = app_config_secoes();
$ok = $err = '';
$sec = trim((string)($_GET['sec'] ?? $_POST['sec'] ?? ''));
if ($sec !== '' && !isset($secoes[$sec])) {
    $sec = '';
}
$updateCheck = null;
$updateTestMsg = '';
$updateApplyLog = '';

// ---- POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secPost = trim((string)($_POST['sec'] ?? ''));
    if (!isset($secoes[$secPost])) {
        $err = 'Seção inválida.';
    } elseif ($secPost === 'site') {
        $keys = ['site_nome', 'site_slogan', 'whatsapp', 'telefone', 'email', 'sobre'];
        foreach ($keys as $k) {
            app_setting_set($k, trim((string)($_POST[$k] ?? '')));
        }
        // Logo
        $logoAtual = trim((string)($_POST['site_logo_atual'] ?? ''));
        if (!empty($_POST['remover_logo'])) {
            if ($logoAtual !== '') admin_delete_local_upload($logoAtual);
            app_setting_set('site_logo', '');
        } else {
            $logoNova = admin_upload_asset('site_logo', 'site', 'logo');
            if ($logoNova !== '') {
                if ($logoAtual !== '' && $logoAtual !== $logoNova) {
                    admin_delete_local_upload($logoAtual);
                }
                app_setting_set('site_logo', $logoNova);
            }
        }
        // Favicon
        $favAtual = trim((string)($_POST['site_favicon_atual'] ?? ''));
        if (!empty($_POST['remover_favicon'])) {
            if ($favAtual !== '') admin_delete_local_upload($favAtual);
            app_setting_set('site_favicon', '');
        } else {
            $favNova = admin_upload_asset('site_favicon', 'site', 'favicon');
            if ($favNova !== '') {
                if ($favAtual !== '' && $favAtual !== $favNova) {
                    admin_delete_local_upload($favAtual);
                }
                app_setting_set('site_favicon', $favNova);
            }
        }
        $ok = 'Configurações do site salvas.';
        $sec = 'site';
    } elseif ($secPost === 'formulario_contato') {
        app_setting_set('form_contato_ativo', !empty($_POST['form_contato_ativo']) ? '1' : '0');
        app_setting_set('form_contato_titulo', trim((string)($_POST['form_contato_titulo'] ?? 'Contato')));
        app_setting_set('form_contato_intro', trim((string)($_POST['form_contato_intro'] ?? '')));
        app_setting_set('form_contato_btn', trim((string)($_POST['form_contato_btn'] ?? 'Enviar mensagem')));
        $ok = 'Formulário de contato atualizado.';
        $sec = 'formulario_contato';
    } elseif ($secPost === 'formulario_texto') {
        app_setting_set('form_texto_ativo', !empty($_POST['form_texto_ativo']) ? '1' : '0');
        app_setting_set('form_texto_titulo', trim((string)($_POST['form_texto_titulo'] ?? 'Envio de texto')));
        app_setting_set('form_texto_intro', trim((string)($_POST['form_texto_intro'] ?? '')));
        app_setting_set('form_texto_instrucoes', trim((string)($_POST['form_texto_instrucoes'] ?? '')));
        app_setting_set('form_texto_btn', trim((string)($_POST['form_texto_btn'] ?? 'Enviar texto')));
        $ok = 'Formulário de envio de texto atualizado.';
        $sec = 'formulario_texto';
    } elseif ($secPost === 'financeiro') {
        app_setting_set('finance_ativo', !empty($_POST['finance_ativo']) ? '1' : '0');
        app_setting_set('finance_bloquear_atraso', !empty($_POST['finance_bloquear_atraso']) ? '1' : '0');
        app_setting_set('asaas_sandbox', !empty($_POST['asaas_sandbox']) ? '1' : '0');

        // API Key: só atualiza se preenchida (não expor no form)
        $apiKey = trim((string)($_POST['asaas_api_key'] ?? ''));
        if ($apiKey !== '') {
            app_setting_set('asaas_api_key', $apiKey);
        }
        if (!empty($_POST['limpar_asaas_api_key'])) {
            app_setting_set('asaas_api_key', '');
        }

        $whToken = trim((string)($_POST['asaas_webhook_token'] ?? ''));
        if ($whToken !== '') {
            app_setting_set('asaas_webhook_token', $whToken);
        }
        if (!empty($_POST['limpar_webhook_token'])) {
            app_setting_set('asaas_webhook_token', '');
        }

        $ok = 'Configurações financeiras (Asaas) salvas.';
        $sec = 'financeiro';
    } elseif ($secPost === 'atualizacao') {
        $sec = 'atualizacao';
        $action = trim((string)($_POST['action'] ?? 'save'));

        if ($action === 'save' || $action === 'test' || $action === 'apply') {
            // Salva repo/branch sempre (antes de testar/aplicar)
            $repoIn = trim((string)($_POST['github_repo'] ?? ''));
            $branchIn = trim((string)($_POST['github_branch'] ?? ''));
            if ($repoIn !== '') {
                $repoIn = preg_replace('#^https?://github\.com/#i', '', $repoIn) ?? $repoIn;
                $repoIn = rtrim($repoIn, '/');
                if (str_ends_with(strtolower($repoIn), '.git')) {
                    $repoIn = substr($repoIn, 0, -4);
                }
                app_setting_set('github_repo', $repoIn);
            }
            if ($branchIn !== '') {
                app_setting_set('github_branch', $branchIn);
            }
            $tokenIn = trim((string)($_POST['github_token'] ?? ''));
            if ($tokenIn !== '') {
                app_setting_set('github_token', $tokenIn);
            }
            if (!empty($_POST['remover_github_token'])) {
                app_setting_set('github_token', '');
            }
        }

        if ($action === 'save') {
            $ok = 'Configurações de atualização salvas.';
        } elseif ($action === 'test') {
            $test = app_update_test();
            $updateTestMsg = (string)$test['message'];
            if (!empty($test['ok'])) {
                $ok = $updateTestMsg;
            } else {
                $err = $updateTestMsg;
            }
        } elseif ($action === 'apply') {
            $apply = app_update_apply();
            $updateApplyLog = (string)($apply['log'] ?? '');
            if (!empty($apply['ok'])) {
                $ok = (string)$apply['message'];
            } else {
                $err = (string)($apply['message'] ?? 'Falha ao aplicar atualização.');
            }
            $updateCheck = app_update_check(true);
        }
    }
}

// Contagens de envios
$qContatos = 0;
$qTextos = 0;
$qContatosNovos = 0;
$qTextosNovos = 0;
try {
    $qContatos = (int)$pdo->query('SELECT COUNT(*) FROM contatos')->fetchColumn();
    $qContatosNovos = (int)$pdo->query('SELECT COUNT(*) FROM contatos WHERE lido = 0')->fetchColumn();
    $qTextos = (int)$pdo->query('SELECT COUNT(*) FROM textos_gravacao')->fetchColumn();
    $qTextosNovos = (int)$pdo->query('SELECT COUNT(*) FROM textos_gravacao WHERE lido = 0')->fetchColumn();
} catch (Throwable $e) { /* ok */ }

$title = $sec !== '' ? ($secoes[$sec]['label'] ?? 'Configurações') : 'Configurações';
admin_header($title, 'config');
admin_flash($ok, $err);

// ========== HUB ==========
if ($sec === ''):
?>
<div class="card">
    <p class="muted" style="margin-bottom:16px;">Escolha o que deseja configurar.</p>
    <div class="conteudo-hub">
        <?php foreach ($secoes as $key => $meta): ?>
            <a class="conteudo-hub-card" href="configuracoes.php?sec=<?= e($key) ?>">
                <div class="conteudo-hub-icon"><?= $meta['icon'] ?></div>
                <h3><?= e($meta['label']) ?></h3>
                <p><?= e($meta['desc']) ?></p>
                <?php if ($key === 'formulario_contato'): ?>
                    <div class="conteudo-hub-count"><?= $qContatos ?> envio(s)<?= $qContatosNovos ? " · {$qContatosNovos} novo(s)" : '' ?></div>
                <?php elseif ($key === 'formulario_texto'): ?>
                    <div class="conteudo-hub-count"><?= $qTextos ?> texto(s)<?= $qTextosNovos ? " · {$qTextosNovos} novo(s)" : '' ?></div>
                <?php elseif ($key === 'financeiro'): ?>
                    <div class="conteudo-hub-count"><?= app_finance_ativo() ? 'Módulo ativo' : 'Módulo inativo' ?> · <?= asaas_configured() ? 'Asaas ok' : 'Asaas pendente' ?></div>
                <?php elseif ($key === 'atualizacao'): ?>
                    <div class="conteudo-hub-count"><?= e(app_update_hub_status()) ?></div>
                <?php else: ?>
                    <div class="conteudo-hub-count">Identidade e contato do site</div>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php
// ========== SITE ==========
elseif ($sec === 'site'):
    $vals = [
        'site_nome' => app_setting('site_nome'),
        'site_slogan' => app_setting('site_slogan'),
        'whatsapp' => app_setting('whatsapp'),
        'telefone' => app_setting('telefone'),
        'email' => app_setting('email'),
        'sobre' => app_setting('sobre'),
        'site_logo' => app_setting('site_logo'),
        'site_favicon' => app_setting('site_favicon'),
    ];
?>
<div class="actions" style="margin-bottom:12px;">
    <a class="btn btn-secondary btn-small" href="configuracoes.php">← Configurações</a>
</div>
<div class="card">
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="sec" value="site">
        <input type="hidden" name="site_logo_atual" value="<?= e($vals['site_logo']) ?>">
        <input type="hidden" name="site_favicon_atual" value="<?= e($vals['site_favicon']) ?>">

        <h3 style="margin-bottom:14px;">Identidade</h3>
        <div class="field"><label>Nome do site</label><input name="site_nome" value="<?= e($vals['site_nome']) ?>"></div>
        <div class="field"><label>Slogan</label><input name="site_slogan" value="<?= e($vals['site_slogan']) ?>"></div>

        <div class="field-row">
            <div class="field">
                <label>Logomarca do site</label>
                <p class="muted" style="margin:4px 0 8px;">PNG/JPG — redimensionada (máx. 480×160), salva em PNG leve.</p>
                <?php if ($vals['site_logo']): ?>
                    <p style="margin-bottom:8px;"><img src="../<?= e($vals['site_logo']) ?>" alt="Logo" style="max-height:64px;max-width:240px;background:#0f172a;padding:8px;border-radius:8px;"></p>
                    <label class="muted" style="font-weight:600;"><input type="checkbox" name="remover_logo" value="1"> Remover logo atual</label>
                <?php endif; ?>
                <input type="file" name="site_logo" accept="image/*" style="margin-top:8px;">
            </div>
            <div class="field">
                <label>Favicon do site</label>
                <p class="muted" style="margin:4px 0 8px;">Ícone da aba do navegador — convertido para 64×64 PNG.</p>
                <?php if ($vals['site_favicon']): ?>
                    <p style="margin-bottom:8px;"><img src="../<?= e($vals['site_favicon']) ?>" alt="Favicon" style="width:32px;height:32px;background:#0f172a;padding:4px;border-radius:6px;"></p>
                    <label class="muted" style="font-weight:600;"><input type="checkbox" name="remover_favicon" value="1"> Remover favicon atual</label>
                <?php endif; ?>
                <input type="file" name="site_favicon" accept="image/*" style="margin-top:8px;">
            </div>
        </div>

        <h3 style="margin:20px 0 14px;">Contato exibido no site</h3>
        <div class="field"><label>WhatsApp (somente números, com DDI 55)</label><input name="whatsapp" value="<?= e($vals['whatsapp']) ?>" placeholder="5561999999999"></div>
        <div class="field-row">
            <div class="field"><label>Telefone</label><input name="telefone" value="<?= e($vals['telefone']) ?>"></div>
            <div class="field"><label>E-mail</label><input name="email" value="<?= e($vals['email']) ?>"></div>
        </div>
        <div class="field"><label>Texto “Sobre”</label><textarea name="sobre" rows="4"><?= e($vals['sobre']) ?></textarea></div>

        <button class="btn btn-primary" type="submit">Salvar</button>
    </form>
</div>
<?php
// ========== FORM CONTATO ==========
elseif ($sec === 'formulario_contato'):
?>
<div class="actions" style="margin-bottom:12px;">
    <a class="btn btn-secondary btn-small" href="configuracoes.php">← Configurações</a>
    <a class="btn btn-secondary btn-small" href="contatos.php">Ver envios (<?= $qContatos ?>)</a>
    <a class="btn btn-secondary btn-small" href="../contato.php" target="_blank">Abrir formulário no site</a>
</div>
<div class="card">
    <p class="muted" style="margin-bottom:16px;">
        Formulário padrão do site com: <strong>Nome</strong>, <strong>E-mail</strong>, <strong>Telefone</strong>, <strong>WhatsApp</strong> e <strong>Mensagem / conteúdo</strong>.
        Os envios ficam gravados em <a href="contatos.php">Contatos</a>.
    </p>
    <form method="post">
        <input type="hidden" name="sec" value="formulario_contato">
        <div class="field">
            <label><input type="checkbox" name="form_contato_ativo" value="1" <?= app_setting('form_contato_ativo', '1') === '1' ? 'checked' : '' ?>> Formulário ativo no site</label>
        </div>
        <div class="field"><label>Título da página</label><input name="form_contato_titulo" value="<?= e(app_setting('form_contato_titulo', 'Contato')) ?>"></div>
        <div class="field"><label>Texto de introdução</label><textarea name="form_contato_intro" rows="3"><?= e(app_setting('form_contato_intro')) ?></textarea></div>
        <div class="field"><label>Texto do botão</label><input name="form_contato_btn" value="<?= e(app_setting('form_contato_btn', 'Enviar mensagem')) ?>"></div>
        <button class="btn btn-primary" type="submit">Salvar</button>
    </form>
</div>
<div class="card">
    <h3 style="margin-bottom:10px;">Campos do formulário</h3>
    <ul class="muted" style="margin-left:18px;line-height:1.9;">
        <li><strong>Nome</strong> — obrigatório</li>
        <li><strong>E-mail</strong></li>
        <li><strong>Telefone</strong></li>
        <li><strong>WhatsApp</strong></li>
        <li><strong>Mensagem / conteúdo</strong> — texto livre, obrigatório</li>
    </ul>
</div>
<?php
// ========== FORM TEXTO ==========
elseif ($sec === 'formulario_texto'):
?>
<div class="actions" style="margin-bottom:12px;">
    <a class="btn btn-secondary btn-small" href="configuracoes.php">← Configurações</a>
    <a class="btn btn-secondary btn-small" href="textos.php">Ver textos (<?= $qTextos ?>)</a>
    <a class="btn btn-secondary btn-small" href="../texto.php" target="_blank">Abrir formulário no site</a>
</div>
<div class="card">
    <p class="muted" style="margin-bottom:16px;">
        Formulário exclusivo da <strong>área do cliente</strong> (requer login).
        Cada envio grava o texto <strong>junto com os dados cadastrados do cliente</strong> (nome, e-mail, WhatsApp).
        Veja em <a href="textos.php">Textos a gravar</a>.
    </p>
    <form method="post">
        <input type="hidden" name="sec" value="formulario_texto">
        <div class="field">
            <label><input type="checkbox" name="form_texto_ativo" value="1" <?= app_setting('form_texto_ativo', '1') === '1' ? 'checked' : '' ?>> Formulário ativo no site</label>
        </div>
        <div class="field"><label>Título da página</label><input name="form_texto_titulo" value="<?= e(app_setting('form_texto_titulo', 'Envio de texto para gravação')) ?>"></div>
        <div class="field"><label>Texto de introdução</label><textarea name="form_texto_intro" rows="3"><?= e(app_setting('form_texto_intro')) ?></textarea></div>
        <div class="field"><label>Instruções (acima do campo de texto)</label><textarea name="form_texto_instrucoes" rows="3"><?= e(app_setting('form_texto_instrucoes')) ?></textarea></div>
        <div class="field"><label>Texto do botão</label><input name="form_texto_btn" value="<?= e(app_setting('form_texto_btn', 'Enviar texto')) ?>"></div>
        <button class="btn btn-primary" type="submit">Salvar</button>
    </form>
</div>
<div class="card">
    <h3 style="margin-bottom:10px;">Campos do formulário</h3>
    <ul class="muted" style="margin-left:18px;line-height:1.9;">
        <li><strong>Nome</strong> — obrigatório</li>
        <li><strong>E-mail</strong></li>
        <li><strong>Telefone</strong></li>
        <li><strong>WhatsApp</strong></li>
        <li><strong>Título / referência</strong> (ex.: programa, campanha)</li>
        <li><strong>Texto para gravação</strong> — obrigatório (gravado no banco)</li>
    </ul>
</div>
<?php
// ========== FINANCEIRO / ASAAS ==========
elseif ($sec === 'financeiro'):
    $webhook = (isset($_SERVER['HTTP_HOST'])
        ? (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http')
          . '://' . $_SERVER['HTTP_HOST']
        : '') . app_url('api/asaas-webhook.php');
    $hasKey = asaas_configured();
    $hasWh = asaas_webhook_token() !== '';
?>
<div class="actions" style="margin-bottom:12px;">
    <a class="btn btn-secondary btn-small" href="configuracoes.php">← Configurações</a>
    <a class="btn btn-secondary btn-small" href="financeiro.php">Abrir faturas</a>
</div>

<div class="card">
    <h3 style="margin-bottom:10px;">Módulo financeiro</h3>
    <p class="muted" style="margin-bottom:14px;">
        Integração com o <strong>Asaas</strong> (Pix + boleto via API). Documentação:
        <a href="https://docs.asaas.com/" target="_blank" rel="noopener">docs.asaas.com</a>
    </p>
    <form method="post">
        <input type="hidden" name="sec" value="financeiro">

        <div class="field">
            <label><input type="checkbox" name="finance_ativo" value="1" <?= app_setting('finance_ativo', '0') === '1' ? 'checked' : '' ?>> Financeiro ativo (mostra menu Financeiro para o cliente)</label>
        </div>
        <div class="field">
            <label><input type="checkbox" name="finance_bloquear_atraso" value="1" <?= app_setting('finance_bloquear_atraso', '1') === '1' ? 'checked' : '' ?>> Bloquear conteúdos/textos se houver fatura vencida</label>
        </div>
        <?php
        $keyEnv = asaas_key_environment();
        $efetivoSandbox = asaas_sandbox();
        ?>
        <div class="field">
            <label><input type="checkbox" name="asaas_sandbox" value="1" <?= $efetivoSandbox ? 'checked' : '' ?>> Usar ambiente de homologação (sandbox)</label>
            <p class="muted" style="margin-top:6px;font-size:.82rem;">
                O sistema prioriza o ambiente indicado pela própria API Key
                (<code>$aact_hmlg_…</code> = sandbox, <code>$aact_prod_…</code> = produção),
                para evitar o erro “chave não pertence a este ambiente”.
            </p>
        </div>

        <h3 style="margin:20px 0 12px;font-size:1.05rem;">API Key Asaas</h3>
        <p class="muted" style="margin-bottom:12px;font-size:.85rem;">
            Conta Asaas → <strong>Integrações → API Key</strong>.
            Sandbox: <a href="https://sandbox.asaas.com" target="_blank" rel="noopener">sandbox.asaas.com</a>
            (<code>$aact_hmlg_…</code>).
            Produção: <a href="https://www.asaas.com" target="_blank" rel="noopener">asaas.com</a>
            (<code>$aact_prod_…</code>).
            Variável <code>ASAAS_API_KEY</code> no EasyPanel tem prioridade se preenchida.
        </p>
        <?php if ($keyEnv === 'production' && app_setting('asaas_sandbox', '1') === '1'): ?>
            <div class="alert alert-ok" style="margin-bottom:12px;">
                Detectamos chave de <strong>produção</strong>. O sistema usará a API de produção automaticamente (mesmo com sandbox marcado no formulário).
            </div>
        <?php elseif ($keyEnv === 'sandbox' && app_setting('asaas_sandbox', '1') !== '1'): ?>
            <div class="alert alert-ok" style="margin-bottom:12px;">
                Detectamos chave de <strong>sandbox</strong>. O sistema usará a API de homologação automaticamente.
            </div>
        <?php endif; ?>
        <div class="field">
            <label>API Key</label>
            <input type="password" name="asaas_api_key" value="" placeholder="<?= $hasKey ? '•••••••• (deixe em branco para manter)' : '$aact_hmlg_... ou $aact_prod_...' ?>" autocomplete="new-password">
            <?php if ($hasKey): ?>
                <label style="margin-top:8px;display:block;"><input type="checkbox" name="limpar_asaas_api_key" value="1"> Remover API Key salva</label>
            <?php endif; ?>
        </div>

        <h3 style="margin:20px 0 12px;font-size:1.05rem;">Webhook (recomendado)</h3>
        <p class="muted" style="margin-bottom:12px;font-size:.85rem;">
            No painel Asaas → Integrações → Webhooks, cadastre a URL abaixo e os eventos
            <code>PAYMENT_RECEIVED</code> e <code>PAYMENT_CONFIRMED</code>.
            Se definir um token de autenticação no Asaas, coloque o mesmo valor aqui.
        </p>
        <div class="field">
            <label>Token do webhook (opcional, header <code>asaas-access-token</code>)</label>
            <input type="password" name="asaas_webhook_token" value="" placeholder="<?= $hasWh ? '•••••••• (deixe em branco para manter)' : 'Gere um token forte e use no Asaas' ?>" autocomplete="new-password">
            <?php if ($hasWh): ?>
                <label style="margin-top:8px;display:block;"><input type="checkbox" name="limpar_webhook_token" value="1"> Remover token do webhook</label>
            <?php endif; ?>
        </div>

        <div style="background:#0f172a;border:1px solid var(--line);border-radius:10px;padding:12px 14px;margin:16px 0;">
            <strong>Status</strong>
            <div class="muted" style="margin-top:8px;line-height:1.7;">
                API Key: <?= $hasKey ? '✅ configurada' : '❌ pendente' ?>
                <?php if ($keyEnv): ?> (detectada: <?= $keyEnv === 'sandbox' ? 'sandbox' : 'produção' ?>)<?php endif; ?><br>
                Webhook token: <?= $hasWh ? '✅ definido' : '⚠️ opcional (recomendado em produção)' ?><br>
                Ambiente efetivo: <?= e(asaas_ambiente_label()) ?><br>
                URL API: <code><?= e(asaas_base_url()) ?></code>
            </div>
            <p class="muted" style="margin-top:10px;font-size:.82rem;word-break:break-all;">
                URL do webhook (cadastre no Asaas):<br><code><?= e($webhook) ?></code>
            </p>
            <p class="muted" style="margin-top:8px;font-size:.82rem;">
                Cadastre a <strong>chave Pix</strong> na conta Asaas (menu Pix) para QR Codes estáveis.
                Informe <strong>CPF/CNPJ</strong> no cadastro de cada cliente.
            </p>
        </div>

        <button class="btn btn-primary" type="submit">Salvar financeiro</button>
    </form>
</div>
<?php
// ========== ATUALIZAÇÃO DO SITE ==========
elseif ($sec === 'atualizacao'):
    $local = app_update_local_version();
    $meta = app_update_meta_read();
    $repoVal = app_setting('github_repo', '') !== '' ? app_setting('github_repo') : app_update_repo();
    $branchVal = app_setting('github_branch', '') !== '' ? app_setting('github_branch') : app_update_branch();
    $hasToken = app_update_token_saved();
    $tokenEnv = app_update_token_from_env();
    $logText = $updateApplyLog !== '' ? $updateApplyLog : app_update_log_read();

    if ($updateCheck === null) {
        // status local sem forçar rede na abertura (usa última meta + version)
        $updateCheck = [
            'ok' => true,
            'up_to_date' => null,
            'remote_short' => (string)($meta['last_test_sha'] ?? $meta['last_apply_sha'] ?? ''),
            'remote_message' => (string)($meta['last_test_message'] ?? $meta['last_apply_commit_message'] ?? ''),
        ];
        if (!empty($updateCheck['remote_short']) && strlen($updateCheck['remote_short']) > 7) {
            $updateCheck['remote_short'] = substr($updateCheck['remote_short'], 0, 7);
        }
    }

    $lastApplyAt = (string)($meta['last_apply_at'] ?? $local['updated_at'] ?? '');
    $lastApplyOk = array_key_exists('last_apply_ok', $meta) ? !empty($meta['last_apply_ok']) : null;
    $systemOk = class_exists('ZipArchive') && function_exists('curl_init');
?>
<div class="actions" style="margin-bottom:12px;">
    <a class="btn btn-secondary btn-small" href="configuracoes.php">← Configurações</a>
</div>

<form method="post" id="formAtualizacao">
    <input type="hidden" name="sec" value="atualizacao">
    <input type="hidden" name="action" id="updAction" value="save">

    <div class="update-toolbar">
        <button type="submit" class="btn btn-secondary" onclick="document.getElementById('updAction').value='test';">
            Testar GitHub
        </button>
        <button type="submit" class="btn btn-primary" onclick="document.getElementById('updAction').value='save';">
            Salvar
        </button>
        <button type="submit" class="btn btn-warn" onclick="document.getElementById('updAction').value='apply'; return confirm('Baixar a versão mais recente do GitHub e atualizar os arquivos do sistema?\n\nSerão preservados: banco de dados, uploads, fotos, sessões e config/.');">
            Buscar e aplicar atualização
        </button>
    </div>

    <div class="update-grid">
        <div class="card" style="margin-bottom:0;">
            <h3 style="margin-bottom:6px;">GitHub</h3>
            <p class="muted" style="margin-bottom:14px;font-size:.85rem;">
                Configure o repositório e, se necessário, um token (repo privado ou rate limit da API).
            </p>

            <div class="field">
                <label>Repositório (dono/repositorio)</label>
                <input name="github_repo" value="<?= e($repoVal) ?>" placeholder="medeiroscv/sucesso-no-radio" autocomplete="off">
            </div>
            <div class="field">
                <label>Branch</label>
                <input name="github_branch" value="<?= e($branchVal) ?>" placeholder="master" autocomplete="off">
            </div>
            <div class="field">
                <label>Token do GitHub <?= $hasToken ? '<span class="badge badge-ok">token salvo</span>' : '<span class="badge badge-off">sem token</span>' ?></label>
                <input type="password" name="github_token" value="" placeholder="<?= $hasToken ? '•••••••• (deixe em branco para manter)' : 'ghp_… ou github_pat_… (opcional se o repo for público)' ?>" autocomplete="new-password">
                <?php if ($tokenEnv): ?>
                    <p class="muted" style="margin-top:6px;font-size:.8rem;">Há <code>GITHUB_TOKEN</code> no ambiente (EasyPanel) — tem prioridade sobre o token salvo no painel.</p>
                <?php endif; ?>
            </div>
            <?php if ($hasToken && !$tokenEnv): ?>
                <div class="field">
                    <label><input type="checkbox" name="remover_github_token" value="1"> Remover token salvo</label>
                </div>
            <?php elseif ($hasToken && $tokenEnv): ?>
                <p class="muted" style="font-size:.82rem;">Token efetivo vem do ambiente. Para limpar o do banco, marque e salve após remover a variável no EasyPanel.</p>
                <div class="field">
                    <label><input type="checkbox" name="remover_github_token" value="1"> Remover token salvo no banco</label>
                </div>
            <?php endif; ?>

            <p class="muted" style="margin-top:8px;font-size:.8rem;line-height:1.5;">
                A atualização baixa o ZIP do GitHub e sobrescreve só o código.
                <strong>Não altera</strong> banco, <code>uploads/</code>, <code>data/</code> (sessões/logs) nem <code>config/</code>.
            </p>
        </div>

        <div class="card" style="margin-bottom:0;">
            <h3 style="margin-bottom:6px;">Status atual</h3>
            <p class="muted" style="margin-bottom:14px;font-size:.85rem;">Situação do sistema de atualização e da versão instalada.</p>

            <div class="update-status-row">
                <span class="k">Sistema de atualização</span>
                <span class="v" style="color:<?= $systemOk ? '#86efac' : '#fca5a5' ?>;">
                    <?= $systemOk ? 'Funcionando (cURL + Zip)' : 'Incompleto (falta cURL ou Zip)' ?>
                </span>
            </div>
            <div class="update-status-row">
                <span class="k">Token configurado</span>
                <span class="v"><?= $hasToken ? ($tokenEnv ? 'Sim (ambiente)' : 'Sim (banco)') : 'Não (repo público ok)' ?></span>
            </div>
            <div class="update-status-row">
                <span class="k">Repositório</span>
                <span class="v"><code><?= e(app_update_repo()) ?></code></span>
            </div>
            <div class="update-status-row">
                <span class="k">Branch</span>
                <span class="v"><code><?= e(app_update_branch()) ?></code></span>
            </div>
            <div class="update-status-row">
                <span class="k">SHA / commit instalado</span>
                <span class="v">
                    <?php if ($local['short'] !== ''): ?>
                        <code title="<?= e($local['commit']) ?>"><?= e($local['short']) ?></code>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </span>
            </div>
            <div class="update-status-row">
                <span class="k">Mensagem do commit</span>
                <span class="v" style="font-weight:600;font-size:.85rem;"><?= e($local['message'] !== '' ? $local['message'] : '—') ?></span>
            </div>
            <div class="update-status-row">
                <span class="k">Última atualização</span>
                <span class="v">
                    <?php if ($lastApplyAt !== ''): ?>
                        <?= e(date('d/m/Y H:i:s', strtotime($lastApplyAt))) ?>
                        <?php if ($lastApplyOk === true): ?>
                            <span class="badge badge-ok">ok</span>
                        <?php elseif ($lastApplyOk === false): ?>
                            <span class="badge badge-off">falhou</span>
                        <?php endif; ?>
                    <?php else: ?>
                        Ainda não aplicada pelo painel
                    <?php endif; ?>
                </span>
            </div>
            <?php if (!empty($meta['last_apply_files'])): ?>
            <div class="update-status-row">
                <span class="k">Arquivos na última aplicação</span>
                <span class="v"><?= (int)$meta['last_apply_files'] ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($meta['last_test_at'])): ?>
            <div class="update-status-row">
                <span class="k">Último teste GitHub</span>
                <span class="v">
                    <?= e(date('d/m/Y H:i', strtotime((string)$meta['last_test_at']))) ?>
                    <?php if (!empty($meta['last_test_ok'])): ?>
                        <span class="badge badge-ok">ok</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="card">
    <h3 style="margin-bottom:10px;">Log da última operação</h3>
    <?php if (trim($logText) === ''): ?>
        <p class="muted">Nenhuma operação registrada ainda. Use <strong>Testar GitHub</strong> ou <strong>Buscar e aplicar atualização</strong>.</p>
    <?php else: ?>
        <div class="update-log"><?= e($logText) ?></div>
    <?php endif; ?>
</div>
<?php
endif;

admin_footer();
