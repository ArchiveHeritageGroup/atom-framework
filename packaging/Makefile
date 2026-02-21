# AtoM Heratio - Package Build Makefile
# Alternative to build.sh for make-based workflows

SHELL := /bin/bash

.PHONY: build clean dist

FRAMEWORK_PATH := $(shell dirname $(CURDIR))
VERSION := $(shell php -r "echo json_decode(file_get_contents('$(FRAMEWORK_PATH)/version.json'),true)['version'] ?? '2.10.0';")
PKG_NAME := atom-heratio
PKG_VERSION := $(VERSION)-1
DIST_DIR := $(FRAMEWORK_PATH)/dist

build:
	@echo "Building $(PKG_NAME) $(PKG_VERSION)..."
	@bash build.sh

build-no-tarball:
	@echo "Building $(PKG_NAME) $(PKG_VERSION) (without AtoM tarball)..."
	@bash build.sh --no-tarball

clean:
	@echo "Cleaning build artifacts..."
	@rm -rf /tmp/atom-heratio-build-*
	@rm -f $(DIST_DIR)/$(PKG_NAME)_*.deb
	@echo "Clean."

dist: build
	@echo "Package ready at: $(DIST_DIR)/$(PKG_NAME)_$(PKG_VERSION)_all.deb"

version:
	@echo "$(PKG_VERSION)"

help:
	@echo ""
	@echo "AtoM Heratio Package Builder"
	@echo ""
	@echo "Targets:"
	@echo "  build            Build .deb package (with bundled AtoM tarball)"
	@echo "  build-no-tarball Build .deb package (without tarball, smaller)"
	@echo "  clean            Remove build artifacts"
	@echo "  dist             Build and show output path"
	@echo "  version          Show package version"
	@echo "  help             Show this help"
	@echo ""
