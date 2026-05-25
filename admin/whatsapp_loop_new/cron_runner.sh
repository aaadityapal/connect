#!/bin/bash
# This script processes both the master loops and campaign queues sequentially

cd /Applications/XAMPP/xamppfiles/htdocs/connect/admin/whatsapp_loop_new/api

# Run Master Loop Queue
/Applications/XAMPP/xamppfiles/bin/php process_master_loop_queue.php

# Run Campaign Queue
/Applications/XAMPP/xamppfiles/bin/php process_campaign_queue.php
