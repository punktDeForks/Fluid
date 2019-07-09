<?php
declare(strict_types=1);
namespace TYPO3Fluid\Fluid\Tests\Unit\Core\Fixtures;
/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

/**
 * Class ClassWithProtectedGetter
 */
class ClassWithProtectedGetter
{

    protected function getTest(): string
    {
        return 'test result';
    }

}