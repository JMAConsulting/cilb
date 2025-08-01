#!/bin/bash

set -e

docker compose exec civicrm cv api4 Cilb.importCandidates cutOffDate=2019-09-01
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/1-candidates.sql

docker compose exec civicrm cv api4 Cilb.importCandidateEntities cutOffDate=2019-09-01
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/2-candidate-entities.sql

docker compose exec civicrm cv api4 Cilb.importExams cutOffDate=2019-09-01
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/3-exams.sql

docker compose exec civicrm cv api4 Cilb.importRegistrations cutOffDate=2019-09-01 transactionYear=2019
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/4-registrations-2019.sql

docker compose exec civicrm cv api4 Cilb.importRegistrations cutOffDate=2019-09-01 transactionYear=2020
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/4-registrations-2020.sql

docker compose exec civicrm cv api4 Cilb.importRegistrations cutOffDate=2019-09-01 transactionYear=2021
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/4-registrations-2021.sql

docker compose exec civicrm cv api4 Cilb.importRegistrations cutOffDate=2019-09-01 transactionYear=2022
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/4-registrations-2022.sql

docker compose exec civicrm cv api4 Cilb.importRegistrations cutOffDate=2019-09-01 transactionYear=2023
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/4-registrations-2023.sql

docker compose exec civicrm cv api4 Cilb.importRegistrations cutOffDate=2019-09-01 transactionYear=2024
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/4-registrations-2024.sql

docker compose exec civicrm cv api4 Cilb.importRegistrations cutOffDate=2019-09-01 transactionYear=2025
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/4-registrations-2025.sql

docker compose exec civicrm cv api4 Cilb.importActivities cutOffDate=2019-09-01 transactionYear=2019
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/5-activities-2019.sql

docker compose exec civicrm cv api4 Cilb.importActivities cutOffDate=2019-09-01 transactionYear=2020
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/5-activities-2020.sql

docker compose exec civicrm cv api4 Cilb.importActivities cutOffDate=2019-09-01 transactionYear=2021
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/5-activities-2021.sql

docker compose exec civicrm cv api4 Cilb.importActivities cutOffDate=2019-09-01 transactionYear=2022
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/5-activities-2022.sql

docker compose exec civicrm cv api4 Cilb.importActivities cutOffDate=2019-09-01 transactionYear=2023
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/5-activities-2023.sql

docker compose exec civicrm cv api4 Cilb.importActivities cutOffDate=2019-09-01 transactionYear=2024
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/5-activities-2024.sql

docker compose exec civicrm cv api4 Cilb.importActivities cutOffDate=2019-09-01 transactionYear=2025
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/5-activities-2025.sql

docker compose exec civicrm cv api4 Cilb.importBlockedUsers cutOffDate=2019-09-01
docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE' > data/6-blocked-users.sql
