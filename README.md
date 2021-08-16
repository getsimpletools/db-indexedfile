High-throughput non-concurrent NoSQL key-value file-based database with a memory-backed index.
========================================

IndexedFile is a DB system allowing to very quickly process and store big amount of data by leveraging memory as an index while keeping all the other data on disk with high-throughput read and write engine.

To simplify snippets below, its assumed that the following namespaces are in `use`:

```php
use \Simpletools\Db\IndexedFile;
```

### Initialise Temp DB

To start a new temp DB which will get removed when the script terminates:

```php
$indexedFile = new IndexedFile\File();
```

### Initialise from existing DB

To start DB which might have already exists or which should persist after script terminate:

```php
$indexedFile = new IndexedFile\File('/path/to/my/db.jsonl');
```

### Setup a custom IndexStore

You can write your own IndexStore which implements `IndexStoreInterface` and preset it with the following static method:

```php
//the default IndexStore
IndexedFile\File::indexStoreClass('Simpletools\Db\IndexedFile\IndexStore\ArrayIndexStore');
```

### Storing data

Inserting/Replacing data by key

```php
$indexedFile->insert('key',["foo"=>"bar"]);
```

### Storing data if not exists

Ignoring insert if key already exists

```php
$indexedFile->insertIgnore('key',["foo"=>"bar"]);
```

### Reading data

Reading by key

```php
$value = $indexedFile->read('key');
```

### Iterating through all entries

Iterating through all entires

```php
foreach($indexedFile->iterate() as $key => $value)
{
    var_dump($key,$value);
}
```

### Upserting data

Updating/Inserting your data

```php
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
```

### Removing data

Removing a key

```php
$indexedFile->remove('key');
```

### Truncating database

Removing all entires

```php
$indexedFile->truncate();


or when booting up

```php
$indexedFile = new IndexedFile\File('/path/to/my/db.jsonl',true);
```

### Sorting

Declare text sort
```php
$indexedFile->sort('title','ASC', 'string');
```

Declare integer sort
```php
$indexedFile->sort('count','DESC', 'int');
```

`includeSortStats` flag adds to the indexed object `_sort` meta field with `position` in sort and `percent` of total (integer only)
```php
$indexedFile->sort('count','DESC', 'int', true);
```

Own input and output files
```php
$indexedFile->sort('title','ASC', 'string',true, __DIR__.'/unsorted.csv', __DIR__.'/sorted.csv');
```

Example of use for sorting by price and list with an iterator 
```php
$indexedFile->sort('price','DESC', 'int');

$indexedFile->insertIgnore($data['id'],[
    'title' => $data['title'],
    'price' => $data['price']
]); 

$indexedFile->runSort();

foreach ($indexedFile->sortIterate() as $groupId => $row)
{
  echo 'Position: '. $row->_sort->position.' Percent: '.$row->_sort->position.' Index key: '. $groupId .' Price: '. $row->price."\n";
}

```