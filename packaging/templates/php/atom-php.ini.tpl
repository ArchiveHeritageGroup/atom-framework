; AtoM Heratio PHP overrides
; Installed by atom-heratio package

memory_limit = 512M
max_execution_time = 120
max_input_time = 120
post_max_size = 72M
upload_max_filesize = 64M
max_file_uploads = 20

; OPcache
opcache.enable = 1
opcache.memory_consumption = 192
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
opcache.interned_strings_buffer = 16

; Session
session.gc_maxlifetime = 3600

; Date
date.timezone = UTC
