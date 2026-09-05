install:
	composer install

lint:
	composer exec phpcs -- --standard=PSR12 bin src tests

lint-fix:
	composer exec phpcbf -- --standard=PSR12 bin src tests

test:
	composer exec phpunit

.PHONY: install lint lint-fix test
