##### 自定义命令 解释
```php
php artisan make:newctrl unt UnitTypeController 提示备注
```

在config/app.php的providers中加添
```php
Gen\Code\GenCodeServiceProvider::class,
```