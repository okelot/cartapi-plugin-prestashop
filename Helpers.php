<?php

class CartAPI_Handlers_Helpers
{
	protected static $APPIXIA_COOKIE_NAME = 'appixia';
	
	public static function isAppixiaMobileEngine()
	{
		if (isset($_COOKIE[self::$APPIXIA_COOKIE_NAME]) && ($_COOKIE[self::$APPIXIA_COOKIE_NAME] == 1)) return true;
		return false;
	}
	
	public static function setAppixiaMobileEngine()
	{
		setcookie(self::$APPIXIA_COOKIE_NAME, 1, 0, '/');
	}
	
	public static function unsetAppixiaMobileEngine()
	{
		setcookie(self::$APPIXIA_COOKIE_NAME);
	}
	
	public static function setServerNotices()
	{
		// enable this if we want to see notices
	
		/*
		ini_set('display_errors', 1);
		ini_set('log_errors', 1);
		ini_set('error_log', dirname(__FILE__) . '/errorlog');
		error_reporting(E_ALL);
		*/
		
		ini_set('log_errors', 1);
		ini_set('error_log', dirname(__FILE__) . '/errorlog');
	}

	public static function getShopDomain()
	{
		if (!($domain = Configuration::get('PS_SHOP_DOMAIN')))
			$domain = Tools::getHttpHost();
		return 'http://'.$domain;
	}
	
	// does end with a slash, last dir is appixiacartapi
	public static function getCartApiHomeUrl()
	{
		return CartAPI_Handlers_Helpers::getShopBaseUrl() . 'modules/appixiacartapi/';
	}
	
	// does end with a slash
	public static function getShopBaseUrl()
	{
		return CartAPI_Handlers_Helpers::getShopDomain() . __PS_BASE_URI__;
	}
	
	public static function removeHtmlTagsFromString($string)
	{
		$string = strip_tags($string);
		$string = str_replace("\xc2\xa0",' ', $string); // change &nbsp to regular spaces
		$string = html_entity_decode($string, ENT_QUOTES , 'UTF-8');
		return trim($string); 
	}
	
	// remove html tags from string or array
	public static function removeHtmlTags($from)
	{
		if (is_array($from))
		{
			$res = array();
			foreach ($from as $string) $res[] = CartAPI_Handlers_Helpers::removeHtmlTagsFromString($string);
			return $res;
		}
		else
		{
			return CartAPI_Handlers_Helpers::removeHtmlTagsFromString($from);
		}
	}
	
	public static function preInit($metadata)
	{
		Configuration::set('PS_CANONICAL_REDIRECT', 0);
		
		// language
		if (isset($metadata['X-LANGUAGE'])) $_GET['isolang'] = $metadata['X-LANGUAGE'];
		
		// register a new translation smarty function
		global $smarty;
		smartyRegisterFunction($smarty, 'function', 'l2', 'smartyTranslate2');
	}
	
	public static function syncLocale($metadata)
	{
		global $cookie;
		
		// currency
		$currency_id = false;
		if (isset($metadata['X-CURRENCY'])) $currency_id = Currency::getIdByIsoCode($metadata['X-CURRENCY']);
		if (!$currency_id) $currency_id = (int)(Configuration::get('PS_CURRENCY_DEFAULT'));
		
		// taken from Tools:setCurrency
		$currency = Currency::getCurrencyInstance($currency_id);
		if (is_object($currency) AND $currency->id AND $currency->active)
			$cookie->id_currency = (int)($currency->id);
			
			
		/* currently handled in preInit, may cause redirect loops if set manually
		
		// language
		$language_id = false;
		if (isset($metadata['X-LANGUAGE']) && Validate::isLanguageIsoCode($metadata['X-LANGUAGE'])) $language_id = Language::getIdByIso($metadata['X-LANGUAGE']);
		if (!$language_id) $language_id = (int)(Configuration::get('PS_LANG_DEFAULT'));
		
		// taken from Tools:setCookieLanguage
		$lang = new Language($language_id);
		if (Validate::isLoadedObject($lang) AND $lang->active)
		{
			//header('Location: ignore://');
			$cookie->id_lang = (int)($lang->id);
			
			@include_once(_PS_THEME_DIR_.'lang/'.$lang->iso_code.'.php');
		}
		*/
		
	}
	
	public static function getLocale()
	{
		global $cookie;
		
		$locale = array();
		
		// get the currency
		$currency = Currency::getCurrencyInstance($cookie->id_currency);
		if (is_object($currency) AND $currency->id) $locale['Currency'] = $currency->iso_code;
		
		return $locale;
	}
	
	public static function newHandlerInstance($encoder, $handler)
	{
		$handler_filename = $handler . '.php';
		$class_name = 'CartAPI_Handlers_'.$handler;

		// load the correct file
		if (!file_exists(dirname(__FILE__).'/override/'.$handler_filename)) 
		{
			// load the base
			require_once(dirname(__FILE__).'/'.$handler_filename);
		}
		else 
		{
			// load the override
			$class_name = 'CartAPI_Handlers_Override_'.$handler;
			require_once(dirname(__FILE__).'/override/'.$handler_filename);
		}

		// init the class
		if (!class_exists($class_name, false)) CartAPI_Helpers::dieOnError($encoder, 'UnsupportedOperation', 'Cannot create instance of '.$handler.' handler');
		return new $class_name();
	}

}


// code taken from config/smarty.config.inc.php smartyTranslate()
function smartyTranslate2($params, &$smarty)
{
	/*
	 * Warning in Smarty-v2 : 2 lines have been added to the Smarty class.
	 * "public $currentTemplate = null;" into the class itself
	 * "$this->currentTemplate = Tools::substr(basename($resource_name), 0, -4);" into the "fetch" method
	 * Notice : before 1.4.2.5, this modification was in the display method
	 *
	 * In Smarty-v3 : No modifications, using the existing var $this->smarty->_current_file instead
	 */
	global $_LANG, $_MODULES, $cookie, $_MODULE;
	if (!isset($params['js'])) $params['js'] = 0;
	if (!isset($params['mod'])) $params['mod'] = false;
	
	$string = str_replace('\'', '\\\'', $params['s']);
	$key = '';
	if (Configuration::get('PS_FORCE_SMARTY_2')) /* Keep a backward compatibility for Smarty v2 */
		$key = $smarty->currentTemplate.'_'.md5($string);
	else
	{
		// CHANGE //////////////////////////////////
		$filename = $params['tpl'].'.tpl';
		// CHANGE /////////////////////////////////
		$key = Tools::substr(basename($filename), 0, -4).'_'.md5($string);
	}
	$lang_array = $_LANG;
	if ($params['mod'])
	{
		$iso = Language::getIsoById($cookie->id_lang);

		if (Tools::file_exists_cache(_PS_THEME_DIR_.'modules/'.$params['mod'].'/'.$iso.'.php'))
		{
			$translationsFile = _PS_THEME_DIR_.'modules/'.$params['mod'].'/'.$iso.'.php';
			$key = '<{'.$params['mod'].'}'._THEME_NAME_.'>'.$key;
		}
		else
		{
			$translationsFile = _PS_MODULE_DIR_.$params['mod'].'/'.$iso.'.php';
			$key = '<{'.$params['mod'].'}prestashop>'.$key;
		}
		
		if(!is_array($_MODULES))
			$_MODULES = array();
		if (@include_once($translationsFile))
			if (is_array($_MODULE))
				$_MODULES = array_merge($_MODULES, $_MODULE);
		$lang_array = $_MODULES;
	}
	
	if (is_array($lang_array) AND key_exists($key, $lang_array))
		$msg = $lang_array[$key];
	elseif (is_array($lang_array) AND key_exists(Tools::strtolower($key), $lang_array))
		$msg = $lang_array[Tools::strtolower($key)];
	else
		$msg = $params['s'];
	
	if ($msg != $params['s'])
		$msg = $params['js'] ? addslashes($msg) : stripslashes($msg);
	return $params['js'] ? $msg : Tools::htmlentitiesUTF8($msg);
}

?>