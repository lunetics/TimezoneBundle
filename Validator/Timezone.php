<?php
/**
 * This file is part of the LuneticsTimezoneBundle package.
 *
 * <https://github.com/lunetics/TimezoneBundle/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that is distributed with this source code.
 */

namespace Lunetics\TimezoneBundle\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Timezone Constraint
 *
 * @Annotation
 */
class Timezone extends Constraint
{
    public $message = 'The timezone "%string%" is not a valid timezone';
}