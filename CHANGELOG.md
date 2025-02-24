### 1.0.11 (2025-02-24)
1. **Simpletools\Db\IndexedFile\File**
   1. Added `reloadSort` method for refresh sort if keys were grouped

### 1.0.10 (2025-01-14)
1. **Simpletools\Db\IndexedFile\File**
   1. Added `next` method for manual iteration

### 1.0.9 (2023-11-28)
1. **Simpletools\Db\IndexedFile\File**
   1. Deduplicate sort file before sorting if upsert was used

### 1.0.8 (2022-11-28)
1. **Simpletools\Db\IndexedFile\File**
   1. Added 400 code exception for when no `$key` (`null` or `empty string`) is being passed across `insert`, `insertIgnore`, `upsert`, `remove` and `read`
   
### 1.0.7 (2021-12-21)
1. **Simpletools\Db\IndexedFile\File**
    1. Replaced `tmpfile` method to `sys_get_temp_dir`

### 1.0.6 (2021-08-18)
1. **Simpletools\Db\IndexedFile\File**
    1. Added exception on empty key
    
### 1.0.5(2021-08-16)
1. **Added sort to the Readme**

### 1.0.4 (2021-08-16)
1. **Simpletools\Db\IndexedFile\File**
   1. Added `sort` method
   2. Added `refreshSortStats` method
   3. Added `sortIterate` method
   3. Added `runSort` method

### 1.0.3 (2021-08-09)
1. **Added Readme**
2. **Simpletools\Db\IndexedFile\File**
   1. Added iterator to yield `$key`
   2. Moved custom store class check to `__construct` to lazy load
   3. Added `flock` to avoid 2 scripts connecting at once

### 1.0.2 (2021-08-08)
1. **Examples update**

### 1.0.1 (2021-08-08)
1. **Autoload path correction**

### 1.0.0 (2021-08-08)
1. **Simpletools\Db\IndexedFile\File**
   1. Added
1. **Simpletools\Db\IndexedFile\IndexStore\ArrayStore**
   1. Added
1. **Simpletools\Db\IndexedFile\IndexStore\IndexStoreInterface**
   1. Added