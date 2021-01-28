<?php
namespace tinymeng\uploads\exception;

use tinymeng\tools\exception\TinymengException as Exception;

/**
 * Class TinymengException
 * @package tinymeng\tools\exception
 * @Author: TinyMeng <666@majiameng.com>
 * @Created: 2020/8/17
 */
class TinymengException extends Exception
{
    /**
     * @var int
     */
    private $headers;

    public function __construct($message = '', \Exception $previous = null, array $headers = [], $code = 0)
    {
        $this->headers    = $headers;

        parent::__construct(400,$message,$previous,$headers,$code);
    }

    public function getHeaders()
    {
        return $this->headers;
    }
}
