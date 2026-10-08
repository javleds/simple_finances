<?php

use App\Console\Commands\PostDeploy;
use Mockery\MockInterface;

afterEach(function (): void {
    Mockery::close();
});

it('forces migrations when the migrate option is present', function (): void {
    $command = buildPostDeployCommand();

    $command->shouldReceive('option')
        ->once()
        ->with('migrate')
        ->andReturn(true);

    $command->shouldReceive('confirm')->never();

    expectPostDeployStep($command, 'migrate', ['--force' => true]);
    expectPostDeployCacheSteps($command);

    expect($command->handle())->toBe(PostDeploy::SUCCESS);
});

it('runs forced migrations when the interactive confirmation is accepted', function (): void {
    $command = buildPostDeployCommand();

    $command->shouldReceive('option')
        ->once()
        ->with('migrate')
        ->andReturn(false);

    $command->shouldReceive('confirm')
        ->once()
        ->with('Do you want to run database migrations?', false)
        ->andReturn(true);

    expectPostDeployStep($command, 'migrate', ['--force' => true]);
    expectPostDeployCacheSteps($command);

    expect($command->handle())->toBe(PostDeploy::SUCCESS);
});

it('skips migrations when the interactive confirmation is rejected', function (): void {
    $command = buildPostDeployCommand();

    $command->shouldReceive('option')
        ->once()
        ->with('migrate')
        ->andReturn(false);

    $command->shouldReceive('confirm')
        ->once()
        ->with('Do you want to run database migrations?', false)
        ->andReturn(false);

    $command->shouldReceive('runCommand')
        ->with('migrate', ['--force' => true], Mockery::any())
        ->never();

    expectPostDeployCacheSteps($command);

    expect($command->handle())->toBe(PostDeploy::SUCCESS);
});

function buildPostDeployCommand(): PostDeploy&MockInterface
{
    return Mockery::mock(PostDeploy::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
}

function expectPostDeployCacheSteps(PostDeploy&MockInterface $command): void
{
    expectPostDeployStep($command, 'config:cache');
    expectPostDeployStep($command, 'route:cache');
    expectPostDeployStep($command, 'event:cache');
}

function expectPostDeployStep(
    PostDeploy&MockInterface $command,
    string $name,
    array $arguments = [],
): void {
    $command->shouldReceive('call')
        ->once()
        ->andReturnUsing(fn () => $command->runCommand($name, $arguments, Mockery::mock()));

    $command->shouldReceive('runCommand')
        ->once()
        ->with($name, $arguments, Mockery::any())
        ->andReturn(0);
}

it('stops deployment when migrations fail', function (): void {
    $command = buildPostDeployCommand();
    $command->shouldReceive('option')->with('migrate')->andReturn(true);
    $command->shouldReceive('call')->once()->with('migrate', ['--force' => true])->andReturn(1);

    expect($command->handle())->toBe(PostDeploy::FAILURE);
});

it('stops deployment when cache generation fails', function (): void {
    $command = buildPostDeployCommand();
    $command->shouldReceive('option')->with('migrate')->andReturn(true);
    $command->shouldReceive('call')->once()->with('migrate', ['--force' => true])->andReturn(0);
    $command->shouldReceive('call')->once()->with('config:cache')->andReturn(2);

    expect($command->handle())->toBe(2);
});
