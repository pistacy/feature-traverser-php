phpstan:
	vendor/bin/phpstan analyse -c phpstan.dist.neon

phpcs:
	vendor/bin/phpcs --standard=phpcs.xml.dist

phpcbf:
	vendor/bin/phpcbf --standard=phpcs.xml.dist

stan: phpcbf phpcs phpstan

test:
	vendor/bin/phpunit -c phpunit.xml

.SILENT:
