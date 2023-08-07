<?php

namespace Fiserv\Gateway\Model\Resource\Tokendetails;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection {

    protected function _construct() {
        $this->_init('Fiserv\Gateway\Model\Tokendetails', 'Fiserv\Gateway\Model\Resource\Tokendetails');
    }

}