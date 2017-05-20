<?php

namespace AppBundle\Monolog;

use Monolog\Formatter;

class  CustomFormatter extends Formatter\LineFormatter
{
    public function __construct($format = NULL, $dateFormat = NULL, $allowInlineLineBreaks = TRUE, $ignoreEmptyContextAndExtra = FALSE)
    {
        parent::__construct($format, $dateFormat, TRUE, $ignoreEmptyContextAndExtra);
    }
}