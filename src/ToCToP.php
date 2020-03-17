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
    public function setOrder($currency_code, $total, $country, $description = '')
    {
        $total  = str_pad($total, 12, '0', STR_PAD_LEFT);

        $this->currencyCode = $currency_code;
        $this->panCountry = $country;
        $this->amt = $total;
        $this->desc = $description;

        return $this;
    }

    public function setCardInfo($card_number, $holder_name, $exp_year, $exp_month, $cvv)
    {
        $this->cardnumber = $card_number;
        $this->cardholderName = $holder_name;
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
        if ($debug_mode)
            $this->ToCToPUrl = 'https://demo2.2c2p.com/2C2PFrontEnd/SecurePayment/Payment.aspx';
        else
            $this->ToCToPUrl = 'https://demo2.2c2p.com/2C2PFrontEnd/SecurePayment/Payment.aspx';
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
        $result = '<form id="2c2p-payment-form" method="post" action='.route('v1.2c2p.checkout.pay').'>';

        foreach($this as $key => $value) {
            if (in_array($key, ['cardnumber', 'cvv', 'year', 'month'])) {
                $result .= '<input type="hidden" data-encrypt="'. $key .'" name="' . $key . '" value="' . $value . '">';
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

    public function setEncryptCardInfo($cardholderName, $encrypt_card_info)
    {
        $this->cardholderName = $cardholderName;
        $this->encCardData = $encrypt_card_info;

        return $this;
    }

    public function pay()
    {
        $xml = '<PaymentRequest>';
        foreach ($this as $k => $v) {
            if (in_array($k, ['merchantID', 'uniqueTransactionCode', 'desc', 'amt', 'currencyCode', 'panCountry', 'cardholderName', 'encCardData'])) {
                $xml .= "<$k>$v</$k>";
            }
        }

        $xml .= '</PaymentRequest>';

        $paymentPayload = base64_encode($xml); //Convert payload to base64

        $secretKey = $this->secretKey;

        $signature = strtoupper(hash_hmac('sha256', $paymentPayload, $secretKey, false));

        $version = $this->Version;

        $paymentPayload = base64_encode($xml); //Convert payload to base64
        $payloadXML = "<PaymentRequest>
           <version>$version</version>
           <payload>$paymentPayload</payload>
           <signature>$signature</signature>
           </PaymentRequest>";
        $data = base64_encode($payloadXML); //Convert payload to base64
        $payload = urlencode($data);        //encode with base64

        //open connection
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $this->ToCToPUrl);
        curl_setopt($ch,CURLOPT_POSTFIELDS, "paymentRequest=$payload");
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        //execute post
        $response = curl_exec($ch); //close connection
        curl_close($ch);


        //decode response with base64
        $reponsePayLoadXML = base64_decode($response);

        //Parse ResponseXML
        $xmlObject = simplexml_load_string($reponsePayLoadXML) or die("Error: Cannot create object");

        //decode payload with base64 to get the Reponse
        $payloadxml = base64_decode($xmlObject->payload);

        return (array) simplexml_load_string($payloadxml);
    }

    private function setVersion($version)
    {
        $this->Version = $version;

        return $this;
    }

}
