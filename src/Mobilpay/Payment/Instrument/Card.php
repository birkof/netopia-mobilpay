<?php

namespace Mobilpay\Payment\Instrument;

/**
 * Class Card
 * @copyright   Copyright (c) NETOPIA
 * @author      Claudiu Tudose / maintainer  Daniel Stancu
 * @version     2.0
 *
 */
class Card
{
    const ERROR_LOAD_FROM_XML_CARD_ELEM_MISSING = 0x40000001;
    const ERROR_LOAD_FROM_XML_EXP_YEAR_ELEM_MISSING = 0x40000002;
    const ERROR_LOAD_FROM_XML_EXP_MONTH_ELEM_MISSING = 0x40000003;
    const ERROR_LOAD_FROM_XML_CVV2_ELEM_MISSING = 0x40000004;
    const ERROR_INVALID_CARD = 0x40000021;
    const ERROR_INVALID_EXP_YEAR = 0x40000022;
    const ERROR_INVALID_EXP_MONTH = 0x40000023;
    const ERROR_INVALID_CVV2 = 0x40000024;

    public $number = null;
    public $name = null;
    public $expYear = null;
    public $expMonth = null;
    public $cvv2 = null;

    public function __construct(\DOMNode $elem = null)
    {
        if ($elem != null) {
            $this->loadFromXml($elem);
        }
    }

    protected function loadFromXml(\DOMNode $elem)
    {

        $elems = $elem->getElementsByTagName('number');
        if ($elems->length != 1) {
            throw new \Exception(
                'Mobilpay\Payment\Instrument\Card::loadFromXml failed: invalid card number',
                self::ERROR_LOAD_FROM_XML_CARD_ELEM_MISSING
            );
        }
        $xmlElem = $elems->item(0);
        $this->number = $xmlElem->nodeValue;

        $elems = $elem->getElementsByTagName('exp_year');
        if ($elems->length != 1) {
            throw new \Exception(
                'Mobilpay\Payment\Instrument\Card::loadFromXml failed: invalid exp year',
                self::ERROR_LOAD_FROM_XML_EXP_YEAR_ELEM_MISSING
            );
        }
        $xmlElem = $elems->item(0);
        $this->expYear = $xmlElem->nodeValue;

        $elems = $elem->getElementsByTagName('exp_month');
        if ($elems->length != 1) {
            throw new \Exception(
                'Mobilpay\Payment\Instrument\Card::loadFromXml failed: invalid exp month',
                self::ERROR_LOAD_FROM_XML_EXP_MONTH_ELEM_MISSING
            );
        }
        $xmlElem = $elems->item(0);
        $this->expMonth = $xmlElem->nodeValue;

        $elems = $elem->getElementsByTagName('cvv2');
        if ($elems->length != 1) {
            if (substr($this->number, 1) != 6) {
                throw new \Exception(
                    'Mobilpay\Payment\Instrument\Card::loadFromXml failed: invalid cvv2',
                    self::ERROR_LOAD_FROM_XML_CVV2_ELEM_MISSING
                );
            } else {
                $this->cvv2 = '';
            }
        } else {
            $xmlElem = $elems->item(0);
            $this->cvv2 = $xmlElem->nodeValue;
        }

        $elems = $elem->getElementsByTagName('name');
        if ($elems->length == 1) {
            $xmlElem = $elems->item(0);
            $this->name = $xmlElem->nodeValue;
        } else {
            $this->name = '';
        }
    }

    public function createXmlElement(\DOMDocument $xmlDoc)
    {
        if (!($xmlDoc instanceof \DOMDocument)) {
            throw new \Exception('', self::ERROR_INVALID_PARAMETER);
        }

        $xmlCardElem = $xmlDoc->createElement('card');

        if (is_null($this->number)) {
            throw new \Exception('Invalid card number', self::ERROR_INVALID_CARD);
        }

        if (is_null($this->expYear)) {
            throw new \Exception('Invalid exp year', self::ERROR_INVALID_EXP_YEAR);
        }

        if (is_null($this->expMonth)) {
            throw new \Exception('Invalid exp month', self::ERROR_INVALID_EXP_MONTH);
        }

        if (empty($this->cvv2) && substr($this->number, 1) != 6) {
            throw new \Exception('Invalid cvv2', self::ERROR_INVALID_CVV2);
        }

        $xmlElem = $xmlDoc->createElement('number', $this->number);
        $xmlCardElem->appendChild($xmlElem);

        $xmlElem = $xmlDoc->createElement('exp_year', $this->expYear);
        $xmlCardElem->appendChild($xmlElem);

        $xmlElem = $xmlDoc->createElement('exp_month', $this->expMonth);
        $xmlCardElem->appendChild($xmlElem);

        $xmlElem = $xmlDoc->createElement('cvv2', empty($this->cvv2) ? '' : $this->cvv2);
        $xmlCardElem->appendChild($xmlElem);

        $xmlElem = $xmlDoc->createElement('name', empty($this->name) ? '' : $this->name);
        $xmlCardElem->appendChild($xmlElem);

        return $xmlCardElem;
    }

}
