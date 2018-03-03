<?php
/**
 * @package	HikaShop for Joomla!
 * @version	3.3.0
 * @author	hikashop.com
 * @copyright	(C) 2010-2018 HIKARI SOFTWARE. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');
?><?php
class CartController extends hikashopController {
	public $display = array(
		'show', 'listing', 'cancel', '',
		'share','printcart',
		'showcart','showcarts'
	);
	public $modify_views = array();
	public $add = array('add');
	public $modify = array(
		'apply','save',
		'setcurrent',
		'addtocart',
		'sendshare'
	);
	public $delete = array('remove');
	public $type = 'cart';

	public function __construct($config = array(), $skip = false) {
		parent::__construct($config, $skip);

		$config =& hikashop_config();
		if($config->get('checkout_legacy', 0)) {
			$this->display[] = 'convert';
		} else {
			$this->modify[] = 'convert';
		}

		if(!$skip) {
			$this->registerDefaultTask('show');
		}
	}

	protected function isLogged() {
		$user_id = hikashop_loadUser(false);
		if(!empty($user_id))
			return true;

		$app = JFactory::getApplication();
		$app->enqueueMessage(JText::_('PLEASE_LOGIN_FIRST'));

		global $Itemid;
		$suffix = (!empty($Itemid) ? '&Itemid=' . $Itemid : '');

		if(!HIKASHOP_J16) {
			$url = 'index.php?option=com_user&view=login';
		} else {
			$url = 'index.php?option=com_users&view=login';
		}

		$app->redirect(JRoute::_($url . $suffix . '&return='.urlencode(base64_encode(hikashop_currentUrl('', false))), false));
		return false;
	}

	public function show() {
		hikashop_nocache();

		$cartClass = hikashop_get('class.cart');
		$cart_id = hikashop_getCID('cart_id');

		if(empty($cart_id)) {
			$cart_id = 0;

			$cart_type = hikaInput::get()->getCmd('cart_type', '');

			if(empty($cart_type)) {
				$app = JFactory::getApplication();
				$menus = $app->getMenu();
				$menu = $menus->getActive();
				global $Itemid;
				if(empty($menu) && !empty($Itemid)) {
					$menus->setActive($Itemid);
					$menu = $menus->getItem($Itemid);
				}
				if(is_object($menu) && is_object($menu->params))
					$cart_type = $menu->params->get('cart_type');
			}

			if(!empty($cart_type) && $cart_type == 'wishlist') {
				$cart_id = $cartClass->getCurrentCartId('wishlist');
				if(empty($cart_id)) {
					$this->setRedirect(hikashop_completeLink('user'), JText::_('WISHLIST_EMPTY'));
					return true;
				}
				hikaInput::get()->set('cart_id', $cart_id);
			}
		}

		$cart = $cartClass->get($cart_id);

		if(empty($cart) && empty($cart_id)) {
			hikashop_get('helper.checkout');
			$checkoutHelper = hikashopCheckoutHelper::get();
			$this->setRedirect($checkoutHelper->getRedirectUrl(), JText::_('CART_EMPTY'));
			return true;
		}

		if(empty($cart))
			return false;

		$user_id = hikashop_loadUser(false);
		if($cart->cart_type == 'wishlist' && $cart->user_id != $user_id && $cart->cart_share == 'email') {
			$token = hikaInput::get()->getString('token');
			if(!empty($cart->cart_params->token) && $token != $cart->cart_params->token) {
				$app = JFactory::getApplication();
				$app->enqueueMessage(JText::_('CART_SHARE_INVALID_TOKEN'), 'error');
				return false;
			}
		}

		return parent::show();
	}

	public function edit() {
		return $this->show();
	}

	public function showcart() {
		return $this->show();
	}

	public function listing() {
		if($this->isLogged() == false)
			return false;
		return parent::listing();
	}

	public function showcarts() {
		return $this->listing();
	}

	public function share() {
		hikashop_nocache();
		hikaInput::get()->set('tmpl','component');
		$cart_id = hikashop_getCID('cart_id');
		if(empty($cart_id)) {
			hikashop_display('No wishlist ID provided');
			return false;
		}
		$cartClass = hikashop_get('class.cart');
		$cart = $cartClass->getFullCart($cart_id);
		if(empty($cart)) {
			hikashop_display('We couldn`\'t find any wishlist to share for the id '.$cart_id);
			return false;
		}
		if($cart->cart_type != 'wishlist') {
			hikashop_display('The Id provided is not a wishlist');
			return false;
		}
		$user_id = hikashop_loadUser(false);
		if($cart->user_id != $user_id) {
			hikashop_display('You are not the owner of the wishlist');
			return false;
		}
		hikaInput::get()->set('layout', 'share');
		return parent::display();
	}

	public function sendshare(){
		$emails = hikaInput::get()->getVar('emails','');
		if(empty($emails)) {
			hikashop_display(JText::_('PLEASE_ENTER_EMAIL_ADDRESSES'), 'error');
			return $this->share();
		}
		hikashop_nocache();
		$emails = preg_split("/[\s,]+/", $emails);
		jimport('joomla.mail.helper');
		$ok = true;
		$bcc = array();
		foreach($emails as $k => $email){
			$email = trim($email);
			if(empty($email))
				continue;
			if(method_exists('JMailHelper', 'isEmailAddress') && !JMailHelper::isEmailAddress($email)){
				hikashop_display(JText::sprintf('THE_EMAIL_ADDRESS_X_IS_INVALID', $email), 'error');
				$ok = false;
			}else{
				$bcc[] = $email;
			}
		}



		if(!$ok)
			return $this->share();

		$cart_id = hikashop_getCID('cart_id');
		if(empty($cart_id)) {
			hikashop_display('No wishlist ID provided');
			return false;
		}

		$cartClass = hikashop_get('class.cart');
		$cart = $cartClass->getFullCart($cart_id);
		$user_id = hikashop_loadUser(false);
		if($cart->user_id != $user_id) {
			hikashop_display('You are not the owner of the wishlist');
			return false;
		}

		if($cart->cart_share == 'nobody'){
			$cart->cart_share = 'email';
			$cartClass->save($cart);
		}

		$mail = $cartClass->loadNotification($cart_id, 'wishlist_share');

		if(!$mail)
			hikashop_display('We couldn`\'t find any wishlist to share for the id '.$cart_id);

		$copy = hikaInput::get()->getInt('copy');
		if($copy){
			$bcc[] = $mail->data->user->user_email;
		}
		$mail->bcc_email = $bcc;
		$mailClass = hikashop_get('class.mail');
		$status = $mailClass->sendMail($mail);
		if(!$status) {
			hikashop_display(JText::_('AN_ERROR_OCCURED_DURING_THE_SENDING_OF_THE_EMAIL'), 'error');
			return $this->share();
		}
		hikashop_display(JText::_('THE_EMAIL_HAS_BEEN_SENT_SUCCESSFULLY'));
	}

	public function printcart() {
		hikashop_nocache();

		$cartClass = hikashop_get('class.cart');
		$cart_id = hikashop_getCID('cart_id');

		$cart = $cartClass->get($cart_id);
		if(empty($cart))
			return false;

		hikaInput::get()->set('tmpl','component');
		hikaInput::get()->set('print_cart', true);

		$js = '
window.hikashop.ready(function(){
	window.focus();
	if(document.all) {
		document.execCommand("print", false, null);
	} else {
		window.print();
	}
	setTimeout(function(){ window.top.hikashop.closeBox();}, 2000);
});
';
		$doc = JFactory::getDocument();
		$doc->addScriptDeclaration($js);

		return $this->show();
	}

	public function addtocart() {
		$app = JFactory::getApplication();
		$config = hikashop_config();
		$user_id = hikashop_loadUser(false);
		$cartClass = hikashop_get('class.cart');

		$group = $config->get('group_options', 0);

		$cart_id = hikashop_getCID('cart_id');
		$addto_type = hikaInput::get()->getCmd('addto_type', 'cart');
		$addto_id = hikaInput::get()->getInt('addto_id', 0);
		$request_addto_id = $addto_id;

		$formProducts = hikaInput::get()->get('products', array(), 'array');
		JArrayHelper::toInteger($formProducts);

		$cart = $cartClass->get($cart_id);

		if(empty($cart)) {
			$app->enqueueMessage(JText::_('ERROR'), 'error');
			$app->redirect( hikashop_completeLink('cart&task=listing', false, true) );
			return false;
		}

		$add = $cart->user_id != $user_id || ($request_addto_id === 0 && $addto_type === 'cart');

		$juser = JFactory::getUser();
		if($addto_type == 'wishlist' && (!$config->get('enable_wishlist') || $juser->guest)) {
			if(!$config->get('enable_wishlist'))
				$app->enqueueMessage(JText::_('ERROR'), 'error');
			else
				$app->enqueueMessage(JText::_('LOGIN_REQUIRED_FOR_WISHLISTS'), 'error');

			$app->redirect( hikashop_completeLink('cart&task=show&cid='.$cart_id, false, true) );
			return false;
		}

		$products = array();
		foreach($formProducts as $p) {
			if(!isset($cart->cart_products[$p]))
				continue;

			if($group && !empty($cart->cart_products[$p]->cart_product_option_parent_id))
				continue;

			$products[$p] = $cart->cart_products[$p];
		}

		if(empty($products)) {
			$app->enqueueMessage(JText::_('PLEASE_SELECT_A_PRODUCT_FIRST'), 'error');
			$app->redirect( hikashop_completeLink('cart&task=show&cid='.$cart_id, false, true) );
			return false;
		}

		if(empty($addto_id)) {
			$addto_id = $cartClass->getCurrentCartId($addto_type);
			if($addto_id === false)
				return false;
		}

		if($addto_id <= 0) {
			if(!in_array($addto_type, array('cart','wishlist')))
				return false;
		} else {
			$destCart = $cartClass->get($addto_id);
			if(empty($destCart) || $destCart->cart_type != $addto_type)
				return false;
			if($destCart->cart_type == 'wishlist' && $destCart->user_id != $user_id)
				return false;
		}

		$ret = false;
		if($add) {
			$formData = hikaInput::get()->get('data', array(), 'array');
			foreach($products as $key => &$product) {
				if(!isset($formData['products'][$key]))
					continue;
				$qty_change = $formData['products'][$key]['quantity'] - $product->cart_product_quantity;

				foreach($cart->cart_products as $product_in_cart_key => $product_in_cart){
					if($product_in_cart->cart_product_option_parent_id == $key){
						$products[$product_in_cart_key] = $product_in_cart;
						if($qty_change)
							$products[$product_in_cart_key]->cart_product_quantity = $product_in_cart->cart_product_quantity + ($product_in_cart->cart_product_quantity / $product->cart_product_quantity) * $qty_change;
					}
				}

				$product->cart_product_quantity = (int)$formData['products'][$key]['quantity'];
			}
			unset($product);

			$product_data = $cartClass->cartProductsToArray($products, array('wishlist' => $cart_id));
			$ret = $cartClass->addProduct($addto_id, $product_data);
		} else {
			$cart_product_ids = array_keys($products);
			$ret = $cartClass->moveTo($cart_id, $cart_product_ids, $addto_id, $addto_type);
		}

		if(empty($ret)) {
			$app->enqueueMessage(JText::_('ERROR'), 'error');
			$app->redirect( hikashop_completeLink('cart&task=listing', false, true) );
			return false;
		}

		$translation = $addto_type == 'wishlist' ? 'PRODUCT_SUCCESSFULLY_ADDED_TO_WISHLIST' : 'PRODUCT_SUCCESSFULLY_ADDED_TO_CART';
		$app->enqueueMessage(JText::_($translation));

		$app->redirect( hikashop_completeLink('cart&task=show&cid='.$ret, false, true) );
		return false;
	}

	public function convert() {
		$config = hikashop_config();
		if(!$config->get('enable_wishlist'))
			return false;

		if($this->isLogged() == false)
			return false;

		$app = JFactory::getApplication();
		$cart_id = hikashop_getCID('cart_id');
		$cartClass = hikashop_get('class.cart');

		$ret = $cartClass->convert($cart_id, false);

		if(!$ret) {
			$app->enqueueMessage(JText::_('ERROR'), 'error');
			$app->redirect( hikashop_completeLink('cart&task=listing', false, true) );
			return false;
		}

		$app->enqueueMessage(JText::_('SUCCESS'));
		$app->redirect( hikashop_completeLink('cart&task=show&cid='.(int)$cart_id, false, true) );
		return true;
	}

	public function setcurrent() {
		if($this->isLogged() == false)
			return false;

		$app = JFactory::getApplication();
		$cart_id = hikashop_getCID('cart_id');
		$cartClass = hikashop_get('class.cart');
		$user_id = hikashop_loadUser(false);

		$cart = $cartClass->get($cart_id);
		$cart_type = @$cart->cart_type;

		if(empty($cart) || $cart->user_id != $user_id || !in_array($cart_type, array('cart','wishlist'))) {
			$app->redirect( hikashop_completeLink('cart&task=listing', false, true) );
			return false;
		}

		$ret = $cartClass->setCurrent($cart_id);

		$app = JFactory::getApplication();
		if(!$ret) {
			$app->enqueueMessage(JText::_('ERROR'), 'error');
		}

		$url_params = ($cart->cart_type == 'cart') ? '' : '&cart_type='.$cart->cart_type;
		$app->redirect( hikashop_completeLink('cart&task=listing'.$url_params, false, true) );
		return false;
	}

	public function remove() {
		if($this->isLogged() == false)
			return false;

		$app = JFactory::getApplication();

		$cart_type = '';
		$cids = hikaInput::get()->get('cid', array(), 'array');
		if(empty($cids)) {
			$app->redirect( hikashop_completeLink('cart&task=listing', false, true) );
			return false;
		}

		$cid = is_array($cids) ? (int)reset($cids) : (int)$cid;
		if(!empty($cid) && $cid > 0) {
			$cartClass = hikashop_get('class.cart');
			$cart = $cartClass->get( $cid );
			if(!empty($cart) && in_array($cart->cart_type, array('cart','wishlist')))
				$cart_type = '&cart_type='.$cart->cart_type;
		}

		parent::remove();

		$app->redirect( hikashop_completeLink('cart&task=listing'.$cart_type, false, true) );
		return false;
	}
}
