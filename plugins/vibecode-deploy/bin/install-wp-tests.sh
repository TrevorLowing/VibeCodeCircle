#!/bin/bash

# Install WordPress test suite
# Usage: bin/install-wp-tests.sh [db-name] [db-user] [db-pass] [db-host] [wp-version]

if [ $# -lt 4 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}

WP_TESTS_DIR=${WP_TESTS_DIR-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-/tmp/wordpress}

set -e

install_wp() {
	if [ -d $WP_CORE_DIR ]; then
		return;
	fi

	download https://wordpress.org/nightly-builds/wordpress-latest.zip $TMPDIR/wordpress.zip
	unzip -q $TMPDIR/wordpress.zip -d $TMPDIR
	mv $TMPDIR/wordpress $WP_CORE_DIR
}

install_test_suite() {
	# portable in-place sed for Linux/Mac
	sed -i.bak "s/.*ABSPATH.*/define( 'ABSPATH', dirname( __FILE__ ) . '\/' );/" $WP_CORE_DIR/wp-config-sample.php
	mv $WP_CORE_DIR/wp-config-sample.php $WP_CORE_DIR/wp-config.php

	sed -i.bak "s/.*DB_NAME.*/define( 'DB_NAME', '$DB_NAME' );/" $WP_CORE_DIR/wp-config.php
	sed -i.bak "s/.*DB_USER.*/define( 'DB_USER', '$DB_USER' );/" $WP_CORE_DIR/wp-config.php
	sed -i.bak "s/.*DB_PASSWORD.*/define( 'DB_PASSWORD', '$DB_PASS' );/" $WP_CORE_DIR/wp-config.php
	sed -i.bak "s/.*DB_HOST.*/define( 'DB_HOST', '$DB_HOST' );/" $WP_CORE_DIR/wp-config.php

	download https://raw.githubusercontent.com/wp-cli/wp-cli/v2.8.1/php/WP_CLI/Runner.php $WP_TESTS_DIR/includes/functions.php
}

install_db() {
	# parse DB_HOST for port or socket references
	local PARTS=(${DB_HOST//\:/ })
	local DB_HOSTNAME=${PARTS[0]};
	local DB_SOCK_OR_PORT=${PARTS[1]};
	local EXTRA=""

	if ! [ -z $DB_HOSTNAME ] ; then
		if [ $(echo $DB_SOCK_OR_PORT | grep -e '^[0-9]\{1,\}$') ]; then
			EXTRA=" --port=$DB_SOCK_OR_PORT"
		elif [ -n $DB_SOCK_OR_PORT ] ; then
			EXTRA=" --socket=$DB_SOCK_OR_PORT"
		fi
	fi

	# create database
	mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA || true
}

download() {
	if [ `which curl` ]; then
		curl -s "$1" > "$2";
	elif [ `which wget` ]; then
		wget -nv -O "$2" "$1"
	fi
}

if [ $WP_TESTS_DIR == $WP_CORE_DIR ]; then
	echo "Error: WP_TESTS_DIR and WP_CORE_DIR cannot be the same"
	exit 1
fi

mkdir -p $WP_TESTS_DIR
mkdir -p $TMPDIR

install_db
install_wp
install_test_suite
