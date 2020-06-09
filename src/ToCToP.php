<?php

namespace Maras0830\ToCToP;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Maras0830\Pay2Go\Validation\OrderValidatesRequests;

class ToCToP
{
    private $merchantID;
    private $uniqueTransactionCode;
    private $desc;
    private $amt;
    private $currencyCode;
    private $panCountry;
    private $cardholderName;
    private $encCardData;

    private $secretKey;
    private $ToCToPUrl;
    private $Version;
    private $cardnumber;
    private $year;
    private $month;
    private $cvv;
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
        $this->uniqueTransactionCode = $order_serial;

        return $this;
    }

    public function setCardInfo($card_number, $exp_year, $exp_month, $cvv, $holder_name = null)
    {
        if ($holder_name != null) {
            $this->cardHolderName = $holder_name;
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
            $this->ToCToPUrl = 'https://demo2.2c2p.com/2C2PFrontEnd/SecurePayment/PaymentAuth.aspx';
        } else {
            $this->ToCToPUrl = 'https://t.2c2p.com/SecurePayment/PaymentAuth.aspx';
        }
    }

    public function getMerchantID()
    {
        return $this->merchantID;
    }

    public function setEncryptCardInfo($encrypt_card_info, $cardholderName = null)
    {
        $this->encCardData = $encrypt_card_info;

        if (!is_null($cardholderName)) {
            $this->cardholderName = $cardholderName;
        }

        return $this;
    }

    public function getCheckoutToken()
    {
        $xml = '<PaymentRequest>';

        foreach ($this as $k => $v) {
            if (in_array($k, ['merchantID', 'uniqueTransactionCode', 'desc', 'amt', 'currencyCode', 'panCountry', 'cardholderName', 'encCardData'])) {
                $xml .= "<$k>$v</$k>";
            }
        }

        $xml .= '</PaymentRequest>';

        \Log::info('to_c_to_p_xml', [$xml]);

        $paymentPayload = base64_encode($xml); //Convert payload to base64

        $secretKey = $this->secretKey;

        $signature = strtoupper(hash_hmac('sha256', $paymentPayload, $secretKey, false));

        $version = $this->Version;

        $payloadXML = "<PaymentRequest><version>$version</version><payload>$paymentPayload</payload><signature>$signature</signature></PaymentRequest>";

        $checkout_token = base64_encode($payloadXML); //encode with base64

        \Log::info('2c2p_checkout_token', [$checkout_token]);

        return $checkout_token;
    }

    public function setVersion($version)
    {
        $this->Version = $version;

        return $this;
    }
}
