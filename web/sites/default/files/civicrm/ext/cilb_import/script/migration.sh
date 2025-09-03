#!/bin/bash

set -e

MIGRATION_NAME=2025-08-15
DB_DUMP="mysqldump -h $DB_HOST --no-tablespaces --skip-triggers -u $DB_USER -p$DB_PASS $CIVICRM_DB_NAME"
CUT_OFF_DATE=2019-09-01
BACKUP_FOLDER=data

mkdir -p $BACKUP_FOLDER/$MIGRATION_NAME

cv api4 Cilb.importCandidates cutOffDate=$CUT_OFF_DATE
$DB_DUMP > $BACKUP_FOLDER/$MIGRATION_NAME/1-candidates.sql

cv api4 Cilb.importCandidateEntities cutOffDate=$CUT_OFF_DATE
$DB_DUMP > $BACKUP_FOLDER/$MIGRATION_NAME/2-candidate-entities.sql

cv api4 Cilb.importExams cutOffDate=$CUT_OFF_DATE
$DB_DUMP > $BACKUP_FOLDER/$MIGRATION_NAME/3-exams.sql

for YEAR in 2019 2020 2021 2022 2023 2024 2025; do

  cv api4 Cilb.importRegistrations cutOffDate=$CUT_OFF_DATE transactionYear=$YEAR
  $DB_DUMP > $BACKUP_FOLDER/$MIGRATION_NAME/4-$YEAR-1-registrations.sql

  cv api4 Cilb.importRegistrationsBF cutOffDate=$CUT_OFF_DATE transactionYear=$YEAR
  $DB_DUMP > $BACKUP_FOLDER/$MIGRATION_NAME/4-$YEAR-2-registrations-bf.sql

  cv api4 Cilb.importActivities cutOffDate=$CUT_OFF_DATE transactionYear=$YEAR
  $DB_DUMP > $BACKUP_FOLDER/$MIGRATION_NAME/4-$YEAR-3-activities.sql

done

cv api4 Cilb.importBlockedUsers cutOffDate=$CUT_OFF_DATE
$DB_DUMP > $BACKUP_FOLDER/$MIGRATION_NAME/5-blocked-users.sql
