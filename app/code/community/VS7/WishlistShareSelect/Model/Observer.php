<?php

class VS7_WishlistShareSelect_Model_Observer extends Mage_Core_Model_Abstract
{

    public function addShowInShareFilter($observer)
    {
        $collection = $observer->getCollection();
        if ($collection && $collection instanceof Mage_Wishlist_Model_Resource_Item_Collection) {
            if (
                empty($collection->getPageSize())
                && (
                    $collection->getSelect()->getPart(Zend_Db_Select::LIMIT_COUNT) != null
                    || $collection->getSelect()->getPart(Zend_Db_Select::LIMIT_OFFSET) != null
                )
            ) {
                $collection->getSelect()->reset(Zend_Db_Select::LIMIT_COUNT);
                $collection->getSelect()->reset(Zend_Db_Select::LIMIT_OFFSET);
            }
            if (Mage::registry('vs7_wishlistshareselect_apply_filter')) {
                $collection->addFieldToFilter('show_in_share', 1);
            }
        }
    }

    public function saveItemShare($observer)
    {
        $object = $observer->getObject();
        if ($object && $object instanceof Mage_Wishlist_Model_Item) {
            $patchedItems = Mage::registry('vs7_wishlist_patched_share_items');
            if (empty($patchedItems)) {
                $patchedItems = array();
            }
            $post = Mage::app()->getRequest()->getPost();
            if ($post && isset($post['description']) && is_array($post['description'])) {
                $postedShare = is_null($post['show_in_share'][$object->getWishlistItemId()]) ? 0 : 1;
                $share = (int)$object->getShowInShare();
                if ($postedShare != $share) {
                    $object->setShowInShare($postedShare);
                }
                $patchedItems[] = $object->getWishlistItemId();
                Mage::unregister('vs7_wishlist_patched_share_items');
                Mage::register('vs7_wishlist_patched_share_items', $patchedItems);
            }
        }
    }

    public function registryApplyFilter($observer)
    {
        $event = $observer->getEvent();
        $block = $observer->getBlock();
        if (!empty($event) && !empty($block)) {
            $eventName = $event->getName();
            $blockName = $block->getNameInLayout();
            if ($blockName == 'customer.wishlist') {
                if ($eventName == 'core_block_abstract_to_html_before') {
                    Mage::register('vs7_wishlistshareselect_apply_filter', true);
                }
                if ($eventName == 'core_block_abstract_to_html_after') {
                    Mage::unregister('vs7_wishlistshareselect_apply_filter');
                }
            }
        }
    }
}