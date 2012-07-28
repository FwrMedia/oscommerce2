<?php
  /**
  * form handler and sanitiser
  */
  class form_handler {
    /**
    * Container for superglobal _POST or _GET
    * 
    * @var array
    * @access protected - internal method would be protected in PHP5
    */
    var $_superglobal;
    /**
    * Session token linked to the osCommerce global $sessiontoken by reference
    * 
    * @var string
    */
    var $_sessiontoken;
    /**
    * Array of keys that must be present in the superglobal
    * 
    * @var array
    * @access protected - internal method would be protected in PHP5
    */
    var $_required_keys = array();
    /**
    * Array of optional keys that may be present in the superglobal
    * 
    * @var array
    * @access protected - internal method would be protected in PHP5
    */
    var $_optional_keys = array();
    /**
    * Whether form_handler is required to perform CSRF validation
    * 
    * @var bool
    */
    var $_do_csrf_check = true;
    /**
    * Forces form_handler to return true/false immediately following the CSRF check 
    * 
    * @var bool
    * @access protected - internal method would be protected in PHP5
    */
    var $_do_csrf_check_only = false;
    /**
    * Array of key values that were extracted from the superglobal.
    * At point of return these values are sanitised.
    * 
    * @var array
    */
    var $_extracted = array();
    /**
    * Default sanitiser used where _required_keys or _optional_keys are passed as a numerically indexed array
    * @example array( 'firstname', 'lastname', ) etc
    * 
    * @var string
    * @access protected - internal method would be protected in PHP5
    */
    var $_default_sanitiser = 'strip_tags';
    /**
    * array of acceptable mime types used by file upload checking
    * 
    * @var array - mime types e.g. array( 'image/jpeg', '   image/png', '   image/gif');
    * @access protected - internal method would be protected in PHP5
    */
    var $_acceptable_upload_mime_types = array();
    /**
    * Constructor
    * 
    * @param string $superglobal_type
    * @access public
    * @return void
    */
    function form_handler($superglobal_type = 'post') {
      $this->_superglobal = strtolower($superglobal_type) === 'post' ? $_POST : $_GET;  
    }
    /**
    * Array of keys required by the form, to be extracted from the superglobal and the sanitiser to apply
    * array( 'review_id' => 'int', 'comment' => 'strip_tags' )
    * Optional - numerically indexed forcing the use of the default sanitiser
    * array( 'name', 'comment' )
    * 
    * @param array $args
    * @access public
    * @return form_handler - allows chaining
    */
    function setRequiredFormKeys(array $args = array()) {
      $this->_required_keys = $this->numericKeysToDefaultSanitiser($args);
      return $this;  
    }
    /**
    * Array of optional keys to be extracted from the superglobal and the sanitiser to apply
    * array( 'review_id' => 'int', 'comment' => 'strip_tags' )
    * Optional - numerically indexed forcing the use of the default sanitiser
    * array( 'name', 'comment' )
    * 
    * @param array $args
    * @access public
    * @return form_handler - allows chaining
    */
    function setOptionalFormKeys(array $args = array()) {
      $this->_optional_keys = $this->numericKeysToDefaultSanitiser($args);
      return $this;  
    }
    /**
    * Set the default sanitiser to be used in instances where the keys are passed in as a numerically indexed array
    * 
    * @param string $sanitiser
    * @access public
    * @return form_handler - allows chaining
    */
    function setDefaultSanitiser($sanitiser = 'strip_tags') {
      $this->_default_sanitiser = $sanitiser;
      return $this;  
    }
    /**
    * Does form_handler perform CSRF validation
    * 
    * @param bool $required
    * @access public
    * @return form_handler - allows chaining
    */
    function requireCsrfCheck($required = true) {
      $this->_do_csrf_check = (bool)$required;
      return $this;  
    }
    /**
    * Does form_handler return true/false immediately following the CSRF check
    * 
    * @param bool $csrf_only
    * @access public
    * @return form_handler - allows chaining
    */
    function limitCsrfCheckOnly($csrf_only = false) {
      $this->_do_csrf_check_only = (bool)$csrf_only;
      return $this;  
    }
    /**
    * Add an array of acceptable mime types to be used checking upload validity
    * 
    * @param array $args - e.g. array( 'image/jpeg', 'image/png', 'image/gif' )
    */
    function addAcceptableUploadMimeTypes( array $args = array() ) {
      $this->_acceptable_upload_mime_types = $args;
      return $this;
    }
    /**
    * Reset used in instances where form validation is chained
    * 
    * @example if ( form_validates ) elseif ( different form validates[reset here] )
    * @param string $superglobal_type
    * @access public
    * @return form_handler - allows chaining
    */
    function reset($superglobal_type = 'post') {
      $this->_superglobal = strtolower($superglobal_type) === 'post' ? $_POST : $_GET;
      $this->_required_keys = array();
      $this->_optional_keys = array();
      $this->_do_csrf_check = true;
      $this->_do_csrf_check_only = false;
      $this->_extracted = array();
      return $this;  
    }
    /**
    * Performs a check of form validity
    * Performs a CSRF check
    * Extracts required variables from the superglobal
    * Extracts optional variables from the superglobal
    * Applies sanitising and type casting to the extracted values
    * 
    * @param array $args - not currently used but allows for future settings
    * @access public
    * @return mixed - bool false on failure, array of sanitised values on success
    */
    function validate(array $args = array()) {
      global $sessiontoken;
      $this->_sessiontoken =& $sessiontoken;
      if ($this->_do_csrf_check) $this->_required_keys['formid'] = 1;
      $this->_extracted = array_intersect_key($this->_superglobal, $this->_required_keys);
      if ($this->validateCsrf() === false) return false;
      if ($this->_do_csrf_check_only) return true;
      if (count($this->_extracted) !== count($this->_required_keys)) return false;
      $this->_extracted = array_merge((array)$this->extractOptionals(), $this->_extracted);
      return $this->handleValues();
    }
    /**
    * If numeric keys are passed into setXxxFormKeys the value is set as the key the default sanitiser is applied as the value.
    * 
    * @param array $args
    * @access protected - internal method would be protected in PHP5
    * @return mixed
    */
    function numericKeysToDefaultSanitiser(array $args = array()) {
      $required_keys = array();
      foreach ($args as $key => $value) {
        if (is_numeric($key)) {
          $required_keys[$value] = $this->_default_sanitiser; 
        }
        else $required_keys[$key] = $value;
      }
      return (array)$required_keys;
    }
    /**
    * Extract optional keys from the superglobal
    * 
    * @param array $args
    * @access protected - internal method would be protected in PHP5
    * @return array - key=>values extracted from the superglobal
    */
    function extractOptionals(array $args = array()) {
      $possibles = array_intersect_key($this->_superglobal, $this->_optional_keys);
      return (array)$possibles;
    }
    /**
    * Perform CSRF validation
    * 
    * @param mixed $args
    * @access protected - internal method would be protected in PHP5
    * @return bool
    */
    function validateCsrf(array $args = array()) {
      if ($this->_do_csrf_check === false) return true;
      $new_session_token = $this->generateNewToken();
      if ($this->_extracted['formid'] == $this->_sessiontoken) {
        unset($this->_extracted['formid'],$this->_required_keys['formid']);
        return true;
      } else { 
        $this->_sessiontoken = $new_session_token;
      }
      return false;
    }
    /**
    * Generate a new sessiontoken ( should be a tep_ function )
    * 
    * @param mixed $args
    * @access protected - internal method would be protected in PHP5
    * @return osCommerce session token
    */
    function generateNewToken(array $args = array()) {
      return md5(tep_rand() . tep_rand() . tep_rand() . tep_rand());
    }
    /**
    * Apply sanitisation and type casting to array values
    * 
    * @access protected - internal method would be protected in PHP5
    * @return mixed - false on failure - array of sanitised key=>values
    */
    function handleValues() {
      $optionals_found = array_intersect_key($this->_optional_keys,$this->_extracted);
      $required_plus_optionals = array_merge($this->_required_keys, $optionals_found);
      foreach($required_plus_optionals as $key => $value) {
        switch($value) {
          case 'strip_tags':
            $this->_extracted[$key] = tep_db_prepare_input(strip_tags((string)$this->_extracted[$key]));
            break;
          case 'file':
          case 'uploaded_file':
            if ( PHP_VERSION < '4.1.0' ) break;
            if (!array_key_exists($key, $_FILES)) return false;
            if ((PHP_VERSION >= '4.2.0') && ($_FILES[$key]['error'] !== 0)) return false;
            if (!array_key_exists('tmp_name', $_FILES[$key]) || !is_uploaded_file($_FILES[$key]['tmp_name'])) return false;
            if (!empty($this->_acceptable_upload_mime_types)) {
              if( !array_key_exists( 'type', $_FILES[$key]) || !in_array($_FILES[$key]['type'], $this->_acceptable_upload_mime_types)) return false;
            }
            break;
          case 'int':
            $this->_extracted[$key] = (int)$this->_extracted[$key];
            break;
          case 'numeric':
            if (!is_numeric($this->_extracted[$key])) $this->_extracted[$key] = (int)$this->_extracted[$key];
            break;
          case 'real':
          case 'double':
          case 'float':
            $this->_extracted[$key] = (float)$this->_extracted[$key];
            break;
          case 'string':
            $this->_extracted[$key] = tep_db_prepare_input((string)$this->_extracted[$key]);
            break;
          case 'array':
            $this->_extracted[$key] = tep_db_prepare_input((array)$this->_extracted[$key]);
            break;
          case 'empty':
          case 'null':
            if (tep_not_null( $this->_extracted[$key])) {
              $this->_extracted[$key] = is_array($this->_extracted[$key]) ? array() : '';
            }
            break;
          case 'boolean':
          case 'bool':
            $this->_extracted[$key] = (bool)$this->_extracted[$key];
            break;
          case 'bypass':
            // For some unknown reason we don't want this one formatted
            break;
          /**
          * When the value is an array it could be confusing unless explained
          * 
          * @example address_book_process.php
          * if (isset($HTTP_POST_VARS['action']) && (($HTTP_POST_VARS['action'] == 'process') || ($HTTP_POST_VARS['action'] == 'update'))
          * action is checked against the array e.g array( 'process', 'update' ) and if the action doesn't match any this returns false
          */
          case is_array($value):
              if (!in_array((string)$this->_extracted[$key], $value)) return false; // Effectively an OR
            break;
          case false !== strpos((string)$value, 'tep_'):
            if (function_exists($value)) {
              $this->_extracted[$key] = $value(tep_db_prepare_input($this->_extracted[$key])); // Pass the value through a tep_ function e.g. tep_output_string()
            }
            break;
          case false !== strpos((string)$value, 'php_'):
            $possible_function = substr($value, 4, strlen($value));
            $disallowed = array( 'eval','exec','shell_exec','escapeshellarg','escapeshellcmd','system',
                                 'passthru','readfile','proc_close','proc_open','ini_alter','dl','popen',
                                 'parse_ini_file','show_source', 'curl_exec' );
            if (in_array($possible_function,$disallowed)) return false; // Many PHP functions are dangerous
            if (function_exists($possible_function)) {
              $this->_extracted[$key] = $possible_function(tep_db_prepare_input($this->_extracted[$key]));
            }
            break;
          default:
            if ((string)$value != (string)$this->_extracted[$key]) return false;  // Checking simple matches like action => process
            break; 
        } 
      }
      return (array)$this->_extracted;
    }  
  } // end class