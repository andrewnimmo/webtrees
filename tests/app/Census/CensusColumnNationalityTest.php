<?php
/**
 * webtrees: online genealogy
 * Copyright (C) 2019 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

namespace Fisharebest\Webtrees\Census;

use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Place;
use Mockery;

/**
 * Test harness for the class CensusColumnNationality
 */
class CensusColumnNationalityTest extends \Fisharebest\Webtrees\TestCase
{
    /**
     * Delete mock objects
     *
     * @return void
     */
    public function tearDown()
    {
        Mockery::close();
    }

    /**
     * Get place mock.
     *
     * @param string $place Gedcom Place
     *
     * @return Place
     */
    private function getPlaceMock($place): Place
    {
        $placeMock = Mockery::mock(Place::class);
        $placeMock->shouldReceive('gedcomName')->andReturn($place);

        return $placeMock;
    }

    /**
     * @covers \Fisharebest\Webtrees\Census\CensusColumnNationality
     * @covers \Fisharebest\Webtrees\Census\AbstractCensusColumn
     *
     * @return void
     */
    public function testNoBirthPlace(): void
    {
        $individual = Mockery::mock(Individual::class);
        $individual->shouldReceive('getBirthPlace')->andReturn($this->getPlaceMock(''));
        $individual->shouldReceive('facts')->with(['IMMI', 'EMIG', 'NATU'], true)->andReturn([]);

        $census = Mockery::mock(CensusInterface::class);
        $census->shouldReceive('censusPlace')->andReturn('Deutschland');

        $column = new CensusColumnNationality($census, '', '');

        $this->assertSame('Deutsch', $column->generate($individual, $individual));
    }

    /**
     * @covers \Fisharebest\Webtrees\Census\CensusColumnNationality
     * @covers \Fisharebest\Webtrees\Census\AbstractCensusColumn
     *
     * @return void
     */
    public function testPlaceCountry(): void
    {
        $individual = Mockery::mock(Individual::class);
        $individual->shouldReceive('getBirthPlace')->andReturn($this->getPlaceMock('Australia'));
        $individual->shouldReceive('facts')->with(['IMMI', 'EMIG', 'NATU'], true)->andReturn([]);

        $census = Mockery::mock(CensusInterface::class);
        $census->shouldReceive('censusPlace')->andReturn('England');

        $column = new CensusColumnNationality($census, '', '');

        $this->assertSame('Australia', $column->generate($individual, $individual));
    }

    /**
     * @covers \Fisharebest\Webtrees\Census\CensusColumnNationality
     * @covers \Fisharebest\Webtrees\Census\AbstractCensusColumn
     *
     * @return void
     */
    public function testBritish(): void
    {
        $individual = Mockery::mock(Individual::class);
        $individual->shouldReceive('getBirthPlace')->andReturn($this->getPlaceMock('London, England'));
        $individual->shouldReceive('facts')->with(['IMMI', 'EMIG', 'NATU'], true)->andReturn([]);

        $census = Mockery::mock(CensusInterface::class);
        $census->shouldReceive('censusPlace')->andReturn('England');

        $column = new CensusColumnNationality($census, '', '');

        $this->assertSame('British', $column->generate($individual, $individual));
    }

    /**
     * @covers \Fisharebest\Webtrees\Census\CensusColumnNationality
     * @covers \Fisharebest\Webtrees\Census\AbstractCensusColumn
     *
     * @return void
     */
    public function testEmigrated(): void
    {
        $place1 = Mockery::mock('Fisharebest\Webtrees\Place');
        $place1->shouldReceive('gedcomName')->andReturn('United States');

        $fact1 = Mockery::mock(Fact::class);
        $fact1->shouldReceive('place')->andReturn($place1);
        $fact1->shouldReceive('date')->andReturn(new Date('1855'));

        $place2 = Mockery::mock('Fisharebest\Webtrees\Place');
        $place2->shouldReceive('gedcomName')->andReturn('Australia');

        $fact2 = Mockery::mock(Fact::class);
        $fact2->shouldReceive('place')->andReturn($place2);
        $fact2->shouldReceive('date')->andReturn(new Date('1865'));

        $individual = Mockery::mock(Individual::class);
        $individual->shouldReceive('getBirthPlace')->andReturn($this->getPlaceMock('London, England'));
        $individual->shouldReceive('facts')->with(['IMMI', 'EMIG', 'NATU'], true)->andReturn([
            $fact1,
            $fact2,
        ]);

        $census = Mockery::mock(CensusInterface::class);
        $census->shouldReceive('censusPlace')->andReturn('England');
        $census->shouldReceive('censusDate')->andReturn('01 JUN 1860');

        $column = new CensusColumnNationality($census, '', '');

        $this->assertSame('United States', $column->generate($individual, $individual));
    }
}
