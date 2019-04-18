# CHANGELOG

## Master
* Remove Codecov token from .travis.yml

# 0.4.0
* Add some missing return typehints
* Allow lazy loading when query return many results
* Add sort for files
* Allow to filter result by metadata
* Use metadata mapping instead of hardcode them
* Use bucket as a full identifier of file

# 0.3.0
* Use native S3 client limit and allow to resume a truncated query
* Add truncated results management in README
* Add on FileResultSet method getBucketsTruncated that return bucket names where the previous query didn't return all files
* Return \ArrayObject implementations instead of arrays in repositories for returning many entities
* Launch an exception when updating an identifier of a non detached object
* Add tests and update README for object cloning

# 0.2.0
* Use filename as a full metadata
* Authorize only A-Za-z0-9._- for bucket name
* Throw exception when some required defined properties are empty
* Fix File getter return types
* Disallow bucket without name
* Fix typos in tests
* Fix wrong test class name
* Throw an exception when using a non-existent bucket
* Add missing return types in some AbstractCephPersister methods
* Throw an exception when a metadata name has non-supported characters

# 0.1.1
* Add functional tests
* Catch correctly S3 client 404 exception
* Fix identifier when deleting a file
* Remove codecov comments
* Fix bucket mapping
* Fix mistake on README

# 0.1.0
* Initial release
