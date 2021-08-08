<?php

namespace Simpletools\Db\IndexedFile\IndexStore;

class ArrayIndexStore implements IndexStoreInterface
{
    protected $_index = [];

    public function insert($key,$value)
    {
        $this->_index[$key] = $value;
    }

    public function remove($key)
    {
        unset($this->_index[$key]);
    }

    public function read($key)
    {
        return @$this->_index[$key];
    }

    public function exists($key)
    {
        return isset($this->_index[$key]);
    }

    public function length()
    {
        return count($this->_index);
    }

    public function flush()
    {
        $this->_index = [];
    }
}