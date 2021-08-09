<?php

namespace Simpletools\Db\IndexedFile;

class File
{
    protected $_dbStream;
    protected $_dbIndex;
    protected $_seekPosition = 0;
    protected $_tombstoneChar = '-';
    protected static $_indexStoreClass;

    const ROW_FORMAT_VERSION = '1';

    public static function indexStoreClass($className)
    {
        self::$_indexStoreClass = $className;
    }

    public function __construct($dbFilePath = null, $truncate = false)
    {
        $indexStoreClass = self::$_indexStoreClass;
        if(!$indexStoreClass)
            $indexStoreClass = 'Simpletools\Db\IndexedFile\IndexStore\ArrayIndexStore';
        elseif(!class_exists($indexStoreClass))
            throw new \Exception("$indexStoreClass not found",404);

        $this->_dbIndex = new $indexStoreClass();

        if (!$dbFilePath)
            $this->_dbStream = tmpfile();
        else
            $this->_dbStream = fopen($dbFilePath, 'c+');

        flock($this->_dbStream,LOCK_EX);

        if(!$truncate && $dbFilePath)
        {
            fseek($this->_dbStream, 0, SEEK_END);
            $this->_seekPosition = ftell($this->_dbStream);

            if ($this->_seekPosition > 0)
                $this->_reconstructIndex();
        }
        elseif($truncate && $dbFilePath)
        {
            $this->truncate();
        }

        $this->_log('startup', [
            'time' => time(),
            'rowFormatVer' => self::ROW_FORMAT_VERSION
        ]);
    }

    public function truncate()
    {
        ftruncate($this->_dbStream,0);
        fseek($this->_dbStream, 0, SEEK_CUR);

        $this->_dbIndex->flush();
        $this->_seekPosition = 0;

        $this->_log('truncate', [
            'time' => time(),
            'rowFormatVer' => self::ROW_FORMAT_VERSION
        ]);
    }

    protected function _reconstructIndex()
    {
        foreach($this->_iterate(true) as $row)
        {
            if(isset($row->log)) continue;

            $this->_dbIndex->insert($row->k,base_convert($row->pos,10,36));
        }
    }

    protected function _log($type,$meta)
    {
        fseek($this->_dbStream, $this->_seekPosition, SEEK_SET);

        $log = [
            'log'   => [
                'type'    => $type,
                'meta'    => $meta
            ]
        ];

        fwrite($this->_dbStream, json_encode($log) . "\n");
        $this->_seekPosition = ftell($this->_dbStream);
    }

    public function log($meta)
    {
        $this->_log('user',$meta);
    }

    protected function _indexPosition($id)
    {
        $pos = $this->_dbIndex->read(((string) $id));
        if ($pos === "" || $pos === null) return false;

        return base_convert($pos, 36, 10);
    }

    protected function _tombstone($position)
    {
        fseek($this->_dbStream, $position, SEEK_SET);
        fwrite($this->_dbStream, $this->_tombstoneChar);
        fseek($this->_dbStream, $this->_seekPosition, SEEK_SET);
    }

    protected function _insert($key, $value, $position = null, $insertIgnore=false)
    {
        fseek($this->_dbStream, $this->_seekPosition, SEEK_SET);

        if ($position === null) //internal otherwise, mainly used by upsert
        {
            $position = $this->_indexPosition($key);

            if($insertIgnore && $position !== false)
                return false;
        }

        if ($position !== false)
        {
            $this->_tombstone($position);
        }

        $this->_dbIndex->insert((string)$key, base_convert($this->_seekPosition, 10, 36));

        $line = json_encode(["k"=>$key,"v"=>$value]);
        fwrite($this->_dbStream, $line . "\n");
        $this->_seekPosition = ftell($this->_dbStream);

        return true;
    }

    public function insertIgnore($key, $value)
    {
        return $this->_insert($key,$value,null,true);
    }

    public function insert($key, $value)
    {
        return $this->_insert($key,$value);
    }

    public function upsert($key, $updater)
    {
        fseek($this->_dbStream, $this->_seekPosition, SEEK_SET);

        $position = $this->_indexPosition($key);
        if ($position === false) {
            $this->_insert($key, $updater(null), $position);
        } else {
            $row = $this->read($key);
            $this->_tombstone($position);
            $this->_insert($key, $updater($row));
        }
    }

    public function read($key)
    {
        $position = $this->_indexPosition($key);
        if ($position === false) return null;

        fseek($this->_dbStream, $position, SEEK_SET);
        $row = fgets($this->_dbStream);
        return json_decode($row)->v;
    }

    public function remove($key)
    {
        $position = $this->_indexPosition($key);
        if ($position === false) return false;
        $this->_tombstone($position);

        $this->_dbIndex->remove($key);

        return true;
    }

    public function iterate()
    {
        foreach($this->_iterate() as $key => $value)
        {
            yield $key => $value;
        }
    }

    public function _iterate($returnRawRow=false)
    {
        fseek($this->_dbStream, 0, SEEK_SET);

        while (($line = fgets($this->_dbStream)) !== false)
        {
            if (substr($line, 0, 1) === $this->_tombstoneChar)
                continue;

            $row = json_decode($line);
            $pos = ftell($this->_dbStream);
            $pos -= strlen($line);
            $row->pos = $pos;

            if($returnRawRow)
            {
                yield $row;
                continue;
            }

            if(isset($row->log)) continue;

            yield $row->k => $row->v;
        }
    }

    public function __destruct()
    {
        if ($this->_dbStream)
        {
            flock($this->_dbStream,LOCK_UN);
            fclose($this->_dbStream);
            unset($this->_dbIndex);
        }
    }
}