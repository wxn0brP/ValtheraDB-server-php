# ValtheraDB Server PHP

ValtheraDB adapter for MariaDB/MySQL. Provides ValtheraDB-compatible API for SQL databases.

## Installation

```bash
git clone https://github.com/wxn0brP/ValtheraDB-server-php.git
```

## Configuration

Copy `config.php.example` to `config.php` and configure:

```php
$auth = [
    'admin' => 'your-token',
];

$default = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'database_name',
    'username' => 'username',
    'password' => 'password',
    'charset' => 'utf8mb4',
    'driver' => 'auto',
];
```

## Server Configuration

**Important:** Endpoints must be called without `.php` extension (e.g., `/db/find`, not `/db/find.php`).

An `.htaccess` file is included for Apache.
For nginx/caddy/lighttpd, configure URL rewriting to strip `.php` from requests.

## Compatibility

API is compatible with [ValtheraDB Server](https://github.com/wxn0brP/ValtheraDB-server).

## License

MIT

## Contributing

Contributions are welcome!
