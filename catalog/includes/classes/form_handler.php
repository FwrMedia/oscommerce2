<?php
  class form_handler {
    var $_superglobal;
    var $_sessiontoken;
    var $_required_keys = array();
    var $_optional_keys = array();
    var $_do_csrf_check = true;
    var $_do_csrf_check_only = false;
    var $_extracted = array();
    function form_handler($superglobal_type = 'post') {
      $this->_superglobal = strtolower($superglobal_type) === 'post' ? $_POST : $_GET;  
    }

    function setRequiredFormKeys(array $args = array()) {
      $this->_required_keys = $args;
      return $this;  
    }

    function setOptionalFormKeys(array $args = array()) {
      $this->_optional_keys = $args;
      return $this;  
    }

    function validate(array $args = array()) {
      global $sessiontoken;
      $this->_sessiontoken =& $sessiontoken;
      if($this->_do_csrf_check) $this->_required_keys['formid'] = 1;
      $this->_extracted = array_intersect_key($this->_superglobal, $this->_required_keys);
      if(count($this->_extracted) !== count($this->_required_keys)) return false;
      $this->_extracted = array_merge( (array)$this->extractOptionals(), $this->_extracted);
      if ($this->validateCsrf() === false) return false;
      if ($this->_do_csrf_check_only) return true;
      return $this->handleValues();
    }
    
    function extractOptionals(array $args = array()) {
      $possibles = array_intersect_key($this->_superglobal, $this->_optional_keys);
      return (array)$possibles;
    }

    function requireCsrfCheck($required = true) {
      $this->_do_csrf_check = (bool)$required;
      return $this;  
    }

    function limitCsrfCheckOnly($csrf_only = false) {
      $this->_do_csrf_check_only = (bool)$csrf_only;
      return $this;  
    }

    function validateCsrf( array $args = array() ) {
      if ( $this->_do_csrf_check === false ) return true;
      $new_session_token = $this->generateNewToken();
      if($this->_extracted['formid'] == $this->_sessiontoken) {
        $this->_sessiontoken = $new_session_token;
        unset($this->_extracted['formid'],$this->_required_keys['formid']);
        return true;
      } else { 
      $this->_sessiontoken = $new_session_token;
      }
      return false;
    }

    function generateNewToken(array $args = array()) {
      return md5(tep_rand() . tep_rand() . tep_rand() . tep_rand());
    }

    function handleValues() {
      $optionals_found = array_intersect_key($this->_optional_keys,$this->_extracted);
      $required_plus_optionals = array_merge($this->_required_keys, $optionals_found);
      foreach($required_plus_optionals as $key => $value) {
        switch($value) {
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
          case 'strip_tags':
            $this->_extracted[$key] = tep_db_prepare_input(strip_tags((string)$this->_extracted[$key]));
            break;
          case 'array':
            $this->_extracted[$key] = tep_db_prepare_input((array)$this->_extracted[$key]);
            break;
          case 'empty':
          case 'null':
            if(tep_not_null( $this->_extracted[$key])) {
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
            if((string)$value != (string)$this->_extracted[$key]) return false;  // Checking simple matches like action => process
            break; 
        } 
      }
      return (array)$this->_extracted;
    }  
  } // end class