# Installation

* Create and adjust `.env` file: `cp .env.example .env`
* `composer install`
* `php index.php`

During the first run of `index.php` all necessary database tables are created.

# Import and analyze

`index.php` is an entry point to a console, providing two different commands.

* `php index.php import` Imports all extensions into `./tmp`
* `php index.php import /path/to/sonar-scanner` analyzes all downloaded extensions
