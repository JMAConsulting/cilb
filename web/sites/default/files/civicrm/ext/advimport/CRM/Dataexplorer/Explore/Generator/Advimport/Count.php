<?php

class CRM_Dataexplorer_Explore_Generator_Advimport_Count extends CRM_Dataexplorer_Explore_Generator_Advimport {

  function config($options = []) {
    $this->_select[] = "count(*) as y";
    return parent::config($options);
  }

}
