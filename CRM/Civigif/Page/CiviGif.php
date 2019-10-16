<?php
use CRM_Civigif_ExtensionUtil as E;

class CRM_Civigif_Page_CiviGif extends CRM_Core_Page {


  public function run() {

    CRM_Utils_System::setTitle(E::ts('CiviGif - Testing'));
    
    $result = civicrm_api3('Contribution', 'get', array(
      'sequential' => 1,
      'return' => array("total_amount", "receive_date"),
      'options' => array('limit' => 10),
      'api.Contact.getsingle' => array('return' => array("first_name")),
    ));
    

    $lines = $result['values'];
    $this->calc_time_since($lines, 'receive_date');    
    $this::generate_image($lines, function($item){
      $name = $item['api.Contact.getsingle']['first_name'];
      if (empty($name)) {
        $name = 'An anonymous donor';
      }
      return $name  . ' gave $' . $item['total_amount'] . ' ' . $item['since'] . ' ago';
    });    
    parent::run();
  }
  
  /**
   * Worker function - converts a date into the time since the date.
   *
   * @param $items array of items to convert
   * @param $key date to convert found in $items[x][$key].
   */
  static private function calc_time_since(&$items, $key) {
    $format_order = [
      ["y", "year"],
      ["m", "minute"],
      ["d", "day"],
      ["h", "hour"],
      ["i", "minute"],
      ["s", "second"],
    ];
    
    $now = new DateTime();
    foreach ($items as &$item) {
      $time_since = $now->diff(new DateTime($item[$key]));            
      $diff_str = [];      
      foreach ($format_order as $format) {
        $component = $time_since->{$format[0]};
        if ($component > 0) {                    
          if ($component > 1){
            $diff_str[]=  $time_since->{$format[0]} . " " . $format[1] ."s";
          }                    
          else {
            $diff_str[]=  $time_since->{$format[0]} . " " . $format[1];
          }                        
        }
        if (sizeof($diff_str) >= 2) {
          break;
        }
      }
      $item['since'] = implode(" ", $diff_str);
    }
  }

  /**
   * Worker function generate_temporary_filename
   *
   * Generates temporary file with filename prefixed by prefix and suffixed by ext.
   *
   * @param prefix - prefix to use
   * @param ext - file extension to use
   *
   * @returns A temporoary file
   */
  static private function generate_temporary_file($prefix = 'IMG_', $ext = 'gif'){
    $temp_dir = '[civicrm.files]/civigif/';
    $dir = Civi::paths()->getPath($temp_dir);
    // Confirm path exists
    if (!is_dir($dir)) {
      if (!mkdir($dir, 775)){
        // @todo fix this error throwing.
        echo 'Failed to create directory' . PHP_EOL;
        CRM_Core_Error::backtrace();
      }
    }
    // Sanitise prefix and ext
    $prefix = preg_replace('/[^A-Za-z0-9_-]+/','',$prefix);
    $ext = preg_replace('/[^A-Za-z0-9_-]+/','',$ext);

    // @todo make thread safe by adding a mutex/semaphore or some kind
    // of lock    
    do {      
      $filename = uniqid($prefix) . '.' . $ext;
    } while(file_exists($dir .  $filename));
    $result = touch($dir .  $filename);

    if ($result) {
      return [
        'path' => $dir . $filename,
        'url' => Civi::paths()->getUrl($temp_dir) . $filename,
      ];      
    }
    else {
      // @todo fix this error throwing.
      echo 'Failed to create file' . PHP_EOL;
      CRM_Core_Error::backtrace();            
    }
  }
  
  /**
   *
   * Generates a gif animation of each item from $lines displayed
   * using $print_line. Items displayed in order and will scroll down
   * the screen in a window that moves through $lines.
   *
   * @param $lines array of items to display.
   * @param $print_line function to convert an item from items to a string.
   * @param $limit Max number of items from items to display at once.
   */
  protected function generate_image($lines,$print_line, $limit = 5 ) {
    
    $result = $this->generate_temporary_file();
    $image_path = $result['path'];
    $image_url = $result['url'];


    $font_size = 30;
    $font_path = E::path("fonts/RobotoCondensed-Bold.ttf");
    
    $max_width = 0;
    $max_height = 0;
    // Get widest string
    foreach ($lines as $i => $line) {
      $bbox = imageftbbox($font_size, 0, $font_path, $print_line($line));
      $width = $bbox[2] - $bbox[0];
      $height = $bbox[1] - $bbox[7];
      if ($width > $max_width) {
        $max_width = $width;        
      }
      if ($height > $max_height){
        $max_height = $height;
      }
    }
    $line_height = $max_height;   
    $width = $max_width;
    $height = ($line_height)*$limit + $line_height/2;
    $padding_x = $line_height/2;
    
    $draw = new ImagickDraw();
    $draw->setFillColor('black');

    
    $draw->setFont($font_path);
    $draw->setFontSize( $font_size );

    
    $background = new ImagickPixel('white');
    $canvas = new Imagick();


    foreach ($lines as $i => $line) {                
      $frames[$i] = new Imagick();
      $frames[$i]->newImage($width,$height, $background);
      $added = FALSE;
      for ($j = $i; $j>=0 && $i - $j < $limit ; $j--) {        
        $frames[$i]->annotateImage($draw, $padding_x, $line_height + ($i-$j)*$line_height, 0, $print_line($lines[$j]));
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
    
    $this->assign('image_path', $image_path);
    $this->assign('image_url', $image_url);

    $fp = fopen($image_path, 'rb');
    header("Content-Type: image/gif");
    header("Content-Length: " . filesize($image_path));
    fpassthru($fp);
    exit;
  }
    

}
