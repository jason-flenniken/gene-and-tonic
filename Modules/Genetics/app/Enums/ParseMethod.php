<?php

namespace Modules\Genetics\Enums;

/**
 * How an upload's markers were read. LocalAi means local parsing stood and only
 * the rows it couldn't read went to the model.
 */
enum ParseMethod: string
{
    case Local = 'local';
    case LocalAi = 'local_ai';
    case Ai = 'ai';
}
