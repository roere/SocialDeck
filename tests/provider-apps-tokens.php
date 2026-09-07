<?php
require dirname(__DIR__).'/api/bootstrap.php';
if(envValue('APP_ENV')!=='test')throw new RuntimeException('Test environment required');
$a=db()->query("SELECT a.* FROM social_accounts a JOIN provider_apps p ON p.id=a.provider_app_id WHERE p.client_id='apps-client-a'")->fetch();
$b=db()->query("SELECT a.* FROM social_accounts a JOIN provider_apps p ON p.id=a.provider_app_id WHERE p.client_id='apps-client-b'")->fetch();
if(!$a||!$b||$a['id']===$b['id']||decryptSecret($a['access_token_encrypted'])!=='apps-token-a'||decryptSecret($a['refresh_token_encrypted'])!=='apps-refresh-a'||!str_starts_with($a['access_token_encrypted'],'spsec:')||$b['access_token_encrypted']!==null||$b['refresh_token_encrypted']!==null)throw new RuntimeException('App token isolation failed');
echo "PASS Provider Apps Token A verschlüsselt erhalten, nur Token B getrennt\n";
if((int)db()->query('SELECT COUNT(*) FROM provider_configs WHERE client_secret_encrypted IS NOT NULL OR client_id IS NOT NULL')->fetchColumn()!==0)throw new RuntimeException('Duplicated credentials');
echo "PASS Provider Apps keine doppelten Provider-Credentials\n";
