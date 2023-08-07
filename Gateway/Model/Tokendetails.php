<?php

namespace Fiserv\Gateway\Model;

use Magento\Framework\Model\AbstractModel;

class Tokendetails extends AbstractModel {

    public function _construct() {
        $this->_init('Fiserv\Gateway\Model\Resource\Tokendetails');
    }

}
