.PHONY: run
run:
	php interpreter.php


.PHONY: analyse
analyse:
	vendor/bin/phpstan analyse
