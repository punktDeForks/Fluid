<?php
declare(strict_types=1);
namespace TYPO3Fluid\Fluid\Tests\Unit\Core\ViewHelper;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperInvoker;
use TYPO3Fluid\Fluid\Tests\Unit\Core\Fixtures\TestViewHelper;
use TYPO3Fluid\Fluid\Tests\UnitTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Class ViewHelperInvokerTest
 */
class ViewHelperInvokerTest extends UnitTestCase
{

    /**
     * @param string|ViewHelperInterface $viewHelperClassName
     * @param array $arguments
     * @param mixed $expectedOutput
     * @param string|NULL $expectedException
     * @test
     * @dataProvider getInvocationTestValues
     */
    public function testInvokeViewHelper($viewHelperClassName, array $arguments, $expectedOutput, ?string $expectedException): void
    {
        $view = new TemplateView();
        $invoker = new ViewHelperInvoker();
        $renderingContext = new RenderingContext($view);
        if ($expectedException) {
            $this->setExpectedException($expectedException);
        }
        $result = $invoker->invoke($viewHelperClassName, $arguments, $renderingContext);
        $this->assertEquals($expectedOutput, $result);
    }

    /**
     * @return array
     */
    public function getInvocationTestValues(): array
    {
        $exception = new Exception('test');
        $fixtureViewHelper = $this->getMockBuilder(ViewHelperInterface::class)->getMock();
        $fixtureViewHelper->expects($this->once())->method('prepareArguments')->willReturn([]);
        $fixtureViewHelper->expects($this->once())->method('initializeArgumentsAndRender')->willThrowException($exception);
        return [
            [TestViewHelper::class, ['param1' => 'foo', 'param2' => ['bar']], 'foo', null],
            [TestViewHelper::class, ['param1' => 'foo', 'param2' => ['bar'], 'add1' => 'baz', 'add2' => 'zap'], 'foo', null],
            [$fixtureViewHelper, [], null, Exception::class],
        ];
    }
}
