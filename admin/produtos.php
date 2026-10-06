<?php
require_once __DIR__ . '/_layout.php';

$pdo=app_pdo();
$ok=$err='';
$edit=null;
$tipos=app_produto_tipos_vitrine();
$ciclos=app_produto_ciclos_vitrine();

function produto_parse_valor(string $raw): int {
    $raw=trim($raw);
    $raw=preg_replace('/[^0-9,.-]/','',$raw) ?? '0';
    if(str_contains($raw,',')){
        $raw=str_replace('.','',$raw);
        $raw=str_replace(',','.',$raw);
    }
    return (int)round(max(0,(float)$raw)*100);
}

function produto_admin_by_id(int $id): ?array {
    if($id<=0)return null;
    $st=app_pdo()->prepare('SELECT * FROM produtos WHERE id=? LIMIT 1');
    $st->execute([$id]);
    $r=$st->fetch();
    return $r?:null;
}

if(isset($_GET['del'])){
    $id=(int)$_GET['del'];
    try{
        $st=$pdo->prepare('SELECT capa FROM produtos WHERE id=?');
        $st->execute([$id]);
        $row=$st->fetch();
        $pdo->prepare('DELETE FROM produtos WHERE id=?')->execute([$id]);
        if($row && !empty($row['capa'])) admin_delete_local_upload((string)$row['capa']);
        header('Location: produtos.php?ok=deleted'); exit;
    }catch(Throwable $e){
        $err='Não foi possível excluir este produto porque existem vínculos antigos. Desative-o para removê-lo da vitrine.';
    }
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $id=(int)($_POST['id']??0);
    $nome=trim((string)($_POST['nome']??''));
    $slug=trim((string)($_POST['slug']??''));
    $tipo=(string)($_POST['tipo']??'plano');
    if(!isset($tipos[$tipo]))$tipo='plano';
    $ciclo=(string)($_POST['ciclo']??'mensal');
    if(!isset($ciclos[$ciclo]))$ciclo='mensal';
    if(in_array($tipo,['avulso','pacote'],true))$ciclo='unico';
    $cent=produto_parse_valor((string)($_POST['valor']??'0'));
    $descricao=trim((string)($_POST['descricao']??''));
    $recursos=trim((string)($_POST['recursos']??''));
    $whmcs=trim((string)($_POST['whmcs_url']??''));
    $botao=trim((string)($_POST['botao_texto']??'Contratar')) ?: 'Contratar';
    $wa=trim((string)($_POST['whatsapp_msg']??''));
    $ordem=(int)($_POST['ordem']??0);
    $ativo=!empty($_POST['ativo'])?1:0;
    $mostrar=!empty($_POST['mostrar_site'])?1:0;
    $destaque=!empty($_POST['destaque'])?1:0;
    $exibirPreco=!empty($_POST['exibir_preco'])?1:0;
    $capaAtual=trim((string)($_POST['capa_atual']??''));
    $capa=$capaAtual;

    if(!empty($_POST['remover_capa'])){
        if($capaAtual!=='')admin_delete_local_upload($capaAtual);
        $capa='';
    }else{
        $nova=admin_upload('capa','produtos',[],900,900,84);
        if($nova!==''){
            if($capaAtual!=='' && $capaAtual!==$nova)admin_delete_local_upload($capaAtual);
            $capa=$nova;
        }
    }

    if($nome===''){
        $err='Informe o nome do produto.';
    }elseif($whmcs!=='' && !filter_var($whmcs,FILTER_VALIDATE_URL)){
        $err='O link do WHMCS precisa ser uma URL válida, começando por http:// ou https://.';
    }else{
        try{
            $baseSlug=app_slug($slug!==''?$slug:$nome);
            $slugTry=$baseSlug; $n=2;
            while(true){
                $st=$pdo->prepare('SELECT id FROM produtos WHERE slug=? AND id<>? LIMIT 1');
                $st->execute([$slugTry,$id]);
                if(!$st->fetch())break;
                $slugTry=$baseSlug.'-'.$n++;
            }
            $slug=$slugTry;
            if($id>0){
                $pdo->prepare(
                    'UPDATE produtos SET nome=?,slug=?,tipo=?,ciclo=?,valor_centavos=?,descricao=?,recursos=?,destaque=?,ativo=?,mostrar_site=?,ordem=?,botao_texto=?,whatsapp_msg=?,whmcs_url=?,capa=?,exibir_preco=?,updated_at=NOW() WHERE id=?'
                )->execute([$nome,$slug,$tipo,$ciclo,$cent,$descricao,$recursos,$destaque,$ativo,$mostrar,$ordem,$botao,$wa,$whmcs,$capa,$exibirPreco,$id]);
            }else{
                $pdo->prepare(
                    'INSERT INTO produtos (nome,slug,tipo,ciclo,valor_centavos,descricao,recursos,destaque,ativo,mostrar_site,ordem,botao_texto,whatsapp_msg,whmcs_url,capa,exibir_preco,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
                )->execute([$nome,$slug,$tipo,$ciclo,$cent,$descricao,$recursos,$destaque,$ativo,$mostrar,$ordem,$botao,$wa,$whmcs,$capa,$exibirPreco]);
                $id=(int)$pdo->lastInsertId();
            }
            admin_salvar_produto_demonstrativos($id);
            header('Location: produtos.php?id='.$id.'&ok=1'); exit;
        }catch(Throwable $e){
            $err='Erro ao salvar: '.$e->getMessage();
        }
    }
    $edit=[
        'id'=>$id,'nome'=>$nome,'slug'=>$slug,'tipo'=>$tipo,'ciclo'=>$ciclo,'valor_centavos'=>$cent,
        'descricao'=>$descricao,'recursos'=>$recursos,'destaque'=>$destaque,'ativo'=>$ativo,'mostrar_site'=>$mostrar,
        'ordem'=>$ordem,'botao_texto'=>$botao,'whatsapp_msg'=>$wa,'whmcs_url'=>$whmcs,'capa'=>$capa,'exibir_preco'=>$exibirPreco
    ];
}

if($edit===null && (isset($_GET['id'])||isset($_GET['novo']))){
    if(!empty($_GET['id'])){
        $edit=produto_admin_by_id((int)$_GET['id']);
    }else{
        $edit=[
            'id'=>0,'nome'=>'','slug'=>'','tipo'=>'plano','ciclo'=>'mensal','valor_centavos'=>0,
            'descricao'=>'','recursos'=>'','destaque'=>0,'ativo'=>1,'mostrar_site'=>1,'ordem'=>0,
            'botao_texto'=>'Contratar','whatsapp_msg'=>'','whmcs_url'=>'','capa'=>'','exibir_preco'=>1
        ];
    }
}

$lista=[];
try{$lista=$pdo->query('SELECT * FROM produtos ORDER BY destaque DESC, ordem ASC, nome ASC')->fetchAll()?:[];}catch(Throwable $e){}
if(isset($_GET['ok']))$ok=($_GET['ok']==='deleted'?'Produto excluído.':'Salvo com sucesso.');

admin_header($edit?(!empty($edit['id'])?'Editar produto':'Novo produto'):'Produtos','produtos');
admin_flash($ok,$err);

if($edit):
    $valorBr=number_format(((int)($edit['valor_centavos']??0))/100,2,',','.');
?>
<div class="actions" style="margin-bottom:12px;">
    <a class="btn btn-secondary btn-small" href="produtos.php">← Lista</a>
    <?php if(!empty($edit['slug'])): ?><a class="btn btn-secondary btn-small" href="../produto.php?slug=<?= rawurlencode((string)$edit['slug']) ?>" target="_blank">Ver no site</a><?php endif; ?>
</div>
<div class="card">
<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
    <input type="hidden" name="capa_atual" value="<?= e($edit['capa']??'') ?>">

    <h3 style="margin-bottom:14px;">Apresentação comercial</h3>
    <div class="field-row">
        <div class="field"><label>Nome *</label><input name="nome" required value="<?= e($edit['nome']??'') ?>"></div>
        <div class="field"><label>Slug</label><input name="slug" value="<?= e($edit['slug']??'') ?>" placeholder="gerado automaticamente"></div>
    </div>
    <div class="field-row">
        <div class="field"><label>Tipo</label><select name="tipo"><?php foreach($tipos as $k=>$m): ?><option value="<?= e($k) ?>" <?= ($edit['tipo']??'')===$k?'selected':'' ?>><?= e($m['icon'].' '.$m['label']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Periodicidade</label><select name="ciclo"><?php foreach($ciclos as $k=>$m): ?><option value="<?= e($k) ?>" <?= ($edit['ciclo']??'')===$k?'selected':'' ?>><?= e($m['label']) ?></option><?php endforeach; ?></select><p class="muted" style="font-size:.78rem;margin-top:4px;">Informação apenas comercial. A cobrança é feita no WHMCS.</p></div>
    </div>
    <div class="field-row">
        <div class="field"><label>Preço exibido (R$)</label><input name="valor" value="<?= e($valorBr) ?>" placeholder="99,90"></div>
        <div class="field"><label>Ordem na vitrine</label><input type="number" name="ordem" value="<?= (int)($edit['ordem']??0) ?>"></div>
    </div>
    <div class="field"><label>Descrição</label><textarea name="descricao" rows="4"><?= e($edit['descricao']??'') ?></textarea></div>
    <div class="field"><label>Recursos / benefícios (um por linha)</label><textarea name="recursos" rows="6"><?= e($edit['recursos']??'') ?></textarea></div>

    <div class="field">
        <label>Capa do produto</label>
        <p class="muted" style="margin:4px 0 8px;">Imagem usada nos cards e na página individual.</p>
        <?php if(!empty($edit['capa'])): ?>
            <p><img class="thumb" src="../<?= e($edit['capa']) ?>" alt="" style="max-width:180px;"></p>
            <label class="muted"><input type="checkbox" name="remover_capa" value="1"> Remover capa atual</label>
        <?php endif; ?>
        <input type="file" name="capa" accept="image/*" style="margin-top:8px;">
    </div>

    <h3 style="margin:22px 0 12px;">Contratação via WHMCS</h3>
    <div class="field"><label>Link direto do WHMCS</label><input type="url" name="whmcs_url" value="<?= e($edit['whmcs_url']??'') ?>" placeholder="https://seu-whmcs.com/cart.php?a=add&pid=..."><p class="muted" style="font-size:.78rem;margin-top:4px;">Se ficar vazio, o botão direciona o interessado para o WhatsApp.</p></div>
    <div class="field-row">
        <div class="field"><label>Texto do botão</label><input name="botao_texto" value="<?= e($edit['botao_texto']??'Contratar') ?>"></div>
        <div class="field"><label>Mensagem de fallback no WhatsApp</label><input name="whatsapp_msg" value="<?= e($edit['whatsapp_msg']??'') ?>" placeholder="Olá! Quero contratar..."></div>
    </div>

    <div class="field" style="display:flex;gap:18px;flex-wrap:wrap;">
        <label><input type="checkbox" name="ativo" value="1" <?= !empty($edit['ativo'])?'checked':'' ?>> Ativo</label>
        <label><input type="checkbox" name="mostrar_site" value="1" <?= !empty($edit['mostrar_site'])?'checked':'' ?>> Mostrar na vitrine</label>
        <label><input type="checkbox" name="destaque" value="1" <?= !empty($edit['destaque'])?'checked':'' ?>> Destaque</label>
        <label><input type="checkbox" name="exibir_preco" value="1" <?= !empty($edit['exibir_preco'])?'checked':'' ?>> Exibir preço</label>
    </div>

    <?php admin_bloco_produto_demonstrativos((int)($edit['id']??0)); ?>

    <div class="actions" style="margin-top:18px;">
        <button class="btn btn-primary" type="submit">Salvar produto</button>
        <a class="btn btn-secondary" href="produtos.php">Cancelar</a>
    </div>
</form>
</div>
<?php else: ?>
<div class="card">
    <div class="actions" style="margin-bottom:14px;justify-content:space-between;width:100%;">
        <p class="muted" style="margin:0;">Gerencie a vitrine. Cada produto pode apontar diretamente para o seu item correspondente no WHMCS.</p>
        <a class="btn btn-primary" href="produtos.php?novo=1">+ Novo produto</a>
    </div>
    <table>
        <thead><tr><th>Produto</th><th>Tipo</th><th>Preço</th><th>WHMCS</th><th>Vitrine</th><th></th></tr></thead>
        <tbody>
        <?php foreach($lista as $p): $tipo=$tipos[$p['tipo']??'']??['label'=>$p['tipo']??'Produto']; ?>
            <tr>
                <td><strong><?= e($p['nome']) ?></strong><?php if(!empty($p['destaque'])): ?> <span class="badge badge-ok">destaque</span><?php endif; ?><?php if(empty($p['ativo'])): ?> <span class="badge badge-off">inativo</span><?php endif; ?></td>
                <td><?= e($tipo['label']) ?></td>
                <td><?= !empty($p['exibir_preco'])?e(app_produto_preco_br((int)$p['valor_centavos'])):'Oculto' ?></td>
                <td><?= !empty($p['whmcs_url'])?'<span class="badge badge-ok">configurado</span>':'<span class="badge badge-off">pendente</span>' ?></td>
                <td><?= !empty($p['mostrar_site'])?'Sim':'Não' ?></td>
                <td class="actions"><a class="btn btn-secondary btn-small" href="produtos.php?id=<?= (int)$p['id'] ?>">Editar</a><a class="btn btn-danger btn-small" href="produtos.php?del=<?= (int)$p['id'] ?>" onclick="return confirm('Excluir este produto? Se houver vínculos antigos, a exclusão será bloqueada.');">Excluir</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if(!$lista): ?><tr><td colspan="6" class="muted">Nenhum produto cadastrado.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; admin_footer(); ?>
