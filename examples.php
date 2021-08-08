<?php
/*
 * Normally autoloaded
 */
require __DIR__ . '/src/Simpletools/Db/IndexedFile/File.php';
require __DIR__ . '/src/Simpletools/Db/IndexedFile/IndexStore/IndexStoreInterface.php';
require __DIR__ . '/src/Simpletools/Db/IndexedFile/IndexStore/ArrayIndexStore.php';

$start = microtime(true);

use Simpletools\Db\IndexedFile;

//used by default
//IndexedFile::indexStoreClass('Simpletools\Db\IndexedFile\IndexStore\ArrayIndexStore');

$indexedFile = new IndexedFile\File();

$indexedFile->insert('key',(object) [
    'counter'   => 0
]);

$indexedFile->upsert('key',function($row){
    if($row)
    {
        $row->counter++;
        return $row;
    }
    else
    {
        return [
            'counter'   => 0
        ];
    }
});

var_dump($indexedFile->read('key'));