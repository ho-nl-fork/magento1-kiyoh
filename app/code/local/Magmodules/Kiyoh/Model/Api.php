<?php

/**
 * Magmodules.eu - http://www.magmodules.eu
 *
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@magmodules.eu so we can send you a copy immediately.
 *
 * @category      Magmodules
 * @package       Magmodules_Kiyoh
 * @author        Magmodules <info@magmodules.eu)
 * @copyright     Copyright (c) 2017 (http://www.magmodules.eu)
 * @license       http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Magmodules_Kiyoh_Model_Api extends Mage_Core_Model_Abstract
{

    /**
     * @param int $storeId
     * @param     $type
     *
     * @return bool
     */
    public function processFeed($storeId = 0, $type)
    {
        if ($feed = $this->getFeed($storeId, $type)) {
            $results = Mage::getModel('kiyoh/reviews')->processFeed($feed, $storeId, $type);
            $results['stats'] = Mage::getModel('kiyoh/stats')->processFeed($feed, $storeId);
            return $results;
        } else {
            return false;
        }
    }

    /**
     * @param        $storeId
     * @param string $type
     *
     * @return bool|SimpleXMLElement
     */
    public function getFeed($storeId, $type = '')
    {
        $apiId = Mage::getStoreConfig('kiyoh/general/api_id', $storeId);
        $apiKey = Mage::getStoreConfig('kiyoh/general/api_key', $storeId);
        $apiUrl = Mage::getStoreConfig('kiyoh/general/api_url', $storeId);

        if ($type == 'stats') {
            $apiUrl = 'https://' . $apiUrl . '/xml/recent_company_reviews.xml?';
            $apiUrl .= 'connectorcode=' . $apiKey . '&company_id=' . $apiId . '&reviewcount=10';
        }

        if ($type == 'reviews') {
            $apiUrl = 'https://' . $apiUrl . '/xml/recent_company_reviews.xml?';
            $apiUrl .= 'connectorcode=' . $apiKey . '&company_id=' . $apiId . '&reviewcount=10';
        }

        if ($type == 'history') {
            $apiUrl = 'https://' . $apiUrl . '/xml/recent_company_reviews.xml?';
            $apiUrl .= 'connectorcode=' . $apiKey . '&company_id=' . $apiId . '&reviewcount=10000';
        }

        // Need to query db because module is saving to cached config data
        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');
        $query = 'SELECT value FROM ' . $resource->getTableName('core/config_data') . ' WHERE PATH=\'kiyoh/reviews/lastrun\'';
        $results = $readConnection->fetchAll($query);
        $date = new DateTime($results[0]['value']);

        $apiUrl = 'https://www.kiyoh.com/v1/publication/review/external?locationId=1046100&tenantId=98&dateSince=' . $date->format('Y-m-d\TH:i:s.v\Z');

        $hash = '90e43255-7ea3-47dc-89f0-aa5f2dfed0ad';

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'X-Publication-Api-Token: ' . $hash
        ));
        //return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $result = curl_exec($ch);
        return json_decode($result, true);

        if ($apiId) {
            $xml = simplexml_load_file($apiUrl);
            if ($xml) {
                if (empty($xml->error)) {
                    return $xml;
                } else {
                    $msg = Mage::helper('kiyoh')->__('API: %s (Please check the online manual for suggestions)', (string)$xml->error);
                    Mage::getSingleton('adminhtml/session')->addError($msg);
                    return false;
                }
            } else {
                $e = file_get_contents($apiUrl);
                $msg = Mage::helper('kiyoh')->__('API: %s (Please check the online manual for suggestions)', $e);
                Mage::getSingleton('adminhtml/session')->addError($msg);
                return false;
            }
        } else {
            return false;
        }

    }

    /**
     * @param $order
     *
     * @return bool
     */
    public function sendInvitation($order)
    {
        $storeId = $order->getStoreId();
        $startTime = microtime(true);
        $crontype = 'orderupdate';
        $apiKey = Mage::getStoreConfig('kiyoh/general/api_key', $storeId);
        $apiUrl = Mage::getStoreConfig('kiyoh/general/api_url', $storeId);
        $apiEmail = Mage::getStoreConfig('kiyoh/invitation/company_email', $storeId);
        $delay = Mage::getStoreConfig('kiyoh/invitation/delay', $storeId);
        $invStatus = Mage::getStoreConfig('kiyoh/invitation/status', $storeId);
        $email = strtolower($order->getCustomerEmail());
        $hash = '90e43255-7ea3-47dc-89f0-aa5f2dfed0ad';

        if ($order->getStatus() == $invStatus) {
            $url = 'https://www.kiyoh.com/v1/invite/external?hash=' . $hash . '&location_id=1046100&tenantId=98&invite_email=' . $email . '&delay=' . $delay . '&first_name=' . $order->getCustomerFirstname() . '&last_name=' . $order->getCustomerLastname() . '&language=nl&ref_code=' . $order->getIncrementId();

            $handle = curl_init();
            curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($handle, CURLOPT_TIMEOUT, 30);
            curl_setopt($handle, CURLOPT_URL, $url);
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($handle);
            $responseHtml = curl_getinfo($handle, CURLINFO_HTTP_CODE);
            curl_close($handle);

            Mage::getModel('kiyoh/log')->add(
                'invitation',
                $order->getStoreId(),
                '',
                $responseHtml,
                (microtime(true) - $startTime),
                $crontype,
                $url,
                $order->getId()
            );

            return true;
        }

        return false;
    }

    /**
     * @return array
     */
    public function getStoreIds()
    {
        $storeIds = array();
        $apiIds = array();
        $stores = Mage::getModel('core/store')->getCollection();
        foreach ($stores as $store) {
            if ($store->getIsActive()) {
                $apiId = Mage::getStoreConfig('kiyoh/general/api_id', $store->getId());
                if (!in_array($apiId, $apiIds)) {
                    $apiIds[] = $apiId;
                    $storeIds[] = $store->getId();
                }
            }
        }

        return $storeIds;
    }

}