#!/bin/bash

set -e

docker compose exec civicrm cv api4 Cilb.importCandidates cutOffDate=2019-09-01
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/1-candidates.sql

docker compose exec civicrm cv api4 Cilb.importCandidateEntities cutOffDate=2019-09-01
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/2-candidate-entities.sql

docker compose exec civicrm cv api4 Cilb.importExams cutOffDate=2019-09-01
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/3-exams.sql

for YEAR in 2019 2020 2021 2022 2023 2024 2025; do

docker compose exec civicrm cv api4 Cilb.importRegistrations cutOffDate=2019-09-01 transactionYear=$YEAR
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/4-registrations-$YEAR.sql

docker compose exec civicrm cv api4 Cilb.importRegistrationsBF cutOffDate=2019-09-01 transactionYear=$YEAR
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/5-registrations-bf-$YEAR.sql

docker compose exec civicrm cv api4 Cilb.importActivities cutOffDate=2019-09-01 transactionYear=$YEAR
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/6-activities-$YEAR.sql

done

docker compose exec civicrm cv api4 Cilb.importBlockedUsers cutOffDate=2019-09-01
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/7-blocked-users.sql