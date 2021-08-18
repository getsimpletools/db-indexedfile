<?php

namespace Simpletools\Db\IndexedFile;

class File
{
	protected $_dbStream;
	protected $_dbIndex;
	protected $_seekPosition = 0;
	protected $_tombstoneChar = '-';
	protected static $_indexStoreClass;
	protected $_sort;

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

	public function sort($field, $order ='ASC', $type ='string', Bool $includeSortStats = true, $sortFilePath = null, $sortOutputFile=null)
	{
		$this->_sort = (object) [
				'field' => $field,
				'order' => strtoupper($order) =='ASC' ? 'ASC' : 'DESC',
				'type' => strtolower($type) == 'string' ? 'string' : 'int',
				'fp' => !$sortFilePath ? tmpfile() : fopen($sortFilePath, 'c+'),
				'sortedFp' => !$sortOutputFile ? tmpfile() : fopen($sortOutputFile, 'c+'),
				'sorted' => false,
				'includeSortStats' => $includeSortStats,
				'statsIncluded' => false,
				'sum' => 0,
				'count' =>0,
				'labels' =>[]
		];

		flock($this->_sort->fp,LOCK_EX);
		flock($this->_sort->sortedFp,LOCK_EX);
	}

	public function refreshSortStats()
	{
		$this->_sort->statsIncluded = true;
		fseek($this->_sort->sortedFp, 0, SEEK_SET);
		$position = 1;
		$sort = $this->_sort;
		while (($data = fgetcsv($this->_sort->sortedFp, 1000, ",")) !== FALSE)
		{
			$this->upsert($data[1], function ($row) use ($position,$sort, $data) {
				if(!$row)
					$row = (object)[];

				$row->_sort =[
						'position' => $position,
				];

				if($this->_sort->type =='int')
					$row->_sort['percent'] = round($data[0]/$this->_sort->sum*100,2);

				return $row;
			});

			$position++;
		}
	}

	public function sortIterate()
	{
		fseek($this->_sort->sortedFp, 0, SEEK_SET);
		while (($data = fgetcsv($this->_sort->sortedFp, 1000, ",")) !== FALSE)
		{
			$item = $this->read($data[1]);
			yield $data[1] => $item;
		}
	}

	protected function addToSort($key, $value)
	{
		fputcsv($this->_sort->fp,[$value, $key]);

		$this->_sort->count++;
		if($this->_sort->type =='int')
			$this->_sort->sum +=$value;
	}

	public function runSort()
	{
		if(!$this->_sort)
		{
			$this->close();
			throw new \Exception('Sort not defined',400);
		}

		$meta_data = stream_get_meta_data($this->_sort->fp);
		$input = realpath($meta_data["uri"]);

		$meta_data = stream_get_meta_data($this->_sort->sortedFp);
		$output = realpath($meta_data["uri"]);

		shell_exec('LC_ALL=C sort -S 500M -k1'.($this->_sort->order=='DESC'?'r':'').($this->_sort->type=='int'?'n':'f').' -o '.$output.' '.$input);

		if($this->_sort->includeSortStats)
		{
			$this->refreshSortStats();
		}
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
		if($key==='' || $key ===false || $key === null)
			throw new \Exception("You can't insert empty key",400);

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
		elseif ($this->_sort && !$this->_sort->statsIncluded)
		{
			if(is_array($value) && isset($value[$this->_sort->field]))
				$this->addToSort($key,$value[$this->_sort->field]);
			elseif(is_object($value) && isset($value->{$this->_sort->field}))
				$this->addToSort($key,$value->{$this->_sort->field});
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

	protected function _iterate($returnRawRow=false)
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

	public function close()
	{
		$this->__destruct();
	}

	public function __destruct()
	{
		if ($this->_dbStream)
		{
			flock($this->_dbStream,LOCK_UN);
			fclose($this->_dbStream);
			$this->_dbStream = null;
			unset($this->_dbIndex);
		}
		if($this->_sort)
		{
			if($this->_sort->fp)
			{
				flock($this->_sort->fp,LOCK_UN);
				fclose($this->_sort->fp);
			}
			if($this->_sort->sortedFp)
			{
				flock($this->_sort->sortedFp,LOCK_UN);
				fclose($this->_sort->sortedFp);
			}
			$this->_sort = null;
		}
	}
}