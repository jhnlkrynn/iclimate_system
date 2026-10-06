<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

Updated Community Feed and Messaging Feature.

## Running iClimate (Windows / XAMPP)

iClimate needs five things running: the MySQL database, the web app, the scheduler, the queue worker and the ML service.
Without the scheduler and queue worker, predictions and reports stay "loading" and weather, risk and advisories stop updating.

```
start-services.bat                    start everything (safe to run again; closes duplicate copies)
start-services.bat status             show what is running
start-services.bat restart            after changing code
start-services.bat install-autostart  start everything when you sign in to Windows (after a reboot)
```

- For real use set `ICLIMATE_WEB_SERVER=apache` in `.env`: the script then runs Apache with four PHP workers instead of
  the one-request-at-a-time dev server. See [deploy/README.md](deploy/README.md) for the full deployment checklist.
- PHP 8.2 or newer is required. XAMPP's own PHP 8.0 cannot run the app; the script uses `C:\php85-cli\php.exe`
  (set `ICLIMATE_PHP` to use another one).
- Each service is restarted automatically if it stops. Logs are in `storage/logs`.
- Password reset by email needs real mail settings in `.env` (`MAIL_MAILER=smtp` ...). With `MAIL_MAILER=log`
  the forgot-password page tells people to ask MAO/IT for a new password instead.
- A daily database backup (03:15) is written to `storage/app/backups`. Run one now with `php artisan iclimate:backup-database`.
- The farmer masterlist (`database/data/iclimate_farmer_masterlist.csv`) holds personal data and is not kept in git:
  copy it onto the server by hand before running `FarmerMasterlistSeeder`.
- Check the data with `php artisan iclimate:data-audit`.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
