<?php

/**
 * AtoM arBaseTask — standalone shim.
 *
 * Replaces lib/task/arBaseTask.class.php. The original boots
 * ProjectConfiguration + sfContext. This shim boots Laravel DB
 * and loads settings — everything plugin tasks actually need.
 */

if (!class_exists('arBaseTask', false)) {
    abstract class arBaseTask extends sfBaseTask
    {
        public const MAX_LINE_SIZE = 2048;

        public function __construct($dispatcher = null, $formatter = null)
        {
            parent::__construct($dispatcher, $formatter);

            if ($this->formatter) {
                $this->formatter->setMaxLineSize(self::MAX_LINE_SIZE);
            }
        }

        /**
         * Original arBaseTask::execute() creates sfContext and loads settings.
         * Our shim boots Laravel DB + sfConfig instead.
         */
        public function execute($arguments = [], $options = [])
        {
            $this->bootDatabase();
            $this->bootConfiguration();

            // Create sfContext adapter if available (some tasks use $this->context)
            if (class_exists('sfContext', false) && method_exists('sfContext', 'hasInstance')) {
                if (!sfContext::hasInstance()) {
                    // Create minimal context for tasks that access it
                    if (class_exists(\AtomFramework\Http\Compatibility\SfContextAdapter::class)) {
                        $request = new \Illuminate\Http\Request();
                        \AtomFramework\Http\Compatibility\SfContextAdapter::create($request);
                    }
                }
                if (sfContext::hasInstance()) {
                    $this->context = sfContext::getInstance();
                }
            }
        }
    }
}
