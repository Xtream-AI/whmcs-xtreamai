<?php

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/bootstrap.php';

use WhmcsXtreamAI\CheckoutValidator;

add_hook('ShoppingCartValidateProductUpdate', 1, static function ($vars) {
    try {
        $pid = (int) ($vars['pid'] ?? 0);
        if ($pid < 1) {
            $index = $vars['i'] ?? null;
            if ($index !== null && is_scalar($index)) {
                $cartProduct = $_SESSION['cart']['products'][$index] ?? null;
                if (is_array($cartProduct)) {
                    $pid = (int) ($cartProduct['pid'] ?? 0);
                }
            }
        }

        if ($pid < 1) {
            return [];
        }

        $fields = is_array($vars['customfield'] ?? null) ? $vars['customfield'] : [];
        $clientId = (int) ($_SESSION['uid'] ?? 0);

        return CheckoutValidator::validateProduct($pid, $fields, $clientId);
    } catch (\Throwable $e) {
        return [];
    }
});

add_hook('ShoppingCartValidateCheckout', 1, static function ($vars) {
    try {
        $products = $_SESSION['cart']['products'] ?? [];
        if (!is_array($products)) {
            return [];
        }

        $clientId = (int) ($vars['clientId'] ?? 0);
        if ($clientId < 1) {
            $clientId = (int) ($_SESSION['uid'] ?? 0);
        }

        $errors = [];
        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }

            $pid = (int) ($product['pid'] ?? 0);
            $fields = is_array($product['customfields'] ?? null) ? $product['customfields'] : [];

            foreach (CheckoutValidator::validateProduct($pid, $fields, $clientId) as $message) {
                if (!in_array($message, $errors, true)) {
                    $errors[] = $message;
                }
            }
        }

        return $errors;
    } catch (\Throwable $e) {
        return [];
    }
});
