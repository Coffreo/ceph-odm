<?php


namespace Coffreo\CephOdm\Test\Unit\Extractor;


use Coffreo\CephOdm\Extractor\GetterExtractor;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Coffreo\CephOdm\Extractor\GetterExtractor
 */
class GetterExtractorTest extends TestCase
{
    /**
     * @covers ::extract
     */
    public function testExtract(): void
    {
        $sut = new GetterExtractor();
        $this->assertEquals('myvalue', $sut->extract(new DummyObject(), 'value'));
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Can't find getNonexistentgetter for accessing nonexistentgetter property
     *
     * @covers ::extract
     */
    public function testExtractWithNonExistentGetterShouldThrowException(): void
    {
        $sut = new GetterExtractor();
        $sut->extract(new DummyObject(), 'nonexistentgetter');
    }
}

class DummyObject
{
    public function getValue(): string
    {
        return 'myvalue';
    }
}