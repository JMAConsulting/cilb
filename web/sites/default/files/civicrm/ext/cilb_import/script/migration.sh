#!/bin/bash

set -e

MIGRATION_NAME=2025-08-07
CV=docker compose exec civicrm cv
DB_DUMP=docker compose exec db sh -c 'mysqldump -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE'

mkdir data/$MIGRATION_NAME

$CV api4 Cilb.importCandidates cutOffDate=2019-09-01
$DB_DUMP > data/$MIGRATION_NAME/1-candidates.sql

$CV api4 Cilb.importCandidateEntities cutOffDate=2019-09-01
$DB_DUMP > data/$MIGRATION_NAME/2-candidate-entities.sql

$CV api4 Cilb.importExams cutOffDate=2019-09-01
$DB_DUMP > data/$MIGRATION_NAME/3-exams.sql

for YEAR in 2019 2020 2021 2022 2023 2024 2025; do

$CV api4 Cilb.importRegistrations cutOffDate=2019-09-01 transactionYear=$YEAR
$DB_DUMP > data/$MIGRATION_NAME/4-registrations-$YEAR.sql

$CV api4 Cilb.importRegistrationsBF cutOffDate=2019-09-01 transactionYear=$YEAR
$DB_DUMP > data/$MIGRATION_NAME/5-registrations-bf-$YEAR.sql

$CV api4 Cilb.importActivities cutOffDate=2019-09-01 transactionYear=$YEAR
$DB_DUMP > data/$MIGRATION_NAME/6-activities-$YEAR.sql

done

$CV api4 Cilb.importBlockedUsers cutOffDate=2019-09-01
$DB_DUMP > data/$MIGRATION_NAME/7-blocked-users.sql
