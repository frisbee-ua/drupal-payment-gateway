ARCHIVE_NAME = fondy-payment-gateway-drupal.tar.gz
ARCHIVE_DIRECTORY = fondy-payment-gateway

build:
	rm -f $(ARCHIVE_NAME)
	mkdir /tmp/${ARCHIVE_DIRECTORY}
	cp -r * /tmp/${ARCHIVE_DIRECTORY}
	tar -cf $(ARCHIVE_NAME) /tmp/${ARCHIVE_DIRECTORY}
	rm -rf /tmp/${ARCHIVE_DIRECTORY}
