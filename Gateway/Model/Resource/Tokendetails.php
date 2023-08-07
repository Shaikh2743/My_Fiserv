<?php

namespace Fiserv\Gateway\Model\Resource;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Tokendetails extends AbstractDb {

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct() {
        /* Custom Table Name */
        $this->_init('Fiserv_savecard', 'id');
    }

}