# Тестирование загрузчика страниц (PHP)

[![main](https://github.com/mikitasazan/php-phpunit-testing-project-75/actions/workflows/main.yml/badge.svg)](https://github.com/mikitasazan/php-phpunit-testing-project-75/actions/workflows/main.yml)
[![hexlet-check](https://github.com/mikitasazan/php-phpunit-testing-project-75/actions/workflows/hexlet-check.yml/badge.svg)](https://github.com/mikitasazan/php-phpunit-testing-project-75/actions/workflows/hexlet-check.yml)

PageLoader — утилита командной строки: скачивает страницу вместе с её картинками,
стилями и скриптами и складывает всё на диск, чтобы страницу можно было открыть
без интернета. Главное в проекте — тесты: у загрузчика много побочных эффектов,
и все они подавляются подставным HTTP-клиентом.

## Стек

PHP 8.1+, Guzzle, Docopt, Monolog, illuminate/collections, PHPUnit 9, PHP_CodeSniffer (PSR-12).

## Установка

```bash
make install
```

## Использование

```bash
./bin/page-loader https://ru.hexlet.io/courses -o /var/tmp
Page was successfully downloaded into /var/tmp/ru-hexlet-io-courses.html
```

Без `-o` страница сохраняется в текущую директорию. Рядом со страницей
создаётся директория `<имя-страницы>_files` с её ресурсами и файл
`page-loader.log` с ходом загрузки.

## Проверка локально

```bash
make install
make lint
make test
```

## Что проверяют тесты

- Возвращается полный путь к сохранённой странице, и файл действительно создан.
- Ссылки на ресурсы того же домена заменены на локальные, а внешние ссылки
  (CDN, чужие сайты) остались нетронутыми — страница сравнивается с эталоном
  целиком.
- Каждый ресурс скачан с настоящим содержимым: картинка, стиль, скрипт и
  канонический адрес сравниваются с эталонными файлами побайтово.
- В директорию с ресурсами не попало ничего лишнего.
- Ошибки сети и ответы 404 и 500 доходят до вызывающего кода, и при них на диск
  ничего не пишется.
- Ошибки файловой системы: директории для сохранения нет, вместо директории
  передан файл, в директорию нельзя писать, директория ресурсов уже существует.
- Утилита командной строки печатает справку и завершается ненулевым кодом,
  когда хост недоступен.

## Как устроены тесты

- `tests/FakeClient.php` — подставной HTTP-клиент: сеть не трогает, отдаёт
  записанные ответы и умеет отвечать ошибкой на заранее известные адреса.
- `tests/fixtures/` — исходная страница и эталон того, что должно получиться.
- `tests/DownloaderTest.php` — тесты библиотечной функции.
- `tests/CliTest.php` — тесты исполняемого файла.
