<?php
  include 'includes/application_top.php';
  include DIR_WS_CLASSES . '/form_handler.php';
  
  // Test 1 should validate
  $_POST = array( 'action'    => 'process',
                  'bool_test' => 'true',
                  'int_test'  => '1',
                  'tags_test' => '<script>Alert(\'Hacked!\');</script>',
                  'formid'    => $sessiontoken );

  $formHandler = new form_handler();
  
  if ( ( $extracted = $formHandler->setRequiredFormKeys( array( 'action'    => 'process',
                                                                'bool_test' => 'bool',
                                                                'int_test'  => 'int',
                                                                'tags_test' => 'strip_tags' ) )
                                  ->setOptionalFormKeys( array() )->validate() ) !== false ) {
  
    echo 'Test 1 - Correct! the form was expected to validated<br /><br />' . PHP_EOL;
    var_dump ( $extracted ). PHP_EOL;
    echo '<br /><br />' . PHP_EOL;             
  } else {
    echo 'Test 1 - Incorrect! Form failed to validate<br /><br />'. PHP_EOL;  
  }
  ###########################################################
  
  // Test 2 should not validate as bad formid
  $_POST = array( 'action'    => 'process',
                  'bool_test' => 'true',
                  'int_test'  => '1',
                  'tags_test' => '<script>Alert(\'Hacked!\');</script>',
                  'formid'    => 'abc7ry27yr742r3' );

  if ( ( $extracted = $formHandler->reset()->setRequiredFormKeys( array( 'action'    => 'process',
                                                                         'bool_test' => 'bool',
                                                                         'int_test'  => 'int',
                                                                         'tags_test' => 'strip_tags' ) )
                                  ->setOptionalFormKeys( array() )->validate()) !== false ) {
    echo 'Test 2 - Incorrect! the form should not have validated!<br /><br />' . PHP_EOL;
    var_dump ( $extracted ) . PHP_EOL;
    echo '<br /><br />' . PHP_EOL;             
  } else {
    echo 'Test 2 - Form failed to validate as expected.<br /><br />'. PHP_EOL;  
  }
  ###########################################################
  
  // Test 3 should validate
  $_POST = array( 'action'    => 'process',
                  'bool_test' => 'true',
                  'int_test'  => '1',
                  'tags_test' => '<script>Alert(\'Hacked!\');</script>',
                  'formid'    => $sessiontoken );

  if ( ( $formHandler->reset()->limitCsrfCheckOnly( true )->validate() ) !== false ) {
    echo 'Test 3 - Form validation successful as expected<br /><br />'. PHP_EOL;  
  } else {
    echo 'Test 3 - Incorrect! this form should have validated<br /><br />'. PHP_EOL;  
  }
  ###########################################################
  
  // Test 4 should validate action update
  $_POST = array( 'action'    => 'update',
                  'bool_test' => 'true',
                  'int_test'  => '1',
                  'tags_test' => '<script>Alert(\'Hacked!\');</script>',
                  'formid'    => $sessiontoken );

  if ( ( $extracted = $formHandler->reset()->setRequiredFormKeys( array( 'action'    => 'process',
                                                                         'bool_test' => 'bool',
                                                                         'int_test'  => 'int',
                                                                         'tags_test' => 'strip_tags' ) )
                                           ->setOptionalFormKeys( array() )->validate() ) !== false ) {
  
    echo 'Test 4 - Validated the wrong one!!<br /><br />' . PHP_EOL;
  } elseif( ( $extracted = $formHandler->reset()->setRequiredFormKeys( array( 'action'    => 'update',
                                                                              'bool_test' => 'bool',
                                                                              'int_test'  => 'int',
                                                                              'tags_test' => 'strip_tags' ) )
                                                ->setOptionalFormKeys( array() )->validate() ) !== false ) {
    echo 'Test 4 - Validated the update action as expected<br /><br />' . PHP_EOL;
  }