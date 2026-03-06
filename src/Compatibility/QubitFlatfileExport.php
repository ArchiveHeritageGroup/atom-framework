<?php

/**
 * QubitFlatfileExport — Compatibility shim.
 *
 * Minimal stub for the CSV/flatfile export framework.
 * The full class (~600 lines) handles YAML-configured CSV export.
 * This stub provides the API surface needed by templates and CLI references.
 */

if (!class_exists('QubitFlatfileExport', false)) {
    class QubitFlatfileExport
    {
        protected $columns = [];
        protected $standardColumns = [];
        protected $columnNames = [];
        protected $rows = [];
        protected $options = [];
        protected $resource;

        // File handle
        protected $fileHandle;
        protected $filePath;

        /**
         * @param  string $filePath  Output CSV file path
         * @param  array  $options
         */
        public function __construct($filePath = null, $options = [])
        {
            $this->filePath = $filePath;
            $this->options = $options;
        }

        /**
         * Load column configuration from YAML.
         *
         * @param  string $configFile  Path to YAML config
         *
         * @return static
         */
        public function loadResourceSpecificConfiguration($configFile)
        {
            if (file_exists($configFile) && function_exists('yaml_parse_file')) {
                $config = yaml_parse_file($configFile);
                if (is_array($config)) {
                    $this->standardColumns = $config['columnNames'] ?? [];
                }
            } elseif (file_exists($configFile) && class_exists('sfYaml', false)) {
                try {
                    $config = \sfYaml::load($configFile);
                    if (is_array($config)) {
                        $this->standardColumns = $config['columnNames'] ?? [];
                    }
                } catch (\Exception $e) {
                    // Ignore YAML parse errors
                }
            }

            return $this;
        }

        /**
         * Set the current resource for export.
         *
         * @param  object $resource
         */
        public function setResource($resource)
        {
            $this->resource = $resource;
        }

        /**
         * Get the current resource.
         *
         * @return object|null
         */
        public function getResource()
        {
            return $this->resource;
        }

        /**
         * Set column definitions.
         *
         * @param  array $columns
         */
        public function setColumns($columns)
        {
            $this->columns = $columns;
        }

        /**
         * Get column definitions.
         *
         * @return array
         */
        public function getColumns()
        {
            return $this->columns;
        }

        /**
         * Set column names (header row).
         *
         * @param  array $names
         */
        public function setColumnNames($names)
        {
            $this->columnNames = $names;
        }

        /**
         * Get column names.
         *
         * @return array
         */
        public function getColumnNames()
        {
            return $this->columnNames ?: $this->standardColumns;
        }

        /**
         * Add a row to the export buffer.
         *
         * @param  array $row
         */
        public function addRow($row)
        {
            $this->rows[] = $row;
        }

        /**
         * Get all buffered rows.
         *
         * @return array
         */
        public function getRows()
        {
            return $this->rows;
        }

        /**
         * Write the header row.
         */
        public function writeHeaderRow()
        {
            if ($this->fileHandle) {
                fputcsv($this->fileHandle, $this->getColumnNames());
            }
        }

        /**
         * Write a data row.
         *
         * @param  array $row
         */
        public function writeRow($row)
        {
            if ($this->fileHandle) {
                fputcsv($this->fileHandle, $row);
            }

            $this->rows[] = $row;
        }

        /**
         * Open the output file.
         *
         * @return bool
         */
        public function open()
        {
            if ($this->filePath) {
                $this->fileHandle = fopen($this->filePath, 'w');

                return (bool) $this->fileHandle;
            }

            return false;
        }

        /**
         * Close the output file.
         */
        public function close()
        {
            if ($this->fileHandle) {
                fclose($this->fileHandle);
                $this->fileHandle = null;
            }
        }

        /**
         * Get an option value.
         *
         * @param  string $name
         * @param  mixed  $default
         *
         * @return mixed
         */
        public function getOption($name, $default = null)
        {
            return $this->options[$name] ?? $default;
        }

        /**
         * Set an option value.
         *
         * @param  string $name
         * @param  mixed  $value
         */
        public function setOption($name, $value)
        {
            $this->options[$name] = $value;
        }
    }
}
