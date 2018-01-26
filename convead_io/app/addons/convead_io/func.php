<?php if (!defined('BOOTSTRAP')) { die('Access denied'); }

use Tygh\Settings;
use Tygh\Registry;


function fn_convead_io_companies($lang_code = CART_LANGUAGE)
{
  $addon_name = 'convead_io';
  $setting_name = 'convead_io_companies';
  $setting_companies = false;
  
    $section = Settings::instance()->getSectionByName($addon_name, Settings::ADDON_SECTION);
    $section_id = !empty($section['section_id']) ? $section['section_id'] : 0;
    $settings = Settings::instance()->getList($section_id, 0, true);
    foreach($settings as $setting)
    {
      if ($setting['name'] == $setting_name) 
      {
        $setting_companies = $setting;
        break;
      }
    }
  
    $companis = db_get_array("SELECT company_id, storefront FROM ?:companies");
    
  $html = '';
  foreach($companis as $company)
  {
    $html .= '
    <h4 class="subheader hand">'.$company['storefront'].'</h4>
    <div class="control-group setting-wide convead_io">
      <label class="control-label ">APP-key</label>
      <div class="controls">
      <input type="text" name="'.$company['company_id'].'" size="30" value="" class="company_option user-success" onchange="setCompanyValue('.$company['company_id'].', this);">
      </div>
    </div>
    ';
  }
  
  $html .= '
<input type="hidden" name="addon_data[options]['.$setting_companies['object_id'].']" value=\''.$setting_companies['value'].'\' id="company_options" />
<script>
var setCompanyValue = function(option, el) {
  var hidden = document.getElementById("company_options");
  var data = {};
  var el = document.querySelectorAll(".company_option");
  for(var k in el)
  {
    data[ el[k].name ] = el[k].value;
  }
  hidden.value = JSON.stringify(data);
}
var applyCompanyValues = function() {
  var hidden = document.getElementById("company_options");
  var options = (hidden.value ? JSON.parse(hidden.value) : {});
  for(var k in options)
  {
    var el = document.querySelector(".company_option");
    for (var lel in el)
    {
      if (el.name == k) el.value = options[k];
    }
  }
}
applyCompanyValues();
</script>
  ';

    return $html;
}

function getCompanyId()
{
  if (Registry::get('runtime.simple_ultimate')) {
    return Registry::get('runtime.forced_company_id');
  } else {
    return Registry::get('runtime.company_id');
  }
}

function fn_convead_io_change_order_status($status_to, $status_from, $order_info, $force_notification, $order_statuses, $place_order)
{
  $state = switch_order_state($status_to);
  if ($state) {
    $api_key = Settings::instance()->getValue('convead_io_api_key', 'convead_io');
    if($api_key){
        include_once('ConveadTracker.php');
        $convead_tracker = new ConveadTracker($api_key, Registry::get('config.current_host'));
        $convead_tracker->webHookOrderUpdate($order_info['order_id'], $state);
      }
  }
}

function fn_convead_io_clear_cart($cart) {
  $convead_tracker = get_convead_tracker();
  if(!empty($_GET['dispatch']) and $_GET['dispatch'] == 'checkout.clear' and $convead_tracker) {
    if ($convead_tracker->generated_uid) return true;
    $convead_tracker->eventUpdateCart(array());
  }
}

function fn_convead_io_pre_add_to_cart(&$product_data, &$cart, &$auth, &$update) {
  $convead_tracker = get_convead_tracker();
  if($convead_tracker) {
    if ($convead_tracker->generated_uid) return true;
    foreach ($product_data AS $product) {
      $url = fn_url('products.view?product_id=' . $product['product_id']);
      $_product_data = fn_get_product_data($product['product_id'], $auth);
      $price = $_product_data['price'];
      $name = $_product_data['product'];
      $convead_tracker->eventAddToCart($product['product_id'], $product['amount'], $price, $name, $url);
    }
  }
}

function fn_convead_io_add_to_cart($cart, $product_id, $_id) {
  $convead_tracker = get_convead_tracker();
  if($convead_tracker) {
    if ($convead_tracker->generated_uid) return true;
    $order_array = array();
    foreach ($cart['products'] AS $product) {
      // запретить отрицательное количество товаров
      if ($product['amount'] <= 0) continue;
      $order_array[] = array(
        'product_id' => $product['product_id'],
        'qnt' => $product['amount'],
        'price' => $product['price']
      );
    }
    $convead_tracker->eventUpdateCart($order_array);
  }
}

function fn_convead_io_post_add_to_cart(&$product_data, &$cart, &$auth, &$update){
  $convead_tracker = get_convead_tracker();
  if($convead_tracker) {
    if ($convead_tracker->generated_uid) return true;
    $order_array = array();
    foreach ($product_data AS $product) {
      // запретить отрицательное количество товаров
      if ($product['amount'] <= 0) continue;
      $_product_data = fn_get_product_data($product['product_id'], $auth);
      $order_array[] = array(
        'product_id' => $product['product_id'],
        'qnt' => $product['amount'],
        'price' => $_product_data['price']
      );
    }
    $convead_tracker->eventUpdateCart($order_array);
  }
}

function fn_convead_io_delete_cart_product(&$cart, &$cart_id, &$full_erase){
  $convead_tracker = get_convead_tracker();
  if($convead_tracker) {
    if ($convead_tracker->generated_uid) return true;
    $product_id = $cart['products'][$cart_id]['product_id'];
    $_product_data = fn_get_product_data($product_id, $_SESSION['auth']);
    $qnt = $cart['products'][$cart_id]['amount'];
    $product_name = $_product_data['product'];
    $product_url = fn_url('products.view?product_id=' . $product_id);
    $convead_tracker->eventRemoveFromCart($product_id, $qnt, $product_name, $product_url);
  }
}

//function fn_convead_io_update_cart_products_pre(&$cart, &$product_data, &$auth){
//  $__a = '';
//  return $__a;
//}
//
//function fn_convead_io_update_cart_products_post(&$cart, &$product_data, &$auth){
//  $__a = '';
//  return $__a;
//}

function fn_convead_io_place_order($order_id, $action, $order_status, $cart, $auth){
  $_order = fn_get_order_info($order_id);
  if($_order and !$action){
    $visitor_info = array(
      'first_name'=> $_order['firstname'],
      'last_name' => $_order['lastname'],
      'email'=> $_order['email'],
      'phone'=> $_order['b_phone']
    );
    $convead_tracker = get_convead_tracker($visitor_info);
    if($convead_tracker){
      $revenue = $_order['total'];
      $order_array = false;
      foreach($_order['products'] AS $_product ){
        $order_array[] = array(
          'product_id' => $_product['product_id'],
          'qnt'=> $_product['amount'],
          'price' => $_product['price']
        );
      }
      $state = switch_order_state($_order['status']);
      $convead_tracker->eventOrder($order_id, $revenue, $order_array, $state);
    }
  }
}

function get_app_key()
{
  $app_key = Settings::instance()->getValue('convead_io_api_key', 'convead_io');
  
  $json_companies = Settings::instance()->getValue('convead_io_companies', 'convead_io');
  if ($json_companies)
  {
    $companies_app_keys = json_decode($json_companies);
    $id = getCompanyId();
    if ($companies_app_keys and isset($companies_app_keys->$id)) $app_key = $companies_app_keys->$id;
  }
  
  return $app_key;
}

function get_convead_tracker($visitor_info = array()){
  $api_key = get_app_key();

  if($api_key){
    $guest_uid = !empty($_COOKIE['convead_guest_uid']) ? $_COOKIE['convead_guest_uid'] : false;
    $domain = Registry::get('config.current_host');
    $referrer = $_SERVER['HTTP_REFERER'];
    if(isset($_SESSION['auth']['user_id'])){
      $visitor_uid = $_SESSION['auth']['user_id'];
      $_visitor_info = fn_get_user_info($visitor_uid, false);
      if(!empty($_visitor_info['firstname'])) $visitor_info['first_name'] = $_visitor_info['firstname'];
      if(!empty($_visitor_info['lastname'])) $visitor_info['lastname'] = $_visitor_info['lastname'];
      if(!empty($_visitor_info['email'])) $visitor_info['email'] = $_visitor_info['email'];
      if(!empty($_visitor_info['phone'])) $visitor_info['phone'] = $_visitor_info['phone'];
      if(!empty($_visitor_info['birthday'])) $visitor_info['birthday'] = $_visitor_info['birthday'];
    }else{
      $visitor_uid = false;
    }
    include_once('ConveadTracker.php');
    $convead_tracker = new ConveadTracker($api_key, $domain, $guest_uid, $visitor_uid, $visitor_info, $referrer, $domain);
  }else{
    $convead_tracker = false;
  }
  return $convead_tracker;
}

function switch_order_state($new_state = ''){
  switch ($new_state) {
    case 'O':
      $state = 'new'; // Открыт
      break;
    case 'N':
      $state = 'new'; // Открыт
      break;
    case 'D':
      $state = 'cancelled'; // Отклонен
      break;
    case 'I':
      $state = 'cancelled'; // Анулирован
      break;
    default:
      $state = $new_state;
  }
  return $state;
}
