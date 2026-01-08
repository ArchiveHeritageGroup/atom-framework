
// Handle extension:disable command with --force option
if ($command === 'extension:disable') {
    $pluginName = $argv[2] ?? null;
    $force = in_array('--force', $argv) || in_array('-f', $argv);
    
    if (!$pluginName) {
        echo "Usage: php bin/atom extension:disable <plugin_name> [--force]\n";
        exit(1);
    }
    
    require_once __DIR__ . '/../src/Commands/ExtensionDisableCommand.php';
    $cmd = new \AtomFramework\Commands\ExtensionDisableCommand();
    exit($cmd->execute($pluginName, $force));
}
