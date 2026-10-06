<?php
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok'=>false,
    'message'=>'O módulo financeiro local foi desativado. Contratações e cobranças são processadas pelo WHMCS.'
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
