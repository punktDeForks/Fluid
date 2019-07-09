<?php
declare(strict_types=1);
namespace TYPO3Fluid\Fluid\Tests\Unit\Core\Variables;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Variables\VariableExtractor;
use TYPO3Fluid\Fluid\Tests\Unit\Core\Fixtures\ArrayAccessDummy;
use TYPO3Fluid\Fluid\Tests\Unit\Core\Fixtures\ClassWithMagicGetter;
use TYPO3Fluid\Fluid\Tests\Unit\Core\Fixtures\ClassWithProtectedGetter;
use TYPO3Fluid\Fluid\Tests\Unit\ViewHelpers\Fixtures\UserWithoutToString;
use TYPO3Fluid\Fluid\Tests\UnitTestCase;

/**
 * Class VariableExtractorTest
 */
class VariableExtractorTest extends UnitTestCase
{

    /**
     * @param mixed $subject
     * @param string $path
     * @param mixed $expected
     * @test
     * @dataProvider getPathTestValues
     */
    public function testGetByPath($subject, string $path, $expected): void
    {
        $result = VariableExtractor::extract($subject, $path);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function getPathTestValues(): array
    {
        $namedUser = new UserWithoutToString('Foobar Name');
        $unnamedUser = new UserWithoutToString('');
        return [
            [null, '', null],
            [['foo' => 'bar'], 'foo', 'bar'],
            [['foo' => 'bar'], 'foo.invalid', null],
            [['user' => $namedUser], 'user.name', 'Foobar Name'],
            [['user' => $unnamedUser], 'user.name', ''],
            [['user' => $namedUser], 'user.named', true],
            [['user' => $unnamedUser], 'user.named', false],
            [['user' => $namedUser], 'user.invalid', null],
            [['foodynamicbar' => 'test', 'dyn' => 'dynamic'], 'foo{dyn}bar', 'test'],
            [['foo' => ['dynamic' => ['bar' => 'test']], 'dyn' => 'dynamic'], 'foo.{dyn}.bar', 'test'],
            [['user' => $namedUser], 'user.hasAccessor', true],
            [['user' => $namedUser], 'user.isAccessor', true],
            [['user' => $unnamedUser], 'user.hasAccessor', false],
            [['user' => $unnamedUser], 'user.isAccessor', false],
        ];
    }

    /**
     * @param mixed $subject
     * @param string $path
     * @param mixed $expected
     * @test
     * @dataProvider getAccessorsForPathTestValues
     */
    public function testGetAccessorsForPath($subject, string $path, $expected): void
    {
        $result = VariableExtractor::extractAccessors($subject, $path);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function getAccessorsForPathTestValues(): array
    {
        $namedUser = new UserWithoutToString('Foobar Name');
        $inArray = ['user' => $namedUser];
        $inArrayAccess = new ArrayAccessDummy($inArray);
        $inPublic = (object) $inArray;
        $asArray = VariableExtractor::ACCESSOR_ARRAY;
        $asGetter = VariableExtractor::ACCESSOR_GETTER;
        $asPublic = VariableExtractor::ACCESSOR_PUBLICPROPERTY;
        return [
            [null, '', []],
            [['inArray' => $inArray], 'inArray.user', [$asArray, $asArray]],
            [['inArray' => $inArray], 'inArray.user.name', [$asArray, $asArray, $asGetter]],
            [['inArrayAccess' => $inArrayAccess], 'inArrayAccess.user.name', [$asArray, $asArray, $asGetter]],
            [['inArrayAccessWithGetter' => $inArrayAccess], 'inArrayAccessWithGetter.property', [$asArray, $asGetter]],
            [['inPublic' => $inPublic], 'inPublic.user.name', [$asArray, $asPublic, $asGetter]],
        ];
    }

    /**
     * @param mixed $subject
     * @param string $path
     * @param string|null $accessor
     * @param mixed $expected
     * @test
     * @dataProvider getExtractRedectAccessorTestValues
     */
    public function testExtractRedetectsAccessorIfUnusableAccessorPassed($subject, string $path, ?string $accessor, $expected): void
    {
        $result = VariableExtractor::extract($subject, $path, [$accessor]);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function getExtractRedectAccessorTestValues(): array
    {
        return [
            [['test' => 'test'], 'test', null, 'test'],
            [['test' => 'test'], 'test', 'garbageextractionname', 'test'],
            [['test' => 'test'], 'test', VariableExtractor::ACCESSOR_PUBLICPROPERTY, 'test'],
            [['test' => 'test'], 'test', VariableExtractor::ACCESSOR_GETTER, 'test'],
            [['test' => 'test'], 'test', VariableExtractor::ACCESSOR_ASSERTER, 'test'],
            [(object) ['test' => 'test'], 'test', VariableExtractor::ACCESSOR_ARRAY, 'test'],
            [(object) ['test' => 'test'], 'test', VariableExtractor::ACCESSOR_ARRAY, 'test'],
            [new \ArrayObject(['testProperty' => 'testValue']), 'testProperty', null, 'testValue'],
        ];
    }

    /**
     * @test
     */
    public function testExtractCallsMagicMethodGetters(): void
    {
        $subject = new ClassWithMagicGetter();
        $result = VariableExtractor::extract($subject, 'test');
        $this->assertEquals('test result', $result);
    }

    /**
     * @test
     */
    public function testExtractReturnsNullOnProtectedGetters(): void
    {
        $subject = new ClassWithProtectedGetter();
        $result = VariableExtractor::extract($subject, 'test');
        $this->assertEquals(null, $result);
    }

}
