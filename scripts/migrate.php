<?php
declare(strict_types=1);
require dirname(__DIR__).'/api/bootstrap.php';
if(envValue('APP_ENV','local')==='production'){fwrite(STDERR,"Migration ist in Produktion über dieses Skript deaktiviert.\n");exit(2);}
try{
    foreach(['003-text-blocks.sql','004-text-block-system-model.sql','005-email-settings.sql'] as $name){
        $path=dirname(__DIR__).'/database/migrations/'.$name;
        $contents=file_get_contents($path);
        if($contents===false)throw new RuntimeException("Migration kann nicht gelesen werden: $path");
        $sql=trim($contents);
        if($sql==='')throw new RuntimeException("Migration ist leer: $path");
        db()->exec($sql);
    }
    seedSystemTextBlocks(db());echo "Migrationen und System-Textbaustein-Katalog wurden abgeglichen.\n";
}catch(Throwable $exception){fwrite(STDERR,$exception->getMessage()."\n");exit(1);}
