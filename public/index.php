<?php

declare(strict_types=1);

/**
 * Точка входа приложения.
 * Session запускается централизованно здесь (и только здесь).
 * Простой роутинг; автозагрузка — в src/bootstrap.php.
 */

// Session — один раз в bootstrap, не в view
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Автозагрузка классов (namespace App\...)
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Controllers\CartController;
use App\Controllers\CatalogController;
use App\Controllers\CheckoutController;
use App\Repositories\CategoryRepository;
use App\Repositories\DeliveryMethodRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Services\CartService;
use App\Services\OrderService;
use App\Support\View;
use App\Validation\OrderValidator;

// Пути к dataset
$basePath = dirname(__DIR__);
$productsFile = $basePath . '/dataset/products.json';
$categoriesFile = $basePath . '/dataset/categories.json';
$deliveryFile = $basePath . '/dataset/delivery_methods.json';
$ordersSeedFile = $basePath . '/dataset/orders_seed.json';
$ordersStorageFile = $basePath . '/storage/orders.json';

// Внедрение зависимостей
$productRepository = new ProductRepository($productsFile);
$categoryRepository = new CategoryRepository($categoriesFile);
$cartService = new CartService($productRepository);
$deliveryRepository = new DeliveryMethodRepository($deliveryFile);
$orderRepository = new OrderRepository($ordersSeedFile, $ordersStorageFile);
$orderService = new OrderService($cartService, $orderRepository, $deliveryRepository);
$orderValidator = new OrderValidator($deliveryRepository);

$catalogController = new CatalogController($productRepository, $categoryRepository, $cartService);
$cartController = new CartController($cartService);
$checkoutController = new CheckoutController($cartService, $deliveryRepository, $orderValidator, $orderService);

// Роутер
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = rtrim((string) $uri, '/') ?: '/';
$path = $uri;

if (str_starts_with($path, '/index.php')) {
    $path = substr($path, strlen('/index.php')) ?: '/';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// --- Catalog ---
if ($path === '/' || $path === '/catalog') {
    $catalogController->index();
    exit;
}

if (preg_match('#^/catalog/(\d+)$#', $path, $matches)) {
    $catalogController->show((int) $matches[1]);
    exit;
}

// --- Cart ---
if ($path === '/cart' && $method === 'GET') {
    $cartController->index();
    exit;
}

if ($path === '/cart/add' && $method === 'POST') {
    $cartController->add();
    exit;
}

if ($path === '/cart/update' && $method === 'POST') {
    $cartController->update();
    exit;
}

if ($path === '/cart/remove' && $method === 'POST') {
    $cartController->remove();
    exit;
}

if ($path === '/cart/clear' && $method === 'POST') {
    $cartController->clear();
    exit;
}

// --- Checkout ---
if ($path === '/checkout' && $method === 'GET') {
    $checkoutController->form();
    exit;
}

if ($path === '/checkout' && $method === 'POST') {
    $checkoutController->submit();
    exit;
}

if ($path === '/checkout/success' && $method === 'GET') {
    $checkoutController->success();
    exit;
}

// 404
http_response_code(404);
View::render('not-found', [
    'message' => 'Запрошенная страница не найдена.',
    'cartCount' => $cartService->count(),
    'flash' => $cartService->getFlash(),
]);
