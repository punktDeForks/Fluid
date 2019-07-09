<?php
declare(strict_types=1);
namespace TYPO3Fluid\Fluid\Tests\Unit\ViewHelpers;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;
use TYPO3Fluid\Fluid\ViewHelpers\CycleViewHelper;

/**
 * Testcase for CycleViewHelper
 */
class CycleViewHelperTest extends ViewHelperBaseTestcase
{

    /**
     * @var CycleViewHelper
     */
    protected $viewHelper;

    public function setUp(): void
    {
        parent::setUp();
        $this->viewHelper = $this->getMock(CycleViewHelper::class, ['renderChildren']);
        $this->injectDependenciesIntoViewHelper($this->viewHelper);
        $this->viewHelper->initializeArguments();
    }

    /**
     * @test
     */
    public function renderAddsCurrentValueToTemplateVariableContainerAndRemovesItAfterRendering(): void
    {
        $values = ['bar', 'Fluid'];
        $this->viewHelper->setArguments(['values' => $values, 'as' => 'innerVariable']);
        $output = $this->viewHelper->render();
        $this->assertEquals('', $output);
    }

    /**
     * @test
     */
    public function renderThrowsExceptionWhenPassingObjectsToValuesThatAreNotTraversable(): void
    {
        $this->expectException(Exception::class);

        $object = new \stdClass();
        $this->viewHelper->setArguments(['values' => $object, 'as' => 'innerVariable']);
        $this->viewHelper->render();
    }

    /**
     * @test
     */
    public function renderReturnsChildNodesIfValuesIsNull(): void
    {
        $this->viewHelper->expects($this->once())->method('renderChildren')->will($this->returnValue('Child nodes'));
        $this->viewHelper->setArguments(['values' => null, 'as' => 'foo']);
        $this->assertEquals('Child nodes', $this->viewHelper->render());
    }

    /**
     * @test
     */
    public function renderReturnsChildNodesIfValuesIsAnEmptyArray(): void
    {
        $this->viewHelper->expects($this->once())->method('renderChildren')->will($this->returnValue('Child nodes'));
        $this->viewHelper->setArguments(['values' => [], 'as' => 'foo']);
        $this->assertEquals('Child nodes', $this->viewHelper->render());
    }

    /**
     * @test
     */
    public function renderIteratesThroughElementsOfTraversableObjects(): void
    {
        $traversableObject = new \ArrayObject(['key1' => 'value1', 'key2' => 'value2']);
        $this->viewHelper->setArguments(['values' => $traversableObject, 'as' => 'innerVariable']);
        $o1 = $this->viewHelper->render();
        $o2 = $this->viewHelper->render();
        $o3 = $this->viewHelper->render();
        $this->assertEquals($o1, $o2);
        $this->assertEquals($o2, $o3);
    }
}
