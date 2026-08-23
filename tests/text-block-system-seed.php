<?php
declare(strict_types=1);
require dirname(__DIR__).'/api/bootstrap.php';
$catalog=[['key'=>'test_required_system','title'=>'Test-Systembaustein','category'=>'Test','defaultContent'=>'Standard','sortOrder'=>7]];
db()->prepare('DELETE FROM text_blocks WHERE block_key=?')->execute(['test_required_system']);
seedSystemTextBlocks(db(),$catalog);$row=db()->query("SELECT content,is_active,is_system FROM text_blocks WHERE block_key='test_required_system'")->fetch();
if(!$row||$row['content']!=='Standard'||!(bool)$row['is_active']||!(bool)$row['is_system'])throw new RuntimeException('Systembaustein wurde nicht korrekt angelegt');echo "PASS fehlender Systembaustein wird angelegt\n";
db()->exec("UPDATE text_blocks SET content='Admin-Text',is_active=0,is_system=0 WHERE block_key='test_required_system'");seedSystemTextBlocks(db(),$catalog);$row=db()->query("SELECT content,is_active,is_system FROM text_blocks WHERE block_key='test_required_system'")->fetch();
if($row['content']!=='Admin-Text'||!(bool)$row['is_active']||!(bool)$row['is_system'])throw new RuntimeException('Systembaustein-Abgleich hat Inhalt oder Schutz falsch behandelt');echo "PASS Systemkatalog bewahrt Admin-Inhalt und stellt Schutz wieder her\n";
db()->prepare('DELETE FROM text_blocks WHERE block_key=?')->execute(['test_required_system']);
