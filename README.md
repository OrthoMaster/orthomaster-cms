# Deploy
ps aux | grep apache2
sudo chown -R ubuntu:ubuntu /var/www/laravel
sudo chmod -R 775 /var/www/laravel

# Rewrite .env
cd /var/www/laravel
sudo nano .env

cd /var/www/laravel
sudo  php artisan cache:clear
sudo  php artisan config:clear
sudo  php artisan route:clear
sudo  php artisan view:clear

# After deploy
cd /var/www/laravel
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan view:clear

sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

sudo chown -R www-data:www-data /var/www/laravel/storage
sudo chown -R www-data:www-data /var/www/laravel/bootstrap/cache
sudo chmod -R 775 /var/www/laravel/storage
sudo chmod -R 775 /var/www/laravel/bootstrap/cache

sudo -u www-data php artisan queue:restart
sleep 3
sudo supervisorctl restart laravel-worker:*

sudo systemctl restart apache2
sudo supervisorctl status laravel-worker:*

sudo -u www-data php artisan schedule:list

## Crontab
sudo crontab -u www-data -e
* * * * * cd /var/www/laravel && php artisan schedule:run >> /dev/null 2>&1

# Chat
php artisan ai-search:index

php artisan cache:clear
php artisan ai-search:chat --user=4

## SuperVisor
# Xem status
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart laravel-worker:*

# Stop workers
sudo supervisorctl stop laravel-worker:*

# Start workers
sudo supervisorctl start laravel-worker:*