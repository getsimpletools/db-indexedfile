<?php

namespace Simpletools\Db\IndexedFile\IndexStore;

interface IndexStoreInterface
{
    public function insert(string $key,string $value);
    public function remove(string $key);
    public function read(string $key);
    public function exists(string $key);
    public function flush();
    public function length();
}