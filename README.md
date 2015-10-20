# Bol.com Plaza API Client
[![Latest Stable Version](https://poser.pugx.org/mcs/bol-plaza/v/stable)](https://packagist.org/packages/mcs/bol-plaza) [![Total Downloads](https://poser.pugx.org/mcs/bol-plaza/downloads)](https://packagist.org/packages/mcs/bol-plaza) [![Latest Unstable Version](https://poser.pugx.org/mcs/bol-plaza/v/unstable)](https://packagist.org/packages/mcs/bol-plaza) [![License](https://poser.pugx.org/mcs/bol-plaza/license)](https://packagist.org/packages/mcs/bol-plaza)

Installation:
```bash
$ composer require mcs/bol-plaza
```

Features:
 * Get all open orders
 * Create a shipment for an order

**Note:** Bol.com requires you to use their carrier codes. See: [this page](https://developers.bol.com/documentatie/plaza-api/developer-guide-plaza-api/appendix-a-transporters/) or the source code for the available options. The plugin also throws an exception if you use an unsupported one.

Basic usage:

```php
use \MCS\PlazaClient;
use \MCS\PlazaOrder;

try{

    $publicKey = '<publicKey>';
    $privateKey = '<privateKey>';

    // For live enviroment, set the 3rd parameter to false or remove it
    $client = new PlazaClient($publicKey, $privateKey, true);

    // Get all orders
    $orders = PlazaOrder::all($client);
    print_r($orders);

    // Or get 1 order
    $order = PlazaOrder::find($client, '123');
    // And ship it
    $order->ship('TNT', '3S283892138213798');
    print_r($order);

}
catch(Exception $e){
    echo $e->getMessage(); 
}
```