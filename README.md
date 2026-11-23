# Практическая работа №10 — PHP MVC интернет-магазин. Оформление заказа

**Дисциплина:** Технологии разработки программного обеспечения  
**Тема:** Оформление заказа, серверная валидация и представления форм

Продолжение ПР-09 (корзина, session). Состояние после ПР-09 зафиксировано тегом `pr09-done`.

## Результат

Checkout-сценарий: форма → серверная валидация → сохранение учебного заказа в JSON → очистка корзины → страница подтверждения.

- `GET /checkout` — форма (варианты доставки берутся из `dataset/delivery_methods.json`);
- `POST /checkout` — валидация; при ошибках возвращается та же форма с сообщениями и введёнными значениями;
- `GET /checkout/success` — страница подтверждения: номер заказа, состав, итоговая сумма, способ доставки (email и адрес **не** выводятся);
- CSRF-токен в форме (учебная мера);
- паттерн **POST → Redirect → GET**: обновление страницы подтверждения не создаёт второй заказ.

## Требования и запуск

- PHP 8.1+ (используются `readonly`-свойства, `never`), расширение `mbstring`. Composer не нужен.

```bash
git clone https://github.com/Nastyamyrr/trpo-pr10-student.git
cd trpo-pr10-student
php -S localhost:8080 -t public
```

- http://localhost:8080/catalog
- http://localhost:8080/cart
- http://localhost:8080/checkout (при пустой корзине перенаправит в `/cart`)

Папка `storage/` должна быть доступна PHP на запись: файл `storage/orders.json` создаётся при первом заказе и не хранится в Git.

## Быстрая проверка после чистого клонирования

1. Запустить сервер (команда выше).
2. В каталоге нажать «В корзину» у любого товара, перейти в корзину → «Оформить заказ».
3. Отправить пустую форму → появятся ошибки у имени, email и способа доставки.
4. Выбрать «Курьер» без адреса → ошибка адреса; ввести `bad` в email → ошибка email. Введённые значения сохраняются.
5. Заполнить корректно → страница подтверждения с номером `ORD-0001`, корзина пуста (счётчик в header = 0).
6. Обновить страницу подтверждения (F5) → заказ не дублируется (`storage/orders.json` содержит одну запись).
7. Автотесты сценариев: `php tests/run_all.php`.

## Схема запроса (диаграмма последовательности)

```
Браузер        index.php(router)   CheckoutController   OrderValidator   OrderService / Repo      View
   |  GET /checkout  |                     |                    |                  |                 |
   |---------------->|--- form() --------->|                    |                  |                 |
   |                 |                     |-- cart пуста? -> redirect /cart (flash)                |
   |                 |                     |-- normalize([]) -->|                  |                 |
   |                 |                     |-- render form (методы из delivery_methods.json) ------>|
   |<------------------------------------------------ HTML формы + CSRF-токен ---------------------|
   |                 |                     |                    |                  |                 |
   |  POST /checkout |                     |                    |                  |                 |
   |---------------->|--- submit() ------->|                    |                  |                 |
   |                 |                     |-- Csrf::isValid? --(нет)--> 403 + форма                |
   |                 |                     |-- normalize($_POST) >|                 |                 |
   |                 |                     |-- validate() ------->|                 |                 |
   |                 |                     |<-- $errors ----------|                 |                 |
   |                 |                     |-- $errors != [] --> 422 + та же форма + old + errors ->|
   |<------------------------------------------------ HTML формы с ошибками -----------------------|
   |                 |                     |-- $errors == [] --> new CheckoutData (DTO)             |
   |                 |                     |-- place(DTO) ------------------------->|               |
   |                 |                     |                    |   lines() из CartService          |
   |                 |                     |                    |   new Order(...) -> repo->add()  |
   |                 |                     |                    |   (flock, запись storage/orders.json)
   |                 |                     |                    |   cart->clear(), last_order_id    |
   |                 |                     |<-- Order --------------------------|                  |
   |<-- 302 Location: /checkout/success ---|                    |                  |                 |
   |  GET /checkout/success                |                    |                  |                 |
   |---------------->|-- success() ------->|-- lastOrder() ---------------------->|                 |
   |                 |                     |-- render success (номер, сумма, доставка) ------------>|
   |<------------------------------------------------ HTML подтверждения -------------------------|
```

Ответственность слоёв: **Router** (`public/index.php`) только сопоставляет URL и метод с действием контроллера и не формирует HTML; **Controller** координирует; **OrderValidator** проверяет; **OrderService** создаёт заказ; **Repository** читает/пишет JSON; **View** только выводит готовые данные.

## Что хранится в session

```php
$_SESSION['cart']          = [productId => quantity];  // только id и количество
$_SESSION['flash']         = 'сообщение';              // показывается один раз
$_SESSION['csrf_token']    = '64 hex-символа';         // токен формы checkout
$_SESSION['last_order_id'] = 1;                        // для страницы подтверждения
```

Полные данные товара в session не дублируются, они берутся из `ProductRepository`. Заказ хранится не в session, а в `storage/orders.json`.

## Dataset и где он используется

| Файл | Где используется |
|------|------------------|
| `products.json`, `categories.json` | `ProductRepository`, `CategoryRepository` (каталог, корзина) |
| `delivery_methods.json` | `DeliveryMethodRepository` → варианты в форме, проверка выбранного способа, цена доставки в заказе |
| `orders_seed.json` | `OrderRepository`: начальные данные, пока нет `storage/orders.json` |
| `order_cases.json` | `tests/run_order_cases.php` (сценарии валидации) |
| `cart_scenarios.json` | `tests/run_cart_scenarios.php` |
| `catalog_cases.csv` | `tests/run_catalog_cases.php` |

## Валидация (`App\Validation\OrderValidator`)

`normalize()` — приводит всё к строкам, `trim`, схлопывает пробелы (массивы из `$_POST` → пустая строка). `validate()` — возвращает `поле => сообщение`:

| Поле | Правило |
|------|---------|
| `name` | обязательно, 2–100 символов (строка из пробелов = пусто) |
| `email` | обязательно, `FILTER_VALIDATE_EMAIL`, до 254 символов |
| `delivery` | обязательно, должен существовать в `delivery_methods.json` |
| `address` | обязателен для `courier`, 5–200 символов |
| `comment` | необязателен, до 500 символов |

Объект `CheckoutData` (DTO) создаётся только если ошибок нет.

## Что изменилось по шагам (ПР-10)

- **Шаг 3 — структура заказа.** Контакт: `customerName`, `email`. Доставка: `deliveryId`, `deliveryTitle`, `deliveryPrice`, `address`. Состав: `items` (`OrderItem` с названием и ценой на момент заказа). Данные формы — DTO `CheckoutData`, итоговый заказ — модель `Order`.
- **Шаг 10 — страница подтверждения.** Показывает номер (`ORD-0001`), состав, сумму товаров, доставку и итог. Email, адрес и комментарий не выводятся. Заказ открывается только из session (`last_order_id`), по id из URL посмотреть чужой заказ нельзя.

## Что изменилось по шагам (ПР-09, уточнения)

- **Шаг 4 — CartService.** Выбран подход «сервис»: бизнес-логика корзины в `CartService`, модели остаются «чистыми».
- **Шаг 6 — добавление.** Только `POST /cart/add` + redirect. Redirect допускает лишь пути внутри приложения (`Redirect::isSafePath`, защита от `//evil.com`).
- **Шаг 13 — flash.** Сообщение читается и удаляется в `CartService::getFlash()`, поэтому показывается один раз.
- Количество в `/cart/update` проверяется как целое 1..99; `session_start()` вызывается только в `public/index.php`.

## Почему POST для изменений

- GET не должен менять состояние (идемпотентность, кэш, повтор при F5).
- После POST выполняется redirect, поэтому F5 не дублирует действие.

## CSRF

Реализован учебный механизм: `App\Security\Csrf` создаёт случайный токен в session, форма отправляет его в скрытом поле `_csrf`, `POST /checkout` без совпадающего токена возвращает 403 и форму. Токен защищает только checkout; корзинные POST-формы токена не используют (это можно добавить тем же способом).

## Допущения

- **Остаток (`stock`) не проверяется** ни при добавлении в корзину, ни при заказе: сценарий `add:4, set:4:3` из `cart_scenarios.json` ожидает `{4: 3}` при `stock = 1`.
- Хранилище заказов: JSON-файл. `OrderRepository` блокирует файл (`flock`), не перезаписывает повреждённый JSON (бросает исключение, пользователь видит сообщение, корзина при этом **не** очищается) и проверяет результат записи.

## Тесты сценариев

```bash
php tests/run_all.php            # все
php tests/run_cart_scenarios.php # cart_scenarios.json (состав + сумма)
php tests/run_order_cases.php    # order_cases.json + доп. случаи
php tests/run_catalog_cases.php  # catalog_cases.csv
```

Результаты прогона и ручной проверки: `docs/test-results.md`, чек-лист: `docs/checklist-pr10.md`.

## Структура

```
public/index.php                 # session_start + DI + router
src/
  bootstrap.php                  # автозагрузка (spl_autoload_register)
  Controllers/                   # CatalogController, CartController, CheckoutController
  Models/                        # Product, Category, DeliveryMethod, CheckoutData (DTO), Order, OrderItem
  Repositories/                  # Product, Category, DeliveryMethod, Order
  Services/                      # CartService, OrderService
  Validation/OrderValidator.php
  Security/Csrf.php
  Support/                       # View (рендер), Redirect
views/
  layouts/main.php               # header со счётчиком корзины, flash
  catalog/ cart/ checkout/       # form.php, success.php
  not-found.php
dataset/                         # все json/csv
storage/                         # orders.json создаётся при первом заказе (в .gitignore)
tests/                           # прогонщики сценариев
docs/                            # чек-листы и результаты проверок
```
