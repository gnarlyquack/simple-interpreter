.PHONY: run
run:
	./interpret


.PHONY: analyse
analyse:
	vendor/bin/phpstan analyse
