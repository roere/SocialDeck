<?php
declare(strict_types=1);
require dirname(__DIR__).'/api/bootstrap.php';
if(envValue('APP_ENV')!=='test')throw new RuntimeException('Test environment required');
$pdo=db();$secret=encryptSecret('migration-fixture-secret');$token=encryptSecret('migration-fixture-access');$refresh=encryptSecret('migration-fixture-refresh');
$pdo->prepare("INSERT INTO provider_configs(provider_id,enabled,client_id,client_secret_encrypted,redirect_uri,scopes,created_at,updated_at) VALUES('linkedin',1,'migration-fixture',?,'http://localhost/callback','openid profile w_member_social',NOW(),NOW())")->execute([$secret]);
$pdo->prepare("INSERT INTO social_accounts(provider_id,external_account_id,display_name,access_token_encrypted,refresh_token_encrypted,token_expires_at,scopes,status,created_at,updated_at) VALUES('linkedin','migration-member','Migration Test',?,?,DATE_ADD(NOW(),INTERVAL 1 DAY),'openid profile w_member_social','connected',NOW(),NOW())")->execute([$token,$refresh]);$account=(int)$pdo->lastInsertId();
upsertLinkedInPersonalChannel($pdo,$account,'migration-member','Migration Test',['w_member_social'],date('Y-m-d H:i:s'));
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/012-provider-apps.sql');$pdo->exec($sql);$app=$pdo->query("SELECT * FROM provider_apps WHERE provider_id='linkedin'")->fetch();
function verifyMigration(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);echo "PASS Migration $name\n";}
verifyMigration($app['name']==='LinkedIn Standard'&&$app['client_id']==='migration-fixture'&&$app['configured_scopes']==='openid profile w_member_social'&&(int)$app['enabled']===1,'bestehende App übernommen');
verifyMigration($app['client_secret_encrypted']===$secret,'Secret unverändert verschlüsselt');
$a=$pdo->query("SELECT * FROM social_accounts WHERE id=$account")->fetch();verifyMigration((int)$a['provider_app_id']===(int)$app['id'],'Account zugeordnet');
verifyMigration($a['access_token_encrypted']===$token&&$a['refresh_token_encrypted']===$refresh,'Tokens unverändert');
verifyMigration((int)providerCapabilityAccount('linkedin','posting')['id']===$account,'Posting-Verbindung weiterhin verfügbar');
verifyMigration($pdo->query("SELECT client_secret_encrypted FROM provider_configs WHERE provider_id='linkedin'")->fetchColumn()===null,'keine doppelten Credentials');
$pdo->exec($sql);verifyMigration((int)$pdo->query('SELECT COUNT(*) FROM provider_apps')->fetchColumn()===1,'Wiederholung ohne Duplikate');
$pdo->exec("DELETE FROM social_channels WHERE social_account_id=$account");$pdo->exec("DELETE FROM social_accounts WHERE id=$account");$pdo->exec('DELETE FROM provider_apps');$pdo->exec('DELETE FROM provider_configs');
