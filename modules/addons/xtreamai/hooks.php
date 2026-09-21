<?php

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/bootstrap.php';

use WhmcsXtreamAI\CheckoutValidator;

if (!function_exists('xtreamai_cart_hook_cart_products')) {
    function xtreamai_cart_hook_cart_products()
    {
        $products = $_SESSION['cart']['products'] ?? null;
        if (!is_array($products)) {
            return null;
        }

        $out = [];
        foreach (array_slice($products, 0, 20, true) as $index => $product) {
            $fieldIds = [];
            if (is_array($product) && is_array($product['customfields'] ?? null)) {
                foreach (array_slice(array_keys($product['customfields']), 0, 20) as $key) {
                    $fieldIds[] = (int) $key;
                }
            }

            $out[] = [
                'index' => is_int($index) ? $index : (string) $index,
                'pid' => is_array($product) ? (int) ($product['pid'] ?? 0) : 0,
                'customfield_ids' => $fieldIds,
            ];
        }

        return $out;
    }
}

if (!function_exists('xtreamai_cart_hook_payload')) {
    function xtreamai_cart_hook_payload($hook, $vars, array $extra = [])
    {
        $hook = (string) $hook;
        $vars = is_array($vars) ? $vars : [];

        $fieldIds = [];
        if (is_array($vars['customfield'] ?? null)) {
            foreach (array_keys($vars['customfield']) as $key) {
                $fieldIds[] = (int) $key;
            }
        }

        $payload = [
            'hook' => $hook,
            'vars_keys' => array_slice(array_map('strval', array_keys($vars)), 0, 40),
            'vars_pid' => (int) ($vars['pid'] ?? 0),
            'vars_i' => (isset($vars['i']) && is_scalar($vars['i'])) ? (string) $vars['i'] : null,
        ];

        if ($hook === 'checkout') {
            $payload['vars_client_id'] = (int) ($vars['clientId'] ?? 0);
        }

        $payload['session_uid'] = (int) ($_SESSION['uid'] ?? 0);
        $payload['cart_products'] = xtreamai_cart_hook_cart_products();
        $payload['customfield_ids_in_vars'] = array_slice($fieldIds, 0, 20);

        return $payload + $extra;
    }
}

if (!function_exists('xtreamai_cart_hook_log')) {
    function xtreamai_cart_hook_log(array $payload, $exception = null)
    {
        if (!function_exists('logModuleCall')) {
            return;
        }

        try {
            $hook = (string) ($payload['hook'] ?? '');
            $response = 'hook=' . $hook
                . ' resolved_pid=' . (int) ($payload['resolved_pid'] ?? 0)
                . ' validator_called=' . (empty($payload['validator_called']) ? '0' : '1');

            if (is_string($exception) && $exception !== '') {
                $response .= ' exception=' . $exception;
            }

            $request = json_encode($payload);
            if (!is_string($request)) {
                $request = 'hook=' . $hook;
            }

            logModuleCall('xtreamai', 'checkout_hook', $request, $response);
        } catch (\Throwable $ignored) {
        }
    }
}

if (!function_exists('xtreamai_cart_hook_exception')) {
    function xtreamai_cart_hook_exception($hook, $vars, $e)
    {
        try {
            $message = get_class($e) . ': ' . $e->getMessage();
            if (strlen($message) > 200) {
                $message = substr($message, 0, 200);
            }

            $extra = [
                'resolved_pid' => 0,
                'validator_called' => false,
                'exception' => $message,
            ];

            try {
                $payload = xtreamai_cart_hook_payload($hook, $vars, $extra);
            } catch (\Throwable $ignored) {
                $payload = ['hook' => (string) $hook] + $extra;
                $payload['vars_keys'] = is_array($vars) ? array_slice(array_map('strval', array_keys($vars)), 0, 40) : [];
            }

            xtreamai_cart_hook_log($payload, $message);
        } catch (\Throwable $ignored) {
        }
    }
}

add_hook('ShoppingCartValidateProductUpdate', 1, static function ($vars) {
    try {
        $vars = is_array($vars) ? $vars : [];

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

        xtreamai_cart_hook_log(xtreamai_cart_hook_payload('product_update', $vars, [
            'resolved_pid' => $pid,
            'validator_called' => $pid > 0,
        ]));

        if ($pid < 1) {
            return [];
        }

        $fields = is_array($vars['customfield'] ?? null) ? $vars['customfield'] : [];
        $clientId = (int) ($_SESSION['uid'] ?? 0);

        return CheckoutValidator::validateProduct($pid, $fields, $clientId);
    } catch (\Throwable $e) {
        xtreamai_cart_hook_exception('product_update', $vars, $e);

        return [];
    }
});

add_hook('ShoppingCartValidateCheckout', 1, static function ($vars) {
    try {
        $vars = is_array($vars) ? $vars : [];

        $products = $_SESSION['cart']['products'] ?? [];
        if (!is_array($products)) {
            xtreamai_cart_hook_log(xtreamai_cart_hook_payload('checkout', $vars, [
                'resolved_pid' => 0,
                'validator_called' => false,
                'products_validated' => 0,
            ]));

            return [];
        }

        $validated = 0;
        foreach ($products as $product) {
            if (is_array($product)) {
                $validated++;
            }
        }

        xtreamai_cart_hook_log(xtreamai_cart_hook_payload('checkout', $vars, [
            'resolved_pid' => 0,
            'validator_called' => $validated > 0,
            'products_validated' => $validated,
        ]));

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
        xtreamai_cart_hook_exception('checkout', $vars, $e);

        return [];
    }
});
