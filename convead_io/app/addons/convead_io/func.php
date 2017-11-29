<?php if (!defined('BOOTSTRAP')) { die('Access denied'); }

use Tygh\Settings;
use Tygh\Registry;


function fn_convead_io_change_order_status($status_to, $status_from, $order_info, $force_notification, $order_statuses, $place_order)
{
  switch (strtolower($status_to)) {
    case 'o':
      $state = 'new'; // Открыт
      break;
    case 'd':
      $state = 'cancelled'; // Отклонен
      break;
    case 'i':
      $state = 'cancelled'; // Анулирован
      break;
    default:
      $state = $status_to;
  }
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
    $convead_tracker->eventUpdateCart(array());
  }
}

function fn_convead_io_pre_add_to_cart(&$product_data, &$cart, &$auth, &$update) {
  $convead_tracker = get_convead_tracker();
  if($convead_tracker) {
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
    $convead_tracker = get_convead_tracker();
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

      $order_shipping = array(
        'first_name'=> $_order['firstname'],
        'last_name' => $_order['lastname'],
        'email'=> $_order['email'],
        'phone'=> $_order['b_phone']
      );

      $convead_tracker->eventOrder($order_id, $revenue, $order_array, $order_shipping);
    }
  }
}

function get_convead_tracker(){
  $api_key = Settings::instance()->getValue('convead_io_api_key', 'convead_io');
  if($api_key){
    $guest_uid = !empty($_COOKIE['convead_guest_uid']) ? $_COOKIE['convead_guest_uid'] : false;
    $domain = Registry::get('config.current_host');
    $referrer = $_SERVER['HTTP_REFERER'];
    if(isset($_SESSION['auth']['user_id'])){
      $visitor_uid = $_SESSION['auth']['user_id'];
      $_visitor_info = fn_get_user_info($visitor_uid, false);
      $visitor_info = array();
      if(!empty($_visitor_info['firstname'])) $visitor_info['first_name'] = $_visitor_info['firstname'];
      if(!empty($_visitor_info['lastname'])) $visitor_info['lastname'] = $_visitor_info['lastname'];
      if(!empty($_visitor_info['email'])) $visitor_info['email'] = $_visitor_info['email'];
      if(!empty($_visitor_info['phone'])) $visitor_info['phone'] = $_visitor_info['phone'];
      if(!empty($_visitor_info['birthday'])) $visitor_info['birthday'] = $_visitor_info['birthday'];
    }else{
      $visitor_uid = false;
      $visitor_info = false;
    }
    include_once('ConveadTracker.php');
    $convead_tracker = new ConveadTracker($api_key, $domain, $guest_uid, $visitor_uid, $visitor_info, $referrer, $domain);
  }else{
    $convead_tracker = false;
  }
  return $convead_tracker;
}
