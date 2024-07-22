<?php

class STM_CIE_Background_Import extends WP_Background_Process
{

    protected $prefix = 'stm_cie';

    protected $action = 'stm_cie_import_process';

    protected function task( $item ) {
        // Actions to perform.
        $import = new STM_CIE_Import_Manage();
        $import->item_handler($item);

        return false;
    }

    protected function complete() {
        parent::complete();

        $import = new STM_CIE_Import_Manage();
        $import->import_completed();

    }
}