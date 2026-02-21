[atom]
user = www-data
group = www-data

listen = /run/php/php{{PHP_VERSION}}-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 30
pm.start_servers = 5
pm.min_spare_servers = 3
pm.max_spare_servers = 10
pm.max_requests = 500

php_value[memory_limit] = 512M
php_value[max_execution_time] = 120
php_value[max_input_time] = 120
php_value[post_max_size] = 72M
php_value[upload_max_filesize] = 64M
php_value[max_file_uploads] = 20

php_admin_value[error_log] = /var/log/php-atom.log
php_admin_flag[log_errors] = on

php_admin_value[opcache.memory_consumption] = 192
php_admin_value[opcache.max_accelerated_files] = 20000
php_admin_value[opcache.validate_timestamps] = 0
