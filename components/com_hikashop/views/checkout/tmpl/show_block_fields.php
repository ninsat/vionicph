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
$cart = $this->checkoutHelper->getCart();
if(!hikashop_level(2) || empty($cart->order_fields))
	return;

$labelcolumnclass = 'hkc-sm-4';
$inputcolumnclass = 'hkc-sm-8';

if(empty($this->ajax)) {
?>
<div id="hikashop_checkout_fields_<?php echo $this->step; ?>_<?php echo $this->module_position; ?>" class="hikashop_checkout_fields">
<?php } ?>
	<div class="hikashop_checkout_loading_elem"></div>
	<div class="hikashop_checkout_loading_spinner"></div>

<?php
	$this->checkoutHelper->displayMessages('fields');

?>
<fieldset class="hkform-horizontal">
	<legend><?php echo JText::_('ADDITIONAL_INFORMATION'); ?></legend>
<?php
	foreach($cart->order_fields as $fieldName => $oneExtraField) {
		$oneExtraField->registration_page = @$this->registration_page;
?>
	<div class="hkcontrol-group control-group hikashop_checkout_<?php echo $fieldName;?>_line" id="hikashop_order_<?php echo $this->step . '_' . $this->module_position . '_' . $oneExtraField->field_namekey; ?>">
<?php
		echo $this->fieldClass->getFieldName($oneExtraField, true, $labelcolumnclass.' hkcontrol-label');
?>
		<div class="<?php echo $inputcolumnclass;?>">
<?php
		$onWhat = ($oneExtraField->field_type == 'radio') ? 'onclick' : 'onchange';
		echo $this->fieldClass->display(
			$oneExtraField,
			(isset($_SESSION['hikashop_order_data']) && is_object($_SESSION['hikashop_order_data']) && !is_null($_SESSION['hikashop_order_data']->$fieldName)) ? $_SESSION['hikashop_order_data']->$fieldName : @$cart->cart_fields->$fieldName,
			'checkout[fields]['.$fieldName.']',
			false,
			' class="hkform-control" '.$onWhat.'="window.hikashop.toggleField(this.value,\''.$fieldName.'\',\'order_' . $this->step . '_' . $this->module_position.'\',0,\'hikashop_\');"',
			false,
			$cart->order_fields,
			$cart->cart_fields,
			false
		);
?>
		</div>
	</div>
<?php
	}
?>
	<div class="hkform-group control-group hikashop_fields_button_line">
		<div class="<?php echo $labelcolumnclass;?> hkcontrol-label"></div>
		<div class=" <?php echo $inputcolumnclass;?>">
			<button type="submit" onclick="return window.checkout.submitFields(<?php echo $this->step.','.$this->module_position; ?>);" class="<?php echo $this->config->get('css_button','hikabtn'); ?> hikabtn_checkout_fields_submit">
				<?php echo JText::_('HIKA_SUBMIT_FIELDS'); ?>
			</button>
		</div>
	</div>
</fieldset>
<?php
	if(!empty($this->options['js'])) {
?>
<script type="text/javascript">
<?php echo $this->options['js']; ?>
</script>
<?php
	}
	if(empty($this->ajax)) { ?>
</div>
<script type="text/javascript">
if(!window.checkout) window.checkout = {};
window.Oby.registerAjax(['checkout.fields.updated','cart.updated'], function(params){
	window.checkout.refreshFields(<?php echo (int)$this->step; ?>, <?php echo (int)$this->module_position; ?>);
});
window.checkout.refreshFields = function(step, id) { return window.checkout.refreshBlock('fields', step, id); };
window.checkout.submitFields = function(step, id) {
	return window.checkout.submitBlock('fields', step, id);
};
</script>
<?php }
