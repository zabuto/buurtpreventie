#!/bin/bash
# The image grants the app user only MYSQL_DATABASE. The test and v3 databases share its name as a prefix
# (buurtpreventie_test, buurtpreventie_legacy), so the app user may create and use every database with that prefix.
# The image runs this once, on an empty data volume.
#
# The image sources a script it cannot execute and executes one it can. Docker Desktop shows every mounted file as
# executable to root, so this script always runs as its own process, without the helper functions of the image: it
# is executable in Git as well, and talks to the server through the client.
set -euo pipefail

MYSQL_PWD="${MARIADB_ROOT_PASSWORD:-$MYSQL_ROOT_PASSWORD}" mariadb --user=root <<-EOSQL
	GRANT ALL PRIVILEGES ON \`${MARIADB_DATABASE:-$MYSQL_DATABASE}\_%\`.* TO '${MARIADB_USER:-$MYSQL_USER}'@'%';
EOSQL
