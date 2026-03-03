# AtoM Patches

These patches fix issues in base AtoM or vendor code.
Applied automatically by the install script (`bin/install` Step 11).

## qbAclPlugin/lib/QubitAcl.class.php
Fixes Role 99 in_array check.

## zend-acl-duplicate-role.php
Fixes "Role id 'XX' already exists in the registry" exception.
Changes throw to return in Zend_Acl_Role_Registry::add()

## apps/qubit/modules/digitalobject/templates/_imageflow.php
Modern Slick carousel template replacing the old ImageFlow coverflow.
Required for Bootstrap 5 theme — without this patch, child digital objects
display as stacked images instead of a carousel.

## apps/qubit/modules/user/actions/loginAction.class.php
Integrates LoginSecurityService for brute-force protection:
- Account lockout after 5 failed attempts (15-minute window)
- Login attempt recording (success/failure)

## apps/qubit/modules/user/actions/passwordEditAction.class.php
Integrates PasswordPolicyService for password security:
- Prevents reuse of recent passwords (last 5 by default)
- Records password changes in history for expiry tracking
