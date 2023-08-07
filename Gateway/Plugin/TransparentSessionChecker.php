<?php
namespace Fiserv\Gateway\Plugin;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Session\SessionStartChecker;


class TransparentSessionChecker
{

     private const TRANSPARENT_REDIRECT_PATH = [
	  'Fiserv/response'
    ];


    
    private $request;

    
    public function __construct(
        Http $request
    ) {
        $this->request = $request;
    }

  
    public function afterCheck(\Magento\Framework\Session\SessionStartChecker $subject, bool $result) : bool
    {
        if ($result === false) {
            return false;
        }
		
		$inArray = true;
		
		 foreach (self::TRANSPARENT_REDIRECT_PATH as $path) {
            if (strpos((string)$this->request->getPathInfo(), $path) !== false) {
                $inArray = false;
                break;
            }
        }
        return $inArray;
		
		
    }
}