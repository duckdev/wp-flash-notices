# WP Flash Admin Notices
WordPress admin notices as flash notice using transient API to display after page reload.

By default WordPress doesn't provide a solution to show admin notices after page reload. Frameworks lik CodeIgniter does have a [flash message](https://www.codeigniter.com/user_guide/libraries/sessions.html#flashdata) feature. Using which, you can show one time notices across user interface. With this simple library you can make use of WordPress' [transient API](https://codex.wordpress.org/Transients_API) and show one time admin notices like a flash message. 

## Installation

You can install this library using composer
```
composer require duckdev/wp-flash-notices
```

Or, you can download latest version of the library from [releases tab](https://github.com/DuckDev/wp-flash-notices/releases).

### Dependencies
* PHP 5.4+
* WordPress 4.0+

## Usage Example

Import the library class and assign custom namespace.

```php
use DuckDev\WP_Flash_Notices as My_Custom_Notices;
```

Create new instance of the notices class with our custom transient name.

```php
$notices = new My_Custom_Notices( 'my-custom-notices' );
```

### Notice Types

There are 4 types of notices.

#### Success Notice:
Notice with green bar.

```php
// Register new success notice to the queue.
$notices->add( 'custom-success', 'This is a custom success notice.', 'success' );
```

#### Error Notice:
Notice with red bar.

```php
// Register new error notice to the queue.
$notices->add( 'custom-error', 'This is a custom error notice.', 'error' );
```

#### Warning Notice:
Notice with yellow bar.

```php
// Register new warning notice to the queue.
$notices->add( 'custom-warning', 'This is a custom warning notice.', 'warning' );
```

#### Info Notice:
Notice with blue bar.

```php
// Register new info notice to the queue.
$notices->add( 'custom-info', 'This is a custom info notice.', 'info' );
```

### Dismissible Notice
Show a dismiss button in notice.

```php
// Register new info notice to the queue.
$notices->add( 'custom-success', 'This is a custom dismissible notice.', 'success', true );
```

### Network Admin Notice
By default all notices are shown within single site admin screens. If you have Multisite you can add network admin notices by setting last argument as true:

```php
// Register new info notice to the queue.
$notices->add( 'custom-success', 'This is a custom dismissible notice.', 'success', true, true );
```