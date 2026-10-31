<?php

namespace Tests\Unit;

use App\Modules\MedicalStaff\DuplicateSpecialtyName;
use App\Modules\MedicalStaff\InvalidSpecialtyInput;
use App\Modules\MedicalStaff\SpecialtyRepository;
use App\Modules\MedicalStaff\SpecialtyService;
use Mockery;
use Tests\TestCase;

class SpecialtyServiceTest extends TestCase
{
    public function test_names_are_normalized_and_compared_without_regard_to_case(): void
    {
        $repository = Mockery::mock(SpecialtyRepository::class);
        $repository->shouldReceive('nameTaken')->once()->with('Pediatría', null)->andReturn(true);
        $repository->shouldReceive('createSpecialty')->never();

        try {
            (new SpecialtyService($repository))->createSpecialty('  Pediatría  ', null, true);
            $this->fail('Expected DuplicateSpecialtyName');
        } catch (DuplicateSpecialtyName) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_an_empty_name_is_rejected(): void
    {
        $repository = Mockery::mock(SpecialtyRepository::class);
        $repository->shouldReceive('nameTaken')->never();
        $repository->shouldReceive('createSpecialty')->never();

        try {
            (new SpecialtyService($repository))->createSpecialty('   ', null, true);
            $this->fail('Expected InvalidSpecialtyInput');
        } catch (InvalidSpecialtyInput) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }
}
