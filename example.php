<?php

    require_once 'vendor/autoload.php';

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
        // Ship it
        $order->ship('TNT', '3S283892138213798');
        print_r($order);
        
    }
    catch(Exception $e){
        echo $e->getMessage(); 
    }
