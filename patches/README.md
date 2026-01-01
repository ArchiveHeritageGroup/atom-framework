# AtoM Patches

These patches fix issues in base AtoM or vendor code.
Applied automatically by the install script.

## zend-acl-duplicate-role.php
Fixes "Role id 'XX' already exists in the registry" exception.
Changes throw to return in Zend_Acl_Role_Registry::add()
