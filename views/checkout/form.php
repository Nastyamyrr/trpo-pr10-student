<?php
/** @var array<string, string> $errors */
/** @var array{name:string,email:string,delivery:string,address:string,comment:string} $old */
/** @var App\Models\DeliveryMethod[] $deliveryMethods */
/** @var string $csrfField */
/** @var string $csrfToken */
/** @var list<array{product: App\Models\Product, quantity: int, lineTotal: float}> $lines */
/** @var string $formattedTotal */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$fieldErrors = array_diff_key($errors, ['_form' => true]);
?>
<a class="back-link" href="/cart">← Назад в корзину</a>

<div class="hero">
    <span class="pill">оформление заказа</span>
    <h1>Оформление заказа</h1>
    <p class="muted">
        Данные проверяются на сервере. Варианты доставки загружаются из dataset/delivery_methods.json.
    </p>
</div>

<div class="checkout">
    <form method="post" action="/checkout" class="form-card" novalidate>
        <input type="hidden" name="<?= $h($csrfField) ?>" value="<?= $h($csrfToken) ?>">

        <?php if (isset($errors['_form'])): ?>
            <div class="form-error" role="alert"><?= $h($errors['_form']) ?></div>
        <?php endif; ?>
        <?php if ($fieldErrors !== []): ?>
            <div class="form-error" role="alert">Исправьте ошибки в форме: <?= count($fieldErrors) ?>.</div>
        <?php endif; ?>

        <fieldset>
            <legend>Контакт</legend>

            <div class="field">
                <label for="name">Имя</label>
                <input type="text" id="name" name="name" maxlength="100" autocomplete="name"
                       value="<?= $h($old['name']) ?>"
                       <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="err-name"' : '' ?>>
                <?php if (isset($errors['name'])): ?>
                    <div class="field-error" id="err-name"><?= $h($errors['name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="254" autocomplete="email"
                       value="<?= $h($old['email']) ?>"
                       <?= isset($errors['email']) ? 'aria-invalid="true" aria-describedby="err-email"' : '' ?>>
                <?php if (isset($errors['email'])): ?>
                    <div class="field-error" id="err-email"><?= $h($errors['email']) ?></div>
                <?php endif; ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Доставка</legend>

            <div class="field" <?= isset($errors['delivery']) ? 'aria-describedby="err-delivery"' : '' ?>>
                <?php foreach ($deliveryMethods as $method): ?>
                    <label class="radio">
                        <input type="radio" name="delivery" value="<?= $h($method->id) ?>"
                            <?= $old['delivery'] === $method->id ? 'checked' : '' ?>>
                        <?= $h($method->title) ?> — <?= $h($method->formattedPrice()) ?>
                    </label>
                <?php endforeach; ?>
                <?php if (isset($errors['delivery'])): ?>
                    <div class="field-error" id="err-delivery"><?= $h($errors['delivery']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="address">Адрес <span class="muted">(обязателен для курьера)</span></label>
                <input type="text" id="address" name="address" maxlength="200" autocomplete="street-address"
                       value="<?= $h($old['address']) ?>"
                       <?= isset($errors['address']) ? 'aria-invalid="true" aria-describedby="err-address"' : '' ?>>
                <?php if (isset($errors['address'])): ?>
                    <div class="field-error" id="err-address"><?= $h($errors['address']) ?></div>
                <?php endif; ?>
            </div>
        </fieldset>

        <div class="field">
            <label for="comment">Комментарий <span class="muted">(необязательно)</span></label>
            <textarea id="comment" name="comment" rows="3" maxlength="500"
                      <?= isset($errors['comment']) ? 'aria-invalid="true" aria-describedby="err-comment"' : '' ?>><?= $h($old['comment']) ?></textarea>
            <?php if (isset($errors['comment'])): ?>
                <div class="field-error" id="err-comment"><?= $h($errors['comment']) ?></div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn">Подтвердить заказ</button>
    </form>

    <aside class="order-summary">
        <h2>Ваш заказ</h2>
        <table class="table">
            <tbody>
            <?php foreach ($lines as $line): ?>
                <tr>
                    <td><?= $h($line['product']->name) ?> × <?= (int) $line['quantity'] ?></td>
                    <td><strong><?= $h(App\Models\Order::money($line['lineTotal'])) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="muted">Товары: <strong><?= $h($formattedTotal) ?></strong></p>
        <p class="muted">Стоимость доставки добавится после выбора способа.</p>
        <a class="btn btn-outline btn-sm" href="/cart">Изменить корзину</a>
    </aside>
</div>
