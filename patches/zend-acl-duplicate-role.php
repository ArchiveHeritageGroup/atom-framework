<?php
/**
 * Fix Zend ACL duplicate role exception
 * 
 * Problem: When ACL is initialized multiple times, duplicate roles cause exception
 * Solution: Silently ignore duplicate role registration
 */
$file = sfConfig::get('sf_root_dir') . '/plugins/qbAclPlugin/lib/vendor/Zend/Acl/Role/Registry.php';

if (!file_exists($file)) {
    echo "File not found: $file\n";
    return;
}

$content = file_get_contents($file);

// Check if already patched
if (strpos($content, 'AHG patch') !== false) {
    echo "Already patched: zend-acl-duplicate-role\n";
    return;
}

$old = 'throw new Zend_Acl_Role_Registry_Exception("Role id \'$roleId\' already exists in the registry");';
$new = 'return $this; // Silently ignore duplicate roles (AHG patch)';

$content = str_replace($old, $new, $content);
file_put_contents($file, $content);

echo "Applied patch: zend-acl-duplicate-role\n";
