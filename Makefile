TIMESTAMP := $(shell date +%s)
TEMP_DIR := /tmp/XC_VM-$(TIMESTAMP)
MAIN_DIR = ./src
DIST_DIR = ./dist
TEMP_ARCHIVE_NAME := $(TIMESTAMP).tar.gz
MAIN_ARCHIVE_NAME := proxy.tar.gz
LAST_TAG := $(shell git describe --tags --abbrev=0)
HASH_FILE := hashes.md5

# Directories and files to exclude (can be easily edited)
EXCLUDES := \
	.git

EXCLUDE_ARGS := $(addprefix --exclude=,$(EXCLUDES))

.PHONY: new copy_files set_permissions create_archive archive_move clean

default: copy_files set_permissions create_archive archive_move clean


copy_files:
	@echo "==> [MAIN] Creating distribution directory: $(DIST_DIR)"
	mkdir -p ${DIST_DIR}
	@echo "==> [MAIN] Creating temporary directory: $(TEMP_DIR)"
	mkdir -p $(TEMP_DIR)

	@echo "==> [MAIN] Copying files from $(MAIN_DIR)"
	@if command -v rsync >/dev/null 2>&1; then \
		echo "   → Using rsync..."; \
		rsync -a $(EXCLUDE_ARGS) $(MAIN_DIR)/ $(TEMP_DIR)/; \
	else \
		echo "⚠️  rsync not found, falling back to tar..."; \
		tar cf - $(EXCLUDE_ARGS) -C $(MAIN_DIR) . | tar xf - -C $(TEMP_DIR); \
	fi

	@echo "Remove all .gitkeep files..."
	@find $(TEMP_DIR) -name .gitkeep \
		-not -path "*/.git/*" \
		-delete
	@echo "All files gitkeep deleted"

set_permissions:
	@echo "==> Setting file and directory permissions"

	# /bin
	chmod 0750 $(TEMP_DIR)/bin || [ $$? -eq 1 ]

	find $(TEMP_DIR)/bin/nginx -type d -exec chmod 750 {} \ 2>/dev/null || [ $$? -eq 1 ];
	find $(TEMP_DIR)/bin/nginx -type f -exec chmod 550 {} \ 2>/dev/null || [ $$? -eq 1 ];
	chmod 0755 $(TEMP_DIR)/bin/nginx/conf 2>/dev/null || [ $$? -eq 1 ]
	chmod 0755 $(TEMP_DIR)/bin/nginx/conf/server.crt 2>/dev/null || [ $$? -eq 1 ]
	chmod 0755 $(TEMP_DIR)/bin/nginx/conf/server.key 2>/dev/null || [ $$? -eq 1 ]

	find $(TEMP_DIR)/bin/php -exec chmod 550 {} \ 2>/dev/null || [ $$? -eq 1 ];
	chmod 0750 $(TEMP_DIR)/bin/php/etc 2>/dev/null || [ $$? -eq 1 ]
	chmod 0750 $(TEMP_DIR)/bin/php/sessions 2>/dev/null || [ $$? -eq 1 ]
	chmod 0750 $(TEMP_DIR)/bin/php/sockets 2>/dev/null || [ $$? -eq 1 ]
	find $(TEMP_DIR)/bin/php/var -type d -exec chmod 750 {} \ 2>/dev/null || [ $$? -eq 1 ];
	chmod 0551 $(TEMP_DIR)/bin/php/bin/php 2>/dev/null || [ $$? -eq 1 ]
	chmod 0551 $(TEMP_DIR)/bin/php/bin/php 2>/dev/null || [ $$? -eq 1 ]
	chmod 0551 $(TEMP_DIR)/bin/php/sbin/php-fpm 2>/dev/null || [ $$? -eq 1 ]

	chmod 0755 $(TEMP_DIR)/bin/php/lib/php/extensions/no-debug-non-zts-20210902 2>/dev/null || [ $$? -eq 1 ]

	chmod 0755 $(TEMP_DIR)/crons 2>/dev/null || [ $$? -eq 1 ]
	find $(TEMP_DIR)/crons -type f -exec chmod 777 {} \ 2>/dev/null || [ $$? -eq 1 ];
	chmod 0755 $(TEMP_DIR)/includes 2>/dev/null || [ $$? -eq 1 ]
	find $(TEMP_DIR)/includes -type f -exec chmod 777 {} \ 2>/dev/null || [ $$? -eq 1 ];

	chmod 0777 $(TEMP_DIR)/service 2>/dev/null || [ $$? -eq 1 ]

	chmod 0750 $(TEMP_DIR)/config 2>/dev/null || [ $$? -eq 1 ]

create_archive:
	@echo "==> Creating final archive: ${TEMP_ARCHIVE_NAME}"
	@tar -czf ${DIST_DIR}/${TEMP_ARCHIVE_NAME} -C $(TEMP_DIR) .

archive_move:
	@echo "==> Moving MAIN archive to: ${DIST_DIR}/${MAIN_ARCHIVE_NAME}"
	@rm -f ${DIST_DIR}/${MAIN_ARCHIVE_NAME}
	@mv ${DIST_DIR}/${TEMP_ARCHIVE_NAME} ${DIST_DIR}/${MAIN_ARCHIVE_NAME}

clean:
	@echo "==> Cleaning up temporary directory: $(TEMP_DIR)"
	@rm -rf $(TEMP_DIR)
	@echo "✅ Project build complete"