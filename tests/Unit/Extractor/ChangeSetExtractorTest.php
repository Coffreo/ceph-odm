<?php


namespace Coffreo\CephOdm\Test\Unit\Extractor;


use Coffreo\CephOdm\Extractor\ChangeSetExtractor;
use Doctrine\SkeletonMapper\UnitOfWork\Change;
use Doctrine\SkeletonMapper\UnitOfWork\ChangeSet;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Coffreo\CephOdm\Extractor\ChangeSetExtractor
 */
class ChangeSetExtractorTest extends TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Object must be an instance of ChangeSet
     *
     * @covers ::extract
     */
    public function testExtractWithoutChangeSetInstanceShouldThrowException(): void
    {
        $sut = new ChangeSetExtractor();
        $sut->extract(new \stdClass(), '');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Change mychangekey not found
     * @expectedExceptionCode 404
     *
     * @covers ::extract
     */
    public function testExtractWithoutChangeFoundShouldThrowException(): void
    {
        $changeSet = $this->createMock(ChangeSet::class);
        $changeSet
            ->method('getChanges')
            ->willReturn([]);

        $sut = new ChangeSetExtractor();
        $sut->extract($changeSet, 'mychangekey');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Change must be an instance of Change (actually stdClass)
     *
     * @covers ::extract
     */
    public function testExtractWithChangeNotInstanceOfChangeShouldThrowException(): void
    {
        $changeSet = $this->createMock(ChangeSet::class);
        $changeSet
            ->method('getChanges')
            ->willReturn([new \stdClass()]);

        $sut = new ChangeSetExtractor();
        $sut->extract($changeSet, 'mychangekey');
    }

    /**
     * @covers ::extract
     */
    public function testExtract(): void
    {
        $changeSet = $this->createMock(ChangeSet::class);
        $changeSet
            ->method('getChanges')
            ->willReturn([
                new Change('mychangekey1', '', 'mychangedvalue'),
                new Change('mychangekey2', '', 'mychangedvalue')]
            );
        $sut = new ChangeSetExtractor();
        $extracted = $sut->extract($changeSet, 'mychangekey2');
        $this->assertEquals('mychangedvalue', $extracted);
    }
}