<?php

/**
 * Geo Code
 * Extension for Contao Open Source CMS (contao.org)
 *
 * Copyright (c) 2014 de la Haye
 *
 * @package dlh_geocode
 * @author  Christian de la Haye
 * @link    http://delahaye.de
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */



/**
 * Run in a custom namespace, so the class can be replaced
 */
namespace delahaye;


/**
 * Class GeoCode
 *
 * Get geocoordinates for a given address by Google
 * @copyright  2014 de la Haye
 * @author     Christian de la Haye
 * @package    dlh_geocode
 */
class GeoCode
{

    /**
     * Get instance
     * @return object
     */
    static protected $instance;
    public static function getInstance() {
        if(self::$instance == null) {
            self::$instance = new GeoCode();
        }
        return self::$instance;
    }


    /**
     * Get geo coordinates from an address
     * @param string
     * @param string
     * @param string
     * @return string
     */
    public static function getCoordinates($strAddress, $strCountry = 'de', $strLang = 'de')
    {
        if ($strAddress)
        {
            $arrCoords = self::getInstance()->geoCode($strAddress, null, $strLang, $strCountry);

            if($arrCoords)
            {
                $strValue = $arrCoords['lat'] . ',' . $arrCoords['lng'];
            }
            elseif(function_exists("curl_init"))
            {
                $strValue = self::geoCodeCurl($strAddress, $strCountry);
            }
        }

        return $strValue==',' ? '' : $strValue;
    }



    /**
     * Get geo coordinates from address, thanks to Oliver Hoff <oliver@hofff.com>
     * @param array
     * @param bool
     * @param string
     * @param string
     * @param array
     * @return array
     */
    private $arrGeocodeCache = array();
    protected function geoCode($varAddress, $blnReturnAll = false, $strLang = 'de', $strRegion = 'de', array $arrBounds = null)
    {
        if(ini_get('allow_url_fopen') != 1)
            return;

        if(is_array($varAddress))
            $varAddress = implode(' ', $varAddress);

        $varAddress = trim($varAddress);

        if(!strlen($varAddress) || !strlen($strLang))
            return;

        if($strRegion !== null && !strlen($strRegion))
            return;

        if($arrBounds !== null) {
            if(!is_array($arrBounds) || !is_array($arrBounds['tl']) || !is_array($arrBounds['br'])
                || !is_numeric($arrBounds['tl']['lat']) || !is_numeric($arrBounds['tl']['lng'])
                || !is_numeric($arrBounds['br']['lat']) || !is_numeric($arrBounds['br']['lng']))
                return;
        }

        $strURL = sprintf(
            'http://maps.google.com/maps/api/geocode/json?address=%s&sensor=false&language=%s&region=%s&bounds=%s',
            urlencode($varAddress),
            urlencode($strLang),
            strlen($strRegion) ? urlencode($strRegion) : '',
            $arrBounds ? implode(',', $arrBounds['tl']) . '|' . implode(',', $arrBounds['br']) : ''
        );

        if(!isset($this->arrGeocodeCache[$strURL])) {
            $arrGeo = json_decode(file_get_contents($strURL), true);
            $this->arrGeocodeCache[$strURL] = $arrGeo['status'] == 'OK' ? $arrGeo['results'] : false;
        }

        if(!$this->arrGeocodeCache[$strURL])
            return;

        return $blnReturnAll ? $this->arrGeocodeCache[$strURL] : array(
            'lat' => $this->arrGeocodeCache[$strURL][0]['geometry']['location']['lat'],
            'lng' => $this->arrGeocodeCache[$strURL][0]['geometry']['location']['lng']
        );
    }


    /**
     * Get geo coordinates from address by CURL as fallback
     * @param string
     * @param string
     * @return string
     */
    protected static function geoCodeCurl($strAddress, $strCountry)
    {
        $strGeoURL = 'http://maps.google.com/maps/api/geocode/xml?address='.str_replace(' ', '+', $strAddress).'&sensor=false'.($strCountry ? '&region='.$strCountry : '');

        $curl = curl_init();
        if($curl)
        {
            if(curl_setopt($curl, CURLOPT_URL, $strGeoURL) && curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1) && curl_setopt($curl, CURLOPT_HEADER, 0))
            {
                $curlVal = curl_exec($curl);
                curl_close($curl);
                $xml = new \SimpleXMLElement($curlVal);
                if($xml)
                {
                    $strValue = $xml->result->geometry->location->lat . ',' . $xml->result->geometry->location->lng;
                }
            }
        }

        return $strValue==',' ? '' : $strValue;
    }

}