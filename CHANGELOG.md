Changelog
=========
1.0.3 (2013-12-17)
--
* Typecasting results of `AMysql_Expr` to string.
* Do not rely on `array_fill_keys`, for older PHP versions.
* Minimum PHP version brought down to 5.1 from 5.2.4.
* A lot of additions to README.md.

1.0.2 (2013-10-30)
--
* Serious `AMysql_Select` bugfix, as it was unusable. Included a test now.
* Persistent mysql resources are now recognized as well.

1.0.1 (2013-09-24)
--
* Bugfix for `fetchAssoc()` and `count()`.

1.0.0 (2013-09-14)
--
* New system for connecting to the db with the help of a configuration array, allowing for lazy connecting.
* New `AMysql_Select` class for building SELECT queries. `AMysql_Abstract::select()` now invokes this new class.
* Backward incompatible: AMysql no longer throws an exception if no mysql resource was found during construction.
* Adhering (mostly) to PSR-1. Changed tabulation to use 4 spaces.

0.10.0 (2013-09-12) 
--
Added: 
* Support for using `mysqli` as an alternative to `mysql_*`. `mysqli` is used by default if available.

0.9.4 (2013-09-04)
--
* Fixed an issue where objects weren't typecasted into arrays in the way it was intended so.
* Fixed: columns weren't being quoted when using `AMysql_Expr::EXPR_COLUMN_IN`
* Improved performance of `quoteInto()` in case of no binds.
* New `composer.json` for usage with composer.

0.9.3 (2012-10-18)
--
* Now using the MIT license.
* Added `README.md` and `INSTALL.md`
* New: support for profiling.
* Moved file loading into `AMysql/Abstract.php`.
* Store the count of multiple updated rows in a separate member.
* New column fetching method, `fetchAllColumns()`
* New: `fetchObject()` to fetch as object.
* Support for optional PDO style bind indexing (indexes begin at 1).
* Not relying anymore on the availability of `mysql_set_charset()` in case of older PHP versions.
* Non-array binds are now casted to arrays instead of throwing an exception.
* New `replace()` method for `REPLACE` queries.
* New `save()` method for INSERT/UPDATE.
* New Iterator class. `AMysql_Statement` is now iteratable and countable.
* Now using a new guaranteed-to-work value binding method with the help of `preg_replace_callback()`.
* Renamed some static method calls from `AMysql` to `AMysql_Abstract`.
