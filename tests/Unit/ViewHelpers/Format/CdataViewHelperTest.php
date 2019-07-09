<?php
declare(strict_types=1);
namespace TYPO3Fluid\Fluid\Tests\Unit\ViewHelpers\Format;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Tests\Unit\Core\Rendering\RenderingContextFixture;
use TYPO3Fluid\Fluid\Tests\Unit\ViewHelpers\ViewHelperBaseTestcase;
use TYPO3Fluid\Fluid\ViewHelpers\Format\CdataViewHelper;

/**
 * Test for \TYPO3Fluid\Fluid\ViewHelpers\Format\CdataViewHelper
 */
class CdataViewHelperTest extends ViewHelperBaseTestcase
{

    /**
     * @param array $arguments
     * @param string|NULL $tagContent
     * @param string $expected
     * @dataProvider getRenderTestValues
     */
    public function testRender(array $arguments, ?string $tagContent, string $expected): void
    {
        $instance = new CdataViewHelper();
        $instance->setArguments($arguments);
        $instance->setRenderingContext(new RenderingContextFixture());
        $instance->setRenderChildrenClosure(function () use ($tagContent): ?string {
            return $tagContent;
        });
        $this->assertEquals($expected, $instance->initializeArgumentsAndRender());
    }

    /**
     * @return array
     */
    public function getRenderTestValues(): array
    {
        return [
            [[], 'test1', '<![CDATA[test1]]>'],
            [['value' => 'test2'], null, '<![CDATA[test2]]>'],
        ];
    }
}
