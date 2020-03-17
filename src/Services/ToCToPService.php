<?php
namespace Maras0830\ToCToP\Services;
use Illuminate\Support\Facades\Config;
use Maras0830\Pay2Go\Pay2Go;
use Maras0830\ToCToP\ToCToP;

class ToCToPService
{
    private $toCtoP;

    public function __construct()
    {
        $this->toCtoP = new ToCToP(Config::get('to_c_to_p.MerchantID'), Config::get('to_c_to_p.SecretKey'));
    }
}
