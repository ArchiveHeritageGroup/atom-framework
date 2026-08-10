# AtoM Patches

These patches overwrite base AtoM. Several carry security fixes - the login
lockout, the password policy, the ACL role check.

**They are opt-in.** `bin/install` does not apply them unless given
`--with-base-patches`. The AHG plugins do not need them; our own instances use
them.

## Check whether they are still applied

    bin/patches-verify           report
    bin/patches-verify --quiet   silent unless something is reverted

Applying a patch leaves no record, and nothing checks afterwards. An AtoM
upgrade, a `git checkout` in the AtoM tree or an rsync from another instance
restores the upstream file and takes the fix with it. The site keeps working, so
nobody looks - which is how a security fix silently stops existing.

Run it after every AtoM upgrade. Exit code 1 means at least one patch is no
longer in place.

It reports and does not reapply: overwriting base AtoM is a decision, and a
script that quietly re-patched a tree mid-upgrade would be a worse version of the
same problem.

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

## apps/qubit/modules/menu/templates/_userMenu*.php
Base-AtoM user-menu templates. Heratio's install removes them (it renders the
user menu via ahgThemeB5Plugin's _userMenu.mod_standard.php override). They are
required for the base-AtoM fallback mode (`ahg_active_theme` != ahgThemeB5Plugin,
or `.heratio_disabled` flag) — without them the base-mode layout fatals on a
missing _userMenu.php. The Heratio theme override still wins in Heratio mode, so
restoring them is safe for both modes.
