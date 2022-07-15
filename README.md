# Laravel Stock

[![Latest Version on Packagist](https://img.shields.io/packagist/v/appstract/laravel-stock.svg?style=flat-square)](https://packagist.org/packages/appstract/laravel-stock)
[![Total Downloads](https://img.shields.io/packagist/dt/appstract/laravel-stock.svg?style=flat-square)](https://packagist.org/packages/appstract/laravel-stock)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/appstract/laravel-stock/master.svg?style=flat-square)](https://travis-ci.org/appstract/laravel-stock)

Keep stock for Eloquent models. This package will track stock mutations for your models. You can increase, decrease, clear and set stock. It's also possible to check if a model is in stock (on a certain date/time).

## Installation

You can install the package via composer:

``` bash
composer require appstract/laravel-stock
```

By running `php artisan vendor:publish --provider="Appstract\Stock\StockServiceProvider"` in your project all files for this package will be published. Run `php artisan migrate` to migrate the table. There will now be a `stock_mutations` table in your database.

## Usage

Adding the `HasStock` trait will enable stock functionality on the Model.

``` php
use Appstract\Stock\HasStock;

class Book extends Model
{
    use HasStock;
}
```

Your warehouse model has to implement `Warehouse` interface.
``` php
use Appstract\Stock\Warehouse

class Library extends Model implements Warehouse
{
    
}

```

### Basic mutations

```php
$book->increaseStock(10, ['warehouse' => $warehouse_first]);
$book->decreaseStock(10, ['warehouse' => $warehouse_first]);
$book->mutateStock(10, ['warehouse' => $warehouse_first]);
$book->mutateStock(-10, ['warehouse' => $warehouse_first]);
```

### Clearing stock

It's also possible to clear the stock and directly setting a new value.

```php
$book->clearStock();
$book->clearStock(10, ['warehouse' => $warehouse_first]);
```

### Setting stock

It is possible to set stock. This will create a new mutation with the difference between the old and new value.

```php
$book->setStock(10, ['warehouse' => $warehouse_first]);
```

### Check if model is in stock

It's also possible to check if a product is in stock (with a minimal value).

```php
$book->inStock(); // anywhere
$book->inStock(10);
$book->inStock(10, ['warehouse' => $warehouse_first]);
```

### Current stock

Get the current stock value (on a certain date) - all warehouses.

```php
$book->stock;
$book->stock(Carbon::now()->subDays(10));
```



### Current stock in specific warehouse

Get the current stock value (on a certain date) in specific warehouse.

```php
$book->stock(null, ['warehouse' =>$warehouse_first]);
$book->stock(Carbon::now()->subDays(10), ['warehouse' =>$warehouse_first]);
```

### Move between warehouses

Move amount from source warehouse to destination warehouse.

```php
$book->moveBetweenStocks(5,$warehouse_first, $warehouse_second);
```

### Stock arguments

Add a description and/or reference model to de StockMutation.

```php
$book->increaseStock(10, [
    'warehouse' => $warehouse_first,
    'description' => 'This is a description',
    'reference' => $otherModel, // example Order, PurchaseOrder, etc.
]);
```

### Query Scopes

It is also possible to query based on stock.

```php
Book::whereInStock()->get();
Book::whereInStock($warehouse_first)->get();
Book::whereOutOfStock()->get();
Book::whereOutOfStock($warehouse_first)->get();
```

## Testing

``` bash
composer test
```

## Contributing

Contributions are welcome, [thanks to y'all](https://github.com/appstract/laravel-stock/graphs/contributors) :)

## About Appstract

Appstract is a small team from The Netherlands. We create (open source) tools for Web Developers and write about related subjects on [Medium](https://medium.com/appstract). You can [follow us on Twitter](https://twitter.com/appstractnl), [buy us a beer](https://www.paypal.me/appstract/10) or [support us on Patreon](https://www.patreon.com/appstract).

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
