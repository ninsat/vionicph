<?php
/**
 * @version $Id: helperversion.php 72 2017-08-04 10:30:52Z szymon $
 * @package DJ-MegaMenu
 * @copyright Copyright (C) 2017 DJ-Extensions.com LTD, All rights reserved.
 * @license http://www.gnu.org/licenses GNU/GPL
 * @author url: http://dj-extensions.com
 * @author email contact@dj-extensions.com
 * @developer Szymon Woronowski - szymon.woronowski@design-joomla.eu
 *
 * DJ-MegaMenu is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * DJ-MegaMenu is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with DJ-MegaMenu. If not, see <http://www.gnu.org/licenses/>.
 *
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

require_once (dirname(__FILE__) . DS . 'helper.php');

class modDJMegaMenuHelper extends modDJMMHelper {
	
	public static function parseParams(&$params) {
	
		// determine if this is a Pro version
		$params->set('pro', 0);
		
		parent::parseParams($params);
	}		
}
