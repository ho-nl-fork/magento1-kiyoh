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

class Magmodules_Kiyoh_Model_Stats extends Mage_Core_Model_Abstract
{

    public function _construct()
    {
        parent::_construct();
        $this->_init('kiyoh/stats');
    }

    public function processFeed($feed, $storeId = 0)
    {
        $shopId = Mage::getStoreConfig('kiyoh/general/api_id', $storeId);

        if ($feed && isset($feed['numberReviews'])) {
            $data['company'] = array();
            $data['review_list'] = array();
            $data['company']['total_reviews'] = $feed['numberReviews'];
            $data['company']['total_score'] = $feed['averageRating'];
            $data['company']['url'] = $feed['viewReviewUrl'];


            if ($storeId == 0) {
                $config = Mage::getModel('core/config');
                $config->saveConfig('kiyoh/general/url', $data['company']['url'], 'default', $storeId);
                $config->saveConfig('kiyoh/general/company', $data['company']['locationName'], 'default', $storeId);
            } else {
                $config = Mage::getModel('core/config');
                $config->saveConfig('kiyoh/general/url', $data['company']['url'], 'stores', $storeId);
                if (!Mage::getStoreConfig('kiyoh/general/url', 0)) {
                    $config->saveConfig('kiyoh/general/url', $data['company']['url'], 'default', 0);
                }

                if (!Mage::getStoreConfig('kiyoh/general/company', 0)) {
                    $config->saveConfig('kiyoh/general/company', $data['company']['locationName'], 'default', 0);
                }
            }
        }

        if ($data['company']['total_reviews'] > 0) {
            $score = floatval($data['company']['total_score']);
            $score = ($score * 10);
            $scoremax = '100';
            $votes = $data['company']['total_reviews'];

            // Check for update or save
            if ($indatabase = $this->loadbyShopId($shopId)) {
                $id = $indatabase->getId();
            } else {
                $id = '';
            }

            // Save Review Stats
            $model = Mage::getModel('kiyoh/stats');
            $model->setId($id)
                ->setShopId($shopId)
                ->setCompany($data['company']['locationName'])
                ->setScore($score)
                ->setScoremax($scoremax)
                ->setVotes($votes)
                ->save();
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     */
    public function processOverall()
    {
        $stats = Mage::getModel('kiyoh/stats')->getCollection();
        $stats->addFieldToFilter('shop_id', array('neq' => '0'));

        $score = '';
        $scoremax = '';
        $votes = '';
        $i = 0;

        foreach ($stats as $stat) {
            $score += $stat->getScore();
            $scoremax += $stat->getScoremax();
            $votes += $stat->getVotes();
            $i++;
        }

        if($i > 0) {
            $score = ($score / $i);
            $scoremax = ($scoremax / $i);
        }
        $company = 'Overall';

        if ($indatabase = $this->loadbyShopId(0)) {
            $id = $indatabase->getId();
        } else {
            $id = '';
        }

        Mage::getModel('kiyoh/stats')
            ->setId($id)
            ->setShopId(0)
            ->setCompany($company)
            ->setScore($score)
            ->setScoremax($scoremax)
            ->setVotes($votes)
            ->save();
    }

    public function loadbyShopId($shopId)
    {
        $this->_getResource()->load($this, $shopId, 'shop_id');
        return $this;
    }

}