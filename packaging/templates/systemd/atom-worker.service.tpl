[Unit]
Description=AtoM Heratio Gearman Worker
After=network.target gearman-job-server.service mysql.service
Requires=gearman-job-server.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory={{ATOM_PATH}}
ExecStart=/usr/bin/php {{ATOM_PATH}}/symfony jobs:worker
Restart=on-failure
RestartSec=10
StandardOutput=journal
StandardError=journal
SyslogIdentifier=atom-worker

# Resource limits
MemoryMax=512M
TasksMax=10

[Install]
WantedBy=multi-user.target
