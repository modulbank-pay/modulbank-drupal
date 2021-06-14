# Платежный модуль для Drupal8 Commerce

Модуль позволяет принимать платежи банковской картой через Модульбанк.

[Скачать модуль](https://github.com/modulbank-pay/modulbank-drupal/releases/latest/download/modulbank_drupal8_commerce.zip)

### Установка

1. Откройте раздел "Расширения" панели администрирования интернет-магазина и нажмите кнопку "+Установить новый модуль".
![Загрузка платежного модуля Модульбанка](https://modulbank-pay.github.io/screenshots/drupal8_commerce/1.png)
2. На открывшейся странице нажмите кнопку выберите скачанный архив с модулем и нажмите на кнопку "Установить"
![Загрузка платежного модуля Модульбанка](https://modulbank-pay.github.io/screenshots/drupal8_commerce/2.png)
3. После установки вернитесь в раздел "Расширения" поставьте флажок напротив "Commerce Payment Modulbank" и нажмите кнопку "Установить" в низу страницы
![Загрузка платежного модуля Модульбанка](https://modulbank-pay.github.io/screenshots/drupal8_commerce/3.png)
4. После установки вернитесь в раздел "Торговля" > "Конфигурация" > "Оплата" > "Платежные шлюзы" и нажмите на кнопку "+Добавить платежный шлюз"
![Загрузка платежного модуля Модульбанка](https://modulbank-pay.github.io/screenshots/drupal8_commerce/4.png)
5. В поле "Плагин" выберите "Modulbank Gateway" после подгрузки параметров укажите идентификатор и секретный ключ вашего магазина, которые можно найти в личном кабинете Модульбанка. При необходимости включите или отключите тестовый режим.
Для правильной отправки чеков требуется указать систему налогообложения, предмет расчета, метод платежа и ставку НДС по-умолчанию.
6. Нажмите на кнопку "Сохранить".
![Загрузка платежного модуля Модульбанка](https://modulbank-pay.github.io/screenshots/drupal8_commerce/5.png)