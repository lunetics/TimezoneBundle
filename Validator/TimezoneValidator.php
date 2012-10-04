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
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Timezone Validator Class
 *
 * @author Matthias Breddin <mb@lunetics.com>
 */
class TimezoneValidator extends ConstraintValidator
{

    /**
     * Validates the Timezone
     *
     * @param string              $timezone   The timezone string
     * @param Timezone|Constraint $constraint timezone Constraint
     */
    public function validate($timezone, Constraint $constraint)
    {
        if (!in_array($timezone, \DateTimeZone::listIdentifiers())) {
            $this->context->addViolation($constraint->message, array('%string%' => $timezone));
        }
    }
}
