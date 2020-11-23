.PHONY: check
check: analyse test


.PHONY: test
test:
	vendor/bin/easytest


.PHONY: analyse
analyse:
	vendor/bin/phpstan analyse
