<?php

namespace Civi\Advimport;

use \Civi\DataExplorer\Event\DataExplorerEvent;
use CRM_Advimport_ExtensionUtil as E;

class Events {

  static public function fireDataExplorerBoot(DataExplorerEvent $event) {
    $sources = $event->getDataSources();
    $sources['advimport-count'] = E::ts('Advimport - Items');
    $event->setDataSources($sources);

    // FIXME: create helper function or API.get
    $imports = [];
    $dao = \CRM_Core_DAO::executeQuery('SELECT * FROM civicrm_advimport ORDER BY ID DESC LIMIT 15');

    while ($dao->fetch()) {
      $imports[$dao->id] = $dao->classname . ' ' . $dao->start_date;
    }

    $filters = $event->getFilters();
    $filters['advimport'] = [
      'type' => 'items',
      'label' => 'Import',
      'items' => $imports,
      'depends' => [
        'advimport'
      ],
    ];

    $event->setFilters($filters);

    // TODO/FIXME: add 'groupbys' so that we can group by error/warning/success
  }

}
