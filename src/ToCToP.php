<?php

namespace Maras0830\ToCToP;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Maras0830\Pay2Go\Validation\OrderValidatesRequests;

class ToCToP
{
    private $merchantID;
    private $secretKey;
    private $ToCToPUrl;
    private $Version;
    private $uniqueTransactionCode;

    private $currencyCode;
    private $panCountry;
    private $desc;
    private $amt;
    private $cardnumber;
    private $year;
    private $month;
    private $cvv;
    private $encCardData;
    private $cardholderName;
    private $order_serial;

    /**
     * Pay2Go constructor.
     * @param $MerchantID
     * @param null $SecretKey
     */
    public function __construct($MerchantID = null, $SecretKey = null)
    {
        $this->merchantID = ($MerchantID != null ? $MerchantID : Config::get('to_c_to_p.MerchantID'));
        $this->secretKey = ($SecretKey != null ? $SecretKey : Config::get('to_c_to_p.SecretKey'));

        $this->setToCToPUrl(Config::get('to_c_to_p.Debug', true));
        $this->setVersion(Config::get('to_c_to_p.Version', '9.9'));
        $this->setTimeStamp();
    }

    /**
     * 設置訂單
     *
     * @param $currency
     * @param $currency_code
     * @param $total
     * @param $country
     * @param string $description
     * @return $this
     */
    public function setOrder($currency_code, $total, $country, $description = '', $order_serial = '')
    {
        $total = str_pad($total, 12, '0', STR_PAD_LEFT);

        $this->currencyCode = $currency_code;
        $this->panCountry = $country;
        $this->amt = $total;
        $this->desc = $description;
        $this->order_serial = $order_serial;

        return $this;
    }

    public function setCardInfo($card_number, $exp_year, $exp_month, $cvv, $holder_name = null)
    {
        if ($holder_name != null) {
            $this->cardholderName = $holder_name;
        }

        $this->cardnumber = $card_number;
        $this->year = $exp_year;
        $this->month = $exp_month;
        $this->cvv = $cvv;

        return $this;
    }

    /**
     * 是否開啟測試模式
     *
     * @param $debug_mode
     */
    public function setToCToPUrl($debug_mode)
    {
        if ($debug_mode) {
            $this->ToCToPUrl = 'https://demo2.2c2p.com/2C2PFrontEnd/SecurePayment/Payment.aspx';
        } else {
            $this->ToCToPUrl = 'https://demo2.2c2p.com/2C2PFrontEnd/SecurePayment/Payment.aspx';
        }
    }

    public function setToCToPUrl_REDIRECT($debug_mode)
    {
        if ($debug_mode) {
            $this->ToCToPUrl = 'https://demo2.2c2p.com/2C2PFrontEnd/RedirectV3/payment';
        } else {
            $this->ToCToPUrl = 'https://demo2.2c2p.com/2C2PFrontEnd/RedirectV3/payment';
        }
    }

    /**
     * 設定交易時間
     */
    public function setTimeStamp()
    {
        $this->uniqueTransactionCode = time();

        return $this;
    }

    public function getMerchantID()
    {
        return $this->merchantID;
    }

    /**
     * 送出訂單
     *
     * @return string
     */
    public function submitOrder()
    {
        $result = $this->setOrderSubmitForm();

        return $result;
    }

    /**
     * 設置訂單新增的表單
     *
     * @return string
     */
    private function setOrderSubmitForm()
    {
        $result = '<form id="2c2p-payment-form" method="post" action=' . route('v1.2c2p.checkout.pay') . '>';

        foreach ($this as $key => $value) {
            if (in_array($key, ['cardnumber', 'cvv', 'year', 'month'])) {
                $result .= '<input type="hidden" data-encrypt="' . $key . '" name="' . $key . '" value="' . $value . '">';
            } else {
                $result .= '<input type="hidden" name="' . $key . '" value="' . $value . '">';
            }
        }

        $result .= '<input id="payment-submit" type="submit" style="display: none">';

        $result .= '</form>';

        $result .= '<script type="text/javascript" src="https://demo2.2c2p.com/2C2PFrontEnd/SecurePayment/api/my2c2p.1.6.9.min.js"></script>';
        $result .= '<script type="text/javascript">
                        My2c2p.onSubmitForm("2c2p-payment-form", function(errCode,errDesc){
                            if(errCode!=0){
                                alert(errDesc+" ("+errCode+")");
                            }
                        });
                    </script>';

        $result .= '<script type="text/javascript">document.getElementById(\'payment-submit\').click();</script>';

        return $result;
    }

    public function setEncryptCardInfo($encrypt_card_info, $cardholderName = null)
    {
        $this->encCardData = $encrypt_card_info;

        if (!is_null($cardholderName)) {
            $this->cardholderName = $cardholderName;
        }

        return $this;
    }

    public function pay($request3DS = "Y")
    {
        $this->request3DS = $request3DS;

        $xml = '<PaymentRequest>';

        foreach ($this as $k => $v) {
            if (in_array($k, ['merchantID', 'uniqueTransactionCode', 'desc', 'amt', 'currencyCode', 'panCountry', 'cardholderName', 'request3DS', 'encCardData'])) {
                $xml .= "<$k>$v</$k>";
            }
        }

        $xml .= '</PaymentRequest>';

        $paymentPayload = base64_encode($xml); //Convert payload to base64

        $secretKey = $this->secretKey;

        $signature = strtoupper(hash_hmac('sha256', $paymentPayload, $secretKey, false));

        $version = $this->Version;

        $payloadXML = "<PaymentRequest><version>$version</version><payload>$paymentPayload</payload><signature>$signature</signature></PaymentRequest>";
        $data = base64_encode($payloadXML); //Convert payload to base64
        $payload = urlencode($data);        //encode with base64

        $response = $this->post($this->ToCToPUrl, "paymentRequest=" . $payload);

        //decode response with base64
        $reponsePayLoadXML = base64_decode($response);
        \Log::info('$response', [$response]);
        \Log::info('$reponsePayLoadXML', [$reponsePayLoadXML]);


        //Parse ResponseXML
        $xmlObject = simplexml_load_string($reponsePayLoadXML) or die("Error: Cannot create object");

        //decode payload with base64 to get the Reponse
        $payloadxml = base64_decode($xmlObject->payload);

        return (array)simplexml_load_string($payloadxml);
    }

    public function pay_redirect()
    {
        $this->setToCToPUrl_REDIRECT(Config::get('to_c_to_p.Debug', true));

        $result_url_1 = 'https://de3323da.ngrok.io/2c2p/callback';
        //Construct signature string
        $params = $this->Version . $this->merchantID . $this->desc . $this->order_serial . $this->currencyCode . $this->amt . $result_url_1;

        $hash_value = hash_hmac('sha256', $params, $this->secretKey, false);    //Compute hash value

        $html = '<form id="myform" method="post" action="' . $this->ToCToPUrl . '">
                <input type="hidden" name="version" value="' . $this->Version . '"/>
                <input type="hidden" name="merchant_id" value="' . $this->merchantID . '"/>
                <input type="hidden" name="currency" value="' . $this->currencyCode . '"/>
                <input type="hidden" name="result_url_1" value="' . $result_url_1 . '"/>
                <input type="hidden" name="hash_value" value="' . $hash_value . '"/>
                <input type="text" name="payment_description" value="' . $this->desc . '"  readonly/><br/>
                <input type="text" name="order_id" value="' . $this->order_serial . '"  readonly/><br/>
                <input type="text" name="amount" value="' . $this->amt . '" readonly/><br/>
                <input type="submit" name="submit" value="Confirm" />
            </form> 
    <script type="text/javascript">
        document.forms.myform.submit();
    </script>';

        return $html;
    }

    public function setVersion($version)
    {
        $this->Version = $version;

        return $this;
    }


    private function post($url, $fields_string)
    {
        //open connection
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $ca = './keys/ca-globalsign.cer';
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        //execute post
        $result = curl_exec($ch); //close connection
        curl_close($ch);
        return $result;
    }
}
