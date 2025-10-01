

# Deploy
ps aux | grep apache2
sudo chown -R ubuntu:ubuntu /var/www/laravel
sudo chmod -R 775 /var/www/laravel


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

sudo systemctl restart apache2