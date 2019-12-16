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

/**
 * Class Magmodules_Kiyoh_Model_Reviews
 */
class Magmodules_Kiyoh_Model_Reviews extends Mage_Core_Model_Abstract
{

    const CACHE_TAG = 'kiyoh_block';

    public function _construct()
    {
        parent::_construct();
        $this->_init('kiyoh/reviews');
    }

    public function loadbyKiyohId($kiyohId)
    {
        $this->_getResource()->load($this, $kiyohId, 'review_string_id');
        return $this;
    }

    /**
     * @param $feed
     * @param int $storeId
     * @param $type
     * @return array
     */
    public function processFeed($feed, $storeId = 0, $type)
    {
        if ($feed && isset($feed['numberReviews'])){
            $data['company'] = array();
            $data['review_list'] = array();
            $data['company']['total_reviews'] = $feed['numberReviews'];
            $data['company']['total_score'] = $feed['averageRating'];
            $data['company']['url'] = $feed['viewReviewUrl'];
        }


        $updates = 0;
        $new = 0;
        $apiId = Mage::getStoreConfig('kiyoh/general/api_id', $storeId);
        $company = $feed['locationName'];

        foreach ($feed['reviews'] as $review) {
            $kiyohId = $review['reviewId'];
            $customerName = $review['reviewAuthor'];
            $customerPlace = $review['city'];
            $date = $review['updatedSince'];
            $totalScore = $review['rating'];

            foreach($review['reviewContent'] as $question) {
                $questionGroup = $question['questionGroup'];
                if ($questionGroup == 'DEFAULT_ONELINER') {
                    $reaction = $question['rating'];
                }
                if ($questionGroup == 'DEFAULT_OVERALL') {
                    $totalScore = $question['rating'];
                }
                if ($questionGroup == 'DEFAULT_OVERALL') {
                    $recommendation = $question['rating'];
                    if ($recommendation == 'false') {
                        $recommendation = 0;
                    } else {
                        $recommendation = 1;
                    }
                }
            }

            $indatabase = $this->loadbyKiyohId($kiyohId);

            if ($indatabase->getId()) {
                if ($type == 'history') {
                    $reviews = Mage::getModel('kiyoh/reviews');
                    $reviews->setReviewId($indatabase->getReviewId())
                        ->setShopId($apiId)
                        ->setCompany($company)
                        ->setKiyohId($kiyohId)
                        ->setCustomerName($customerName)
                        ->setCustomerPlace($customerPlace)
                        ->setScore($totalScore)
                        ->setRecommendation($recommendation)
                        ->setReaction($reaction)
                        ->setDateCreated($date)
                        ->save();
                    $updates++;
                } else {
                    continue;
                }
            } else {
                $reviews = Mage::getModel('kiyoh/reviews');
                $reviews->setShopId($apiId)
                    ->setCompany($company)
                    ->setKiyohId($kiyohId)
                    ->setCustomerName($customerName)
                    ->setCustomerPlace($customerPlace)
                    ->setScore($totalScore)
                    ->setRecommendation($recommendation)
                   ->setReaction($reaction)
                    ->setDateCreated($date)
                    ->save();
                $new++;
            }
        }

        // Need to query db because module is saving to cached config data
        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');
        $query = 'SELECT value FROM ' . $resource->getTableName('core/config_data') . ' WHERE PATH=\'kiyoh/reviews/lastrun\'';
        $results = $readConnection->fetchAll($query);
        $date = new DateTime($results[0]['value']);
        if (strtotime($date->format('Y-m-d')) < time()) {
            $date->add(new DateInterval('P1D'));
        }

        $config = Mage::getModel('core/config');
        $config->saveConfig('kiyoh/reviews/lastrun', $date->format('Y-m-d H:i:s'), 'default', 0);
        $result = array();
        $result['review_updates'] = $updates;
        $result['review_new'] = $new;
        $result['company'] = $company;
        return $result;
    }

}