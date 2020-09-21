<?php

require_once 'Mage/Wishlist/controllers/IndexController.php';

class VS7_WishlistShareSelect_IndexController extends Mage_Wishlist_IndexController
{
    public function preDispatch()
    {
        $this->_skipAuthentication = true;
        parent::preDispatch();
    }

    public function updateAction()
    {
        if (!$this->_validateFormKey()) {
            return $this->_redirect('*/*/');
        }
        $wishlist = $this->_getWishlist();
        if (empty($wishlist)) {
            return $this->norouteAction();
        }
        if ($wishlist->getItemCollection()->getSize() == 0) { // Empty Wishlist
            $this->_redirect('*/');
            return;
        }

        parent::updateAction();

        $patchedItems = Mage::registry('vs7_wishlist_patched_share_items');

        $post = $this->getRequest()->getPost();
        if ($post && isset($post['description']) && is_array($post['description'])) {
            $updatedItems = 0;

            foreach ($post['description'] as $itemId => $description) {
                if (!is_array($patchedItems) || !in_array($itemId, $patchedItems)) {
                    $item = Mage::getModel('wishlist/item')->load($itemId);
                    if ($item->getId() == null) continue;
                    $postedShare = is_null($post['show_in_share'][$itemId]) ? 0 : 1;
                    $share = (int)$item->getShowInShare();
                    if ($postedShare != $share) {
                        $item->setShowInShare($postedShare);
                        $item->save();
                        $updatedItems++;
                    }
                }
            }

            if ($updatedItems) {
                try {
                    $wishlist->save();
                    Mage::helper('wishlist')->calculate();
                } catch (Exception $e) {
                    Mage::getSingleton('customer/session')->addError($this->__('Can\'t update wishlist'));
                }
            }
        }
        if (isset($post['save_and_share'])) {
            $this->_redirect('*/*/share', array('wishlist_id' => $wishlist->getId()));
            return;
        }
    }

    public function shareAction()
    {
        $wishlistCollection = $this->_getWishlist()->getItemCollection();
        $checkWishlistCollection = clone $wishlistCollection;
        $originalSelectPartWhere = $wishlistCollection->getSelect()->getPart(Varien_Db_Select::WHERE);
        if ($checkWishlistCollection->addFieldToFilter('show_in_share', 1)->getSize() > 0) {
            $wishlistCollection->getSelect()->setPart(Varien_Db_Select::WHERE, $originalSelectPartWhere);
            parent::shareAction();
        } else {
            $this->_redirect('*/');
            return;
        }
    }

    public function sendAction()
    {
        if ($this->_getWishlist()->getItemCollection()->addFieldToFilter('show_in_share', 1)->getSize() > 0) {
//            Mage::register('vs7_wishlistshareselect_apply_filter', true);
            $emails  = explode(',', $this->getRequest()->getPost('emails'));
            if (count($emails) > Mage::getStoreConfig('vs7_wishlistshareselect/email/recipients_count')) {
                Mage::getSingleton('core/session')->addError($this->__('Max count of recipients exceeded'));
                return $this->_redirect('*/');
            }
            $secretKey = Mage::getStoreConfig('vs7_wishlistshareselect/general/secret');
            $gRecaptchaResponse = $this->getRequest()->getPost('g-recaptcha-response');
            if (empty($gRecaptchaResponse)) {
                return $this->_redirect('*/');
            }
            $remoteIp = $_SERVER['HTTP_X_REAL_IP'];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://www.google.com/recaptcha/api/siteverify');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'secret=' . $secretKey . '&response=' . $gRecaptchaResponse . '&remoteip=' . $remoteIp);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $recaptchaCheck = curl_exec($ch);
            curl_close ($ch);
            $result = Mage::helper('core')->jsonDecode($recaptchaCheck);
            $result['post'] = $_POST;
            Mage::log($result, null, 'Wlemail.log', true);
            if (!isset($result['success']) || $result['success'] != 'true') {
                return $this->_redirect('*/');
            }

            parent::sendAction();
//            Mage::unregister('vs7_wishlistshareselect_apply_filter');
        } else {
            $this->_redirect('*/');
            return;
        }
    }
}