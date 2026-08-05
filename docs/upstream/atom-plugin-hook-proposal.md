# Proposal: generalise the plugin-activation hook

## What AtoM already does

`config/ProjectConfiguration.class.php` decides the plugin list in `setup()`, and it
already lets a file on disk change that decision:

```php
// Check if the OIDC plugin should be enabled.
$filePath = 'activate-oidc-plugin';
if (file_exists($filePath) && 0 === filesize($filePath)) {
    $plugins[] = 'arOidcPlugin';
}
```

The mechanism is accepted; it is only hardcoded to one plugin name.

## The problem

Symfony 1 fixes the plugin list during `setup()`, before any plugin configuration
object exists, so no plugin can enable another. `index.php` requires
`config/ProjectConfiguration.class.php` by absolute path, so it cannot be shadowed
either. That leaves editing that file as the only way to add a plugin that is not
in AtoM's hardcoded list.

The consequence is that every distributor of AtoM plugins ships a modified copy of
an AtoM file. Ours had drifted to 305 lines against AtoM's 90 before we reduced it.
A forked bootstrap file cannot survive an upstream release cleanly, and the
divergence is invisible until something breaks.

## Proposed change

Read optional activation files from a directory, alongside the existing OIDC check:

```php
// Plugins activated by an installer or package manager. Each file names one
// plugin; an empty directory or a missing one changes nothing.
$activationDir = sfConfig::get('sf_root_dir').'/config/plugins.d';

if (is_dir($activationDir)) {
    foreach (glob($activationDir.'/*.txt') as $file) {
        $name = trim(file_get_contents($file));

        if ('' !== $name && is_dir(sfConfig::get('sf_root_dir').'/plugins/'.$name)) {
            $plugins[] = $name;
        }
    }
}
```

Roughly ten lines, no new dependency, no behaviour change for an install that does
not use it, and the existing OIDC check could later be expressed through it.

## Why a directory rather than one file

One file per plugin means a package manager can add and remove its own entry
without reading, merging and rewriting a shared file — the same reason
`conf.d`-style directories are used elsewhere. It also makes an uninstall a
deletion rather than an edit.

## What this would replace

An AtoM distribution could then ship plugins with no modification to any AtoM file
at all. For this project it would take the base delta from one file to zero.
