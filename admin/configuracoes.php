<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/asaas.php';

$pdo = app_pdo();
$secoes = app_config_secoes();
$ok = $err = '';
$sec = trim((string)($_GET['sec'] ?? $_POST['sec'] ?? ''));
if ($sec !== '' && !isset($secoes[$sec])) {
    $sec = '';
}

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
        <div class="field">
            <label><input type="checkbox" name="asaas_sandbox" value="1" <?= asaas_sandbox() ? 'checked' : '' ?>> Usar ambiente de homologação (sandbox)</label>
        </div>

        <h3 style="margin:20px 0 12px;font-size:1.05rem;">API Key Asaas</h3>
        <p class="muted" style="margin-bottom:12px;font-size:.85rem;">
            Conta Asaas → <strong>Integrações → API Key</strong>. Gere uma chave de sandbox (<code>$aact_hmlg_…</code>)
            ou produção (<code>$aact_prod_…</code>). Variável de ambiente <code>ASAAS_API_KEY</code> no EasyPanel tem prioridade se preenchida.
        </p>
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
                API Key: <?= $hasKey ? '✅ configurada' : '❌ pendente' ?><br>
                Webhook token: <?= $hasWh ? '✅ definido' : '⚠️ opcional (recomendado em produção)' ?><br>
                Ambiente: <?= asaas_sandbox() ? 'Homologação (sandbox)' : 'Produção' ?>
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
endif;

admin_footer();
