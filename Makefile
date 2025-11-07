phpstan:
	$(PHPROOT) vendor/bin/phpstan analyse -c phpstan.dist.neon

phpcs:
	$(PHPROOT) vendor/bin/phpcs --standard=phpcs.xml.dist

phpcbf:
	$(PHPROOT) vendor/bin/phpcbf --standard=phpcs.xml.dist

stan: phpcbf phpcs phpstan

.SILENT:
