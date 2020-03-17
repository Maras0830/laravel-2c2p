# Laravel5-2C2P

Cash trading with 2C2P Package on Laravel 5.*

## FEATURE
1. Support add order.
2. Support credit payment pay or close.

## Official Documentation

## USAGE
### MPG(Multi Payment Gateway) 單一串接多種支付 ###
routes.php
```php
Route::get('cash', 'CashController@index');
Route::post('cash/create', 'CashController@store');
```

CashController.php
```
public function index()
{
    return view('cash.index');
}
```

resources/views/cash/index.blade.php
```html
<html>
    <head>
        <title>Test Cash</title>
    </head>
    <body>
        <h1>智付寶 - 訂單測試</h1>
        <form name='Pay2go' method='post' action='{{ url('/cash/create') }}'>
            {!! csrf_field() !!}
            商店訂單編號：<input type="text" name="MerchantOrderNo" value="<?php echo "20160825" . random_int(1000,9999) ?>"/> <br/>
            訂單金額：<input type="text" name="Amt" value="<?php echo random_int(0,9999) ?>"> <br/>
            商品資訊：<input type="text" name="ItemDesc" value="測試商品資訊敘述"> <br/>
            Email：<input type="text" name="Email" value="Maras0830@gmail.com"> <br/>
    
            <input type='submit' value='Submit'>
        </form>
    </body>
</html>

````

CashController.php
```php
public function store(Request $request)
{
    $form = $request->except('_token');
    
    // 建立商店
    $pay2go = new Pay2Go(env('CASH_STORE_ID'), env('CASH_STORE_HashKey'), env('CASH_STORE_HashIV'));
    
    // 商品資訊
    $order = $pay2go->setOrder($form['MerchantOrderNo'], $form['Amt'], $form['ItemDesc'], $form['Email'])->submitOrder();  
    

    // 將資訊回傳至自定義 view javascript auto submit
    return view('cash.submit')->with(compact('order'));
}
```

resources/views/cash/submit.blade.php
```html
    <html>
        <head>
            <title>redirect pay2go ...</title>
        </head>
    
        <body>
            {!! $order !!}
        </body>
    </html>
```
### Payment Pay or Close (信用卡請款或退款) ###

routes.php
```php
Route::get('admin/order', 'admin\OrderController@index');
Route::get('admin/order/requestPay/{order_id}', 'admin\OrderController@requestPay');
```

Admin/OrderController.php
```php
    public function index()
    {
        $orders = $this->orderRepository->all()->get();

        return view('admin.cash.index')->with(compact('orders'));
    }
```

```php
    public function requestPay($order_id)
    {
        $order = $this->orderRepository->findBy('order_unique_id', $order_id);

        $pay2go = new Pay2Go(config('pay2go.MerchantID'), config('pay2go.HashKey'), config('pay2go.HashIV'));

        $result = $pay2go->requestPaymentPay($order->order_unique_id, $order->amt);

        return view('admin.cash.request_pay')->with(compact('result'));
    }
```

## ChangeLog
[2017.08.25] Add Debug Mode On .env.  
[2016.11.25] Change API URL to spgateway.com.  
[2016.09.06] Support credit payment pay or close.  
[2016.08.29] Only support add order, will add invoice feature and welcome developers join this project.  
##
