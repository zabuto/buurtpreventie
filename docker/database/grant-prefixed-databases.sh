#!/bin/bash
# The image grants the app user only MYSQL_DATABASE. The test and v3 databases share its name as a prefix
# (buurtpreventie_test, buurtpreventie_legacy), so the app user may create and use every database with that prefix.
# The image runs this once, on an empty data volume.
docker_process_sql --database=mysql <<-EOSQL
	GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\_%\`.* TO '${MYSQL_USER}'@'%';
EOSQL
