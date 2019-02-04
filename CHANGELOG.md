# CHANGELOG

## Master
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