<?php namespace MCS;
 
    use DateTimeZone;
    use DOMDocument;
    use Exception;
    use DateTime;

class PlazaOrder{
  
    public $id = null;
    public $processOrderId = null;
    public $orderData = [];
    public $shippingAddress = [];
    public $billingAddress = [];
    public $orderLines = [];
    private $client;
    
    /**
     * Get all orders
     * @param  Object PlazaClient $client
     * @return boolean/array  return false if no orders / return array with orders
     */
    public static function all(PlazaClient $client)
    {   
        $client->setResource('orders');
        $orders = $client->call();
        
        if (empty($orders)){
            return false;    
        }

        $orders = json_decode(json_encode($orders), true)['OpenOrder'];

        if (isset($orders['OrderId'])){
            $orders = [$orders];
        }
        
        foreach ($orders as $order){
            $array[] = new PlazaOrder($order, $client);
        } 
        
        return $array;
    }
    
    /**
     * Find 1 order
     * @param  object PlazaClient $client
     * @param  integer $id, the order Id
     * @return object PlazaOrder
     */
    public static function find(PlazaClient $client, $id)
    {   
        $index = 0;
        $id = (int) $id;
        $orders = self::all($client);
        
        foreach($orders as $order){
            if ($order->id === $id){
                return $orders[$index];   
            }
            $index++;
        }
        throw new Exception('Order not found');
    }
    
    
    /**
     * Ship the order
     * @param  string $carrier 
     * @param  string $awb
     * @return object PlazaOrder wiht updated $processOrderId
     */
    public function ship($carrier, $awb)
    {
        
        $carriers = [
            'BPOST_BRIEF', 'BRIEFPOST', 'GLS', 'FEDEX_NL',
            'DHLFORYOU', 'UPS', 'KIALA_BE', 'KIALA_NL',
            'DYL', 'DPD_NL', 'DPD_BE', 'BPOST_BE',
            'TNT', 'TNT_EXTRA', 'TNT_BRIEF',
            'FEDEX_BE', 'OTHER', 'DHL', 'SLV'
        ];  
        
        
        if (!in_array($carrier, $carriers)){
            throw new Exception('Carrier not allowed. Use one of: ' . implode(' / ', $carriers));    
        }
        
        $date = new DateTime();
        $date = $date->setTimezone(new DateTimeZone('Etc/Greenwich'))->format('Y-m-d\TH:i:s');
        $domtree = new DOMDocument('1.0', 'UTF-8');
        $ProcessOrders = $domtree->createElementNS('http://plazaapi.bol.com/services/xsd/plazaapiservice-1.0.xsd', 'ProcessOrders');
        $ProcessOrders = $domtree->appendChild($ProcessOrders);
        $ProcessOrders->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $ProcessOrders->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'schemaLocation', 'http://plazaapi.bol.com/services/xsd/plazaapiservice-1.0.xsd plazaapiservice-1.0.xsd');
        $Shipments = $domtree->createElement('Shipments');
        $Shipments = $ProcessOrders->appendChild($Shipments);
        $Shipment = $domtree->createElement('Shipment');
        $Shipment = $Shipments->appendChild($Shipment);
        $Shipment->appendChild($domtree->createElement('OrderId', $this->id));
        $Shipment->appendChild($domtree->createElement('DateTime', $date));
        $Transporter = $domtree->createElement('Transporter');
        $Transporter = $Shipment->appendChild($Transporter);
        $Transporter->appendChild($domtree->createElement('Code', $carrier));
        $Transporter->appendChild($domtree->createElement('TrackAndTraceCode', $awb));
        $OrderItems = $domtree->createElement('OrderItems');
        $OrderItems = $Shipment->appendChild($OrderItems);
        
        foreach($this->orderLines as $line){
            $OrderItems->appendChild($domtree->createElement('Id', $line['id']));
        }
        
        $this->client->setResource('process');
        $resp = (array) $this->client->call($domtree->saveXML());
        
        if (isset($resp['ProcessOrderId'])){
            $this->processOrderId = (int) $resp['ProcessOrderId'];
            return $this;
        }
        
        throw new Exception('Order response empty');
        
    }
    
    /**
     * @param array $order
     * @param object PlazaCLient $client
     */
    public function __construct($order, PlazaCLient $client)
    {
        
        $this->client = $client;    
        $addressStructure = [
            'SalutationCode' => null,        
            'FirstName' => null,        
            'Surname' => null,        
            'Streetname' => null,        
            'Housenumber' => null,        
            'HousenumberExtended' => null,        
            'AddressSupplement' => null,        
            'ZipCode' => null,        
            'City' => null,        
            'CountryCode' => null,        
            'Email' => null,        
            'Telephone' => null,        
            'Company' => null,        
        ];
        
        $this->id = (int) $order['OrderId'];
        
        $this->orderData = [
            'TransactionFee' => 0,
            'TotalPrice' => 0,
            'DeliveryPeriod' => null,
            'DateTimeCustomer' => date('Y-m-d H:i:s', strtotime($order['DateTimeCustomer'])),
            'DateTimeDropShipper' => date('Y-m-d H:i:s', strtotime($order['DateTimeDropShipper'])),
            'Paid' => ( $order['Paid'] === 'true' ? true : false )
        ];
        
        $this->shippingAddress = array_merge($addressStructure, $order['Buyer']['ShipmentDetails']);
        $this->billingAddress = array_merge($addressStructure, $order['Buyer']['BillingDetails']);
            
        foreach($order['OpenOrderItems'] as $line){
            $this->orderLines[] = [
                'id' => (int) $line['OrderItemId'],
                'quantity' => (int) $line['Quantity'],
                'ean' => $line['EAN'],
                'sku' => ( isset($line['ReferenceCode']) ? $line['ReferenceCode'] : null ),
                'name' => $line['Title'],
                'price' => number_format($line['Price'], 2)
            ];
            $this->orderData['DeliveryPeriod'] = $line['DeliveryPeriod'];
            $this->orderData['TotalPrice'] += (float) $line['Price'];
            $this->orderData['TransactionFee'] += $line['TransactionFee'];
        }
        $this->orderData['TotalPrice'] = number_format($this->orderData['TotalPrice'], 2);
        $this->orderData['TransactionFee'] = number_format($this->orderData['TransactionFee'], 2);
    }
}