# Palzin APM | Real-Time Code Execution monitoring and Bug tracking

![Latest Release](https://img.shields.io/packagist/v/palzin-apm/palzin-php?style=for-the-badge)
![Total Downloads (custom server)](https://img.shields.io/packagist/dt/palzin-apm/palzin-php?style=for-the-badge)
![Packagist License](https://img.shields.io/packagist/l/palzin-apm/palzin-php?style=for-the-badge)
![GitHub last commit](https://img.shields.io/github/last-commit/hi-folks/lara-lens?style=for-the-badge)
![GitHub Release Date](https://img.shields.io/github/release-date/hi-folks/lara-lens?style=for-the-badge)

Simple code execution monitoring and bug reporting for PHP developers.

## Requirements

- PHP >= 7.2.0

## Install

Install the latest version of our package by:

```shell
composer require palzin-apm/palzin-php
```

## Use

To start sending data to Palzin APM you need an INGESTION Key to create an instance of the `Configuration` class.
You can obtain `PALZIN_APM_INGESTION_KEY` creating a new project in your [Palzin APM](https://www.palzin.app) dashboard.

```php
use Palzin\Palzin;
use Palzin\Configuration;

$configuration = new Configuration('YOUR_PALZIN_APM_INGESTION_KEY');
$palzin = new Palzin($configuration);
```

All start with a `transaction`. Transaction represent an execution cycle and it can contains one or hundred of segments:

```php
// Start an execution cycle with a transaction
$palzin->startTransaction($_SERVER['PATH_INFO']);
```

Use `addSegment` method to monitor a code block in your transaction:

```php
$result = $palzin->addSegment(function ($segment) {
    // Do something here...
	return "Hello World!";
}, 'my-process');

echo $result; // this will print "Hello World!"
```

Palzin APM will monitor your code execution in real time and keep alerting you if something goes wrong.

## Custom Transport
You can also set up custom transport class to transfer monitoring data from your server to Palzin APM
in a personalized way.

The transport class needs to implement `\Palzin\Transports\TransportInterface`:

```php
class CustomTransport implements \Palzin\Transports\TransportInterface
{
    protected $configuration;

    protected $queue = [];

    public function __constructor($configuration)
    {
        $this->configuration = $configuration;
    }

    public function addEntry(\Palzin\Models\Arrayable $entry)
    {
        // Add an \Palzin\Models\Arrayable entry in the queue.
        $this->queue[] = $entry;
    }

    public function flush()
    {
        // Performs data transfer.
        $handle = curl_init('https://www.palzin.app');
        curl_setopt($handle, CURLOPT_POST, 1);
        curl_setopt($handle, CURLOPT_HTTPHEADER, [
            'X-Palzin-Key: xxxxxxxxxxxx',
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($this->queue));
        curl_exec($handle);
        curl_close($handle);
    }
}
```

Then you can set the new transport in the `Palzin` instance
using a callback the will receive the current configuration state as parameter.

```php
$palzin->setTransport(function ($configuration) {
    return new CustomTransport($configuration);
});
```

## LICENSE

This package is licensed under the [MIT](LICENSE) license.
