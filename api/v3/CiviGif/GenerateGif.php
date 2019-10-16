<?php
use CRM_Civigif_ExtensionUtil as E;

/**
 * CiviGif.GenerateGif API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_civi_gif_GenerateGif_spec(&$spec) {
  $spec['magicword']['api.required'] = 1;
}


function _civicrm_api3_civi_GenerateGif_get_data(){
  $this::generate_image();
  $result = civicrm_api3('Contribution', 'get', array(
              'sequential' => 1,
              'return' => array("total_amount", "receive_date"),
              'options' => array('limit' => 10),
              'api.Contact.getsingle' => array('return' => array("first_name")),
  ));

  if ($result['is_error'] != 0) {
    die("Api Error");
  }
  $lines = [];
  $now = new DateTime();
  foreach ($result['values'] as $contribution) {

    $time_since = $now->diff(new DateTime($contribution['receive_date']));

    $diff_str = [];

    $format_order = [
      ["y", "year"],
      ["m", "minute"],
      ["d", "day"],
      ["h", "hour"],
      ["i", "minute"],
      ["s", "second"],
    ];

    foreach ($format_order as $key) {
      $component = $time_since->{$key[0]};
      if ($component > 0) {
        if ($component > 1) {
          $diff_str[] = $time_since->{$key[0]} . " " . $key[1] . "s";
        }
        else {
          $diff_str[] = $time_since->{$key[0]} . " " . $key[1];
        }
      }
      if (count($diff_str) >= 2) {
        break;
      }
    }

    $lines[] = [
      'name' => $contribution['api.Contact.getsingle']['first_name'],
      'time' => implode(" ", $diff_str),
      'amount' => $contribution['total_amount'],
    ];
  }
  return $lines;
}



/**
 * Generate an animated gif adding lines from $lines one by one.
 *
 */
function _civicrm_api3_civi_GenerateGif_generate_image($lines) {

  $filename = 'test.gif';
  $image_path = Civi::paths()->getPath('[cms.root]/sites/default/files/') . $filename;
  $image_url = Civi::paths()->getUrls('[cms.root]/sites/default/files/') . $filename;
  // Example: Assign a variable for use in a template
  $this->assign('image_path', '/sites/default/files/' . $filename);

  $handle = fopen($image_path, 'w+');
  if (!($handle)) {
    kpr("Failed to open image file");
    kpr($handle);
  }
  else {
    $width = 1200;
    $height = 600;

    $draw = new ImagickDraw();
    $draw->setFillColor('black');
    $font_path = E::path("fonts/RobotoCondensed-Light.ttf");
    $draw->setFont($font_path);
    $draw->setFontSize(30);

    $background = new ImagickPixel('white');

    $canvas = new Imagick();

    foreach ($lines as $i => $line) {
      $frames[$i] = new Imagick();
      $frames[$i]->newImage($width, $height, $background);
      $added = FALSE;
      for ($j = $i; $j >= 0 && $i - $j < 5; $j--) {
        $frames[$i]->annotateImage($draw, 10, 45 + ($i - $j) * 40, 0, "{$lines[$j]['name']} \${$lines[$j]['amount']} {$lines[$j]['time']} ago");
        $added = TRUE;
      }
      if ($added) {
        $frames[$i]->setImageFormat('gif');
        $canvas->addImage($frames[$i]);
        $canvas->setImageDelay(50);
      }
    }

    $canvas->setImageFormat('gif');
    $final_image = $canvas->coalesceImages();
    $final_image->setImageFormat('gif');
    $final_image->setImageIterations(0); //loop forever
    $final_image->mergeImageLayers(\Imagick::LAYERMETHOD_OPTIMIZEPLUS);
    $final_image->writeImages($image_path, TRUE);
  }
  return $image_url;
}



/**
 * CiviGif.GenerateGif API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_civi_gif_GenerateGif($params) {
  /*if (array_key_exists('magicword', $params) && $params['magicword'] == 'sesame') {
    $returnValues = array(
    // OK, return several data rows
    12 => array('id' => 12, 'name' => 'Twelve'),
    34 => array('id' => 34, 'name' => 'Thirty four'),
    56 => array('id' => 56, 'name' => 'Fifty six'),
    );
    // ALTERNATIVE: $returnValues = array(); // OK, success
    // ALTERNATIVE: $returnValues = array("Some value"); // OK, return a single value

    // Spec: civicrm_api3_create_success($values = 1, $params = array(), $entity = NULL, $action = NULL)
    return civicrm_api3_create_success($returnValues, $params, 'NewEntity', 'NewAction');
    }
    else {
    throw new API_Exception(/*errorMessage*/ 'Everyone knows that the magicword is "sesame"', /*errorCode 1234);
                                                                                                }*/
    $returnValues = array(generate_image($lines));
  
}
