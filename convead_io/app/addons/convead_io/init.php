<?php

if (!defined('BOOTSTRAP')) { die('Access denied'); }

fn_register_hooks(
//    'update_cart_products_pre',
//    'update_cart_products_post',
    'delete_cart_product',
    'add_to_cart',
    'place_order',
    'clear_cart',
    'change_order_status'
);
