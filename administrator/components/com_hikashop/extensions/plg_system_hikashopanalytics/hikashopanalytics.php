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
class plgSystemHikashopanalytics extends JPlugin
{
	public function __construct(&$subject, $config) {
		parent::__construct($subject, $config);

		if(isset($this->params))
			return;
		$plugin = JPluginHelper::getPlugin('system', 'hikashopanalytics');
		if(version_compare(JVERSION, '2.5', '<')) {
			jimport('joomla.html.parameter');
			$this->params = new JParameter($plugin->params);
		} else {
			$this->params = new JRegistry($plugin->params);
		}
	}

	public function onBeforeOrderCreate(&$order) {
		if(isset($order->order_type) && $order->order_type != 'sale')
			return;
		$app = JFactory::getApplication();
		if($app->isAdmin())
			return;
		$formData = hikaInput::get()->get('checkout', array(), 'array');
		if(empty($formData) || empty($formData['analytics']) || empty($formData['analytics']['ga']))
			return;

		if(!empty($order->order_payment_params) && is_string($order->order_payment_params))
			$order->order_payment_params = hikashop_unserialize($order->order_payment_params);
		if(empty($order->order_payment_params))
			$order->order_payment_params = new stdClass();
		$order->order_payment_params->analytics_ga = trim($formData['analytics']['ga']);
	}

	public function onAfterOrderCreate(&$order) {
		if(isset($order->order_type) && $order->order_type != 'sale')
			return;

		return $this->checkOrder($order, true);
	}

	public function onAfterOrderUpdate(&$order) {
		if(isset($order->order_type) && $order->order_type != 'sale')
			return;

		return $this->checkOrder($order, false);
	}

	protected function checkOrder(&$order, $creation = false) {
		if(!hikashop_level(2))
			return true;

		if(isset($order->order_type) && $order->order_type != 'sale')
			return true;
		if(isset($order->old->order_type) && $order->old->order_type != 'sale')
			return true;

		if(!isset($order->order_status))
			return true;

		$config = hikashop_config();
		$confirmed_status = $config->get('order_confirmed_status', 'confirmed');

		if( !$this->params->get('use_universal', 0) && $creation ) {
			$app = JFactory::getApplication();
			$app->setUserState(HIKASHOP_COMPONENT.'.ga_check_order', $order->order_id);
			$app->setUserState(HIKASHOP_COMPONENT.'.ga_check_counter', 10);
		}

		if(!$this->params->get('single_submission', 0) && $order->order_status != $confirmed_status)
			return true;

		$invoice_statuses = explode(',', $config->get('invoice_order_statuses','confirmed,shipped'));
		if(empty($invoice_statuses))
			$invoice_statuses = array('confirmed','shipped');
		if($this->params->get('single_submission', 0) && in_array($confirmed_status, $invoice_statuses) && !empty($order->old) && !empty($order->old->order_invoice_id))
			return true;

		$app = JFactory::getApplication();
		$app->setUserState(HIKASHOP_COMPONENT.'.display_ga', 1);
		$app->setUserState(HIKASHOP_COMPONENT.'.order_id', $order->order_id);
		$app->setUserState(HIKASHOP_COMPONENT.'.error_display_ga', 0);

		$app->setUserState(HIKASHOP_COMPONENT.'.ga_check_order', null);
		$app->setUserState(HIKASHOP_COMPONENT.'.ga_check_counter', null);

		if($this->params->get('use_universal', 0)) {
			$call = false;
			if($this->params->get('universal_always_directcall', 0))
				$call = true;
			if(!$call && !$app->isAdmin()) {
				$ctrl = hikaInput::get()->getCmd('ctrl', '');
				$task = hikaInput::get()->getCmd('task', '');
				if($ctrl == 'checkout' && $task == 'notify')
					$call = true;
			}

			if($call) {
				$ret = $this->googleProcess($order->order_id);
				if($ret === true) {
					$app->setUserState(HIKASHOP_COMPONENT.'.display_ga', 0);
				} else if($this->params->get('debug_mode')) {
					$ip = hikashop_getIP();
					$data = 'Transaction validated, postpone the javascript'."\r\n".
						'IP: ' . $ip . "\r\n".
						'URL: ' . hikashop_currentURL();
					$this->writeToLog($data);
				}
			}
		}

		return true;
	}

	public function onAfterRender() {
		$app = JFactory::getApplication();

		$display_ga = (int)$app->getUserState('com_hikashop.display_ga', 0);
		$check_counter = (int)$app->getUserState('com_hikashop.ga_check_counter', 0);
		$check_order = (int)$app->getUserState('com_hikashop.ga_check_order', 0);

		if(empty($display_ga) && (empty($check_counter) || empty($check_order)))
			return true;

		if(!defined('DS'))
			define('DS', DIRECTORY_SEPARATOR);
		if(!include_once(rtrim(JPATH_ADMINISTRATOR,DS).DS.'components'.DS.'com_hikashop'.DS.'helpers'.DS.'helper.php'))
			return true;
		if(!hikashop_level(2))
			return true;

		if(!empty($display_ga)) {
			$order_id = (int)$app->getUserState(HIKASHOP_COMPONENT.'.order_id', 0);
		} else {
			$order_id = $check_order;
			$app->setUserState(HIKASHOP_COMPONENT.'.ga_check_counter', $check_counter - 1);
		}

		$content = $this->googleProcess($order_id, true);
		if(!empty($content) && is_string($content)) {
			$body = JResponse::getBody();
			$alternate_body = false;
			if(empty($body)) {
				$body = $app->getBody();
				$alternate_body = true;
			}

			ini_set('pcre.jit', false);
			$body = preg_replace("#<script type=\"text/javascript\">(?:(?!<script).)*('https://ssl' : 'http://www'\) \+ '\.|window,document,'script','//www\.)google-analytics\.com.*</script>#siU", '', $body);

			$body = str_replace('</head>', $content . '</head>', $body);
			if($alternate_body) {
				$app->setBody($body);
			} else {
				JResponse::setBody($body);
			}

			$app->setUserState(HIKASHOP_COMPONENT.'.display_ga', 0);
			$app->setUserState(HIKASHOP_COMPONENT.'.ga_check_order', null);
			$app->setUserState(HIKASHOP_COMPONENT.'.ga_check_counter', null);
		}
		return true;
	}

	public function onHikashopBeforeDisplayView(&$viewObj) {
		$app = JFactory::getApplication();
		if($app->isAdmin())
			return;

		$viewName = $viewObj->getName();
		if($viewName != 'checkout')
			return;
		$layout = $viewObj->getLayout();
		if(!in_array($layout, array('show', 'step')))
			return;

		$account_configured = false;
		for($i = 1; $i < 6; $i++) {
			$a = $this->params->get('account_' . $i, '');
			if(!empty($a)) {
				$account_configured = true;
				break;
			}
		}
		if(!$account_configured)
			return;

		if(empty($viewObj->extra_data))
			$viewObj->extra_data = array();

		$viewObj->extra_data['analytics_ga'] = '
<input type="hidden" id="hikashop_checkout_analytics_field_ga" name="checkout[analytics][ga]" value=""/>
<script type="text/javascript">
window.hikashop.ready(function(){
	if(!ga || typeof(ga) != "function")
		return;
	ga(function(tracker){
		var clientId = tracker.get("clientId"),
			elem = document.getElementById("hikashop_checkout_analytics_field_ga");
		if(elem) elem.value = clientId;
	});
});
</script>';
	}

	protected function googleProcess($order_id = 0, $render = false) {
		$accounts = array();
		$account_configured = false;
		for($i = 1; $i < 6; $i++) {
			$accounts[$i] = new stdClass();
			$accounts[$i]->account_id = $this->params->get('account_' . $i, '');
			$accounts[$i]->currency = $this->params->get('currency_' . $i, 0);

			if(!empty($accounts[$i]->account_id))
				$account_configured = true;
		}

		if(empty($accounts) || !$account_configured)
			return false;

		$app = JFactory::getApplication();
		if(empty($order_id))
			$order_id = hikaInput::get()->getInt('order_id');
		if(empty($order_id))
			$order_id = $app->getUserState(HIKASHOP_COMPONENT.'.order_id');

		if(empty($order_id))
			return false;

		$config = hikashop_config();
		$confirmed_status = $config->get('order_confirmed_status', 'confirmed');

		$orderClass = hikashop_get('class.order');
		$order = $orderClass->loadFullOrder($order_id, false, false);

		if(empty($order) || $order->order_status != $confirmed_status)
			return false;

		if($order->order_type != 'sale')
			return false;

		$currencyClass = hikashop_get('class.currency');
		$null = array();
		$currencies = array(
			$order->order_currency_id => $order->order_currency_id
		);
		$currencyInfo = $currencyClass->getCurrencies($currencies, $null);
		$currencyInfo = reset($currencyInfo);

		if($this->params->get('use_universal', 0)) {
			$call = (int)$this->params->get('universal_always_directcall', 0);
			if($call && $render)
				return true;

			if(!$call && !$app->isAdmin()) {
				$ctrl = hikaInput::get()->getCmd('ctrl', '');
				$task = hikaInput::get()->getCmd('task', '');
				if($ctrl == 'checkout' && $task == 'notify')
					$call = true;
			}

			if($call)
				return $this->googleDirectCall($accounts, $order, $currencyInfo);
		}
		return $this->googleGetJS($accounts, $order, $currencyInfo);
	}

	protected function googleGetJS($accounts, &$order, $currencyInfo) {
		$found = false;

		$app = JFactory::getApplication();

		foreach($accounts as $acc) {
			if($acc->currency == $currencyInfo->currency_code && !empty($acc->account_id)) {
				$account = $acc->account_id;
				$found = true;
				if(!preg_match('/UA-[0-9]{2,12}-[0-9]{1,3}/',$account)) {
					if(!$app->getUserState(HIKASHOP_COMPONENT.'.error_display_ga')) {
						$app->setUserState(HIKASHOP_COMPONENT.'.error_display_ga', 1);
						$app->enqueueMessage(JText::_('GOOGLE_ACCOUNT_ID_INVALID'));
						$app->redirect(hikashop_currentUrl('', false));
					}
					return '';
				}
				break;
			}
		}

		if(!$found) {
			if(!$app->getUserState(HIKASHOP_COMPONENT.'.error_display_ga')) {
				$app->setUserState(HIKASHOP_COMPONENT.'.error_display_ga', 1);
				$app->enqueueMessage(JText::_('NO_CURRENCY_FOUND_GOOGLE_ANALYTICS'));
				$app->redirect(hikashop_currentUrl('', false));
			}
			return '';
		}

		$jconf = JFactory::getConfig();
		if(HIKASHOP_J30)
			$siteName = $jconf->get('sitename');
		else
			$siteName = $jconf->getValue('config.sitename');

		$tax = ($order->order_subtotal - $order->order_subtotal_no_vat) + $order->order_shipping_tax + $order->order_discount_tax + $order->order_payment_tax;
		if(!empty($order->order_tax_info)) {
			$tax = 0;
			foreach($order->order_tax_info as $tax_info) {
				$tax += $tax_info->tax_amount;
			}
		}

		if($this->params->get('use_universal', 0)) {

			$extra_required = '';
			if($this->params->get('module_displayfeatures', 0))
				$extra_required .= "\r\n" . 'ga("require", "displayfeatures");';
			if($this->params->get('module_linkid', 0))
				$extra_required .= "\r\n" . 'ga("require", "linkid", "linkid.js");';

			$js = '
<!-- Google Analytics -->
<script>
(function(i,s,o,g,r,a,m){i["GoogleAnalyticsObject"]=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,"script","//www.google-analytics.com/analytics.js","ga");

ga("create", "' . $account . '", "auto");'.$extra_required.'
ga("send", "pageview");
ga("require", "ecommerce", "ecommerce.js");

ga("ecommerce:addTransaction", {
	"id": "' . $order->order_id.'",
	"affiliation": "' . str_replace(array('\\','"'), array('\\\\', '\\\"'), $siteName) . '",
	"revenue": "' . round($order->order_full_price, 2) . '",
	"shipping": "' . round($order->order_shipping_price, 2) . '",
	"tax": "' . round($tax, 2) . '",
	"currency: "'.$currencyInfo->currency_code.'"
});
';

			foreach($order->products as $product) {
				$js .= '
ga("ecommerce:addItem", {
	"id": "' . $order->order_id . '",
	"name": "' . str_replace(array('\\','"'), array('\\\\', '\\\"'), strip_tags($product->order_product_name)) . '",
	"sku": "' . str_replace(array('\\','"'), array('\\\\', '\\\"'), $product->order_product_code) . '",
	"category": "",
	"price": "' . ($product->order_product_price + $product->order_product_tax) . '",
	"quantity": "' . (int)$product->order_product_quantity . '"
});
';
			}

			$js .= '
ga("ecommerce:send");
</script>
<!-- End Google Analytics -->
';

			if($this->params->get('debug_mode')) {
				$ip = hikashop_getIP();
				$data = 'Adding google UA javascript'."\r\n".
					'IP: ' . $ip . "\r\n".
					'URL: ' . hikashop_currentURL() ."\r\n\r\n".
					'<pre>' . htmlentities($js) . '</pre>';
				$this->writeToLog($data);
			}
		} else {

			$extra_required = '';
			if($this->params->get('module_linkid', 0))
				$extra_required .= "\r\n" .
'var pluginUrl = "//www.google-analytics.com/plugins/ga/inpage_linkid.js";
_gaq.push(["_require", "inpage_linkid", pluginUrl]);';

			$js = '
<script type="text/javascript">
var _gaq = _gaq || [];'.$extra_required.'
_gaq.push(["_setAccount", "' . $account . '"]);
_gaq.push(["_trackPageview"]);
_gaq.push(["_addTrans",
	"' . $order->order_id . '",
	"' . str_replace(array('\\','"'), array('\\\\', '\\\"'), $siteName) . '",
	"' . round($order->order_full_price, 2) . '",
	"' . $tax . '",
	"' . round($order->order_shipping_price, 2) . '",
	"' . str_replace(array('\\','"'), array('\\\\', '\\\"'), @$order->billing_address->address_city) . '",
	"' . str_replace(array('\\','"'), array('\\\\', '\\\"'), @$order->billing_address->address_state) . '",
	"' . str_replace(array('\\','"'), array('\\\\', '\\\"'), @$order->billing_address->address_country) . '",
]);
';
			foreach($order->products as $product) {
				$js .= '
_gaq.push(["_addItem",
	"' . $order->order_id . '",
	"' . str_replace(array('\\','"'), array('\\\\', '\\\"'), $product->order_product_code) . '",
	"' . str_replace(array('\\','"'), array('\\\\', '\\\"'), strip_tags($product->order_product_name)) . '",
	"",
	"' . ($product->order_product_price + $product->order_product_tax) . '",
	"' . $product->order_product_quantity . '"
]);
';
			}

			$file = 'ga.js';
			if($this->params->get('debug_mode'))
				$file='u/ga_debug.js';

			$js .= '
_gaq.push(["_trackTrans"]);
(function() {
	var ga = document.createElement("script"); ga.type = "text/javascript"; ga.async = true;
	ga.src = ("https:" == document.location.protocol ? "https://ssl" : "http://www") + ".google-analytics.com/' . $file . '";
	var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(ga, s);
})();
</script>
';
		}

		return $js;
	}

	protected function googleDirectCall($accounts, &$order, $currencyInfo) {
		$found = false;
		foreach($accounts as $a) {
			if($a->currency == $currencyInfo->currency_code && !empty($a->account_id)) {
				$account = $a->account_id;
				if(!preg_match('/UA-[0-9]{2,12}-[0-9]{1}/', $account))
					continue;
				$found = true;
				break;
			}
		}
		if(!$found)
			return false;

		$uuid = round((rand() / getrandmax()) * 0x7fffffff);

		$order_params = (!empty($order->order_payment_params) && is_string($order->order_payment_params)) ? hikashop_unserialize($order->order_payment_params) : $order->order_payment_params;
		if(!empty($order_params->analytics_ga))
			$uuid = $order_params->analytics_ga;

		$jconf = JFactory::getConfig();
		if(HIKASHOP_J30)
			$siteName = $jconf->get('sitename');
		else
			$siteName = $jconf->getValue('config.sitename');

		$data = array(
			'v' => 1,
			'tid' => $account,
			'cid' => $uuid,
			'uip' => $order->order_ip,

			't' => 'transaction',
			'ti' => $order->order_id, // order_id
			'ta' => $siteName,
			'tr' => round($order->order_full_price, 2),
			'tt' => round(($order->order_subtotal - $order->order_subtotal_no_vat) + $order->order_shipping_tax + $order->order_discount_tax, 2),
			'ts' => round($order->order_shipping_price, 2),
			'cu' => $currencyInfo->currency_code
		);
		$this->googleDirectCallHit($data);

		foreach($order->products as $product) {
			$data = array(
				'v' => 1,
				'tid' => $account,
				'cid' => $uuid,

				't' => 'item',
				'ti' => $order->order_id, // order_id
				'in' => strip_tags($product->order_product_name), // name
				'ip' => ($product->order_product_price + $product->order_product_tax), // price
				'iq' => $product->order_product_quantity, // qty
				'ic' => $product->order_product_code, // code
				'iv' => '',
				'cu' => $currencyInfo->currency_code
			);
			$this->googleDirectCallHit($data);
		}

		if($this->params->get('debug_mode')) {
			$data = 'Send transaction by direct call
IP: ' . $ip . '
URL: ' . hikashop_currentURL();
			$this->writeToLog($data);
		}

		return true;
	}

	protected function googleDirectCallHit($data) {
		$url = 'http://www.google-analytics.com/collect';
		$headers = array('Content-type: application/x-www-form-urlencoded');
		$user_agent = 'HikaShopAnalyticsTracking/1.0 (http://www.hikashop.com/)';

		$serialized_data = array();
		foreach($data as $k => $v) {
			$serialized_data[] = $k . '=' . urlencode($v);
		}
		$serialized_data = implode('&', $serialized_data);

		$session = curl_init();
		curl_setopt($session, CURLOPT_HEADER,         false);
		curl_setopt($session, CURLOPT_POST,           true);
		curl_setopt($session, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($session, CURLOPT_USERAGENT,      $user_agent);
		curl_setopt($session, CURLOPT_HTTP_VERSION,   CURL_HTTP_VERSION_1_1);
		curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($session, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($session, CURLOPT_ENCODING,       'UTF-8');
		curl_setopt($session, CURLOPT_HTTPHEADER,     $headers);
		curl_setopt($session, CURLOPT_URL,            $url);
		curl_setopt($session, CURLOPT_POSTFIELDS,     $serialized_data);

		$result = curl_exec($session);
		$error = curl_error($session);
		curl_close($session);
	}


	protected function writeToLog($data = null) {
		$dbg = ($data === null) ? ob_get_clean() : $data;
		if(empty($dbg)) {
			if($data === null)
				ob_start();
			return;
		}

		$dbg = '-- ' . date('m.d.y H:i:s') . ' -- [Google Analytics]' . "\r\n" . $dbg;

		jimport('joomla.filesystem.file');
		$config = hikashop_config();
		$file = $config->get('payment_log_file', '');
		$file = rtrim(JPath::clean(html_entity_decode($file)), DS . ' ');
		if(!preg_match('#^([A-Z]:)?/.*#', $file) && (!$file[0] == '/' || !file_exists($file))) {
			$file = JPath::clean(HIKASHOP_ROOT . DS . trim($file, DS . ' '));
		}
		if(!empty($file) && defined('FILE_APPEND')) {
			if(!file_exists(dirname($file))) {
				jimport('joomla.filesystem.folder');
				JFolder::create(dirname($file));
			}
			file_put_contents($file, $dbg, FILE_APPEND);
		}
		if($data === null)
			ob_start();
	}
}
