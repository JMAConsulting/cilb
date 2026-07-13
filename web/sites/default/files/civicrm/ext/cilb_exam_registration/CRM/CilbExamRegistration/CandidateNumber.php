<?php

use Civi\Api4\Participant;

class CRM_CilbExamRegistration_CandidateNumber {

  public static function schedule(int $participantId): void {
    if (!empty(\Civi::$statics[__CLASS__]['scheduled'][$participantId])) {
      return;
    }
    \Civi::$statics[__CLASS__]['scheduled'][$participantId] = TRUE;
    if (CRM_Core_Transaction::isActive()) {
      CRM_Core_Transaction::addCallback(CRM_Core_Transaction::PHASE_POST_COMMIT, [__CLASS__, 'assignScheduled'], [$participantId]);
    }
    else {
      self::assignScheduled($participantId);
    }
  }

  public static function assignScheduled(int $participantId): void {
    unset(\Civi::$statics[__CLASS__]['scheduled'][$participantId]);
    try {
      self::assign($participantId);
    }
    catch (\Throwable $e) {
      \Civi::log()->error('Failed to assign Candidate_Number for participant {id}: {msg}', [
        'id' => $participantId,
        'msg' => $e->getMessage(),
      ]);
    }
  }

  public static function assign(int $participantId): void {
    $participant = Participant::get(FALSE)
      ->addSelect('event_id', 'event_id.Exam_Details.Exam_Format', 'Candidate_Result.Candidate_Number')
      ->addWhere('id', '=', $participantId)
      ->execute()
      ->first();

    if (!$participant
      || $participant['event_id.Exam_Details.Exam_Format'] !== 'paper'
      || !empty($participant['Candidate_Result.Candidate_Number'])) {
      return;
    }

    $eventId = (int) $participant['event_id'];
    $lockName = 'cilb.candidate_number.' . $eventId;
    $locked = CRM_Core_DAO::singleValueQuery('SELECT GET_LOCK(%1, 10)', [1 => [$lockName, 'String']]);
    if (empty($locked)) {
      \Civi::log()->error('Could not acquire Candidate_Number lock for event {event}, participant {id}', [
        'event' => $eventId,
        'id' => $participantId,
      ]);
      return;
    }

    try {
      $current = Participant::get(FALSE)
        ->addSelect('Candidate_Result.Candidate_Number')
        ->addWhere('id', '=', $participantId)
        ->execute()
        ->first();
      if (!empty($current['Candidate_Result.Candidate_Number'])) {
        return;
      }

      $max = (int) Participant::get(FALSE)
        ->addSelect('MAX(Candidate_Result.Candidate_Number) AS max_candidate_number')
        ->addWhere('event_id', '=', $eventId)
        ->execute()
        ->first()['max_candidate_number'];

      $next = $max ? $max + 1 : 570102;
      if ($next > 999999) {
        \Civi::log()->error('Reached max Candidate_Number for event {event}, participant {id}', [
          'event' => $eventId,
          'id' => $participantId,
        ]);
        return;
      }

      unset(\Civi::$statics['cilb_exam_registration']['null_candidate_number'][$participantId]);

      Participant::update(FALSE)
        ->addValue('Candidate_Result.Candidate_Number', str_pad((string) $next, 6, '0', STR_PAD_LEFT))
        ->addWhere('id', '=', $participantId)
        ->execute();
    }
    finally {
      CRM_Core_DAO::singleValueQuery('SELECT RELEASE_LOCK(%1)', [1 => [$lockName, 'String']]);
    }
  }

}
