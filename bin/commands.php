
// Migration commands
case 'migrate':
case 'migrate:run':
    require_once $frameworkPath . '/src/Commands/MigrateCommand.php';
    $cmd = new \AtomFramework\Commands\MigrateCommand();
    $cmd->run($options);
    break;

case 'migrate:status':
    require_once $frameworkPath . '/src/Commands/MigrateCommand.php';
    $cmd = new \AtomFramework\Commands\MigrateCommand();
    $cmd->status();
    break;
