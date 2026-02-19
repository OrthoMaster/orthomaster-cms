# Deploy
ps aux | grep apache2
sudo chown -R ubuntu:ubuntu /var/www/laravel
sudo chmod -R 775 /var/www/laravel

# Rewrite .env
cd /var/www/laravel
sudo nano .env

# After deploy
cd /var/www/laravel
sudo chown -R www-data:www-data /var/www/laravel
sudo find /var/www/laravel -type d -exec chmod 755 {} \;
sudo find /var/www/laravel -type f -exec chmod 644 {} \;
sudo chmod -R 775 /var/www/laravel/storage
sudo chmod -R 775 /var/www/laravel/bootstrap/cache
sudo chmod 640 /var/www/laravel/.env

sudo php artisan cache:clear
sudo php artisan config:clear
sudo php artisan route:clear
sudo php artisan view:clear

sudo php artisan queue:restart
sleep 5

sudo systemctl restart apache2

sudo supervisorctl status laravel-worker:*

sudo php artisan schedule:list

## Crontab
sudo crontab -u www-data -e
* * * * * cd /var/www/laravel && php artisan schedule:run >> /dev/null 2>&1

# Check
sudo -u www-data php artisan schedule:run
sudo -u www-data php artisan schedule:list

sudo grep CRON /var/log/syslog | tail -20

## SuperVisor
# Xem status
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart laravel-worker:*

# Stop workers
sudo supervisorctl stop laravel-worker:*

# Start workers
sudo supervisorctl start laravel-worker:*