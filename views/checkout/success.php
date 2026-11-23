<?php
/** @var App\Models\Order $order */
$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="hero">
    <span class="pill">заказ оформлен</span>
    <h1>Спасибо за заказ!</h1>
    <p class="muted">Номер заказа: <strong><?= $h($order->number()) ?></strong></p>
</div>

<table class="table">
    <thead>
    <tr><th>Товар</th><th>Цена</th><th>Кол-во</th><th>Сумма</th></tr>
    </thead>
    <tbody>
    <?php foreach ($order->items as $item): ?>
        <tr>
            <td><?= $h($item->name) ?></td>
            <td><?= $h(App\Models\Order::money($item->price)) ?></td>
            <td><?= (int) $item->quantity ?></td>
            <td><strong><?= $h(App\Models\Order::money($item->lineTotal())) ?></strong></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="cart-summary">
    <div>
        <div class="muted">Товары: <?= $h($order->formattedItemsTotal()) ?></div>
        <div class="muted">Доставка (<?= $h($order->deliveryTitle) ?>): <?= $h($order->formattedDeliveryPrice()) ?></div>
        <span class="muted">Итого к оплате:</span>
        <div class="total"><?= $h($order->formattedTotal()) ?></div>
    </div>
    <a class="btn" href="/catalog">Вернуться в каталог</a>
</div>
